<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'name',
        'telepon',
        'role',
        'kelas',
        'jurusan',
        'password',
        'avatar',
        'username_changed_at',
        'is_first_login',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'username_changed_at' => 'datetime',
            'is_first_login' => 'boolean',
        ];
    }

    /**
     * Check if user can change username (limit 7 days)
     */
    public function canChangeUsername(): bool
    {
        if (!$this->username_changed_at) {
            return true;
        }
        return $this->username_changed_at->addDays(7)->isPast();
    }

    /**
     * Get days until username can be changed
     */
    public function daysUntilUsernameChange(): int
    {
        if (!$this->username_changed_at) {
            return 0;
        }
        $nextChangeDate = $this->username_changed_at->addDays(7);
        if ($nextChangeDate->isPast()) {
            return 0;
        }
        return now()->diffInDays($nextChangeDate, false);
    }

    /**
     * Get avatar URL
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar) {
            return null;
        }
        return asset('storage/' . $this->avatar);
    }

    /**
     * Relasi ke tugas yang dibuat oleh guru
     */
    public function tugas()
    {
        return $this->hasMany(Tugas::class, 'id_guru');
    }

    /**
     * Relasi ke penugasan untuk siswa
     */
    public function penugasan()
    {
        return $this->hasMany(Penugasaan::class, 'id_siswa');
    }

    /**
     * Relasi ke bot reminder
     */
    public function botReminders()
    {
        return $this->hasMany(BotReminder::class, 'id_siswa');
    }

    /**
     * Get the identifier that will be stored in the JWT subject claim.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
}
