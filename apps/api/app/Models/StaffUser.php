<?php

namespace App\Models;

use App\Auth\ResearchRoleCode;
use Database\Factories\StaffUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class StaffUser extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;

    protected $table = 'pengguna';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['nama', 'email', 'password_hash', 'status'];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
        ];
    }

    protected static function newFactory(): StaffUserFactory
    {
        return StaffUserFactory::new();
    }

    protected static function booted(): void
    {
        static::updated(function (self $staff): void {
            if ($staff->wasChanged(['password_hash', 'status'])) {
                $staff->tokens()->delete();
            }
        });
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function hasRole(ResearchRoleCode $role): bool
    {
        return $this->roles()->where('kode', $role->value)->exists();
    }

    public function roleCodes(): array
    {
        return $this->roles()->pluck('kode')->all();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(ResearchRole::class, 'role_assignments', 'pengguna_id', 'role_id');
    }

    public function participantAssignments(): HasMany
    {
        return $this->hasMany(ParticipantAssignment::class, 'pengguna_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }
}
