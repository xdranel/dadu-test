<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDocument;
use App\Models\SidangTaRegistrationDraft;
use App\Models\SidangTaRegistrationDraftDocument;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaReviewEligibility as Eligibility;
use App\Support\SidangTaRevisionContract;
use App\Support\SidangTaRevisionDeadline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Throwable;
use Illuminate\Validation\ValidationException;

class SidangTaRegistrationReview
{
    private function locked(SidangTaRegistration $registration, int $version): SidangTaRegistration
    {
        $locked = SidangTaRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
        abort_unless($locked->lock_version === $version, 409, 'Pengajuan berubah. Muat ulang sebelum meninjau.');
        abort_unless($locked->requirements_version === 2, 409,
            'Persyaratan pengajuan tidak sesuai. Muat ulang atau hubungi petugas.');
        abort_unless($locked->status === 'submitted', 422,
            'Peninjauan terkunci. Pengajuan sedang direvisi atau sudah diputuskan.');
        return $locked;
    }

    public function document(User $user, SidangTaRegistration $registration, SidangTaRegistrationDocument $document,
                             int $version, string $status, ?string $notes): SidangTaRegistration
    {
        abort_unless($user->can('sidang_ta_registration_review'), 403);
        abort_unless(in_array($status, ['verified', 'rejected'], true), 422);
        $notes = trim($notes ?? '');
        $notes = $notes === '' ? null : $notes;
        if ($status === 'rejected' && $notes === null) {
            throw ValidationException::withMessages(['notes' => 'Isi catatan perbaikan dokumen.']);
        }
        return DB::transaction(function () use ($user, $registration, $document, $version, $status, $notes) {
            $locked = $this->locked($registration, $version);
            $doc = $locked->documents()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($doc->document_type, Eligibility::requiredTypes($locked), true), 422,
                'Dokumen ini tidak berlaku untuk cabang biodata pengajuan.');
            if ($status === 'verified' && !Storage::disk('local')->exists($doc->file_path)) {
                throw ValidationException::withMessages(['document' => 'Berkas tidak ditemukan. Minta mahasiswa mengunggah ulang.']);
            }
            $oldRegistrationStatus = $locked->status;
            if ($status === 'verified') $notes = null;
            $nextStatus = $locked->status;
            if ($doc->status === $status && $doc->notes === $notes && $locked->status === $nextStatus) return $locked;
            $labels = ['pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Perlu perbaikan'];
            $oldDocumentStatus = $labels[$doc->status] ?? $doc->status;
            $doc->status = $status;
            $doc->notes = $notes;
            $doc->save();
            $locked->status = $nextStatus;
            $locked->reviewed_by = $user->id;
            $locked->reviewed_at = now();
            $locked->lock_version++;
            $locked->save();
            $logNotes = SidangTaRegistration::DOCUMENT_TYPES[$doc->document_type].': '.$oldDocumentStatus.' → '.$labels[$status];
            if ($notes !== null) $logNotes .= '. Catatan: '.$notes;
            $locked->logAction($status === 'verified' ? 'document_verified' : 'document_rejected',
                $oldRegistrationStatus, $nextStatus, $logNotes, $user->id);
            return $locked;
        }, 3);
    }

    public function approvalCancellationBlocker(SidangTaRegistration $registration): ?string
    {
        $period = $registration->period;
        if (!$period || !$period->revisionIsConfigured()) {
            return 'Lengkapi tanggal mulai dan selesai Verifikasi Berkas sebelum membatalkan persetujuan.';
        }
        if (!$period->is_revision_active) {
            return 'Verifikasi Berkas sedang dinonaktifkan. Persetujuan tidak dapat dibatalkan.';
        }
        if (now()->lessThan($period->revision_open_at)) {
            return 'Jadwal Verifikasi Berkas belum dimulai. Persetujuan belum dapat dibatalkan.';
        }
        if (now()->greaterThan($period->revision_close_at)) {
            return 'Jadwal Verifikasi Berkas telah berakhir. Persetujuan tidak dapat dibatalkan.';
        }
        return null;
    }

    public function cancelApproval(User $user, SidangTaRegistration $registration, int $version): SidangTaRegistration
    {
        abort_unless($user->can('sidang_ta_registration_review'), 403);
        return DB::transaction(function () use ($user, $registration, $version) {
            // Same student-first order as draft finalization. Historical data may lack its Student row.
            Student::where('student_number', $registration->student_number)->lockForUpdate()->first();
            // Serialize reopening historical approvals for this NIM, including when Student is absent.
            $sameStudent = SidangTaRegistration::where('student_number', $registration->student_number)
                ->orderBy('id')->lockForUpdate()->get();
            $locked = $sameStudent->firstWhere('id', $registration->id);
            abort_unless($locked && $locked->lock_version === $version, 409,
                'Pengajuan berubah. Muat ulang sebelum meninjau.');
            abort_unless($locked->requirements_version === 2, 409,
                'Persyaratan pengajuan tidak sesuai. Muat ulang atau hubungi petugas.');
            abort_unless($locked->status === 'approved', 422, 'Hanya pengajuan disetujui yang dapat dibatalkan persetujuannya.');
            $locked->setRelation('period', $locked->period()->lockForUpdate()->firstOrFail());
            if ($message = $this->approvalCancellationBlocker($locked)) {
                throw ValidationException::withMessages(['action' => $message]);
            }
            abort_if($sameStudent->contains(fn ($other) => $other->id !== $locked->id
                && in_array($other->status, ['submitted', 'needs_review'], true)), 409,
                'Persetujuan tidak dapat dibatalkan karena mahasiswa sudah memiliki pengajuan aktif lain.');
            $locked->status = 'submitted';
            $locked->reviewed_by = null;
            $locked->reviewed_at = null;
            $locked->lock_version++;
            $locked->save();
            $locked->logAction('approval_cancelled', 'approved', 'submitted',
                'Persetujuan dibatalkan untuk peninjauan ulang. Status verifikasi berkas tetap dipertahankan.', $user->id);
            return $locked;
        }, 3);
    }

    public function reopeningBlocker(SidangTaRegistration $registration): ?string
    {
        $period = $registration->period;
        if (!$period || !$period->revisionIsConfigured()) return 'Lengkapi tanggal mulai dan selesai Verifikasi Berkas terlebih dahulu.';
        if (!$period->is_revision_active) return 'Aktifkan Verifikasi Berkas terlebih dahulu pada Kelola Periode.';
        if (now()->lessThan($period->revision_open_at)) return 'Verifikasi Berkas belum dimulai. Tunggu jadwal dibuka atau sesuaikan tanggalnya.';
        if (now()->greaterThan($period->revision_close_at)) return 'Verifikasi Berkas telah berakhir. Perpanjang tanggal verifikasi terlebih dahulu pada Kelola Periode.';
        return null;
    }

    public function reopenRejected(User $user, SidangTaRegistration $registration, int $version): SidangTaRegistration
    {
        abort_unless($user->can('sidang_ta_registration_review'), 403);
        return DB::transaction(function () use ($user, $registration, $version) {
            Student::where('student_number', $registration->student_number)->lockForUpdate()->first();
            $drafts = SidangTaRegistrationDraft::where('source_registration_id', $registration->id)
                ->where('status', SidangTaRegistrationDraft::STATUS_ACTIVE)->orderBy('id')->lockForUpdate()->get();
            $sameStudent = SidangTaRegistration::where('student_number', $registration->student_number)
                ->orderBy('id')->lockForUpdate()->get();
            $locked = $sameStudent->firstWhere('id', $registration->id);
            abort_unless($locked && $locked->lock_version === $version, 409, 'Pengajuan berubah. Muat ulang sebelum meninjau.');
            abort_unless($locked->requirements_version === 2, 409, 'Versi persyaratan pengajuan tidak sesuai.');
            abort_unless($locked->status === 'rejected', 422, 'Hanya pengajuan ditolak yang dapat dibuka kembali.');
            abort_if($sameStudent->contains(fn ($other) => $other->id !== $locked->id
                && in_array($other->status, ['submitted', 'needs_review'], true)), 409,
                'Mahasiswa sudah memiliki pengajuan aktif lain.');
            $period = $locked->period()->lockForUpdate()->firstOrFail();
            $locked->setRelation('period', $period);
            if ($message = $this->reopeningBlocker($locked)) {
                throw ValidationException::withMessages(['action' => $message]);
            }
            $locked->status = 'submitted';
            $locked->reviewed_by = null;
            $locked->reviewed_at = null;
            $locked->lock_version++;
            $locked->save();
            $locked->logAction('reopened', 'rejected', 'submitted',
                'Pengajuan dibuka kembali untuk peninjauan ulang setelah jadwal Verifikasi Berkas diperiksa.', $user->id);
            $retired = [];
            foreach ($drafts as $draft) {
                $retired = array_merge($retired, $draft->documents()->whereNull('source_document_id')->pluck('file_path')->all());
                $draft->delete();
            }
            DB::afterCommit(function () use ($retired) {
                foreach (array_unique($retired) as $path) {
                    try {
                        if (SidangTaRegistrationDocument::where('file_path', $path)->exists()
                            || SidangTaRegistrationDraftDocument::where('file_path', $path)->exists()) continue;
                        if (!Storage::disk('local')->delete($path)) Log::warning('Cleanup draft lama saat buka kembali perlu diulang.', ['path' => $path]);
                    } catch (Throwable $exception) {
                        Log::warning('Cleanup draft lama saat buka kembali gagal.', ['path' => $path, 'error' => $exception->getMessage()]);
                    }
                }
            });
            return $locked;
        }, 3);
    }

    public function decide(User $user, SidangTaRegistration $registration, int $version,
                           string $action, ?string $notes): SidangTaRegistration
    {
        abort_unless($user->can('sidang_ta_registration_review'), 403);
        abort_unless(in_array($action, ['approved', 'needs_review', 'rejected'], true), 422);
        $notes = trim($notes ?? '');
        $notes = $notes === '' ? null : $notes;
        if (in_array($action, ['needs_review', 'rejected'], true) && $notes === null) {
            throw ValidationException::withMessages(['review_notes' => 'Isi catatan revisi atau penolakan.']);
        }
        return DB::transaction(function () use ($user, $registration, $version, $action, $notes) {
            $locked = $this->locked($registration, $version);
            if ($action === 'needs_review') {
                SidangTaRevisionDeadline::ensureOpen($locked, 'action');
                SidangTaRevisionContract::assertCanRequest($locked);
            }
            if ($action === 'approved') {
                $locked->setRelation('documents', $locked->documents()->lockForUpdate()->get());
                $blockers = Eligibility::blockers($locked);
                if ($blockers !== []) {
                    throw ValidationException::withMessages(['documents' => 'Persetujuan belum dapat dilakukan: '.implode('; ', $blockers)]);
                }
            }
            $old = $locked->status;
            $locked->status = $action;
            $locked->review_notes = $notes;
            $locked->reviewed_by = $user->id;
            $locked->reviewed_at = now();
            $locked->lock_version++;
            $locked->save();
            $locked->logAction($action, $old, $action, $notes, $user->id);
            return $locked;
        }, 3);
    }
}
