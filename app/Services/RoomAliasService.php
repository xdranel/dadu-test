<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Room;
use App\Support\WeeklyRoomScheduleNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomAliasService
{
    public function save(?Room $room, array $values): Room
    {
        return DB::transaction(function () use ($room, $values) {
            AcademicPeriod::orderBy('id')->lockForUpdate()->get();
            $rooms = Room::orderBy('id')->lockForUpdate()->get();
            $aliases = preg_split('/[\r\n,]+/', $values['igracias_aliases'] ?? '');
            unset($values['igracias_aliases']);
            $room = $room ? $rooms->firstWhere('id', $room->id) : new Room;
            $room->fill($values);
            $room->save();
            foreach ($aliases as $label) {
                $label = trim($label);
                if ($label === '') {
                    continue;
                }$key = WeeklyRoomScheduleNormalizer::roomKey($label);
                $found = DB::table('room_schedule_aliases')->where('alias_key', $key)->first();
                $other = $rooms->contains(fn ($r) => $r->id !== $room->id && in_array($key, [WeeklyRoomScheduleNormalizer::roomKey($r->room_code), WeeklyRoomScheduleNormalizer::roomKey($r->name)], true));
                if (! $key || mb_strlen($label) > 255 || ($found && (int) $found->room_id !== $room->id) || $other) {
                    throw ValidationException::withMessages(['igracias_aliases' => 'Alias tidak valid atau sudah menunjuk ruangan lain. Data tidak diubah.']);
                }
                if (! $found) {
                    DB::table('room_schedule_aliases')->insert(['room_id' => $room->id, 'alias_key' => $key, 'label' => $label, 'created_at' => now(), 'updated_at' => now()]);
                }
            }

return $room;
        }, 3);
    }
}
