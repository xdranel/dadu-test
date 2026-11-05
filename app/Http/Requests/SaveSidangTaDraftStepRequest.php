<?php

namespace App\Http\Requests;

use App\Models\SidangTaRegistration;
use App\Support\SidangTaMeetRequirements;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Foundation\Http\FormRequest;

class SaveSidangTaDraftStepRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $draft = $this->route('draft');

        $permission = $draft && $draft->source_registration_id !== null
            ? 'sidang_ta_registration_update'
            : 'sidang_ta_registration_create';

        return $user && $user->can($permission)
            && (!$draft || (string) $draft->owner_user_id === (string) $user->id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $step = (int) ($this->route('step') ?? 1);
        $draft = $this->route('draft');
        if ($draft) Requirements::assertKnownVersion($draft);
        $rules = ['version' => [$this->route('draft')
            ? 'required'
            : 'nullable', 'integer', 'min:1']];
        foreach (['requirements_version', 'owner_user_id', 'student_number', 'status', 'payload', 'source_registration_id',
                     'source_version', 'reviewed_version', 'submitted_registration_id', 'file_path',
                     'sidang_ta_period_id', 'student_phone', 'parent_phone', 'graduation_payment_date',
                     'diploma_biodata_accurate'] as $key) {
            $rules[$key] = ['prohibited'];
        }
        $rules = array_replace($rules, Requirements::draftScalarRules($step));
        if ($step === 1) {
            $rules['sidang_ta_period_id'] = ['required', 'integer', 'exists:sidang_ta_periods,id'];
            $rules['documents'] = ['prohibited'];
            return $rules;
        }

        // Tahap 4 boleh menerima scalar yang belum ada. Cabang aktual divalidasi lagi
        // dalam service setelah merge payload dan version check di bawah lock.
        if ($draft && $draft->requirements_version === 2) {
            $types = $step === 4
                ? array_merge(SidangTaMeetRequirements::DIPLOMA, SidangTaMeetRequirements::PDM)
                : Requirements::typesForDraftStep($draft, $step);
        } else {
            $types = $step === 4 ? array_merge(Requirements::DIPLOMA, Requirements::PDM)
                : Requirements::typesForStep($step);
        }
        $rules['documents'] = ['nullable', 'array:'.implode(',', $types)];
        foreach ($types as $type) {
            $rules["documents.{$type}"] = $draft ? Requirements::fileRulesForDraft($draft, $type) : Requirements::fileRules($type);
        }
        return $rules;
    }

    public function messages(): array
    {
        return [
            'sidang_ta_period_id.required' => 'Pilih periode sidang TA.',
            'sidang_ta_period_id.integer' => 'Pilih periode sidang TA yang valid.',
            'sidang_ta_period_id.exists' => 'Periode sidang TA tidak ditemukan.',
            'student_phone.required' => 'Nomor HP mahasiswa wajib diisi.',
            'parent_phone.required' => 'Nomor HP orang tua/wali wajib diisi.',
            'student_phone.string' => 'Nomor HP mahasiswa harus berupa teks.',
            'parent_phone.string' => 'Nomor HP orang tua/wali harus berupa teks.',
            'student_phone.max' => 'Nomor HP mahasiswa maksimal 30 karakter.',
            'parent_phone.max' => 'Nomor HP orang tua/wali maksimal 30 karakter.',
            'graduation_payment_date.required' => 'Tanggal pembayaran wisuda wajib diisi.',
            'graduation_payment_date.date' => 'Tanggal pembayaran wisuda tidak valid.',
            'graduation_payment_date.before_or_equal' => 'Tanggal pembayaran wisuda tidak boleh melewati hari ini.',
            'diploma_biodata_accurate.required' => 'Pilih kesesuaian biodata dummy ijazah.',
            'diploma_biodata_accurate.boolean' => 'Pilihan kesesuaian biodata tidak valid.',
            'version.required' => 'Versi draft tidak tersedia. Buka ulang draft terbaru.',
            'version.integer' => 'Versi draft tidak valid. Buka ulang draft terbaru.',
            'version.min' => 'Versi draft tidak valid. Buka ulang draft terbaru.',
            'documents.array' => 'Jenis dokumen tidak sesuai tahap atau cabang yang dipilih.',
            'documents.*.file' => 'Pilih berkas yang valid.',
            'documents.*.uploaded' => 'Berkas gagal diunggah. Periksa ukuran berkas dan coba lagi.',
            'documents.*.max' => 'Ukuran setiap file maksimal 10 MB.',
            'documents.*.mimes' => 'Format dokumen tidak sesuai persyaratan tahap ini.',
            'prohibited' => 'Isian :attribute tidak diperbolehkan pada tahap ini.',
        ];
    }

    public function attributes(): array
    {
        $attributes = [
            'sidang_ta_period_id' => 'periode sidang TA',
            'student_phone' => 'nomor HP mahasiswa',
            'parent_phone' => 'nomor HP orang tua/wali',
            'graduation_payment_date' => 'tanggal pembayaran wisuda',
            'diploma_biodata_accurate' => 'kesesuaian biodata ijazah',
            'documents' => 'dokumen', 'version' => 'versi draft',
        ];
        foreach (array_merge(SidangTaRegistration::DOCUMENT_TYPES,
            SidangTaMeetRequirements::LABELS) as $type => $label) {
            $attributes["documents.{$type}"] = $label;
        }
        return $attributes;
    }
}

