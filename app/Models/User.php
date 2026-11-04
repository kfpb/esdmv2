<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'employee_nip', 'username', 'role', 'is_active', 'photo_url', 'last_login_at', 'last_login_ip'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_nip', 'nip');
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'superadmin', 'director']);
    }

    public function isHrd(): bool
    {
        return in_array($this->role, ['admin', 'hrd']);
    }

    public function hasAdminAccess(): bool
    {
        return in_array($this->role, ['admin', 'superadmin', 'director', 'hrd', 'supervisor']);
    }

    public function isSecurity(): bool
    {
        return str_starts_with($this->username ?? '', 'SC_')
            || str_starts_with($this->employee_nip ?? '', 'SC_')
            || $this->role === 'security'
            || $this->isAdmin();
    }

    public function isSupervisor(): bool
    {
        if (in_array($this->role, ['supervisor', 'admin', 'superadmin', 'director'])) {
            return true;
        }

        // Cek apakah NIP ini memiliki bawahan di tabel struktur
        if (! empty($this->employee_nip)) {
            try {
                $hasSubordinates = \Illuminate\Support\Facades\DB::table('sql_sdm.tbl_struktur')
                    ->where('id_jab_b', $this->employee_nip)
                    ->exists();
                if ($hasSubordinates) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Ignore fallback
            }
        }

        return false;
    }
}
