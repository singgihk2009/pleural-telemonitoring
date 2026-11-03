<?php

namespace App\Models;

use Database\Factories\SymptomResponseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SymptomResponse extends Model
{
    use HasFactory;

    protected $table = 'jawaban_gejala';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['entri_id', 'symptom_option_id', 'kode_gejala', 'jawaban'];

    protected static function newFactory(): SymptomResponseFactory
    {
        return SymptomResponseFactory::new();
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(DailyEntry::class, 'entri_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(SymptomOption::class, 'symptom_option_id');
    }
}
