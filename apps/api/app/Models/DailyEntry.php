<?php

namespace App\Models;

use Database\Factories\DailyEntryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyEntry extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'entri_harian';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['responden_id', 'kelompok', 'tanggal_pulang', 'tanggal_wib', 'vas_mm', 'tipe', 'diisi_oleh', 'dibuat_di_perangkat_pada', 'diterima_server_pada'];

    protected function casts(): array
    {
        return [
            'tanggal_wib' => 'immutable_date',
            'tanggal_pulang' => 'immutable_date',
            'hari_ke' => 'integer',
            'vas_mm' => 'integer',
            'dibuat_di_perangkat_pada' => 'immutable_datetime',
            'diterima_server_pada' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): DailyEntryFactory
    {
        return DailyEntryFactory::new();
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'responden_id');
    }

    public function symptoms(): HasMany
    {
        return $this->hasMany(SymptomResponse::class, 'entri_id');
    }

    public function classifications(): HasMany
    {
        return $this->hasMany(ClassificationRecord::class, 'entri_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'entri_id');
    }
}
