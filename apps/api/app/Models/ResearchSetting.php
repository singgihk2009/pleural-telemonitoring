<?php

namespace App\Models;

use Database\Factories\ResearchSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchSetting extends Model
{
    use HasFactory;

    protected $table = 'pengaturan';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['kunci', 'nilai_json', 'versi', 'berlaku_mulai', 'disetujui_oleh', 'disetujui_pada'];

    protected function casts(): array
    {
        return [
            'nilai_json' => 'array',
            'berlaku_mulai' => 'immutable_datetime',
            'disetujui_pada' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): ResearchSettingFactory
    {
        return ResearchSettingFactory::new();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'disetujui_oleh');
    }

    public function symptomOptions(): HasMany
    {
        return $this->hasMany(SymptomOption::class, 'pengaturan_id');
    }

    public function followUpOptions(): HasMany
    {
        return $this->hasMany(FollowUpOption::class, 'pengaturan_id');
    }
}
