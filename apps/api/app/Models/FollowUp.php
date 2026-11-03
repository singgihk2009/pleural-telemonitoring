<?php

namespace App\Models;

use Database\Factories\FollowUpFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUp extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'tindak_lanjut';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['peringatan_id', 'perawat_id', 'waktu', 'hasil_asesmen', 'follow_up_option_id', 'keputusan', 'vas_ulang'];

    protected $hidden = ['hasil_asesmen'];

    protected function casts(): array
    {
        return [
            'waktu' => 'immutable_datetime',
            'vas_ulang' => 'integer',
            'hasil_asesmen' => 'encrypted',
        ];
    }

    protected static function newFactory(): FollowUpFactory
    {
        return FollowUpFactory::new();
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class, 'peringatan_id');
    }

    public function nurse(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'perawat_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(FollowUpOption::class, 'follow_up_option_id');
    }
}
