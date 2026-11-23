<x-app-layout>
<x-slot name="header">Jadwal Pelaksanaan Sidang TA</x-slot>
<div class="mx-auto max-w-4xl space-y-5">
<a class="btn btn-secondary" href="{{ route('sidang-ta-registrations.index') }}">Kembali ke pendaftaran</a>
@if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('success') }}</p>@endif
@if($errors->any())<ul role="alert" class="rounded-xl bg-rose-50 p-4 text-rose-800">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
<section class="campus-card space-y-4 p-5"><h2 class="font-bold">Identitas sidang</h2>@include('sidang_ta_registrations.partials.booking-identity')</section>
@php($c=$registration->roomSchedule)
@if($registration->status!=='approved')
<p class="campus-card p-5">Pengajuan belum disetujui. Jadwal belum dapat dipesan.</p>
@elseif($c && ($c->mode==='lecturer' || $c->was_confirmed || $c->room_booking_request_id))
@include('sidang_ta_registrations.partials.booking-card',['bookingRegistration'=>$registration])
@elseif(!$isLatestApproved)
<p class="campus-card p-5">Gunakan pengajuan disetujui terbaru pada halaman Pendaftaran Sidang TA. Hubungi SSC jika memerlukan bantuan.</p>
@elseif(!$registration->period?->executionSetting)
<p class="campus-card p-5">Jadwal pelaksanaan belum ditetapkan. Silakan hubungi SSC.</p>
@else
@php($s=$registration->period->executionSetting)
<section class="campus-card space-y-4 p-5">
<h2 class="font-bold">Cari jadwal</h2>
<p class="text-sm">Pelaksanaan {{ $s->start_date->format('d/m/Y') }}–{{ $s->end_date->format('d/m/Y') }}. Durasi {{ $s->duration_minutes }} menit. Ruangan dan jam dipilih otomatis berdasarkan waktu tersedia paling awal.</p>
<form method="POST" action="{{ route('sidang-ta-bookings.search',$registration) }}" class="flex flex-wrap items-end gap-3">@csrf
<label>Tanggal sidang <span class="text-rose-600">*</span><input required type="date" name="date" min="{{ max(today()->toDateString(),$s->start_date->toDateString()) }}" max="{{ $s->end_date->toDateString() }}" value="{{ old('date',request('date')) }}" class="campus-input mt-1"></label>
@can('sidang_ta_registration_create')<button class="btn btn-primary">Cari jadwal</button>@endcan
</form></section>
@if($preview)
<section class="campus-card space-y-4 p-5"><h2 class="font-bold">Hasil pencarian</h2>
@if($preview['candidate'])
@php($candidate=$preview['candidate'])
<p class="text-lg font-semibold">{{ \Carbon\Carbon::parse($candidate['date'])->format('d/m/Y') }} · {{ substr($candidate['start_time'],0,5) }}–{{ substr($candidate['end_time'],0,5) }} WIB</p>
<p>{{ $candidate['room_code'] }} · {{ $candidate['duration_minutes'] }} menit</p>
<p class="rounded bg-amber-50 p-3 text-sm text-amber-800">Ketersediaan ruangan belum memastikan kehadiran pembimbing dan penguji. Pastikan kesediaan dosen sebelum konfirmasi.</p>
<p class="text-sm text-slate-500">Hasil ini belum menahan ruangan. Konfirmasi untuk mengesahkan. Jika jadwal kurang sesuai, hubungi dosen dan SSC untuk pengaturan melalui peminjaman ruangan.</p>
<form method="POST" action="{{ route('sidang-ta-bookings.confirm',$registration) }}">@csrf<input type="hidden" name="preview_token" value="{{ $preview['preview_token'] }}"><button class="btn btn-primary">Konfirmasi jadwal</button></form>
@else<p role="status" class="rounded bg-amber-50 p-3 text-amber-800">{{ $preview['reason'] }}</p>@endif
</section>
@endif
@endif
</div></x-app-layout>
