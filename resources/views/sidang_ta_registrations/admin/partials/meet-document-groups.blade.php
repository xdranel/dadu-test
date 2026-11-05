<section aria-labelledby="meet-documents-title" class="space-y-4">
    <h2 id="meet-documents-title" class="text-lg font-bold text-slate-800">Dokumen pengajuan</h2>
    @foreach($documentGroups as $title => $types)
        @php
            $stage = $loop->iteration + 1;
        @endphp
        <section data-meet-document-group="{{ $stage }}" class="campus-card space-y-4 p-5">
            <header>
                <p class="text-xs font-semibold text-slate-500">Tahap {{ $stage }}</p>
                <h3 class="mt-1 font-bold text-slate-800">{{ $title }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ count($types) }} berkas pengajuan</p>
            </header>
            <div data-meet-document-grid class="grid items-start gap-4 lg:grid-cols-2">
                @foreach($types as $type)
                    @php
                        $document = $registration->documents->firstWhere('document_type', $type);
                        $label = \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$type] ?? $type;
                        $available = $document && \Illuminate\Support\Facades\Storage::disk('local')->exists($document->file_path);
                        $state = !$document ? 'missing' : (!$available ? 'unavailable' : $document->status);
                        $tone = match ($state) {
                            'verified' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
                            'rejected' => 'border-amber-200 bg-amber-50 text-amber-900',
                            'missing', 'unavailable' => 'border-orange-200 bg-orange-50 text-orange-900',
                            default => 'border-slate-200 bg-slate-50 text-slate-800',
                        };
                        $stateLabel = match ($state) {
                            'verified' => 'Terverifikasi', 'rejected' => 'Perlu perbaikan',
                            'missing' => 'Dokumen belum tersedia', 'unavailable' => 'Berkas tidak ditemukan',
                            default => 'Menunggu verifikasi',
                        };
                    @endphp
                    @if($document)
                        <article id="review-document-{{ $document->id }}" data-ta-detail-document="{{ $document->id }}"
                                 data-document-review-state="{{ $state }}"
                                 class="scroll-mt-24 space-y-3 rounded-xl border p-4 {{ $tone }}">
                            <div class="min-w-0">
                                <h4 class="text-sm font-bold leading-relaxed">{{ $label }}</h4>
                                <p class="mt-1 break-words text-xs opacity-80">{{ $document->file_name }}
                                    · {{ number_format(($document->file_size ?? 0) / 1024) }} KB</p>
                                <span data-document-state-label
                                      class="mt-2 inline-flex rounded-full border border-current bg-white/60 px-2 py-1 text-xs font-semibold">
                                    {{ $stateLabel }}
                                </span>
                                @if($document->notes)
                                    <p data-document-notes class="mt-2 whitespace-pre-line break-words text-xs">
                                        {{ $document->notes }}
                                    </p>
                                @endif
                            </div>
                            @if($available)
                                <a data-document-preview class="btn btn-secondary"
                                   href="{{ route('sidang-ta-registration-documents.preview', $document) }}"
                                   data-preview-url="{{ route('sidang-ta-registration-documents.preview', $document) }}"
                                   data-download-url="{{ route('sidang-ta-registration-documents.download', $document) }}"
                                   data-file-name="{{ $document->file_name }}"
                                   data-file-type="{{ $document->file_type }}"
                                   x-on:click.prevent="$dispatch('sidang-ta-preview', $el.dataset)" target="_blank"
                                   rel="noopener" aria-haspopup="dialog">Lihat berkas</a>
                            @else
                                <p role="alert" class="text-xs font-semibold">
                                    Berkas tidak ditemukan. Dokumen belum dapat diverifikasi.
                                </p>
                            @endif
                            @include('sidang_ta_registrations.admin.partials.document-review-form')
                        </article>
                    @else
                        <article data-ta-missing-document="{{ $type }}"
                                 class="space-y-2 rounded-xl border p-4 {{ $tone }}">
                            <h4 class="text-sm font-bold">{{ $label }}</h4>
                            <p role="alert" class="text-xs">
                                Metadata dokumen belum tersedia. Persetujuan pengajuan ditahan.
                            </p>
                        </article>
                    @endif
                @endforeach
            </div>
        </section>
    @endforeach
</section>
