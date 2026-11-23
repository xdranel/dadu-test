<x-app-layout>
<x-slot name="header">Kelola Jadwal Pelaksanaan Sidang TA</x-slot>
<div class="mx-auto max-w-5xl space-y-5">
<a class="btn btn-secondary" href="{{ route('admin.sidang-ta-registrations.show',$registration) }}">Kembali ke peninjauan</a>
@if(session('success'))<p class="rounded bg-emerald-50 p-4 text-emerald-800">{{ session('success') }}</p>@endif
@if($errors->any())<ul role="alert" class="rounded bg-rose-50 p-4 text-rose-800">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
<section class="campus-card p-5">@include('sidang_ta_registrations.partials.booking-identity')</section>
@php($c=$registration->roomSchedule)
@php($b=$c?->booking)
<section class="campus-card space-y-3 p-5"><h2 class="font-bold">Jadwal saat ini</h2>
<p>{{ $c?->mode==='lecturer'?'Dijadwalkan melalui dosen':'Penjadwalan otomatis' }} · {{ ['unassigned'=>'Belum dijadwalkan','awaiting_lecturer'=>'Menunggu booking dosen','pending_lecturer'=>'Menunggu persetujuan ruangan','confirmed'=>'Disahkan','cancelled'=>'Dibatalkan','rejected'=>'Ditolak','completed'=>'Selesai'][$c?->state??'unassigned']??'-' }}</p>
@if($b)<p>Booking #{{ $b->id }} · {{ $b->room?->room_code }} · Pemesan {{ $b->user?->name }}</p>@foreach($b->occurrences as $o)<p>{{ $o->usage_date->format('d/m/Y') }} · {{ substr($o->start_time,0,5) }}–{{ substr($o->end_time,0,5) }} WIB</p>@endforeach @endif
</section>
@if($registration->status==='approved')
<div class="grid gap-4 md:grid-cols-2">
@if(!$b || !in_array($b->status,['pending','approved']))
@if($c?->active_student_number)
<form class="campus-card space-y-3 p-5" method="POST" action="{{ route('admin.sidang-ta-bookings.cancel',$registration) }}">@csrf<input type="hidden" name="context_version" value="{{ $c->lock_version }}"><h3 class="font-bold">Selesaikan penjadwalan yang menunggu</h3><label>Alasan <textarea required maxlength="2000" name="reason" class="campus-input"></textarea></label><button class="btn btn-secondary">Batalkan penjadwalan dosen</button></form>
@endif
@if($c?->was_confirmed)<form class="campus-card space-y-3 p-5" method="POST" action="{{ route('admin.sidang-ta-bookings.reschedule',$registration) }}">@csrf<input type="hidden" name="context_version" value="{{ $c->lock_version }}"><h3 class="font-bold">Jadwal pengganti melalui SSC</h3>
<label>Tanggal <input type="date" name="date" required class="campus-input"></label><label>Ruang kelas <select name="room_id" required class="campus-select">@foreach($rooms as $room)<option value="{{ $room->id }}">{{ $room->room_code }} · {{ $room->building ?: 'Lokasi belum diisi' }} · Kapasitas {{ $room->capacity ?: '—' }}</option>@endforeach</select></label>
<label>Jam mulai <input type="time" name="start_time" required class="campus-input"></label><label>Jam selesai <input type="time" name="end_time" required class="campus-input"></label><label>Alasan <textarea required maxlength="2000" name="reason" class="campus-input"></textarea></label><button class="btn btn-primary">Simpan perubahan jadwal</button>
</form>@endif
<form class="campus-card space-y-3 p-5" method="POST" action="{{ route('admin.sidang-ta-bookings.mark-lecturer',$registration) }}">@csrf
<input type="hidden" name="context_version" value="{{ $c?->lock_version??0 }}"><h3 class="font-bold">Dijadwalkan melalui dosen</h3><p class="text-sm">Tandai sebelum dosen memesan ruang. Mahasiswa tidak dapat mengonfirmasi pencarian otomatis setelah penandaan ini.</p><label>Alasan <span class="text-rose-600">*</span><textarea required maxlength="2000" name="reason" class="campus-input"></textarea></label><button class="btn btn-secondary">Tandai mode dosen</button>
</form>
@if($c?->mode==='lecturer' && (auth()->user()->hasRole('Akademik') || auth()->user()->can('room_booking_review')))
<form class="campus-card space-y-3 p-5" method="POST" action="{{ route('admin.sidang-ta-bookings.link-lecturer',$registration) }}">@csrf
<input type="hidden" name="context_version" value="{{ $c->lock_version }}"><h3 class="font-bold">Tautkan peminjaman dosen</h3><label>Booking dosen <select name="booking_id" required class="campus-select"><option value="">Pilih booking</option>@foreach($bookings as $choice)<option value="{{ $choice->id }}">#{{ $choice->id }} · {{ $choice->user?->name }} · {{ $choice->room?->room_code }} · {{ $choice->start_date->format('d/m/Y') }} {{ substr($choice->start_time,0,5) }}–{{ substr($choice->end_time,0,5) }} · {{ $choice->status==='pending'?'Menunggu':'Disetujui' }}</option>@endforeach</select></label><p class="text-xs">100 booking terbaru ditampilkan. Hanya booking satu tanggal yang memenuhi jendela pelaksanaan dapat ditautkan. Booking menunggu tetap memerlukan persetujuan ruang.</p><label>Alasan <textarea required maxlength="2000" name="reason" class="campus-input"></textarea></label><button class="btn btn-primary">Tautkan booking</button>
</form>
@endif
@else
<form class="campus-card space-y-3 p-5" method="POST" action="{{ route('admin.sidang-ta-bookings.reschedule',$registration) }}">@csrf<input type="hidden" name="context_version" value="{{ $c->lock_version }}"><h3 class="font-bold">Pindahkan jadwal melalui SSC</h3>
<label>Tanggal <input type="date" name="date" required class="campus-input"></label><label>Ruang kelas <select name="room_id" required class="campus-select">@foreach($rooms as $room)<option value="{{ $room->id }}">{{ $room->room_code }} · {{ $room->building ?: 'Lokasi belum diisi' }} · Kapasitas {{ $room->capacity ?: '—' }}</option>@endforeach</select></label>
<label>Jam mulai <input type="time" name="start_time" required class="campus-input"></label><label>Jam selesai <input type="time" name="end_time" required class="campus-input"></label><label>Alasan <textarea required maxlength="2000" name="reason" class="campus-input"></textarea></label><button class="btn btn-primary">Simpan perubahan jadwal</button>
</form>
<form class="campus-card space-y-3 p-5" method="POST" action="{{ route('admin.sidang-ta-bookings.cancel',$registration) }}">@csrf<input type="hidden" name="context_version" value="{{ $c->lock_version }}"><h3 class="font-bold">Batalkan booking</h3><p class="text-sm">Ruangan akan kembali tersedia. Riwayat tetap tersimpan. Mahasiswa harus menghubungi SSC untuk penjadwalan pengganti.</p><label>Alasan <textarea required maxlength="2000" name="reason" class="campus-input"></textarea></label><button class="btn btn-secondary">Batalkan booking</button>
</form>
@endif</div>
@endif
@if($b)<section class="campus-card space-y-3 p-5"><h3 class="font-bold">Riwayat booking #{{ $b->id }}</h3>@foreach($b->histories as $log)<article class="border-b border-slate-100 py-2 text-sm"><p>{{ $log->created_at->format('d/m/Y H:i') }} WIB · {{ $log->from_status }} → {{ $log->to_status }}</p><p>{{ $log->note }}</p></article>@endforeach</section>@endif
</div></x-app-layout>
