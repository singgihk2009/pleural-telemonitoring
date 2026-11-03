<?php

namespace App\Models;

use Database\Factories\RoleAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleAssignment extends Model
{
    use HasFactory;

    protected $table = 'role_assignments';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['pengguna_id', 'role_id'];

    protected static function newFactory(): RoleAssignmentFactory
    {
        return RoleAssignmentFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'pengguna_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(ResearchRole::class, 'role_id');
    }
}
