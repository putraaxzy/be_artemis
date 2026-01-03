<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserFollowed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $follower;
    public $targetUserId;

    /**
     * Create a new event instance.
     */
    public function __construct(User $follower, int $targetUserId)
    {
        $this->follower = $follower;
        $this->targetUserId = $targetUserId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->targetUserId}"),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        $avatarUrl = $this->follower->avatar 
            ? $this->follower->avatar_url 
            : "https://ui-avatars.com/api/?name=" . urlencode($this->follower->name) . "&size=200&background=6366f1&color=fff&bold=true";

        return [
            'type' => 'user_followed',
            'follower' => [
                'id' => $this->follower->id,
                'username' => $this->follower->username,
                'name' => $this->follower->name,
                'avatar' => $avatarUrl,
            ],
            'message' => "{$this->follower->name} mulai mengikuti Anda",
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'user.followed';
    }
}
