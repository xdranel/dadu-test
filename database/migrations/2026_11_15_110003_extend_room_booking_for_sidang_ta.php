<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', fn (Blueprint $t) => $t->boolean('is_classroom')->nullable()->index());
        Schema::table('room_booking_requests', function (Blueprint $t) {
            $t->string('booking_origin')->default('ordinary');
            if (DB::getDriverName() !== 'sqlite') {
                $t->foreignId('sidang_ta_registration_id')->nullable()->constrained()->restrictOnDelete();
            }
        });
        // SQLite table rebuilds inside an existing transaction can cascade-delete children.
        // Native nullable inline REFERENCES preserves rows and enforces the same FK.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE room_booking_requests ADD COLUMN sidang_ta_registration_id INTEGER REFERENCES sidang_ta_registrations(id) ON DELETE RESTRICT');
        }
        Schema::table('room_booking_histories', function (Blueprint $t) {
            $t->json('before_data')->nullable();
            $t->json('after_data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('room_booking_histories', fn (Blueprint $t) => $t->dropColumn(['before_data', 'after_data']));
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE room_booking_requests DROP COLUMN sidang_ta_registration_id');
        }
        Schema::table('room_booking_requests', function (Blueprint $t) {
            if (DB::getDriverName() !== 'sqlite') {
                $t->dropConstrainedForeignId('sidang_ta_registration_id');
            }$t->dropColumn('booking_origin');
        });
        Schema::table('rooms', function (Blueprint $t) {
            $t->dropIndex(['is_classroom']);
            $t->dropColumn('is_classroom');
        });
    }
};
