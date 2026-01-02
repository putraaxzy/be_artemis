<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * Get registration options
     */
    public function registerOptions()
    {
        return response()->json([
            'berhasil' => true,
            'data' => [
                'kelas' => ['X', 'XI', 'XII'],
                'jurusan' => ['MPLB', 'RPL', 'PM', 'TKJ', 'AKL']
            ],
            'pesan' => 'Opsi registrasi berhasil diambil'
        ]);
    }

    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:users|regex:/^[a-zA-Z0-9_]+$/',
            'name' => 'required|string|max:255',
            'telepon' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:8',
            'kelas' => 'required|in:X,XI,XII',
            'jurusan' => 'required|in:MPLB,RPL,PM,TKJ,AKL'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'berhasil' => false,
                'pesan' => $validator->errors()->first()
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
        ]);

        $this->autoAssignTasksToNewStudent($user);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'berhasil' => true,
            'data' => [
                'token' => $token,
                'pengguna' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'name' => $user->name,
                    'telepon' => $user->telepon,
                    'role' => $user->role,
                    'kelas' => $user->kelas,
                    'jurusan' => $user->jurusan,
                    'dibuat_pada' => $user->created_at->toISOString(),
                    'diperbarui_pada' => $user->updated_at->toISOString()
                ]
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
                'pengguna' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'name' => $user->name,
                    'telepon' => $user->telepon,
                    'role' => $user->role,
                    'kelas' => $user->kelas,
                    'jurusan' => $user->jurusan,
                    'dibuat_pada' => $user->created_at->toISOString(),
                    'diperbarui_pada' => $user->updated_at->toISOString()
                ]
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
            'data' => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'telepon' => $user->telepon,
                'role' => $user->role,
                'kelas' => $user->kelas,
                'jurusan' => $user->jurusan,
                'dibuat_pada' => $user->created_at->toISOString(),
                'diperbarui_pada' => $user->updated_at->toISOString()
            ]
        ]);
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
