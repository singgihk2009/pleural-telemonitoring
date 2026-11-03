<?php

namespace App\Models;

use Database\Factories\QuestionnaireVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionnaireVersion extends Model
{
    use HasFactory;

    protected $table = 'questionnaire_versions';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['versi', 'definisi_item', 'aturan_skoring', 'disetujui_oleh', 'disetujui_pada'];

    protected function casts(): array
    {
        return [
            'definisi_item' => 'array',
            'aturan_skoring' => 'array',
            'disetujui_pada' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): QuestionnaireVersionFactory
    {
        return QuestionnaireVersionFactory::new();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'disetujui_oleh');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(UsabilityResponse::class, 'versi_kuesioner', 'versi');
    }
}
