<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Models\Tugas;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Subscribe user ke push notifications
     */
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'endpoint' => 'required|string|url',
            'auth_key' => 'required|string',
            'p256dh_key' => 'required|string',
        ]);

        $user = Auth::user();

        // Update atau create subscription
        PushSubscription::updateOrCreate(
            [
                'user_id' => $user->id,
                'endpoint' => $validated['endpoint'],
            ],
            [
                'auth_key' => $validated['auth_key'],
                'p256dh_key' => $validated['p256dh_key'],
                'user_agent' => $request->userAgent(),
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Push subscription registered successfully',
        ]);
    }

    /**
     * Unsubscribe user dari push notifications
     */
    public function unsubscribe(Request $request)
    {
        $validated = $request->validate([
            'endpoint' => 'required|string|url',
        ]);

        $user = Auth::user();

        PushSubscription::where('user_id', $user->id)
            ->where('endpoint', $validated['endpoint'])
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Push subscription removed successfully',
        ]);
    }

    /**
     * Get VAPID public key
     */
    public function getVapidPublicKey()
    {
        return response()->json([
            'vapid_public_key' => config('services.push.public_key'),
        ]);
    }

    /**
     * Send test notification (untuk development)
     */
    public function sendTestNotification(Request $request)
    {
        $user = Auth::user();

        $notification = [
            'title' => 'Test Notification',
            'body' => 'Ini adalah test notification dari sistem',
            'icon' => url('/batik.png'),
            'badge' => url('/batik.png'),
            'tag' => 'test-notification',
            'data' => [
                'url' => url('/'),
            ],
        ];

        $service = new PushNotificationService();
        $success = $service->sendToUser($user, $notification);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Test notification sent' : 'No active subscriptions',
        ]);
    }

    /**
     * Get notification subscriptions count (untuk user)
     */
    public function getSubscriptionsCount()
    {
        $user = Auth::user();
        $count = PushSubscription::where('user_id', $user->id)->count();

        return response()->json([
            'subscriptions_count' => $count,
            'has_active_subscription' => $count > 0,
        ]);
    }
}
