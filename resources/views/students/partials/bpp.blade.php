<div data-student-bpp-history class="campus-card p-6">
    <h3 class="mb-4 border-b border-slate-100 pb-2 text-base font-semibold text-slate-800">Status Pembayaran BPP</h3>
    <div class="overflow-x-auto">
        <table class="campus-table">
            <thead><tr><th>Periode pembayaran</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($detail['bpp_history'] as $payment)
                    <tr><td>{{ $payment['period'] }}</td><td>@include('students.partials.status-badge', ['status' => $payment['status']])</td></tr>
                @empty
                    <tr><td colspan="2" class="text-slate-500">Belum ada data pembayaran BPP.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
