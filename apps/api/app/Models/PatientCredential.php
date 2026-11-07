<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class PatientCredential extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;

    protected $primaryKey = 'responden_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['responden_id', 'kelompok', 'pin_hash', 'issued_by', 'issued_at', 'revoked_at'];

    protected $hidden = ['pin_hash'];

    protected function casts(): array
    {
        return ['pin_hash' => 'hashed', 'issued_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $credential): void {
            if (! $credential->exists || $credential->isDirty(['pin_hash', 'revoked_at']) || $credential->revision === null) {
                $credential->revision = (string) Str::uuid();
            }
        });
        static::updated(function (self $credential): void {
            if ($credential->wasChanged(['pin_hash', 'revoked_at'])) {
                $credential->tokens()->delete();
            }
        });
    }

    public function getAuthPasswordName(): string
    {
        return 'pin_hash';
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'responden_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'issued_by');
    }
}
