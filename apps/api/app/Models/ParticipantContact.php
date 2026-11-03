<?php

namespace App\Models;

use Database\Factories\ParticipantContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipantContact extends Model
{
    use HasFactory;

    protected $table = 'kontak_responden';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $primaryKey = 'responden_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['responden_id', 'inisial_encrypted', 'no_hp_pasien_encrypted', 'no_hp_keluarga_encrypted'];

    protected $hidden = ['inisial_encrypted', 'no_hp_pasien_encrypted', 'no_hp_keluarga_encrypted'];

    protected function casts(): array
    {
        return [
            'inisial_encrypted' => 'encrypted',
            'no_hp_pasien_encrypted' => 'encrypted',
            'no_hp_keluarga_encrypted' => 'encrypted',
        ];
    }

    protected static function newFactory(): ParticipantContactFactory
    {
        return ParticipantContactFactory::new();
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'responden_id');
    }
}
