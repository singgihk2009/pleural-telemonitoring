<?php

namespace App\Models;

use Database\Factories\SymptomOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SymptomOption extends Model
{
    use HasFactory;

    protected $table = 'symptom_options';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['pengaturan_id', 'kode_gejala', 'jawaban'];

    protected static function newFactory(): SymptomOptionFactory
    {
        return SymptomOptionFactory::new();
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(ResearchSetting::class, 'pengaturan_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SymptomResponse::class, 'symptom_option_id');
    }
}
