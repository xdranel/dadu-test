<?php

namespace Database\Seeders;

use App\Models\QueueTicket;
use App\Models\QueueState;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class QueueTicketSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing data to avoid constraint violations during seed
        QueueState::where('id', 1)->update([
            'current_id' => null,
            'last_id' => null,
        ]);
        QueueTicket::query()->delete();

        // 1. Create some sample tickets
        $ticket1 = QueueTicket::create([
            'code' => 'A-001',
            'status' => 'finished',
            'device_token' => (string) Str::uuid(),
            'called_at' => now()->subMinutes(15),
            'finished_at' => now()->subMinutes(10),
        ]);

        $ticket2 = QueueTicket::create([
            'code' => 'A-002',
            'status' => 'called',
            'device_token' => (string) Str::uuid(),
            'called_at' => now()->subMinutes(5),
            'finished_at' => null,
        ]);

        $ticket3 = QueueTicket::create([
            'code' => 'A-003',
            'status' => 'waiting',
            'device_token' => (string) Str::uuid(),
        ]);

        $ticket4 = QueueTicket::create([
            'code' => 'A-004',
            'status' => 'waiting',
            'device_token' => (string) Str::uuid(),
        ]);

        // 2. Setup the singleton queue_states row
        QueueState::updateOrCreate(
            ['id' => 1],
            [
                'current_id' => $ticket2->id,
                'last_id' => $ticket1->id,
            ]
        );
    }
}
