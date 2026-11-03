<?php

namespace App\Models;

use Database\Factories\AlertFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alert extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'peringatan';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['responden_id', 'kelompok', 'entri_id', 'level', 'sebab', 'dibuat_pada', 'batas_respons_pada', 'dikonfirmasi_oleh', 'dikonfirmasi_pada', 'selesai_pada', 'status'];

    protected function casts(): array
    {
        return [
            'dibuat_pada' => 'immutable_datetime',
            'batas_respons_pada' => 'immutable_datetime',
            'dikonfirmasi_pada' => 'immutable_datetime',
            'selesai_pada' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): AlertFactory
    {
        return AlertFactory::new();
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'responden_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(DailyEntry::class, 'entri_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'dikonfirmasi_oleh');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class, 'peringatan_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class, 'peringatan_id');
    }
}
