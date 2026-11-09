<x-app-layout>
    <x-slot name="header">Pendaftaran Sidang TA</x-slot>
    @php
        $canUpdateRegistration = auth()->user()->can('sidang_ta_registration_update');
        $schedulePeriods = $openPeriods->concat($drafts->pluck('period'))->concat($registrations->pluck('period'))->concat(collect([$activeRegistration?->period]))->filter()->unique('id');
    @endphp

    <div class="space-y-5">
        @if($errors->any())
            <div role="alert"
                 class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif
        @if(session('success'))
            <div role="status"
                 class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        <div data-registration-header class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0 space-y-1">
                <h2 class="text-xl font-bold text-slate-800">Pendaftaran Sidang Tugas Akhir</h2>
                <p class="text-xs leading-relaxed text-slate-500 sm:text-sm">Daftar pengajuan pendaftaran sidang tugas
                    akhir Anda.</p>
                <span data-open-period-count
                      class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">{{ $openPeriods->count() }} periode terbuka</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($activeRegistration)
                    @if($activeRegistration->status !== 'needs_review')
                        <a data-registration-active class="btn btn-secondary"
                           href="{{ route('sidang-ta-registrations.show', $activeRegistration) }}">Lihat pengajuan aktif</a>
                    @endif
                    @if($activeRegistration->status === 'needs_review')
                        @can('sidang_ta_registration_update')
                            <a data-registration-revise class="btn btn-primary"
                               href="{{ route('sidang-ta-registrations.edit', $activeRegistration) }}">Mulai revisi</a>
                        @endcan
                    @endif
                @elseif($openPeriods->isNotEmpty())
                    @can('sidang_ta_registration_create')
                        <a data-registration-start href="{{ route('sidang-ta-registrations.create') }}"
                           class="btn btn-primary shadow-sm">
                            <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor"
                                 viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 4v16m8-8H4"/>
                            </svg>
                            Mulai Pendaftaran
                        </a>
                    @else
                        <p data-registration-readonly class="text-xs text-slate-500">Akses Anda hanya untuk melihat
                            pengajuan dan draft tersimpan.</p>
                    @endcan
                @else
                    <button data-registration-closed type="button" disabled
                            class="btn btn-secondary cursor-not-allowed opacity-60">
                        <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        Pendaftaran Ditutup
                    </button>
                @endif
            </div>
        </div>
        @if(!$activeRegistration && $openPeriods->isEmpty())
            <p class="text-xs leading-relaxed text-slate-500">Pendaftaran baru belum tersedia. Draft tersimpan tetap
                dapat dilihat di bawah.</p>
        @endif
        <div data-student-period-schedules class="grid gap-4 xl:grid-cols-2">
            @foreach($schedulePeriods as $schedulePeriod)
                <div class="campus-card p-4">@include('sidang_ta_registrations.partials.period-schedule')</div>
            @endforeach
        </div>
        <section data-testid="drafts-panel" class="campus-card w-full max-w-xl space-y-4 p-5">
            <div>
                <h3 class="text-base font-bold text-slate-800">Draft tersimpan</h3>
                <p class="mt-1 text-xs leading-relaxed text-slate-500">Lanjutkan progres yang sudah disimpan sebelum
                    mengirim pendaftaran.</p>
            </div>
            @forelse($drafts as $savedDraft)
                @php
                    $source = $savedDraft->sourceRegistration;
                    $stale = $savedDraft->source_registration_id !== null
                        && (!$source || $source->status !== 'needs_review' || $source->lock_version !== $savedDraft->source_version);
                    $writePermission = $savedDraft->source_registration_id === null
                        ? 'sidang_ta_registration_create' : 'sidang_ta_registration_update';
                @endphp
                <article class="min-w-0 space-y-3 rounded-xl border border-slate-200 p-4" data-testid="draft-card">
                    <div class="space-y-1">
                        <p class="break-words text-sm font-semibold text-slate-800">{{ $savedDraft->period?->name ?? 'Periode tidak tersedia' }}</p>
                        <p class="text-xs text-slate-500">{{ $savedDraft->source_registration_id ? 'Draft revisi' : 'Draft pendaftaran baru' }}</p>
                        <p class="text-xs text-slate-500">Terakhir
                            disimpan {{ $savedDraft->updated_at->format('d/m/Y H:i') }}</p>
                    </div>
                    @if($stale)
                        <p class="rounded-lg bg-amber-50 p-3 text-xs leading-relaxed text-amber-800">Pengajuan asal
                            berubah. Hapus draft lama sebelum memulai revisi kembali.</p>
                    @endif
                    <div data-draft-actions class="flex items-center gap-2">
                        @unless($stale)
                            <a class="btn btn-secondary whitespace-nowrap !px-3 !py-2 !text-xs"
                               href="{{ route('sidang-ta-drafts.edit', $savedDraft) }}">Lanjutkan draft</a>
                        @endunless
                        @can($writePermission)
                            <button data-open-discard type="button" aria-haspopup="dialog"
                                    aria-controls="discard-draft-{{ $savedDraft->id }}"
                                    class="btn btn-secondary whitespace-nowrap !border-rose-200 !px-3 !py-2 !text-xs !text-rose-700 hover:!bg-rose-50"
                                    onclick="this.closest('article').querySelector('dialog').showModal()">Hapus draft
                            </button>
                        @endcan
                    </div>
                    @can($writePermission)
                        <dialog id="discard-draft-{{ $savedDraft->id }}"
                                aria-labelledby="discard-title-{{ $savedDraft->id }}"
                                aria-describedby="discard-description-{{ $savedDraft->id }}"
                                class="m-auto w-11/12 max-w-md rounded-2xl border border-slate-200 bg-white p-5 text-slate-800 shadow-xl backdrop:bg-slate-900/50">
                            <h4 id="discard-title-{{ $savedDraft->id }}" class="text-lg font-bold">Hapus draft ini?</h4>
                            <p class="mt-2 break-words text-sm font-medium text-slate-700">{{ $savedDraft->period?->name ?? 'Periode tidak tersedia' }}</p>
                            <div id="discard-description-{{ $savedDraft->id }}"
                                 class="mt-3 space-y-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm leading-relaxed text-amber-900">
                                <p>Draft yang dihapus tidak dapat dipulihkan. Progres dan berkas khusus draft ini akan
                                    dihapus.</p>
                                <p>Pengajuan yang sudah dikirim beserta berkas finalnya tetap tersimpan.</p>
                            </div>
                            <form method="POST" class="mt-5 flex items-center justify-end gap-2"
                                  action="{{ route('sidang-ta-drafts.destroy', $savedDraft) }}">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="version" value="{{ $savedDraft->version }}">
                                <button data-cancel-discard type="button" autofocus class="btn btn-secondary"
                                        onclick="this.closest('dialog').close()">Batal
                                </button>
                                <button data-confirm-discard type="submit" class="btn btn-primary">Ya, hapus draft
                                </button>
                            </form>
                        </dialog>
                    @endcan
                </article>
            @empty
                <p class="text-sm text-slate-500">Belum ada draft tersimpan.</p>
            @endforelse
        </section>


        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="font-bold text-slate-800">
                    Riwayat Pengajuan
                </h3>
                <p class="mt-1 text-xs text-slate-500">
                    Status dan catatan peninjauan pendaftaran Anda.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Periode</th>
                        <th class="px-5 py-3">Tanggal pengajuan</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse($registrations as $registration)
                        <tr>
                            <td class="px-5 py-4 font-medium text-slate-800">
                                {{ $registration->period?->name ?? '-' }}
                            </td>
                            <td class="px-5 py-4 text-slate-600">
                                {{ $registration->submitted_at?->translatedFormat('d M Y H:i') ?? '-' }}
                            </td>
                            <td class="px-5 py-4">
                                <x-status-badge :status="$registration->status"
                                                :label="match($registration->status) {'submitted' => 'Menunggu', 'needs_review' => 'Perlu revisi', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', default => ucfirst($registration->status)}"/>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a class="btn btn-secondary text-xs"
                                   href="{{ route($registration->status === 'needs_review' && $canUpdateRegistration ? 'sidang-ta-registrations.edit' : 'sidang-ta-registrations.show', $registration) }}">
                                    {{ $registration->status === 'needs_review' && $canUpdateRegistration ? 'Detail Revisi' : 'Detail' }}
                                </a>
                                <a data-registration-history class="btn btn-secondary text-xs" href="{{ route('sidang-ta-registrations.history', $registration) }}">Riwayat</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500">
                                Belum ada pengajuan Sidang TA.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-3">{{ $registrations->links() }}</div>
        </section>
    </div>
</x-app-layout>
