<?php

namespace Database\Factories;

use App\Models\NotificationDelivery;
use App\Models\Participant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Wholly fabricated test data. Never use to populate a research deployment. */
class NotificationDeliveryFactory extends Factory
{
    protected $model = NotificationDelivery::class;

    public function definition(): array
    {
        return [
            'responden_id' => Participant::factory(),
            'pengguna_id' => null,
            'channel' => 'FCM',
            'template_version' => 'SYNTHETIC-TEST-v1',
            'status' => 'PLANNED',
            'attempts' => 0,
        ];
    }
}
