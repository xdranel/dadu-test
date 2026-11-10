<div class="campus-card space-y-5 p-6">
    <div data-student-tak-summary class="rounded-xl border border-rose-200 bg-rose-50 p-4">
        <h3 class="text-sm font-semibold text-slate-800">Total TAK</h3>
        <p data-tak-score class="mt-1 text-2xl font-bold text-[#DA2D3B]">{{ $detail['tak_score'] === null ? 'Belum tersedia' : (float) $detail['tak_score'] }}</p>
    </div>
    <section data-student-tak-history>
        <h3 class="mb-3 text-base font-semibold text-slate-800">Riwayat TAK Tersimpan</h3>
        <div class="overflow-x-auto">
            <table class="campus-table">
                <thead><tr><th>Total TAK</th><th>Tanggal</th><th>Berlaku sampai</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($detail['tak_history'] as $row)
                        <tr><td>{{ $row['score'] === null ? 'Belum tersedia' : (float) $row['score'] }}</td><td>{{ $row['date'] ?? 'Belum tersedia' }}</td><td>{{ $row['expires_at'] ?? 'Belum tersedia' }}</td>
                            <td><span class="badge {{ $row['validity'] === 'Berlaku' ? 'badge-success' : ($row['validity'] === 'Tidak berlaku' ? 'badge-danger' : 'badge-warning') }}">{{ $row['validity'] }}</span></td></tr>
                    @empty
                        <tr><td colspan="4" class="text-slate-500">Riwayat TAK belum tersedia.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section data-student-local-activities>
        <h3 class="mb-3 text-base font-semibold text-slate-800">Kegiatan Lokal</h3>
        <div class="overflow-x-auto">
            <table class="campus-table">
                <thead><tr><th>Kegiatan</th><th>Jenis</th><th>Peran</th><th>Poin</th><th>Tanggal</th></tr></thead>
                <tbody>
                    @forelse($student->activities as $activity)
                        <tr><td>{{ $activity->activity_name }}</td><td>{{ $activity->activity_type ?? '-' }}</td><td>{{ $activity->role ?? '-' }}</td><td>{{ $activity->points }}</td><td>{{ $activity->date?->format('d/m/Y') ?? '-' }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="text-slate-500">Belum ada kegiatan lokal.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
