<?php

namespace App\Models;

use Database\Factories\ClassificationRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassificationRecord extends Model
{
    use HasFactory;

    protected $table = 'klasifikasi';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['entri_id', 'level', 'aturan_terpicu', 'ruleset_key', 'ruleset_version'];

    protected function casts(): array
    {
        return [
            'aturan_terpicu' => 'array',
        ];
    }

    protected static function newFactory(): ClassificationRecordFactory
    {
        return ClassificationRecordFactory::new();
    }

    public function ruleset(): BelongsTo
    {
        return $this->belongsTo(ResearchSetting::class, 'ruleset_version', 'versi')->where('kunci', 'classification');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(DailyEntry::class, 'entri_id');
    }
}
