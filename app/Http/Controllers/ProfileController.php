<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Follow;
use App\Models\Penugasaan;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Get user profile
     */
    public function show($id)
    {
        $user = auth()->user();
        $profile = User::findOrFail($id);

        $followersCount = Follow::where('following_id', $id)->count();
        $followingCount = Follow::where('follower_id', $id)->count();
        
        $isFollowing = false;
        if ($user) {
            $isFollowing = Follow::where('follower_id', $user->id)
                ->where('following_id', $id)
                ->exists();
        }

        // Get performance stats (siswa only)
        $stats = null;
        if ($profile->role === 'siswa') {
            $penugasaan = Penugasaan::where('id_siswa', $id)
                ->whereNotNull('nilai')
                ->get();

            $stats = [
                'total_tasks' => Penugasaan::where('id_siswa', $id)->count(),
                'completed_tasks' => Penugasaan::where('id_siswa', $id)
                    ->where('status', 'selesai')
                    ->count(),
                'average_score' => round($penugasaan->avg('nilai') ?? 0, 1),
                'performance_data' => $this->getPerformanceData($id),
            ];
        }

        return response()->json([
            'berhasil' => true,
            'data' => [
                'id' => $profile->id,
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
        $user = auth()->user();

        if ($user->id == $id) {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Tidak bisa follow diri sendiri',
            ], 400);
        }

        $target = User::findOrFail($id);

        $exists = Follow::where('follower_id', $user->id)
            ->where('following_id', $id)
            ->exists();

        if ($exists) {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Sudah follow user ini',
            ], 400);
        }

        Follow::create([
            'follower_id' => $user->id,
            'following_id' => $id,
        ]);

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
        
        $users = User::where('name', 'like', "%{$query}%")
            ->select('id', 'name', 'role', 'kelas', 'jurusan', 'avatar')
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
