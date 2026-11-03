<?php

namespace App\Models;

use App\Auth\AccessGrantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentifiedAccessGrantScope extends Model
{
    protected $fillable = ['scope'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['scope' => AccessGrantScope::class];
    }

    public function grant(): BelongsTo
    {
        return $this->belongsTo(IdentifiedAccessGrant::class, 'grant_id');
    }
}
