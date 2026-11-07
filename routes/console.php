<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::call(function () {
    DB::transaction(function () {
        // Move all data from queue_tickets to queue_ticket_histories
        $tickets = DB::table('queue_tickets')->get();
        foreach ($tickets as $ticket) {
            $archivedStatus = in_array($ticket->status, ['waiting', 'called']) ? 'unserved' : $ticket->status;
            DB::table('queue_ticket_histories')->updateOrInsert(
                ['id' => $ticket->id],
                [
                    'code' => $ticket->code,
                    'nim' => $ticket->nim,
                    'name' => $ticket->name,
                    'study_program' => $ticket->study_program,
                    'service_category' => $ticket->service_category,
                    'notes' => $ticket->notes,
                    'queue_date' => $ticket->queue_date ?? ($ticket->created_at ? substr($ticket->created_at, 0, 10) : now()->toDateString()),
                    'status' => $archivedStatus,
                    'device_token' => $ticket->device_token,
                    'counter_id' => $ticket->counter_id ?? null,
                    'called_by' => $ticket->called_by ?? null,
                    'called_at' => $ticket->called_at,
                    'finished_at' => $ticket->finished_at,
                    'skipped_at' => $ticket->skipped_at ?? null,
                    'skipped_by' => $ticket->skipped_by ?? null,
                    'created_at' => $ticket->created_at,
                    'updated_at' => $ticket->updated_at,
                ]
            );
        }

        // Reset the queue_states table for all counters
        DB::table('queue_states')->update([
            'current_id' => null,
            'last_id' => null,
            'updated_at' => now(),
        ]);

        // Delete all records from queue_tickets
        DB::table('queue_tickets')->delete();
    });
})->dailyAt('08:00')->timezone('Asia/Jakarta');

// Export otomatis arsip bulanan antrian pada tanggal 1 tiap bulan jam 01:00 WIB
Schedule::command('queue:export-month')
    ->monthlyOn(1, '01:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('sidang-ta:expire-revisions')->everyMinute()->withoutOverlapping(10);

Schedule::command('sidang-ta:purge-expired-drafts')->everyMinute()->withoutOverlapping(10);
