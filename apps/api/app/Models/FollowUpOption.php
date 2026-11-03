<?php

namespace App\Models;

use Database\Factories\FollowUpOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FollowUpOption extends Model
{
    use HasFactory;

    protected $table = 'follow_up_options';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['pengaturan_id', 'kode'];

    protected static function newFactory(): FollowUpOptionFactory
    {
        return FollowUpOptionFactory::new();
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(ResearchSetting::class, 'pengaturan_id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class, 'follow_up_option_id');
    }
}
