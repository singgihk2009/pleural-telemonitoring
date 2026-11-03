<?php

namespace App\Models;

use Database\Factories\OutcomeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Outcome extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'formulir_hasil';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['responden_id', 'vas_hari30', 'kekambuhan_a', 'kekambuhan_b', 'kekambuhan_c', 'kekambuhan_akhir', 'readmission', 'tanggal_readmission', 'rumah_sakit', 'sumber_data', 'dinilai_oleh', 'tanggal_penilaian', 'pengaturan_id'];

    protected $hidden = ['rumah_sakit'];

    protected function casts(): array
    {
        return [
            'kekambuhan_a' => 'boolean',
            'kekambuhan_b' => 'boolean',
            'kekambuhan_c' => 'boolean',
            'kekambuhan_akhir' => 'boolean',
            'readmission' => 'boolean',
            'tanggal_readmission' => 'immutable_date',
            'tanggal_penilaian' => 'immutable_datetime',
            'vas_hari30' => 'integer',
            'rumah_sakit' => 'encrypted',
        ];
    }

    protected static function newFactory(): OutcomeFactory
    {
        return OutcomeFactory::new();
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'responden_id');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'dinilai_oleh');
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ResearchSetting::class, 'pengaturan_id');
    }
}
