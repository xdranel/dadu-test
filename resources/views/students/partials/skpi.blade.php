<div data-student-skpi class="campus-card p-6">
    <h3 class="mb-4 border-b border-slate-100 pb-2 text-base font-semibold text-slate-800">SKPI</h3>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-xl border border-slate-200 p-4 text-center">
            <p class="mb-2 text-xs text-slate-500">Status SKPI</p>
            @include('students.partials.status-badge', ['status' => $detail['skpi']['status']])
        </div>
        @foreach($detail['skpi']['parts'] as $part => $value)
            <div data-skpi-status="{{ $part }}" class="rounded-xl border border-slate-200 p-4 text-center">
                <p class="mb-2 text-xs text-slate-500">Status {{ $part }}</p>
                @include('students.partials.status-badge', ['status' => $value])
            </div>
        @endforeach
        <div class="rounded-xl border border-slate-200 p-4 text-center">
            <p class="mb-2 text-xs text-slate-500">Approval status</p>
            @include('students.partials.status-badge', ['status' => $detail['skpi']['approval']])
        </div>
    </div>
</div>
<div data-student-sidang-approval class="campus-card p-6">
    <h3 class="mb-4 border-b border-slate-100 pb-2 text-base font-semibold text-slate-800">Persetujuan Sidang iGracias</h3>
    @include('students.partials.status-badge', ['status' => $detail['sidang_approval']])
</div>
