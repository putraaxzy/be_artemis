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
        try {
            $user = auth()->user();
            
            // Try to find user by ID first if numeric, then by username
            $profile = null;
            if (is_numeric($username)) {
                $profile = User::find($username);
            }
            
            // If not found by ID or not numeric, search by username
            if (!$profile) {
                $profile = User::where('username', $username)->first();
            }
            
            // If still not found, return 404
            if (!$profile) {
                \Log::warning('Profile not found', ['username' => $username]);
                return response()->json([
                    'berhasil' => false,
                    'pesan' => 'Profil tidak ditemukan',
                ], 404);
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
                $stats = $this->getStatsData($profile->id);
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
        } catch (\Exception $e) {
            \Log::error('Profile show error', [
                'username' => $username,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Terjadi kesalahan saat memuat profil',
            ], 500);
        }
    }

    /**
     * Update profile bio
     */
    public function updateBio(Request $request)
    {
        $user = auth()->user();
        
        $request->validate([
            'bio' => 'nullable|string|max:200',
        ], [
            'bio.max' => 'Bio maksimal 200 karakter',
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
            ->with('follower:id,username,name,role,kelas,jurusan,avatar')
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
            ->with('following:id,username,name,role,kelas,jurusan,avatar')
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
     * Get comprehensive stats data for siswa
     */
    private function getStatsData($userId)
    {
        try {
            // Get all penugasaan with nilai
            $penugasaan = Penugasaan::where('id_siswa', $userId)
                ->whereNotNull('nilai')
                ->get();

            // Calculate stats
            $totalTasks = Penugasaan::where('id_siswa', $userId)->count();
            $completedTasks = Penugasaan::where('id_siswa', $userId)
                ->where('status', 'selesai')
                ->count();
            $averageScore = $penugasaan->count() > 0 
                ? round($penugasaan->avg('nilai'), 1) 
                : 0;

            return [
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'average_score' => $averageScore,
                'performance_data' => $this->getPerformanceData($userId),
            ];
        } catch (\Exception $e) {
            \Log::error('Error getting stats data', [
                'userId' => $userId,
                'error' => $e->getMessage(),
            ]);
            
            // Return empty stats instead of crashing
            return [
                'total_tasks' => 0,
                'completed_tasks' => 0,
                'average_score' => 0,
                'performance_data' => [],
            ];
        }
    }

    /**
     * Get performance data for chart (last 10 tasks)
     */
    private function getPerformanceData($userId)
    {
        try {
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
        } catch (\Exception $e) {
            \Log::error('Error getting performance data', [
                'userId' => $userId,
                'error' => $e->getMessage(),
            ]);
            
            return [];
        }
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
