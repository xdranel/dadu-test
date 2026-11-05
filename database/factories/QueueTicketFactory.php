<?php

namespace Database\Factories;

use App\Models\QueueTicket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class QueueTicketFactory extends Factory
{
    protected $model = QueueTicket::class;

    public function definition(): array
    {
        return [
            'code' => 'A-' . str_pad(fake()->numberBetween(1, 100), 3, '0', STR_PAD_LEFT),
            'status' => 'waiting',
            'device_token' => (string) Str::uuid(),
            'called_at' => null,
            'finished_at' => null,
        ];
    }
}
