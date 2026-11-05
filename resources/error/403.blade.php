<x-app-layout>
    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8 text-center">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-10">
                <p class="text-6xl font-bold text-red-500 mb-4">403</p>
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Akses Ditolak</h2>
                <p class="text-gray-500 text-sm mb-6">
                    {{ $exception->getMessage() ?? 'Anda tidak memiliki izin untuk mengakses halaman ini.' }}
                </p>
                <a href="{{ url()->previous() }}"
                   class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded-md">
                    ← Kembali
                </a>
            </div>
        </div>
    </div>
</x-app-layout>