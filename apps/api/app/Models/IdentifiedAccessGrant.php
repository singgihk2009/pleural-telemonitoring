<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentifiedAccessGrant extends Model
{
    protected $fillable = ['pengguna_id', 'responden_id', 'approved_by', 'purpose_code', 'starts_at', 'expires_at', 'revoked_at'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'pengguna_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'responden_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'approved_by');
    }
}
