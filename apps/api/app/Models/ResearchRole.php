<?php

namespace App\Models;

use Database\Factories\ResearchRoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ResearchRole extends Model
{
    use HasFactory;

    protected $table = 'user_roles';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['kode'];

    protected static function newFactory(): ResearchRoleFactory
    {
        return ResearchRoleFactory::new();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(StaffUser::class, 'role_assignments', 'role_id', 'pengguna_id');
    }
}
