<x-app-layout>
    <x-slot name="header">Edit Data Yudisium &amp; Tugas Akhir</x-slot>
    @php
        $groups = [
            'Status Sidang Tingkat' => collect(range(1,4))->mapWithKeys(fn($i) => ['sidang_tingkat_'.$i => ['Tingkat '.$i, 'level']])->all(),
            'Data Tugas Akhir' => [
                'thesis_title_id'=>['Judul TA (Indonesia)','textarea'], 'thesis_title_en'=>['Judul TA (Inggris)','textarea'],
                'advisor_1'=>['Pembimbing 1','text'], 'advisor_2'=>['Pembimbing 2','text'],
                'examiner_1'=>['Penguji 1','text'], 'examiner_2'=>['Penguji 2','text'],
                'exam_date'=>['Tanggal sidang','date'], 'exam_time'=>['Waktu sidang','text'], 'exam_location'=>['Tempat sidang','text'],
            ],
            'Nilai TA, TAK & BASILA' => ['ta_grade'=>['Indeks nilai TA','text'], 'tak_score'=>['Total TAK','number'], 'basila_status'=>['Approval foto BASILA','basila']],
            'Status Mata Kuliah' => ['credits_passed'=>['SKS lulus','number'], 'credits_remaining'=>['SKS belum lulus','number'],
                'retaking_courses'=>['MK mengulang','courses'], 'failed_courses'=>['MK belum lulus','courses']],
            'Pembayaran Wisuda' => ['graduation_payment_status'=>['Status bayar wisuda','payment']],
            'SKPI' => ['skpi_status'=>['Status SKPI','skpi'], 'skpi_status_1'=>['Status 1','level'], 'skpi_status_2'=>['Status 2','level'],
                'skpi_status_3'=>['Status 3','level'], 'skpi_approval'=>['Approval status','approval']],
            'Persetujuan Sidang iGracias' => ['sidang_approval_status'=>['Status persetujuan','sidang']],
        ];
        $rows = old('bpp_payments', $values['bpp_payments']);
        $rows = is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
        $rows = array_map(fn($row) => ['period'=>is_scalar($row['period'] ?? null) ? (string)$row['period'] : '',
            'status'=>is_scalar($row['status'] ?? null) ? (string)$row['status'] : ''], $rows);
        $currentIndex = filter_var(old('bpp_current_index', $values['bpp_current_index']), FILTER_VALIDATE_INT);
        $currentIndex = $currentIndex === false ? -1 : $currentIndex;
        $version = old('edit_version', $editVersion);
        $version = is_string($version) ? $version : '';
    @endphp
    <div class="mx-auto max-w-5xl space-y-5">
        <div>
            <h2 class="text-xl font-bold text-slate-800">{{ $student->name }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $student->student_number }} · {{ $student->study_program }}</p>
            <p class="mt-2 text-sm text-slate-600">Koreksi ini digunakan untuk data akademik dan pemeriksaan persyaratan. Impor berikutnya dapat memperbarui nilainya kembali.</p>
        </div>
        @if($errors->any())
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <p class="font-semibold">Periksa koreksi berikut:</p>
                <ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                @error('edit_version')<a class="mt-2 inline-block font-semibold underline" href="{{ route('students.academic-data.edit',$student) }}">Muat ulang data terkini</a>@enderror
            </div>
        @endif
        <form method="POST" action="{{ route('students.academic-data.update',$student) }}" class="space-y-5">
            @csrf @method('PUT')
            <input type="hidden" name="edit_version" value="{{ $version }}">
            @foreach($groups as $title => $fields)
                <section class="campus-card p-5">
                    <h3 class="mb-4 border-b border-slate-100 pb-3 font-semibold text-slate-800">{{ $title }}</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach($fields as $name => [$label,$type])
                            @php
                                $value = old($name,$values[$name]);
                                $value = is_scalar($value) ? (string)$value : '';
                                $options = match($type) {
                                    'level'=>['Y'=>'Y — Ya / Lulus / Memenuhi','N'=>'N — Belum memenuhi'],
                                    'basila'=>['approved'=>'Disetujui','pending'=>'Menunggu','rejected'=>'Ditolak'],
                                    'payment'=>['LUNAS'=>'LUNAS','BELUM LUNAS'=>'BELUM LUNAS','PERLU VERIFIKASI'=>'PERLU VERIFIKASI'],
                                    'skpi'=>['Disetujui'=>'Disetujui','Belum Disetujui'=>'Belum Disetujui','BELUM MENGAJUKAN'=>'BELUM MENGAJUKAN'],
                                    'approval'=>['approved'=>'approved','pending'=>'pending','rejected'=>'rejected'],
                                    'sidang'=>['APPROVED'=>'APPROVED','BELUM DISETUJUI'=>'BELUM DISETUJUI','BELUM TERVERIFIKASI'=>'BELUM TERVERIFIKASI','Tidak Ditemukan'=>'Tidak Ditemukan'],
                                    default=>null,
                                };
                                if($options !== null && $value !== '' && !array_key_exists($value,$options)) $options[$value] = $value;
                            @endphp
                            <div class="{{ in_array($type,['courses','textarea']) ? 'sm:col-span-2' : '' }}">
                                <label class="campus-label" for="edit-{{ $name }}">{{ $label }}</label>
                                @if($options !== null)
                                    <select class="campus-input mt-1" id="edit-{{ $name }}" name="{{ $name }}" @error($name) aria-invalid="true" aria-describedby="error-{{ $name }}" @enderror>
                                        <option value="">Belum tersedia</option>
                                        @foreach($options as $key=>$text)<option value="{{ $key }}" @selected($value === (string)$key)>{{ $text }}</option>@endforeach
                                    </select>
                                @elseif(in_array($type,['textarea','courses']))
                                    <textarea class="campus-input mt-1" id="edit-{{ $name }}" name="{{ $name }}" rows="3" maxlength="{{ $type === 'courses' ? 20000 : 5000 }}" @error($name) aria-invalid="true" aria-describedby="error-{{ $name }}" @enderror>{{ $value }}</textarea>
                                    @if($type === 'courses')<p class="mt-1 text-xs text-slate-500">Satu mata kuliah per baris. Tanda - berarti tidak ada; kosong berarti belum tersedia.</p>@endif
                                @else
                                    <input class="campus-input mt-1" id="edit-{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}"
                                           @if($type === 'number') min="0" step="{{ $name === 'tak_score' ? '0.01' : '1' }}" @endif
                                           @error($name) aria-invalid="true" aria-describedby="error-{{ $name }}" @enderror>
                                @endif
                                @error($name)<p id="error-{{ $name }}" class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
            <section class="campus-card space-y-4 p-5" x-data="{ rows: @js($rows), currentIndex: @js($currentIndex) }">
                <h3 class="border-b border-slate-100 pb-3 font-semibold text-slate-800">Riwayat Pembayaran BPP</h3>
                <p class="text-xs text-slate-500">Format periode: 2627/1 (Ganjil) atau 2627/2 (Genap). Pilih satu baris untuk status BPP saat ini. Biarkan periode kosong hanya jika periode data lama belum diketahui.</p>
                <input type="hidden" name="bpp_current_index" :value="currentIndex">
                <label class="flex items-center gap-2 text-sm"><input type="radio" name="bpp_selection" value="-1" x-model.number="currentIndex">Status BPP saat ini belum ditentukan</label>
                <template x-for="(row,index) in rows" :key="index">
                    <div class="grid gap-3 rounded-xl border border-slate-200 p-4 sm:grid-cols-3">
                        <label class="campus-label">Periode<input class="campus-input mt-1" type="text" x-model="row.period" :name="'bpp_payments['+index+'][period]'" placeholder="2627/1" maxlength="6"></label>
                        <label class="campus-label">Status<input class="campus-input mt-1" type="text" x-model="row.status" :name="'bpp_payments['+index+'][status]'" placeholder="LUNAS / BELUM LUNAS / KREDIT" maxlength="50"></label>
                        <label class="flex items-center gap-2 text-sm"><input type="radio" name="bpp_selection" :value="index" x-model.number="currentIndex">Gunakan sebagai status saat ini</label>
                    </div>
                </template>
                <button type="button" class="btn btn-secondary" @click="if(rows.length < 40) rows.push({period:'',status:''})" :disabled="rows.length >= 40">Tambah periode BPP</button>
                <template x-if="rows.length === 0"><input type="hidden" name="bpp_payments" value=""></template>
                @error('bpp_payments')<p class="text-xs text-rose-700">{{ $message }}</p>@enderror
                @error('bpp_current_index')<p class="text-xs text-rose-700">{{ $message }}</p>@enderror
            </section>
            <section class="campus-card p-5">
                <label class="campus-label" for="edit-note">Catatan koreksi (opsional)</label>
                <textarea class="campus-input mt-1" id="edit-note" name="edit_note" rows="2" maxlength="1000">{{ is_string(old('edit_note')) ? old('edit_note') : '' }}</textarea>
                @error('edit_note')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </section>
            <div class="flex flex-wrap gap-3">
                <button class="btn btn-primary" type="submit">Simpan koreksi</button>
                <a class="btn btn-secondary" href="{{ route('students.show',['student'=>$student,'tab'=>'yudisium']) }}">Kembali ke detail mahasiswa</a>
            </div>
        </form>
    </div>
</x-app-layout>
