<x-app-layout>
<x-slot name="header">Pelaksanaan Sidang TA</x-slot>
<div class="space-y-5">
@if($currentInfo)
@include('sidang_ta_execution.partials.info', ['info'=>$currentInfo])
@else
<section class="campus-card p-5"><h2 class="font-bold">Info Sidang</h2><p class="mt-2 text-sm text-slate-500">Belum ada pelaksanaan sidang aktif. Jadwal yang telah lewat dapat dilihat pada riwayat.</p></section>
@endif
<section class="campus-card overflow-hidden"><h2 class="p-5 font-bold">Riwayat Pelaksanaan</h2>
<div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-slate-500"><tr>@foreach(['Periode','Tanggal','Jam (WIB)','Status','Detail'] as $label)<th class="p-4">{{ $label }}</th>@endforeach</tr></thead><tbody>
@forelse($history as $row)
@php($info=$row['info'])
<tr class="border-t border-slate-100"><td class="p-4">{{ $info['period'] }}</td><td class="p-4">{{ $info['date'] ?: '—' }}</td><td class="p-4">{{ $info['start_time'] ?: '—' }} – {{ $info['end_time'] ?: '—' }}</td><td class="p-4">@include('sidang_ta_execution.partials.result')</td><td class="p-4"><a class="btn btn-secondary" href="{{ route('sidang-ta-execution.show',$row['registration']) }}">Detail</a></td></tr>
@empty<tr><td colspan="5" class="p-5 text-slate-500">Belum ada riwayat pelaksanaan.</td></tr>@endforelse
</tbody></table></div></section>
</div></x-app-layout>
