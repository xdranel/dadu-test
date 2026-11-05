<!-- Modal Pop-up Info Ketentuan Field / Dokumen -->
<div x-data="{
        isOpen: false,
        activeData: null,
        infoMap: {
            revision_form: {
                title: 'Form Revisi (bertanda tangan)',
                image: null,
                bullets: [
                    'Form revisi tugas akhir yang telah disetujui dan ditandatangan basah oleh Pembimbing 1, Pembimbing 2, Penguji 1, dan Penguji 2. Format file: PDF.'
                ]
            },
            final_thesis_book: {
                title: 'Buku TA Final (bertanda tangan)',
                image: null,
                bullets: [
                    'Template dapat diakses pada link: <a href=\'https://linktr.ee/laa.upps.sby\' target=\'_blank\' rel=\'noopener noreferrer\' class=\'text-[#DA2D3B] font-semibold underline hover:text-[#b92532]\'>https://linktr.ee/laa.upps.sby</a>',
                    'Pastikan template buku TA dan logo pada cover telah sesuai dengan template yang disediakan UPPS',
                    'Pastikan tahun pada cover sesuai dengan tanggal pada halaman pengesahan',
                    'Pastikan cover berisi judul TA dalam Bahasa Indonesia dan Bahasa Inggris',
                    'Pastikan Lembar Pernyataan Orisinalitas telah ditandatangan basah oleh mahasiswa'
                ]
            },
            similarity_check_thesis: {
                title: 'Cek Similaritas TA (≤25%)',
                image: null,
                bullets: [
                    'Dokumen diupload secara utuh dalam format PDF',
                    'Pengecekan similarity hanya diizinkan menggunakan aplikasi i-Thenticate Universitas Telkom Kampus Surabaya, dan dapat dilakukan pada masing-masing Program Studi selama jam kerja'
                ]
            },
            similarity_check_publication: {
                title: 'Cek Similaritas Publikasi (≤25%)',
                image: null,
                bullets: [
                    'Dokumen diupload secara utuh dalam format PDF',
                    'Pengecekan similarity hanya diizinkan menggunakan aplikasi i-Thenticate Universitas Telkom Kampus Surabaya, dan dapat dilakukan pada masing-masing Program Studi selama jam kerja'
                ]
            },
            library_clearance_letter: {
                title: 'Surat Bebas Pustaka (SBKP dari openlib Tel-U)',
                image: null,
                bullets: [
                    'Untuk pengajuan silahkan menghubungi Unit Perpustakaan/Open-lib Universitas Telkom Kampus Surabaya maksimal H-3 Penutupan Pendaftaran Yudisium'
                ]
            },
            financial_clearance_letter: {
                title: 'Surat Bebas Keuangan',
                image: null,
                bullets: [
                    'Template dokumen dapat diunduh pada <a href=\'https://linktr.ee/laa.upps.sby\' target=\'_blank\' rel=\'noopener noreferrer\' class=\'text-[#DA2D3B] font-semibold underline hover:text-[#b92532]\'>linktr.ee/laa.upps.sby</a>',
                    'Dokumen diserahkan ke SSC untuk diberikan ke Unit Keuangan TUS maksimal H-3 sebelum penutupan pendaftaran yudisium'
                ]
            },
            lab_loan_clearance_letter: {
                title: 'Surat Bebas Pinjam Lab',
                image: null,
                bullets: [
                    'Untuk pengajuan silahkan menghubungi Unit Laboratorium Universitas Telkom Kampus Surabaya maksimal H-3 sebelum penutupan pendaftaran yudisium'
                ]
            },
            ibasila_screenshot: {
                title: 'Screenshot iBasila',
                image: '{{ asset("assets/images/forms/yudisium-iBasila.svg") }}',
                bullets: [
                    'Pastikan telah mengunggah foto ijazah yang disetujui oleh Dosen Wali',
                    'Pastikan Status yang tertera adalah &quot;Approved&quot;',
                    'Dosen Wali hanya dapat melakukan approval jika foto telah sesuai dengan format yang ditentukan pada iBasila',
                    'Website iBasila dapat diakses pada <a href=\'https://basila.telkomuniversity.ac.id/\' target=\'_blank\' rel=\'noopener noreferrer\' class=\'text-[#DA2D3B] font-semibold underline hover:text-[#b92532]\'>https://basila.telkomuniversity.ac.id/</a>',
                    'Pastikan hasil capture menampilkan nama mahasiswa'
                ]
            },
            diploma_biodata_form: {
                title: 'Form Biodata Ijazah (biodata dummy ijazah)',
                image: null,
                bullets: [
                    'Form biodata dummy ijazah yang sudah divalidasi bersama dan bermaterai fisik (bukan digital) serta ditandatangan basah.'
                ]
            },
            softfile_thesis_upload_proof: {
                title: 'Bukti Unggah Softfile Buku TA (openlib)',
                image: '{{ asset("assets/images/forms/yudisium-softfile-buku-TA.svg") }}',
                bullets: [
                    'Pastikan status telah disetujui oleh Dosen Pembimbing 1',
                    'Pastikan status yang tertera adalah &quot;Document TA/Thesis Archived&quot;',
                    'Pastikan hasil capture menampilkan nama mahasiswa'
                ]
            },
            level_3_passed_capture: {
                title: 'Capture Hasil Lulus Sidang Tingkat 3 (iGracias)',
                image: '{{ asset("assets/images/forms/yudisium-lulus-tingkat-3.svg") }}',
                bullets: [
                    'Pastikan hasil capture menampilkan nama mahasiswa',
                    'Perhatikan contoh terlampir (tabel Data Nilai: NIM, Nama, Kelas, Program Studi, IPK, SKS Total, Tingkat I-IV, TAK)'
                ]
            },
            thesis_title_id: {
                title: 'Judul Tugas Akhir (Bahasa Indonesia)',
                image: null,
                bullets: [
                    'Pastikan terisi dengan huruf kapital. Contoh: &quot;PENGEMBANGAN SISTEM MONITORING DAN KONTROL KELEMBAPAN LAHAN GAMBUT UNTUK ANTISIPASI KEBAKARAN HUTAN BERBASIS IOT DENGAN MENGGUNAKAN METODE FUZZY LOGIC&quot;'
                ]
            },
            thesis_title_en: {
                title: 'Judul Tugas Akhir (Bahasa Inggris)',
                image: null,
                bullets: [
                    'Pastikan terisi dengan huruf kapital. Contoh: &quot;DEVELOPMENT OF PEATLAND MOISTURE MONITORING AND CONTROL SYSTEM FOR IOT-BASED FOREST FIRE ANTICIPATION USING FUZZY LOGIC METHOD&quot;'
                ]
            },
            tak_score: {
                title: 'Score TAK Terbaru',
                image: null,
                bullets: [
                    'Score minimum untuk TAK Mahasiswa - Reguler: 60, Alih Jenjang: 25, Diploma: 45'
                ]
            },
            exam_date: {
                title: 'Tanggal Sidang Tugas Akhir',
                image: null,
                bullets: [
                    'Isikan sesuai dengan jadwal di iGracias'
                ]
            },
            revision_completion_date: {
                title: 'Tanggal Selesai Revisi Buku TA',
                image: null,
                bullets: [
                    'Isikan sesuai tanggal buku TA dan form revisi yang telah disahkan oleh seluruh dosen yang bersangkutan'
                ]
            }
        }
     }"
     x-show="isOpen"
     @open-info-modal.window="activeData = infoMap[$event.detail.key] || null; if (activeData) isOpen = true;"
     @keydown.escape.window="isOpen = false"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 sm:p-6 overflow-y-auto"
     style="display: none;"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 scale-95"
     x-transition:enter-end="opacity-100 scale-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100 scale-100"
     x-transition:leave-end="opacity-0 scale-95">

    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg md:max-w-xl max-h-[88vh] flex flex-col border border-slate-200 overflow-hidden"
         @click.away="isOpen = false">
        
        <!-- Header Modal -->
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center gap-2.5">
                <h3 class="text-sm sm:text-base font-bold text-slate-800 leading-snug" x-text="activeData?.title"></h3>
            </div>
            <button type="button" 
                    @click="isOpen = false" 
                    class="text-slate-400 hover:text-slate-700 p-1.5 rounded-lg hover:bg-slate-200/60 transition-colors flex-shrink-0" 
                    title="Tutup modal"
                    aria-label="Tutup modal">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Body Modal -->
        <div class="px-5 pb-5 pt-0 sm:px-6 sm:pb-6 sm:pt-1 overflow-y-auto space-y-4 flex-1">
            <!-- Gambar Contoh -->
            <template x-if="activeData?.image">
                <div class="bg-slate-50 p-3 sm:p-4 rounded-xl border border-slate-200 text-center">
                    <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Gambar Contoh / Reference Capture:</p>
                    <img :src="activeData?.image" :alt="activeData?.title" class="max-w-full max-h-[350px] mx-auto object-contain rounded-lg border border-slate-200 shadow-sm">
                </div>
            </template>

            <!-- Bullet List Ketentuan -->
            <div>
                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Ketentuan & Panduan Pengisian:
                </h4>
                <ul class="list-disc list-outside pl-5 space-y-2 text-xs sm:text-sm text-slate-700 leading-relaxed">
                    <template x-for="(bullet, index) in (activeData?.bullets || [])" :key="index">
                        <li x-html="bullet"></li>
                    </template>
                </ul>
            </div>
        </div>
    </div>
</div>
