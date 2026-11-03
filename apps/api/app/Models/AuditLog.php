<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_log';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    public $timestamps = false;

    protected $fillable = ['actor_id', 'aksi', 'entity_type', 'entity_id', 'terjadi_pada', 'metadata_redacted'];

    protected function casts(): array
    {
        return [
            'metadata_redacted' => 'array',
            'terjadi_pada' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): AuditLogFactory
    {
        return AuditLogFactory::new();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'actor_id');
    }
}
