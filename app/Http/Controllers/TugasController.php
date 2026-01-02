<?php

namespace App\Http\Controllers;

use App\Models\Tugas;
use App\Models\Penugasaan;
use App\Models\User;
use App\Exports\TugasExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class TugasController extends Controller
{
    /**
     * buat tugas baru (hanya guru)
     */
    public function buatTugas(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'guru') {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Hanya guru yang dapat membuat tugas'
            ], 403);
        }

        // parse id_target jika dalam format json string (dari FormData)
        $idTarget = $request->input('id_target');
        if (is_string($idTarget)) {
            $idTarget = json_decode($idTarget, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'berhasil' => false,
                    'pesan' => 'Format id_target tidak valid'
                ], 400);
            }
            $request->merge(['id_target' => $idTarget]);
        }

        // convert string boolean ke boolean sebenarnya (dari FormData)
        if ($request->has('tampilkan_nilai')) {
            $tampilkanNilai = $request->input('tampilkan_nilai');
            if ($tampilkanNilai === 'true' || $tampilkanNilai === '1' || $tampilkanNilai === 1) {
                $request->merge(['tampilkan_nilai' => true]);
            } elseif ($tampilkanNilai === 'false' || $tampilkanNilai === '0' || $tampilkanNilai === 0) {
                $request->merge(['tampilkan_nilai' => false]);
            }
        }

        // buat validation rules berdasarkan tipe target
        $validationRules = [
            'judul' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'file_detail' => 'nullable|file|max:102400',
            'target' => 'required|in:siswa,kelas',
            'id_target' => 'required|array|min:1',
            'tipe_pengumpulan' => 'required|in:link,langsung',
            'tanggal_mulai' => 'nullable|date',
            'tanggal_deadline' => 'nullable|date|after_or_equal:tanggal_mulai',
            'tampilkan_nilai' => 'nullable|boolean',
        ];

        // tambah conditional rules untuk id_target berdasarkan tipe target
        if ($request->input('target') === 'kelas') {
            $validationRules['id_target.*'] = 'required|array';
            $validationRules['id_target.*.kelas'] = 'required|string';
            $validationRules['id_target.*.jurusan'] = 'required|string';
        } else {
            $validationRules['id_target.*'] = 'required|integer';
        }

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            return response()->json([
                'berhasil' => false,
                'pesan' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 400);
        }

        // validasi target berdasarkan tipe
        $siswaIds = [];
        if ($request->target === 'siswa') {
            // validasi langsung id siswa
            $siswaIds = $request->id_target;
            $siswaCount = User::whereIn('id', $siswaIds)->where('role', 'siswa')->count();
            if ($siswaCount !== count($siswaIds)) {
                return response()->json([
                    'berhasil' => false,
                    'pesan' => 'Beberapa ID siswa tidak valid'
                ], 400);
            }
        } else {
            // target kelas: ambil semua siswa dari kelas yang dipilih
            \Log::info('Processing kelas target', ['id_target' => $request->id_target]);

            $debugInfo = [];
            foreach ($request->id_target as $kelasInfo) {
                if (!isset($kelasInfo['kelas']) || !isset($kelasInfo['jurusan'])) {
                    return response()->json([
                        'berhasil' => false,
                        'pesan' => 'Format target kelas harus berisi kelas dan jurusan'
                    ], 400);
                }

                // trim dan uppercase untuk normalisasi
                $kelas = trim(strtoupper($kelasInfo['kelas']));
                $jurusan = trim(strtoupper($kelasInfo['jurusan']));

                \Log::info('Searching for siswa', ['kelas' => $kelas, 'jurusan' => $jurusan]);

                // simple case-insensitive match
                $siswaKelas = User::where('role', 'siswa')
                    ->whereRaw('UPPER(TRIM(kelas)) = ?', [$kelas])
                    ->whereRaw('UPPER(TRIM(jurusan)) = ?', [$jurusan])
                    ->pluck('id')
                    ->toArray();

                \Log::info('Found siswa', ['count' => count($siswaKelas), 'ids' => $siswaKelas]);

                $debugInfo[] = [
                    'kelas_dicari' => $kelas,
                    'jurusan_dicari' => $jurusan,
                    'siswa_ditemukan' => count($siswaKelas),
                    'siswa_ids' => $siswaKelas
                ];

                $siswaIds = array_merge($siswaIds, $siswaKelas);
            }
            $siswaIds = array_unique($siswaIds);

            \Log::info('Total unique siswa found', ['count' => count($siswaIds), 'ids' => $siswaIds]);
        }

        if (empty($siswaIds)) {
            // debug: cek kelas yang tersedia
            $availableKelas = User::where('role', 'siswa')
                ->select('kelas', 'jurusan')
                ->distinct()
                ->orderBy('kelas')
                ->orderBy('jurusan')
                ->get()
                ->map(fn($u) => [
                    'kelas' => $u->kelas,
                    'jurusan' => $u->jurusan,
                    'kelas_upper' => strtoupper(trim($u->kelas ?? '')),
                    'jurusan_upper' => strtoupper(trim($u->jurusan ?? ''))
                ]);

            return response()->json([
                'berhasil' => false,
                'pesan' => 'Tidak ada siswa ditemukan untuk target yang dipilih. Pastikan kelas dan jurusan sesuai dengan data siswa yang terdaftar.',
                'debug' => [
                    'target_dicari' => $request->id_target,
                    'kelas_tersedia' => $availableKelas,
                    'detail_pencarian' => $debugInfo ?? null
                ]
            ], 400);
        }

        // handle file upload
        $filePath = null;
        if ($request->hasFile('file_detail')) {
            $file = $request->file('file_detail');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('tugas_files', $fileName, 'public');
        }

        // buat tugas
        $tugas = Tugas::create([
            'id_guru' => $user->id,
            'judul' => $request->judul,
            'deskripsi' => $request->deskripsi,
            'file_detail' => $filePath,
            'target' => $request->target,
            'id_target' => $request->id_target,
            'tipe_pengumpulan' => $request->tipe_pengumpulan,
            'tanggal_mulai' => $request->tanggal_mulai,
            'tanggal_deadline' => $request->tanggal_deadline,
            'tampilkan_nilai' => $request->tampilkan_nilai ?? false,
        ]);

        // buat tugas untuk setiap siswa
        foreach ($siswaIds as $siswaId) {
            Penugasaan::create([
                'id_tugas' => $tugas->id,
                'id_siswa' => $siswaId,
                'status' => 'pending'
            ]);
        }

        return response()->json([
            'berhasil' => true,
            'data' => [
                'id' => $tugas->id,
                'id_guru' => $tugas->id_guru,
                'judul' => $tugas->judul,
                'target' => $tugas->target,
                'id_target' => $tugas->id_target,
                'tipe_pengumpulan' => $tugas->tipe_pengumpulan,
                'tampilkan_nilai' => $tugas->tampilkan_nilai,
                'total_siswa' => count($siswaIds),
                'dibuat_pada' => $tugas->created_at->toISOString(),
                'diperbarui_pada' => $tugas->updated_at->toISOString()
            ],
            'pesan' => 'Tugas berhasil dibuat'
        ], 201);
    }

    /**
     * Ambil semua tugas (filter berdasarkan role)
     */
    public function ambilTugas()
    {
        $user = auth()->user();

        if ($user->role === 'guru') {
            $tugas = Tugas::where('id_guru', $user->id)
                ->with(['penugasan'])
                ->latest()
                ->get();
        } else {
            $tugas = Tugas::whereHas('penugasan', function ($query) use ($user) {
                $query->where('id_siswa', $user->id);
            })
                ->with([
                    'guru:id,name',
                    'penugasan' => function ($query) use ($user) {
                        $query->where('id_siswa', $user->id);
                    }
                ])
                ->latest()
                ->get();
        }

        return response()->json([
            'berhasil' => true,
            'data' => $tugas->map(function ($t) use ($user) {
                $data = [
                    'id' => $t->id,
                    'judul' => $t->judul,
                    'target' => $t->target,
                    'tipe_pengumpulan' => $t->tipe_pengumpulan,
                    'tampilkan_nilai' => $t->tampilkan_nilai,
                    'dibuat_pada' => $t->created_at->toISOString(),
                ];

                if ($user->role === 'guru') {
                    $data['total_siswa'] = $t->penugasan->count();
                    $data['pending'] = $t->penugasan->where('status', 'pending')->count();
                    $data['dikirim'] = $t->penugasan->where('status', 'dikirim')->count();
                    $data['selesai'] = $t->penugasan->where('status', 'selesai')->count();
                } else {
                    $penugasan = $t->penugasan->first();
                    $data['guru'] = $t->guru->name;
                    $data['status'] = $penugasan->status ?? 'pending';

                    // siswa hanya bisa lihat nilai jika guru aktifkan fitur tampilkan_nilai
                    if ($t->tampilkan_nilai && $penugasan) {
                        $data['nilai'] = $penugasan->nilai;
                        $data['catatan_guru'] = $penugasan->catatan_guru;
                    }
                }

                return $data;
            }),
            'pesan' => 'Data tugas berhasil diambil'
        ]);
    }

    /**
     * penugasan (siswa)
     */
    public function ajukanPenugasan(Request $request, $id)
    {
        $user = auth()->user();

        if ($user->role !== 'siswa') {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Hanya siswa yang dapat mengajukan penugasan'
            ], 403);
        }

        // ambil penugasan dan tugas untuk cek tipe_pengumpulan
        $penugasan = Penugasaan::where('id_tugas', $id)
            ->where('id_siswa', $user->id)
            ->with('tugas:id,tipe_pengumpulan')
            ->first();

        if (!$penugasan) {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Penugasan tidak ditemukan'
            ], 404);
        }

        if ($penugasan->status === 'selesai') {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Penugasan sudah selesai, tidak dapat diubah'
            ], 400);
        }

        // validasi berdasarkan tipe pengumpulan
        $tipePengumpulan = $penugasan->tugas->tipe_pengumpulan;

        if ($tipePengumpulan === 'link') {
            $validator = Validator::make($request->all(), [
                'link_drive' => 'required|url'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'berhasil' => false,
                    'pesan' => $validator->errors()->first()
                ], 400);
            }

            $penugasan->update([
                'status' => 'dikirim',
                'link_drive' => $request->link_drive,
                'tanggal_pengumpulan' => now()
            ]);
        } else {
            // tipe langsung: tidak perlu link_drive, langsung update status
            $penugasan->update([
                'status' => 'dikirim',
                'tanggal_pengumpulan' => now()
            ]);
        }

        return response()->json([
            'berhasil' => true,
            'data' => [
                'id' => $penugasan->id,
                'id_tugas' => $penugasan->id_tugas,
                'id_siswa' => $penugasan->id_siswa,
                'status' => $penugasan->status,
                'link_drive' => $penugasan->link_drive,
                'tanggal_pengumpulan' => $penugasan->tanggal_pengumpulan->toISOString(),
                'dibuat_pada' => $penugasan->created_at->toISOString(),
                'diperbarui_pada' => $penugasan->updated_at->toISOString()
            ],
            'pesan' => 'Penugasan berhasil diajukan'
        ]);
    }

    /**
     * ambil penugasan pending untuk tugas tertentu (guru)
     */
    public function ambilPenugasanPending($id)
    {
        $user = auth()->user();

        if ($user->role !== 'guru') {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Hanya guru yang dapat melihat data ini'
            ], 403);
        }

        $tugas = Tugas::where('id', $id)
            ->where('id_guru', $user->id)
            ->first();

        if (!$tugas) {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Tugas tidak ditemukan'
            ], 404);
        }

        $penugasan = Penugasaan::where('id_tugas', $id)
            ->where('status', 'pending')
            ->with(['siswa:id,name,telepon,kelas,jurusan'])
            ->get();

        return response()->json([
            'berhasil' => true,
            'data' => $penugasan->map(function ($p) {
                return [
                    'id' => $p->id,
                    'id_siswa' => $p->id_siswa,
                    'siswa' => [
                        'id' => $p->siswa->id,
                        'name' => $p->siswa->name,
                        'telepon' => $p->siswa->telepon,
                        'kelas' => $p->siswa->kelas,
                        'jurusan' => $p->siswa->jurusan,
                    ],
                    'status' => $p->status,
                    'dibuat_pada' => $p->created_at->toISOString(),
                ];
            }),
            'pesan' => 'Data penugasan pending berhasil diambil'
        ]);
    }

    /**
     * ambil detail tugas dengan semua penugasan (guru)
     */
    public function ambilDetailTugas($id)
    {
        $user = auth()->user();

        if ($user->role === 'guru') {
            // guru: full detail with all students
            $tugas = Tugas::where('id', $id)
                ->where('id_guru', $user->id)
                ->with(['penugasan.siswa:id,name,username,telepon,kelas,jurusan'])
                ->first();

            if (!$tugas) {
                return response()->json([
                    'berhasil' => false,
                    'pesan' => 'Tugas tidak ditemukan atau Anda tidak memiliki akses'
                ], 404);
            }

            return response()->json([
                'berhasil' => true,
                'data' => [
                    'id' => $tugas->id,
                    'judul' => $tugas->judul,
                    'deskripsi' => $tugas->deskripsi,
                    'file_detail' => $tugas->file_detail,
                    'target' => $tugas->target,
                    'id_target' => $tugas->id_target,
                    'tipe_pengumpulan' => $tugas->tipe_pengumpulan,
                    'tanggal_mulai' => $tugas->tanggal_mulai,
                    'tanggal_deadline' => $tugas->tanggal_deadline,
                    'tampilkan_nilai' => $tugas->tampilkan_nilai,
                    'dibuat_pada' => $tugas->created_at->toISOString(),
                    'statistik' => [
                        'total_siswa' => $tugas->penugasan->count(),
                        'pending' => $tugas->penugasan->where('status', 'pending')->count(),
                        'dikirim' => $tugas->penugasan->where('status', 'dikirim')->count(),
                        'selesai' => $tugas->penugasan->where('status', 'selesai')->count(),
                        'ditolak' => $tugas->penugasan->where('status', 'ditolak')->count(),
                    ],
                    'penugasan' => $tugas->penugasan->map(function ($p) use ($tugas) {
                        $data = [
                            'id' => $p->id,
                            'siswa' => [
                                'id' => $p->siswa->id,
                                'username' => $p->siswa->username,
                                'name' => $p->siswa->name,
                                'telepon' => $p->siswa->telepon,
                                'kelas' => $p->siswa->kelas,
                                'jurusan' => $p->siswa->jurusan,
                            ],
                            'status' => $p->status,
                            'link_drive' => $p->link_drive,
                            'tanggal_pengumpulan' => $p->tanggal_pengumpulan,
                            'nilai' => $p->nilai,
                            'catatan_guru' => $p->catatan_guru,
                            'dibuat_pada' => $p->created_at->toISOString(),
                            'diperbarui_pada' => $p->updated_at->toISOString(),
                        ];
                        return $data;
                    })
                ],
                'pesan' => 'Detail tugas berhasil diambil'
            ]);
        } else {
            // siswa: only their own assignment
            $tugas = Tugas::with('guru:id,name')->find($id);

            if (!$tugas) {
                return response()->json([
                    'berhasil' => false,
                    'pesan' => 'Tugas tidak ditemukan'
                ], 404);
            }

            $penugasan = Penugasaan::where('id_tugas', $id)
                ->where('id_siswa', $user->id)
                ->first();

            if (!$penugasan) {
                return response()->json([
                    'berhasil' => false,
                    'pesan' => 'Anda tidak ditugaskan untuk tugas ini'
                ], 403);
            }

            return response()->json([
                'berhasil' => true,
                'data' => [
                    'id' => $tugas->id,
                    'judul' => $tugas->judul,
                    'deskripsi' => $tugas->deskripsi,
                    'file_detail' => $tugas->file_detail,
                    'target' => $tugas->target,
                    'tipe_pengumpulan' => $tugas->tipe_pengumpulan,
                    'tanggal_mulai' => $tugas->tanggal_mulai,
                    'tanggal_deadline' => $tugas->tanggal_deadline,
                    'tampilkan_nilai' => $tugas->tampilkan_nilai,
                    'total_siswa' => Penugasaan::where('id_tugas', $tugas->id)->count(),
                    'guru' => $tugas->guru->name ?? null,
                    'dibuat_pada' => $tugas->created_at->toISOString(),
                    'penugasan' => [
                        [
                            'id' => $penugasan->id,
                            'status' => $penugasan->status,
                            'link_drive' => $penugasan->link_drive,
                            'tanggal_pengumpulan' => $penugasan->tanggal_pengumpulan,
                            'nilai' => $penugasan->nilai,
                            'catatan_guru' => $penugasan->catatan_guru,
                        ]
                    ]
                ],
                'pesan' => 'Detail tugas berhasil diambil'
            ]);
        }
    }

    /**
     * update status penugasan (guru)
     */
    public function updateStatusPenugasan(Request $request, $id)
    {
        $user = auth()->user();

        if ($user->role !== 'guru') {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Hanya guru yang dapat mengubah status penugasan'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:selesai,ditolak',
            'nilai' => 'nullable|integer|min:0|max:100',
            'catatan_guru' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'berhasil' => false,
                'pesan' => $validator->errors()->first()
            ], 400);
        }

        $penugasan = Penugasaan::findOrFail($id);
        $tugas = $penugasan->tugas;

        if ($tugas->id_guru !== $user->id) {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Anda tidak memiliki akses untuk mengubah penugasan ini'
            ], 403);
        }

        $penugasan->update([
            'status' => $request->status,
            'nilai' => $request->nilai,
            'catatan_guru' => $request->catatan_guru,
        ]);

        return response()->json([
            'berhasil' => true,
            'data' => [
                'id' => $penugasan->id,
                'status' => $penugasan->status,
                'nilai' => $penugasan->nilai,
                'catatan_guru' => $penugasan->catatan_guru,
                'diperbarui_pada' => $penugasan->updated_at->toISOString()
            ],
            'pesan' => 'Status penugasan berhasil diubah'
        ]);
    }

    /**
     * List semua siswa
     */
    public function listSiswa(Request $request)
    {
        $siswa = User::where('role', 'siswa')
            ->select('id', 'username', 'name', 'kelas', 'jurusan')
            ->orderBy('kelas')
            ->orderBy('jurusan')
            ->orderBy('name')
            ->get();

        return response()->json([
            'berhasil' => true,
            'total' => $siswa->count(),
            'data' => $siswa
        ]);
    }

    /**
     * List kelas yang tersedia
     */
    public function listKelas(Request $request)
    {
        // get all siswa with their kelas and jurusan
        $siswaList = User::where('role', 'siswa')
            ->whereNotNull('kelas')
            ->whereNotNull('jurusan')
            ->where('kelas', '!=', '')
            ->where('jurusan', '!=', '')
            ->select('kelas', 'jurusan')
            ->get();

        // group manually untuk hindari mysql strict mode issues
        $grouped = [];
        foreach ($siswaList as $siswa) {
            $kelasKey = strtoupper(trim($siswa->kelas));
            $jurusanKey = strtoupper(trim($siswa->jurusan));
            $key = $kelasKey . '|' . $jurusanKey;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'kelas' => $kelasKey,
                    'jurusan' => $jurusanKey,
                    'jumlah_siswa' => 0
                ];
            }
            $grouped[$key]['jumlah_siswa']++;
        }

        // convert to array dan sort
        $kelas = array_values($grouped);
        usort($kelas, function ($a, $b) {
            if ($a['kelas'] === $b['kelas']) {
                return strcmp($a['jurusan'], $b['jurusan']);
            }
            return strcmp($a['kelas'], $b['kelas']);
        });

        return response()->json([
            'berhasil' => true,
            'total_kelas' => count($kelas),
            'data' => $kelas
        ]);
    }

    /**
     * Get siswa berdasarkan kelas dan jurusan
     */
    public function getSiswaByKelas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kelas' => 'required|string',
            'jurusan' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'berhasil' => false,
                'pesan' => $validator->errors()->first()
            ], 400);
        }

        $kelas = trim(strtoupper($request->kelas));
        $jurusan = trim(strtoupper($request->jurusan));

        $siswa = User::where('role', 'siswa')
            ->whereRaw('UPPER(TRIM(kelas)) = ?', [$kelas])
            ->whereRaw('UPPER(TRIM(jurusan)) = ?', [$jurusan])
            ->select('id', 'username', 'name', 'kelas', 'jurusan')
            ->get();

        return response()->json([
            'berhasil' => true,
            'pencarian' => [
                'kelas' => $kelas,
                'jurusan' => $jurusan
            ],
            'ditemukan' => $siswa->count(),
            'data' => $siswa
        ]);
    }

    /**
     * Update tugas (hanya guru)
     */
    public function updateTugas(Request $request, $id)
    {
        $user = auth()->user();

        if ($user->role !== 'guru') {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Hanya guru yang dapat mengupdate tugas'
            ], 403);
        }

        $tugas = Tugas::find($id);
        if (!$tugas) {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Tugas tidak ditemukan'
            ], 404);
        }

        if ($tugas->id_guru !== $user->id) {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Anda tidak memiliki akses untuk mengupdate tugas ini'
            ], 403);
        }

        // parse id_target jika json string
        $idTarget = $request->input('id_target');
        if (is_string($idTarget)) {
            $idTarget = json_decode($idTarget, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'berhasil' => false,
                    'pesan' => 'Format id_target tidak valid'
                ], 400);
            }
            $request->merge(['id_target' => $idTarget]);
        }

        // convert string boolean
        if ($request->has('tampilkan_nilai')) {
            $tampilkanNilai = $request->input('tampilkan_nilai');
            if ($tampilkanNilai === 'true' || $tampilkanNilai === '1' || $tampilkanNilai === 1) {
                $request->merge(['tampilkan_nilai' => true]);
            } elseif ($tampilkanNilai === 'false' || $tampilkanNilai === '0' || $tampilkanNilai === 0) {
                $request->merge(['tampilkan_nilai' => false]);
            }
        }

        // Dynamic validation rules based on target type
        $rules = [
            'judul' => 'sometimes|string|max:255',
            'deskripsi' => 'nullable|string',
            'file_detail' => 'nullable|file|max:102400',
            'hapus_file' => 'nullable|boolean',
            'target' => 'sometimes|in:kelas,siswa',
            'tipe_pengumpulan' => 'sometimes|in:link,langsung',
            'tanggal_mulai' => 'nullable|date',
            'tanggal_deadline' => 'nullable|date',
            'tampilkan_nilai' => 'nullable|boolean',
        ];

        // Determine target type (use new or existing)
        $targetType = $request->has('target') ? $request->target : $tugas->target;

        // If id_target is provided, validate it based on target type
        if ($request->has('id_target')) {
            if ($targetType === 'kelas') {
                $rules['id_target'] = 'required|array';
                $rules['id_target.*.kelas'] = 'required|string';
                $rules['id_target.*.jurusan'] = 'required|string';
            } else {
                $rules['id_target'] = 'required|array';
                $rules['id_target.*'] = 'required|integer';
            }
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'berhasil' => false,
                'pesan' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 400);
        }

        // Logic for target change or update
        $shouldUpdateAssignments = false;
        $siswaIds = [];

        // Check if we need to update assignments
        if ($request->has('target') || $request->has('id_target')) {
            $shouldUpdateAssignments = true;

            $newIdTarget = $request->has('id_target') ? $request->id_target : $tugas->id_target;
            // Decode if it's still a json string in $tugas->id_target when not updated in request (unlikely given cast but safe)
            if (is_string($newIdTarget))
                $newIdTarget = json_decode($newIdTarget, true);

            if ($targetType === 'siswa') {
                // Validate siswa IDs
                $siswaIds = $newIdTarget;
                $siswaCount = User::whereIn('id', $siswaIds)->where('role', 'siswa')->count();
                if ($siswaCount !== count($siswaIds)) {
                    return response()->json([
                        'berhasil' => false,
                        'pesan' => 'Beberapa ID siswa tidak valid'
                    ], 400);
                }
            } else {
                // Target kelas
                foreach ($newIdTarget as $kelasInfo) {
                    $kelas = trim(strtoupper($kelasInfo['kelas']));
                    $jurusan = trim(strtoupper($kelasInfo['jurusan']));

                    $siswaKelas = User::where('role', 'siswa')
                        ->whereRaw('UPPER(TRIM(kelas)) = ?', [$kelas])
                        ->whereRaw('UPPER(TRIM(jurusan)) = ?', [$jurusan])
                        ->pluck('id')
                        ->toArray();

                    $siswaIds = array_merge($siswaIds, $siswaKelas);
                }
                $siswaIds = array_unique($siswaIds);
            }

            if (empty($siswaIds)) {
                return response()->json([
                    'berhasil' => false,
                    'pesan' => 'Tidak ada siswa ditemukan untuk target yang dipilih.'
                ], 400);
            }
        }


        // update tugas
        if ($request->has('judul'))
            $tugas->judul = $request->judul;
        if ($request->has('deskripsi'))
            $tugas->deskripsi = $request->deskripsi;
        if ($request->has('target'))
            $tugas->target = $request->target;
        if ($request->has('id_target'))
            $tugas->id_target = $request->id_target; // Cast handling will take care of array
        if ($request->has('tipe_pengumpulan'))
            $tugas->tipe_pengumpulan = $request->tipe_pengumpulan;
        if ($request->has('tanggal_mulai'))
            $tugas->tanggal_mulai = $request->tanggal_mulai;
        if ($request->has('tanggal_deadline'))
            $tugas->tanggal_deadline = $request->tanggal_deadline;
        if ($request->has('tampilkan_nilai'))
            $tugas->tampilkan_nilai = $request->tampilkan_nilai;

        // handle file deletion
        if ($request->input('hapus_file') === true || $request->input('hapus_file') === 'true') {
            if ($tugas->file_detail && \Storage::exists('public/' . $tugas->file_detail)) {
                \Storage::delete('public/' . $tugas->file_detail);
            }
            $tugas->file_detail = null;
        }

        // handle file upload
        if ($request->hasFile('file_detail')) {
            // delete old file if exists
            if ($tugas->file_detail && \Storage::exists('public/' . $tugas->file_detail)) {
                \Storage::delete('public/' . $tugas->file_detail);
            }
            $filePath = $request->file('file_detail')->store('tugas', 'public');
            $tugas->file_detail = $filePath;
        }

        $tugas->save();

        if ($shouldUpdateAssignments) {
            $oldTargetType = $tugas->target;
            $newTargetType = $request->target ?? $oldTargetType;

            $oldIdTarget = $tugas->id_target;
            $newIdTarget = $request->has('id_target') ? $request->id_target : $oldIdTarget;
            if (is_string($newIdTarget))
                $newIdTarget = json_decode($newIdTarget, true);
            if (is_string($oldIdTarget))
                $oldIdTarget = json_decode($oldIdTarget, true);

            $targetChanged = ($oldTargetType !== $newTargetType);

            if (!$targetChanged) {
                $sortedOld = $oldIdTarget;
                $sortedNew = $newIdTarget;

                if (is_array($sortedOld) && is_array($sortedNew)) {
                    if ($newTargetType === 'kelas') {
                        usort($sortedOld, function ($a, $b) {
                            return strcmp(($a['kelas'] ?? '') . ($a['jurusan'] ?? ''), ($b['kelas'] ?? '') . ($b['jurusan'] ?? ''));
                        });
                        usort($sortedNew, function ($a, $b) {
                            return strcmp(($a['kelas'] ?? '') . ($a['jurusan'] ?? ''), ($b['kelas'] ?? '') . ($b['jurusan'] ?? ''));
                        });
                    } else {
                        sort($sortedOld);
                        sort($sortedNew);
                    }

                    if ($sortedOld !== $sortedNew) {
                        $targetChanged = true;
                    }
                } else {
                    $targetChanged = true;
                }
            }

            if ($targetChanged) {
                Penugasaan::where('id_tugas', $id)->delete();

                foreach ($siswaIds as $siswaId) {
                    Penugasaan::create([
                        'id_tugas' => $tugas->id,
                        'id_siswa' => $siswaId,
                        'status' => 'pending',
                    ]);
                }
            }
        }

        return response()->json([
            'berhasil' => true,
            'pesan' => 'Tugas berhasil diupdate',
            'data' => $tugas
        ]);
    }

    /**
     * Hapus tugas (hanya guru)
     */
    public function hapusTugas($id)
    {
        $user = auth()->user();

        if ($user->role !== 'guru') {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Hanya guru yang dapat menghapus tugas'
            ], 403);
        }

        $tugas = Tugas::find($id);
        if (!$tugas) {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Tugas tidak ditemukan'
            ], 404);
        }

        if ($tugas->id_guru !== $user->id) {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Anda tidak memiliki akses untuk menghapus tugas ini'
            ], 403);
        }

        // Delete file if exists
        if ($tugas->file_detail && \Storage::exists('public/' . $tugas->file_detail)) {
            \Storage::delete('public/' . $tugas->file_detail);
        }

        // Delete all penugasan related to this tugas
        Penugasaan::where('id_tugas', $id)->delete();

        // Delete tugas
        $tugas->delete();

        return response()->json([
            'berhasil' => true,
            'pesan' => 'Tugas berhasil dihapus'
        ]);
    }

    /**
     * export tugas ke excel (hanya guru)
     */
    public function exportTugas($id)
    {
        $user = auth()->user();

        if ($user->role !== 'guru') {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Hanya guru yang dapat export tugas'
            ], 403);
        }

        $tugas = Tugas::where('id', $id)
            ->where('id_guru', $user->id)
            ->first();

        if (!$tugas) {
            return response()->json([
                'berhasil' => false,
                'pesan' => 'Tugas tidak ditemukan'
            ], 404);
        }

        // sanitize judul untuk nama file
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $tugas->judul);
        $filename = substr($filename, 0, 50) . '_' . date('Y-m-d_H-i-s') . '.xlsx';

        return Excel::download(new TugasExport($id, $user->id), $filename);
    }
}


