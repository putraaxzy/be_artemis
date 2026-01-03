<?php

namespace App\Events;

use App\Models\Tugas;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskSubmitted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $tugas;
    public $siswa;
    public $guruId;

    /**
     * Create a new event instance.
     */
    public function __construct(Tugas $tugas, User $siswa, int $guruId)
    {
        $this->tugas = $tugas;
        $this->siswa = $siswa;
        $this->guruId = $guruId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->guruId}"),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'type' => 'task_submitted',
            'task' => [
                'id' => $this->tugas->id,
                'judul' => $this->tugas->judul,
            ],
            'siswa' => [
                'id' => $this->siswa->id,
                'name' => $this->siswa->name,
                'kelas' => $this->siswa->kelas,
                'jurusan' => $this->siswa->jurusan,
            ],
            'message' => "{$this->siswa->name} ({$this->siswa->kelas} {$this->siswa->jurusan}) mengumpulkan tugas '{$this->tugas->judul}'",
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
        return 'task.submitted';
    }
}
