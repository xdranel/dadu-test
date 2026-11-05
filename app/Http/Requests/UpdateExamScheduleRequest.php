<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExamScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_id'              => ['required', 'exists:courses,id'],
            'lecturer_code'          => ['required', 'string', 'max:20'],
            'lecturer_code_2'        => ['nullable', 'string', 'max:20'],
            'exam_date'              => ['required_unless:assessment_type,TIDAK ADA ASSESSMEN BERSAMA', 'nullable', 'date'],
            'start_time'             => ['required_unless:assessment_type,TIDAK ADA ASSESSMEN BERSAMA', 'nullable', 'date_format:H:i'],
            'end_time'               => ['required_unless:assessment_type,TIDAK ADA ASSESSMEN BERSAMA', 'nullable', 'date_format:H:i', 'after:start_time'],
            'room_1'                 => ['required_unless:assessment_type,TIDAK ADA ASSESSMEN BERSAMA', 'nullable', 'string', 'max:50'],
            'room_2'                 => ['nullable', 'string', 'max:50'],
            'proctor_name_1'         => ['nullable', 'string', 'max:255'],
            'proctor_name_2'         => ['nullable', 'string', 'max:255'],
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
        ];
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
