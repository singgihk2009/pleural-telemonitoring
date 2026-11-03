<?php

namespace App\Models;

use Database\Factories\StaffUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffUser extends Model
{
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
