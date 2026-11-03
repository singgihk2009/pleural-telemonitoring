<?php

namespace App\Models;

use Database\Factories\UsabilityResponseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsabilityResponse extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'usability';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['responden_id', 'kelompok', 'versi_kuesioner', 'jawaban_item', 'skor_dampak', 'skor_kegunaan', 'skor_kemudahan', 'skor_kendali', 'skor_total', 'tanggal_pengisian', 'pengisi'];

    protected function casts(): array
    {
        return [
            'jawaban_item' => 'array',
            'tanggal_pengisian' => 'immutable_datetime',
            'skor_dampak' => 'decimal:2',
            'skor_kegunaan' => 'decimal:2',
            'skor_kemudahan' => 'decimal:2',
            'skor_kendali' => 'decimal:2',
            'skor_total' => 'decimal:2',
        ];
    }

    protected static function newFactory(): UsabilityResponseFactory
    {
        return UsabilityResponseFactory::new();
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'responden_id');
    }

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireVersion::class, 'versi_kuesioner', 'versi');
    }
}
