@php
$c=$bookingRegistration->roomSchedule;$b=$c?->booking;
$stateLabels=['unassigned'=>'Belum dijadwalkan','awaiting_lecturer'=>'Menunggu booking dosen','pending_lecturer'=>'Menunggu persetujuan ruangan','confirmed'=>'Disahkan','cancelled'=>'Booking dibatalkan','rejected'=>'Booking ditolak','completed'=>'Selesai'];
@endphp
<section data-ta-booking-card class="campus-card space-y-3 p-5">
<h3 class="font-bold">Pelaksanaan Sidang TA</h3>
<p class="text-sm text-slate-500">{{ $bookingRegistration->period?->name }} · {{ $stateLabels[$c?->state??'unassigned']??'Belum dijadwalkan' }}</p>
@if($c?->mode==='lecturer')<p class="text-sm font-semibold">Dijadwalkan melalui dosen</p>@endif
@if($b)
<p class="text-sm">Booking #{{ $b->id }} · {{ $b->room?->room_code }}</p>
@foreach($b->occurrences as $o)<p class="text-sm">{{ $o->usage_date->format('d/m/Y') }} · {{ substr($o->start_time,0,5) }}–{{ substr($o->end_time,0,5) }} WIB</p>@endforeach
@endif
<p class="text-sm text-slate-500">Pilih tanggal untuk mencari ruang kelas dan jam paling awal yang tersedia.</p>
<a class="btn btn-secondary" href="{{ route('sidang-ta-bookings.index',$bookingRegistration) }}">{{ $c && ($c->mode==='lecturer' || $c->was_confirmed) ? 'Lihat jadwal sidang' : 'Cari jadwal sidang' }}</a>
</section>
