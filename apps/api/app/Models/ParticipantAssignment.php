<?php

namespace App\Models;

use Database\Factories\ParticipantAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipantAssignment extends Model
{
    use HasFactory;

    protected $table = 'participant_assignments';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['responden_id', 'pengguna_id', 'jenis', 'mulai_pada', 'berakhir_pada'];

    protected function casts(): array
    {
        return [
            'mulai_pada' => 'immutable_datetime',
            'berakhir_pada' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): ParticipantAssignmentFactory
    {
        return ParticipantAssignmentFactory::new();
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'responden_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'pengguna_id');
    }
}
