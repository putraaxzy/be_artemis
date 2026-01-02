<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class PushNotificationService
{
    private ?WebPush $webPush = null;

    /**
     * Get WebPush instance
     */
    private function getWebPush(): ?WebPush
    {
        if ($this->webPush !== null) {
            return $this->webPush;
        }

        $vapidPublicKey = config('services.push.public_key');
        $vapidPrivateKey = config('services.push.private_key');

        if (!$vapidPublicKey || !$vapidPrivateKey) {
            Log::warning('VAPID keys not configured. Run: php artisan vapid:keys');
            return null;
        }

        try {
            $auth = [
                'VAPID' => [
                    'subject' => config('app.url', 'mailto:admin@example.com'),
                    'publicKey' => $vapidPublicKey,
                    'privateKey' => $vapidPrivateKey,
                ],
            ];

            $this->webPush = new WebPush($auth);
            $this->webPush->setDefaultOptions([
                'TTL' => 86400, // 24 hours
                'urgency' => 'high',
                'topic' => 'new-task',
            ]);

            return $this->webPush;
        } catch (\Exception $e) {
            Log::error('Error creating WebPush instance: ' . $e->getMessage());
            return null;
        }
    }

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

        $webPush = $this->getWebPush();
        if (!$webPush) {
            return false;
        }

        $payload = json_encode($notification);
        $successCount = 0;
        $failedSubscriptions = [];

        foreach ($subscriptions as $subscription) {
            try {
                $webPushSubscription = Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'keys' => [
                        'p256dh' => $subscription->p256dh_key,
                        'auth' => $subscription->auth_key,
                    ],
                ]);

                $webPush->queueNotification($webPushSubscription, $payload);
            } catch (\Exception $e) {
                Log::error("Error queuing notification for subscription {$subscription->id}: " . $e->getMessage());
                $failedSubscriptions[] = $subscription->id;
            }
        }

        // Flush all notifications
        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();
            
            if ($report->isSuccess()) {
                Log::info("Push notification sent successfully to: {$endpoint}");
                $successCount++;
                
                // Update last_used_at
                PushSubscription::where('endpoint', $endpoint)
                    ->where('user_id', $user->id)
                    ->update(['last_used_at' => now()]);
            } else {
                $reason = $report->getReason();
                Log::warning("Push notification failed for {$endpoint}: {$reason}");
                
                // Hapus subscription jika expired/invalid
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::where('endpoint', $endpoint)->delete();
                    Log::info("Removed expired subscription: {$endpoint}");
                }
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

        Log::info("Push notifications sent to {$successCount} users for target {$target}");
        return $successCount;
    }

    /**
     * Send notification to all subscriptions (broadcast)
     */
    public function sendToAll(array $notification): int
    {
        $subscriptions = PushSubscription::with('user')->get();
        
        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $webPush = $this->getWebPush();
        if (!$webPush) {
            return 0;
        }

        $payload = json_encode($notification);
        
        foreach ($subscriptions as $subscription) {
            try {
                $webPushSubscription = Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'keys' => [
                        'p256dh' => $subscription->p256dh_key,
                        'auth' => $subscription->auth_key,
                    ],
                ]);

                $webPush->queueNotification($webPushSubscription, $payload);
            } catch (\Exception $e) {
                Log::error("Error queuing notification: " . $e->getMessage());
            }
        }

        $successCount = 0;
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $successCount++;
            } elseif ($report->isSubscriptionExpired()) {
                $endpoint = $report->getRequest()->getUri()->__toString();
                PushSubscription::where('endpoint', $endpoint)->delete();
            }
        }

        return $successCount;
    }
}
