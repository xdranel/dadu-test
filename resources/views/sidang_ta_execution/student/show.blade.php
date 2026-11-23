<x-app-layout>
    <x-slot name="header">Detail Pelaksanaan Sidang TA</x-slot>
    <div class="space-y-5">
        @include('sidang_ta_execution.partials.info')
        @include('sidang_ta_execution.partials.history')
        <a class="btn btn-secondary" href="{{ route('sidang-ta-execution.index') }}">Kembali</a>
    </div>
</x-app-layout>
