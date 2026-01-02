<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    /**
     * Kirim notifikasi ke user spesifik
     */
    public function sendToUser(User $user, array $notification): bool
    {
        $subscriptions = PushSubscription::where('user_id', $user->id)->get();

        if ($subscriptions->isEmpty()) {
            Log::info("No push subscriptions found for user {$user->id}");
            return false;
        }

        $successCount = 0;
        foreach ($subscriptions as $subscription) {
            if ($this->pushMessage($subscription, $notification)) {
                $successCount++;
                $subscription->update(['last_used_at' => now()]);
            } else {
                // Hapus subscription jika tidak valid
                $subscription->delete();
            }
        }

        return $successCount > 0;
    }

    /**
     * Kirim notifikasi ke semua siswa di kelas tertentu
     */
    public function sendToClass(string $kelas, array $notification): int
    {
        $users = User::where('role', 'siswa')
            ->where('kelas', $kelas)
            ->get();

        $successCount = 0;
        foreach ($users as $user) {
            if ($this->sendToUser($user, $notification)) {
                $successCount++;
            }
        }

        return $successCount;
    }

    /**
     * Kirim notifikasi ke multiple kelas
     */
    public function sendToMultipleClasses(array $classes, array $notification): int
    {
        $successCount = 0;
        foreach ($classes as $kelas) {
            $successCount += $this->sendToClass($kelas, $notification);
        }

        return $successCount;
    }

    /**
     * Kirim notifikasi ke siswa tertentu berdasarkan id_target dan tipe
     */
    public function sendToTargetStudents(string $target, array $idTarget, array $notification): int
    {
        $query = User::where('role', 'siswa');

        if ($target === 'kelas') {
            $query->whereIn('kelas', $idTarget);
        } elseif ($target === 'siswa') {
            $query->whereIn('id', $idTarget);
        } elseif ($target === 'jurusan') {
            $query->whereIn('jurusan', $idTarget);
        }

        $users = $query->get();

        $successCount = 0;
        foreach ($users as $user) {
            if ($this->sendToUser($user, $notification)) {
                $successCount++;
            }
        }

        return $successCount;
    }

    /**
     * Push message ke subscription
     */
    private function pushMessage(PushSubscription $subscription, array $notification): bool
    {
        try {
            $vapidPublicKey = config('services.push.public_key');
            $vapidPrivateKey = config('services.push.private_key');

            if (!$vapidPublicKey || !$vapidPrivateKey) {
                Log::warning('VAPID keys not configured');
                return false;
            }

            $payload = json_encode($notification);

            // Menggunakan curl untuk push notification
            $curlHandle = curl_init();
            curl_setopt_array($curlHandle, [
                CURLOPT_URL => $subscription->endpoint,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'TTL: 24' . (60 * 60), // 24 jam
                    'Urgency: high',
                    'Topic: new-task',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);

            $response = curl_exec($curlHandle);
            $statusCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
            curl_close($curlHandle);

            if ($statusCode >= 200 && $statusCode < 300) {
                Log::info("Push notification sent successfully to {$subscription->endpoint}");
                return true;
            }

            if ($statusCode === 410) {
                // Subscription no longer valid
                Log::info("Push subscription no longer valid: {$subscription->endpoint}");
                return false;
            }

            Log::warning("Push notification failed with status {$statusCode}: {$response}");
            return false;
        } catch (\Exception $e) {
            Log::error("Error pushing notification: {$e->getMessage()}");
            return false;
        }
    }
}
