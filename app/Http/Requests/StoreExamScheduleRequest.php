<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExamScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        if (!$this->has('mode')) {
            $this->merge(['mode' => 'single']);
        }
        if ($this->has('exam_date') && !$this->has('date')) {
            $this->merge(['date' => $this->exam_date]);
        }
        if ($this->has('course_id') && !$this->has('course_code')) {
            $course = \App\Models\Course::find($this->course_id);
            if ($course) {
                $this->merge(['course_code' => $course->course_code]);
            }
        }
        if ($this->has('course_code') && !$this->has('course_id')) {
            $course = \App\Models\Course::where('course_code', $this->course_code)->first();
            if ($course) {
                $this->merge(['course_id' => $course->id]);
            }
        }
        if (!$this->has('course_class') && $this->has('course_id')) {
            $course = \App\Models\Course::find($this->course_id);
            if ($course) {
                $this->merge(['course_class' => $course->class]);
            }
        }
        if ($this->input('mode') === 'single') {
            if (!$this->has('rooms')) {
                $rooms = [];
                if ($this->has('room_1') && $this->room_1) {
                    $rooms[] = $this->room_1;
                }
                if ($this->has('room_2') && $this->room_2) {
                    $rooms[] = $this->room_2;
                }
                $this->merge(['rooms' => $rooms]);
            }
        }

        if ($this->input('mode') === 'all' && $this->has('kelas')) {
            $kelas = $this->input('kelas');
            if (is_array($kelas)) {
                foreach ($kelas as $idx => $k) {
                    if (!isset($k['course_class']) && isset($k['course_id'])) {
                        $c = \App\Models\Course::find($k['course_id']);
                        if ($c) {
                            $kelas[$idx]['course_class'] = $c->class;
                        }
                    }
                    if (!isset($k['rooms'])) {
                        $rooms = [];
                        if (isset($k['room_1']) && $k['room_1']) {
                            $rooms[] = $k['room_1'];
                        }
                        if (isset($k['room_2']) && $k['room_2']) {
                            $rooms[] = $k['room_2'];
                        }
                        $kelas[$idx]['rooms'] = $rooms;
                    }
                }
                $this->merge(['kelas' => $kelas]);
            }
        }
    }

    public function rules(): array
    {
        $rules = [
            'mode'                   => ['required', 'string', 'in:single,all'],
            'course_code'            => ['required', 'exists:courses,course_code'],
            'lecturer_code'          => ['required', 'string', 'max:20'],
            'lecturer_code_2'        => ['nullable', 'string', 'max:20'],
            'assessment_type'        => ['required', 'string', 'in:TIDAK ADA ASSESSMEN BERSAMA,MENGERJAKAN SOAL UJIAN,UJIAN LISAN / PRESENTASI / PROJECT BASED'],
            'execution_method'       => ['required', 'string', 'in:TIDAK ADA ASSESSMEN BERSAMA,DIKERJAKAN ONSITE DI KAMPUS,TAKE HOME ATAU DIKERJAKAN FROM ANYWHERE'],
            'execution_time'         => ['required', 'string', 'in:TIDAK ADA ASSESSMEN BERSAMA,SESUAI JADWAL KELAS PERKULIAHAN,JADWAL SERENTAK se-TUS per MK Prodi,JADWAL SERENTAK se-TUNC'],
            'participant_division'   => ['required', 'string', 'in:TIDAK ADA ASSESSMEN BERSAMA,DIBAGI 2 (JIKA PESERTA KELAS > 30),SEMUA PESERTA DIGABUNG DALAM 1 RUANGAN'],
            'question_type'          => ['required', 'string', 'in:TIDAK ADA ASSESSMEN BERSAMA,PERLU PENGGANDAAN FISIK (HARDCOPY),SOAL TERSEDIA DI LMS'],
            'answer_sheet_type'      => ['required', 'string', 'in:TIDAK ADA ASSESSMEN BERSAMA,MENGGUNAKAN LEMBAR JAWABAN STANDAR AKADEMIK,LEMBAR JAWABAN CUSTOM (PERLU PENGGANDAAN),TIDAK PERLU LEMBAR JAWABAN'],
            'needs_scratch_paper'    => ['required', 'string', 'in:PERLU,TIDAK PERLU'],
            'proctor_type'           => ['required', 'string', 'in:TIDAK ADA ASSESSMEN BERSAMA,DOSEN PENGAMPU,ASPRAK / ASLAB / ASDOS (PRODI SETOR NAMA),TLH / STUDENT STAFF UNIT (DICARIKAN AKADEMIK),TIDAK PERLU PENGAWAS'],
            'exam_question_url'      => ['nullable', 'url', 'max:2048'],
            'custom_answer_sheet_url'=> [
                'required_if:answer_sheet_type,LEMBAR JAWABAN CUSTOM (PERLU PENGGANDAAN)',
                'nullable',
                'url',
                'max:2048'
            ],
            'exam_rules'             => ['nullable', 'string', 'max:1000'],
            'proctor_name_1'         => ['nullable', 'string', 'max:255'],
            'proctor_name_2'         => ['nullable', 'string', 'max:255'],
        ];

        if ($this->input('mode') === 'single') {
            $rules['course_id']    = ['required', 'exists:courses,id'];
            $rules['course_class'] = ['required', 'string', 'max:20'];
            $rules['rooms']        = ['required_unless:assessment_type,TIDAK ADA ASSESSMEN BERSAMA', 'array'];
            $rules['rooms.*']      = ['nullable', 'string', 'max:50'];
            $rules['date']         = ['required_unless:assessment_type,TIDAK ADA ASSESSMEN BERSAMA', 'nullable', 'date'];
            $rules['start_time']   = ['required_unless:assessment_type,TIDAK ADA ASSESSMEN BERSAMA', 'nullable', 'date_format:H:i'];
            $rules['end_time']     = ['required_unless:assessment_type,TIDAK ADA ASSESSMEN BERSAMA', 'nullable', 'date_format:H:i', 'after:start_time'];
        }
 
        if ($this->input('mode') === 'all') {
            $rules['kelas']                  = ['required', 'array', 'min:1'];
            $rules['kelas.*.course_id']      = ['required', 'exists:courses,id'];
            $rules['kelas.*.course_class']   = ['required', 'string', 'max:20'];
            $rules['kelas.*.date']           = ['required_unless:assessment_type,TIDAK ADA ASSESSMEN BERSAMA', 'nullable', 'date'];
            $rules['kelas.*.start_time']     = ['required_unless:assessment_type,TIDAK ADA ASSESSMEN BERSAMA', 'nullable', 'date_format:H:i'];
            $rules['kelas.*.end_time']       = ['required_unless:assessment_type,TIDAK ADA ASSESSMEN BERSAMA', 'nullable', 'date_format:H:i'];
            $rules['kelas.*.rooms']          = ['required_unless:assessment_type,TIDAK ADA ASSESSMEN BERSAMA', 'array'];
            $rules['kelas.*.rooms.*']        = ['nullable', 'string', 'max:50'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'course_id.required'     => 'Mata kuliah wajib dipilih.',
            'course_id.exists'       => 'Mata kuliah tidak ditemukan.',
            'lecturer_code.required' => 'Kode dosen wajib diisi.',
            'exam_date.required'     => 'Tanggal ujian wajib diisi.',
            'start_time.required'    => 'Jam mulai wajib diisi.',
            'end_time.required'      => 'Jam selesai wajib diisi.',
            'end_time.after'         => 'Jam selesai harus setelah jam mulai.',
            'room_1.required'        => 'Ruangan wajib diisi.',
            'custom_answer_sheet_url.required_if' => 'URL Lembar Jawaban Custom wajib diisi jika memilih Lembar Jawaban Custom (Perlu Penggandaan).',
            'custom_answer_sheet_url.url' => 'URL Lembar Jawaban Custom harus berupa URL yang valid.',
        ];
    }
}
