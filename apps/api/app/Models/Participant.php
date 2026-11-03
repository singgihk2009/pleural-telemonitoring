<?php

namespace App\Models;

use Database\Factories\ParticipantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Participant extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'responden';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['kode', 'kelompok', 'tanggal_pulang', 'vas_baseline_mm', 'status', 'alasan_drop_out', 'tanggal_consent', 'versi_consent'];

    protected $hidden = ['alasan_drop_out'];

    protected function casts(): array
    {
        return [
            'tanggal_pulang' => 'immutable_date',
            'tanggal_consent' => 'immutable_date',
            'vas_baseline_mm' => 'integer',
        ];
    }

    protected static function newFactory(): ParticipantFactory
    {
        return ParticipantFactory::new();
    }

    public function contact(): HasOne
    {
        return $this->hasOne(ParticipantContact::class, 'responden_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(DailyEntry::class, 'responden_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'responden_id');
    }

    public function outcome(): HasOne
    {
        return $this->hasOne(Outcome::class, 'responden_id');
    }

    public function usabilityResponses(): HasMany
    {
        return $this->hasMany(UsabilityResponse::class, 'responden_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ParticipantAssignment::class, 'responden_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class, 'responden_id');
    }
}
