<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tugas extends Model
{
    use HasFactory;

    protected $table = 'tugas';
    
    protected $fillable = [
        'id_guru',
        'judul',
        'deskripsi',
        'file_detail',
        'target',
        'id_target',
        'tipe_pengumpulan',
        'tanggal_mulai',
        'tanggal_deadline',
        'tampilkan_nilai',
    ];

    protected $casts = [
        'id_target' => 'array',
        'tampilkan_nilai' => 'boolean',
        'tanggal_mulai' => 'datetime',
        'tanggal_deadline' => 'datetime',
    ];

    /**
     * Relasi ke user (guru pembuat tugas)
     */
    public function guru()
    {
        return $this->belongsTo(User::class, 'id_guru');
    }

    /**
     * Relasi ke penugasan
     */
    public function penugasan()
    {
        return $this->hasMany(Penugasaan::class, 'id_tugas');
    }

    /**
     * Relasi ke bot reminder
     */
    public function botReminders()
    {
        return $this->hasMany(BotReminder::class, 'id_tugas');
    }
}