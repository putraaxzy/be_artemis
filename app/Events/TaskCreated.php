<?php

namespace App\Events;

use App\Models\Tugas;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $tugas;
    public $targetSiswaIds;

    /**
     * Create a new event instance.
     */
    public function __construct(Tugas $tugas, array $targetSiswaIds = [])
    {
        $this->tugas = $tugas;
        $this->targetSiswaIds = $targetSiswaIds;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];
        
        // Broadcast ke setiap siswa yang ditarget
        foreach ($this->targetSiswaIds as $siswaId) {
            $channels[] = new PrivateChannel("user.{$siswaId}");
        }
        
        // Juga broadcast ke channel guru untuk tracking
        $channels[] = new PrivateChannel('guru-notifications');
        
        return $channels;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'type' => 'task_created',
            'task' => [
                'id' => $this->tugas->id,
                'judul' => $this->tugas->judul,
                'deskripsi' => $this->tugas->deskripsi,
                'tanggal_deadline' => $this->tugas->tanggal_deadline,
                'target' => $this->tugas->target,
            ],
            'message' => "Tugas baru: {$this->tugas->judul}",
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'task.created';
    }
}
