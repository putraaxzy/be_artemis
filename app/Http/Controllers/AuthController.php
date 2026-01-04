<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * Get registration options
     */
    public function registerOptions()
    {
        // Fetch jurusan from database
        $jurusanData = \App\Models\Jurusan::orderBy('kelas')
            ->orderBy('jurusan')
            ->get();

        // Group by kelas
        $jurusanByKelas = [];
        foreach ($jurusanData as $item) {
            $kelas = $item->kelas;
            if (!isset($jurusanByKelas[$kelas])) {
                $jurusanByKelas[$kelas] = [];
            }
            $jurusanByKelas[$kelas][] = $item->jurusan;
        }

        // Get unique kelas list
        $kelasList = array_keys($jurusanByKelas);
        sort($kelasList);

        // Flatten semua jurusan untuk backward compatibility
        $allJurusan = [];
        foreach ($jurusanByKelas as $jurusans) {
            $allJurusan = array_merge($allJurusan, $jurusans);
        }
        $allJurusan = array_unique($allJurusan);
        sort($allJurusan);

        return response()->json([
            'berhasil' => true,
            'data' => [
                'kelas' => $kelasList,
                'jurusan' => array_values($allJurusan), 
                'jurusan_by_kelas' => $jurusanByKelas, 
            ],
            'pesan' => 'Opsi registrasi berhasil diambil'
        ]);
    }

    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        // Get valid kelas from database
        $validKelas = \App\Models\Jurusan::distinct()->pluck('kelas')->toArray();
        $kelasRule = 'required|in:' . implode(',', $validKelas);

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|min:3|max:30|unique:users|regex:/^[a-zA-Z0-9_]+$/',
            'name' => 'required|string|max:255',
            'telepon' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:8',
            'kelas' => $kelasRule,
            'jurusan' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'berhasil' => false,
                'pesan' => $validator->errors()->first()
            ], 400);
        }

        // Validasi jurusan sesuai kelas dari database
        $kelas = $request->kelas;
        $jurusan = $request->jurusan;
        
        $isValidJurusan = \App\Models\Jurusan::where('kelas', $kelas)
            ->where('jurusan', $jurusan)
            ->exists();
        
        if (!$isValidJurusan) {
            return response()->json([
                'berhasil' => false,
                'pesan' => "Jurusan '$jurusan' tidak valid untuk kelas $kelas"
            ], 400);
        }

        $user = User::create([
            'username' => $request->username,
            'name' => $request->name,
            'telepon' => $request->telepon,
            'password' => Hash::make($request->password),
            'role' => 'siswa',
            'kelas' => $request->kelas,
            'jurusan' => $request->jurusan,
            'is_first_login' => false, 
        ]);

        // Handle avatar upload (opsional)
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $avatarPath;
            $user->save();
        }

        $this->autoAssignTasksToNewStudent($user);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'berhasil' => true,
            'data' => [
                'token' => $token,
                'pengguna' => $this->formatUserData($user)
            ],
            'pesan' => 'Registrasi berhasil'
        ], 201);
    }

    /**
     * Login user
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'berhasil' => false,
                'pesan' => $validator->errors()->first()
            ], 400);
        }

        $credentials = [
            'username' => $request->username,
            'password' => $request->password
        ];

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Username atau password salah'
            ], 401);
        }

        $user = JWTAuth::user();

        // create response with jwt token
        $response = response()->json([
            'berhasil' => true,
            'data' => [
                'token' => $token,
                'pengguna' => $this->formatUserData($user)
            ],
            'pesan' => 'Login berhasil'
        ]);

        $cookie = cookie(
            'auth_token', 
            $token, 
            60 * 24 * 30, 
            '/', 
            null, 
            true, 
            true, 
            false, 
            'Lax' 
        );

        return $response->cookie($cookie);
    }

    /**
     * Logout user
     */
    public function logout()
    {
        $token = JWTAuth::getToken();
        if ($token) {
            JWTAuth::invalidate($token);
        }

        // clear the auth token cookie
        $cookie = cookie()->forget('auth_token');

        return response()->json([
            'berhasil' => true,
            'pesan' => 'Logout berhasil'
        ])->withCookie($cookie);
    }

    /**
     * Get authenticated user
     */
    public function me()
    {
        $user = JWTAuth::user();

        return response()->json([
            'berhasil' => true,
            'data' => $this->formatUserData($user)
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $user = JWTAuth::user();
        
        $validator = Validator::make($request->all(), [
            'username' => 'sometimes|required|string|min:3|max:30|regex:/^[a-zA-Z0-9_.]+$/|unique:users,username,' . $user->id,
            'name' => 'sometimes|required|string|max:255',
            'telepon' => 'sometimes|nullable|string|max:20',
            'password' => 'sometimes|required|string|min:8',
            'avatar' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240', // Max 10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'berhasil' => false,
                'pesan' => $validator->errors()->first()
            ], 400);
        }

        // Update username (dengan limit 7 hari)
        if ($request->has('username') && $request->username !== $user->username) {
            if (!$user->canChangeUsername()) {
                $daysLeft = $user->daysUntilUsernameChange();
                return response()->json([
                    'berhasil' => false,
                    'pesan' => "Username hanya bisa diubah 7 hari sekali. Tersisa {$daysLeft} hari lagi."
                ], 400);
            }
            $user->username = $request->username;
            $user->username_changed_at = now();
        }

        // Update nama
        if ($request->has('name')) {
            $user->name = $request->name;
        }

        // Update telepon
        if ($request->has('telepon')) {
            $user->telepon = $request->telepon;
        }

        // Update password
        if ($request->has('password')) {
            $user->password = Hash::make($request->password);
        }

        // Upload avatar
        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $avatarPath;
        }

        // Jika user pertama kali login (dari seeder), set flag false
        if ($user->is_first_login) {
            $user->is_first_login = false;
        }

        $user->save();

        return response()->json([
            'berhasil' => true,
            'data' => $this->formatUserData($user),
            'pesan' => 'Profil berhasil diperbarui'
        ]);
    }

    /**
     * Complete first login setup (untuk user dari seeder)
     */
    public function completeFirstLogin(Request $request)
    {
        $user = JWTAuth::user();

        if (!$user->is_first_login) {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Anda sudah menyelesaikan setup awal'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|min:3|max:30|regex:/^[a-zA-Z0-9_.]+$/|unique:users,username,' . $user->id,
            'password' => 'required|string|min:8',
            'telepon' => 'nullable|string|max:20',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240', 
        ]);

        if ($validator->fails()) {
            return response()->json([
                'berhasil' => false,
                'pesan' => $validator->errors()->first()
            ], 400);
        }

        // Update username
        $user->username = $request->username;
        $user->username_changed_at = now();

        // Update password (wajib ganti dari default)
        $user->password = Hash::make($request->password);

        // Update telepon jika ada
        if ($request->has('telepon')) {
            $user->telepon = $request->telepon;
        }

        // Upload avatar jika ada
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $avatarPath;
        }

        // Set first login selesai
        $user->is_first_login = false;

        $user->save();

        return response()->json([
            'berhasil' => true,
            'data' => $this->formatUserData($user),
            'pesan' => 'Setup awal berhasil! Selamat datang.'
        ]);
    }

    /**
     * Format user data untuk response
     */
    private function formatUserData(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'telepon' => $user->telepon,
            'role' => $user->role,
            'kelas' => $user->kelas,
            'jurusan' => $user->jurusan,
            'avatar' => $user->avatar_url,
            'is_first_login' => $user->is_first_login,
            'can_change_username' => $user->canChangeUsername(),
            'days_until_username_change' => $user->daysUntilUsernameChange(),
            'dibuat_pada' => $user->created_at->toISOString(),
            'diperbarui_pada' => $user->updated_at->toISOString()
        ];
    }

    /**
     * Auto-assign existing tasks to newly registered student
     */
    private function autoAssignTasksToNewStudent(User $student)
    {
        $studentKelas = strtoupper(trim($student->kelas));
        $studentJurusan = strtoupper(trim($student->jurusan));

        $tasks = \App\Models\Tugas::where('target', 'kelas')->get();

        $assignedCount = 0;

        foreach ($tasks as $task) {
            $shouldAssign = false;
            
            foreach ($task->id_target as $target) {
                if (!isset($target['kelas']) || !isset($target['jurusan'])) {
                    continue;
                }
                
                $targetKelas = strtoupper(trim($target['kelas']));
                $targetJurusan = strtoupper(trim($target['jurusan']));
                
                if ($targetKelas === $studentKelas && $targetJurusan === $studentJurusan) {
                    $shouldAssign = true;
                    break;
                }
            }

            if ($shouldAssign) {
                $exists = \App\Models\Penugasaan::where('id_tugas', $task->id)
                    ->where('id_siswa', $student->id)
                    ->exists();

                if (!$exists) {
                    \App\Models\Penugasaan::create([
                        'id_tugas' => $task->id,
                        'id_siswa' => $student->id,
                        'status' => 'pending'
                    ]);
                    $assignedCount++;
                }
            }
        }

        \Log::info("Auto-assigned {$assignedCount} tasks to new student", [
            'student_id' => $student->id,
            'kelas' => $studentKelas,
            'jurusan' => $studentJurusan
        ]);

        return $assignedCount;
    }
}
