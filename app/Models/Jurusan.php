<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jurusan extends Model
{
    use HasFactory;

    protected $fillable = [
        'kelas', // 'tingkat' might be better but let's stick to 'kelas' to match previous code usage if possible, or 'tingkat' as per plan? 
                 // The previous code used 'kelas' for 'X', 'XI', 'XII'. 
                 // Plan said 'tingkat'. I will use 'kelas' because the frontend sends 'kelas' (X, XI, XII) and it reduces friction.
                 // Actually the frontend sends `kelas` (X, XI) and `jurusan` (RPL, TKJ).
                 // So the table should probably have `kelas` (e.g. X) and `nama` (e.g. RPL) or `jurusan` (e.g. RPL).
                 // I will use `kelas` and `jurusan` (or `nama_jurusan`) to be clear.
                 // Let's stick to `kelas` and `jurusan` to match the User model.
        'jurusan',
    ];
}
