<?php

namespace App\Models;

use Database\Factories\NotificationDeliveryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'notification_deliveries';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['peringatan_id', 'responden_id', 'pengguna_id', 'channel', 'template_version', 'status', 'attempts', 'error_code', 'consent_recorded_at', 'last_attempt_at', 'delivered_at'];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'consent_recorded_at' => 'immutable_datetime',
            'last_attempt_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): NotificationDeliveryFactory
    {
        return NotificationDeliveryFactory::new();
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'responden_id');
    }

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'pengguna_id');
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class, 'peringatan_id');
    }
}
