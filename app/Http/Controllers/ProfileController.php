<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Follow;
use App\Models\Penugasaan;
use App\Events\UserFollowed;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Get user profile by username
     */
    public function show($username)
    {
        $user = auth()->user();
        
        if (is_numeric($username)) {
            $profile = User::findOrFail($username);
        } else {
            $profile = User::where('username', $username)->firstOrFail();
        }

        $followersCount = Follow::where('following_id', $profile->id)->count();
        $followingCount = Follow::where('follower_id', $profile->id)->count();
        
        $isFollowing = false;
        if ($user) {
            $isFollowing = Follow::where('follower_id', $user->id)
                ->where('following_id', $profile->id)
                ->exists();
        }

        // Get performance stats (siswa only)
        $stats = null;
        if ($profile->role === 'siswa') {
            $penugasaan = Penugasaan::where('id_siswa', $profile->id)
                ->whereNotNull('nilai')
                ->get();

            $stats = [
                'total_tasks' => Penugasaan::where('id_siswa', $profile->id)->count(),
                'completed_tasks' => Penugasaan::where('id_siswa', $profile->id)
                    ->where('status', 'selesai')
                    ->count(),
                'average_score' => round($penugasaan->avg('nilai') ?? 0, 1),
                'performance_data' => $this->getPerformanceData($profile->id),
            ];
        }

        return response()->json([
            'berhasil' => true,
            'data' => [
                'id' => $profile->id,
                'username' => $profile->username,
                'name' => $profile->name,
                'bio' => $profile->bio,
                'avatar' => $this->getAvatarUrl($profile),
                'role' => $profile->role,
                'kelas' => $profile->kelas,
                'jurusan' => $profile->jurusan,
                'followers_count' => $followersCount,
                'following_count' => $followingCount,
                'is_following' => $isFollowing,
                'stats' => $stats,
            ],
        ]);
    }

    /**
     * Update profile bio
     */
    public function updateBio(Request $request)
    {
        $user = auth()->user();
        
        $request->validate([
            'bio' => 'nullable|string|max:200',
        ]);
        
        $user->bio = $request->bio;
        $user->save();
        
        return response()->json([
            'berhasil' => true,
            'pesan' => 'Bio berhasil diupdate',
            'data' => ['bio' => $user->bio],
        ]);
    }

    /**
     * Get avatar URL - use uploaded avatar or fallback to UI Avatars
     */
    private function getAvatarUrl($user)
    {
        // Use uploaded avatar if exists
        if ($user->avatar) {
            return $user->avatar_url;
        }
        
        // Fallback to UI Avatars
        $name = urlencode($user->name);
        return "https://ui-avatars.com/api/?name={$name}&size=200&background=6366f1&color=fff&bold=true";
    }

    /**
     * Follow user
     */
    public function follow($id)
    {
        $currentUser = auth()->user();

        if ($currentUser->id == $id) {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Tidak bisa follow diri sendiri',
            ], 400);
        }

        $target = User::findOrFail($id);

        $exists = Follow::where('follower_id', $currentUser->id)
            ->where('following_id', $id)
            ->exists();

        if ($exists) {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Sudah follow user ini',
            ], 400);
        }

        Follow::create([
            'follower_id' => $currentUser->id,
            'following_id' => $id,
        ]);

        // Get fresh user model for notifications
        $follower = User::find($currentUser->id);

        // Send push notification
        try {
            $notification = [
                'title' => 'Follower Baru!',
                'body' => "{$follower->name} mulai mengikuti Anda",
                'icon' => $this->getAvatarUrl($follower),
                'badge' => url('/batik.png'),
                'tag' => 'user-followed-' . $follower->id,
                'data' => [
                    'type' => 'follow',
                    'url' => "/profile/{$follower->username}",
                    'followerId' => $follower->id,
                    'followerUsername' => $follower->username,
                ],
            ];

            $pushService = new PushNotificationService();
            $pushService->sendToUser($target, $notification);

            \Log::info('Follow notification sent', [
                'from' => $follower->id,
                'to' => $target->id,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send follow notification: ' . $e->getMessage());
        }

        // Broadcast event via Reverb untuk real-time notification
        try {
            broadcast(new UserFollowed($follower, $id));
        } catch (\Exception $e) {
            \Log::error('Failed to broadcast follow event: ' . $e->getMessage());
        }

        return response()->json([
            'berhasil' => true,
            'pesan' => 'Berhasil follow',
        ]);
    }

    /**
     * Unfollow user
     */
    public function unfollow($id)
    {
        $user = auth()->user();

        Follow::where('follower_id', $user->id)
            ->where('following_id', $id)
            ->delete();

        return response()->json([
            'berhasil' => true,
            'pesan' => 'Berhasil unfollow',
        ]);
    }

    /**
     * Get followers list
     */
    public function followers($id)
    {
        $followers = Follow::where('following_id', $id)
            ->with('follower:id,name,role,kelas,jurusan,avatar')
            ->get()
            ->map(function ($follow) {
                $follower = $follow->follower;
                $follower->avatar = $this->getAvatarUrl($follower);
                return $follower;
            });

        return response()->json([
            'berhasil' => true,
            'data' => $followers,
        ]);
    }

    /**
     * Get following list
     */
    public function following($id)
    {
        $following = Follow::where('follower_id', $id)
            ->with('following:id,name,role,kelas,jurusan,avatar')
            ->get()
            ->map(function ($follow) {
                $user = $follow->following;
                $user->avatar = $this->getAvatarUrl($user);
                return $user;
            });

        return response()->json([
            'berhasil' => true,
            'data' => $following,
        ]);
    }

    /**
     * Get performance data for chart (last 10 tasks)
     */
    private function getPerformanceData($userId)
    {
        $data = Penugasaan::where('id_siswa', $userId)
            ->whereNotNull('nilai')
            ->with('tugas:id,judul')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->reverse()
            ->values()
            ->map(function ($penugasaan) {
                return [
                    'task' => substr($penugasaan->tugas->judul ?? 'Tugas', 0, 20),
                    'score' => $penugasaan->nilai,
                    'date' => $penugasaan->created_at->format('d/m'),
                ];
            });

        return $data;
    }

    /**
     * Search users
     */
    public function search(Request $request)
    {
        $query = $request->input('q', '');
        
        $users = User::where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('username', 'like', "%{$query}%");
            })
            ->select('id', 'username', 'name', 'role', 'kelas', 'jurusan', 'avatar')
            ->limit(20)
            ->get()
            ->map(function ($user) {
                $user->avatar = $this->getAvatarUrl($user);
                return $user;
            });

        return response()->json([
            'berhasil' => true,
            'data' => $users,
        ]);
    }
}
