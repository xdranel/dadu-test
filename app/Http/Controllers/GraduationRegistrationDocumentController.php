<?php

namespace App\Http\Controllers;

use App\Models\GraduationRegistration;
use App\Models\GraduationRegistrationDocument;
use App\Models\Student;
use App\Models\User;
use App\Notifications\DocumentRevisionNotification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GraduationRegistrationDocumentController extends Controller
{
    /**
     * Download a graduation registration document.
     */
    public function download(GraduationRegistrationDocument $document)
    {
        $user = auth()->user();
        $registration = $document->registration;

        // Students can only download their own documents
        if ($user->hasRole('mahasiswa') && $registration->student_number !== $this->getStudentNumber()) {
            abort(403, 'Anda tidak memiliki akses ke dokumen ini.');
        }

        if (!Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'File tidak ditemukan.');
        }

        return Storage::disk('public')->download(
            $document->file_path,
            $document->file_name
        );
    }

    /**
     * Preview a graduation registration document in-browser (inline response).
     */
    public function preview(GraduationRegistrationDocument $document)
    {
        $user = auth()->user();
        $registration = $document->registration;

        // Students can only view their own documents
        if ($user->hasRole('mahasiswa') && $registration->student_number !== $this->getStudentNumber()) {
            abort(403, 'Anda tidak memiliki akses ke dokumen ini.');
        }

        if (!Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'File tidak ditemukan.');
        }

        $mimeType = Storage::disk('public')->mimeType($document->file_path);

        return Storage::disk('public')->response(
            $document->file_path,
            $document->file_name,
            ['Content-Type' => $mimeType]
        );
    }

    /**
     * Delete a graduation registration document (only for editable registrations).
     */
    public function destroy(GraduationRegistrationDocument $document)
    {
        $user = auth()->user();
        $registration = $document->registration;

        // Students can only delete their own documents
        if ($user->hasRole('mahasiswa') && $registration->student_number !== $this->getStudentNumber()) {
            abort(403, 'Anda tidak memiliki akses ke dokumen ini.');
        }

        if (!$registration->isEditable()) {
            return back()->with('error', 'Dokumen tidak dapat dihapus.');
        }

        if (Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return back()->with('success', 'Dokumen harus dihapus.');
    }

    private function getStudentNumber(): string
    {
        $user = auth()->user();
        if (!$user) return '';

        if (!empty($user->employee_id)) {
            return (string) $user->employee_id;
        }

        if (!empty($user->username)) {
            $student = Student::where('student_number', $user->username)->first();
            if ($student) {
                return (string) $student->student_number;
            }
        }

        if (!empty($user->email)) {
            $student = Student::where('email', $user->email)->first();
            if ($student) {
                return (string) $student->student_number;
            }
        }

        return (string) ($user->username ?? '');
    }

    /**
     * Review a graduation registration document (setuju/tolak).
     */
    public function review(Request $request, GraduationRegistrationDocument $document)
    {
        // Authorization ditangani oleh route middleware: permission:graduation_registration_update

        // Validasi bahwa document terkait dengan graduation registration yang valid
        if (!$document->registration) {
            abort(404, 'Dokumen tidak ditemukan.');
        }

        // Validasi input
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:verified,rejected'],
            'notes'  => ['nullable', 'string', 'max:500'],
        ], [
            'status.required'   => 'Status review wajib diisi.',
            'status.in'         => 'Status review tidak valid.',
        ]);

        if ($validator->fails()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal.',
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        // Update the file status and make a submitted application editable when a revision is requested.
        $registration = $document->registration;
        $previousStatus = $document->getOriginal('status');
        $previousNotes = $document->getOriginal('notes');
        $newStatus = $request->input('status');

        DB::transaction(function () use ($document, $registration, $newStatus, $request) {
            $document->update([
                'status' => $newStatus,
                'notes'  => $request->input('notes'),
            ]);

            if ($newStatus === 'rejected' && $registration->isReviewable()) {
                $oldRegistrationStatus = $registration->status;
                if ($oldRegistrationStatus === GraduationRegistration::STATUS_SUBMITTED) {
                    $registration->update(['status' => GraduationRegistration::STATUS_NEEDS_REVIEW]);
                }

                $registration->logAction(
                    'document_revision_requested',
                    $oldRegistrationStatus,
                    $registration->fresh()->status,
                    $document->getDocumentTypeLabelAttribute()
                        . ($document->notes ? ': ' . $document->notes : ''),
                    auth()->id()
                );
            }
        });

        // Send revision notification only when document is rejected
        if ($newStatus === 'rejected') {
            $this->sendRevisionNotification($document, $previousStatus, $previousNotes);
        }

        // Return response
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Review dokumen berhasil disimpan.',
                'data' => [
                    'status' => $document->status,
                    'status_label' => $document->getStatusLabelAttribute(),
                    'status_badge' => $document->getStatusBadgeAttribute(),
                    'notes' => $document->notes,
                    'registration_status' => $registration->fresh()->status,
                ]
            ]);
        }

        return back()->with('success', 'Review dokumen harus disimpan.');
    }

    /**
     * Send database notification to the student when their document needs revision.
     *
     * Notification is only sent when:
     * - status is rejected
     * - previous status was not rejected, OR
     * - notes have changed while remaining rejected
     *
     * If the student user cannot be found, the review still succeeds
     * and a warning is logged � no exception is thrown.
     */
    private function sendRevisionNotification(
        GraduationRegistrationDocument $document,
        ?string $previousStatus,
        ?string $previousNotes
    ): void {
        // Skip duplicate notification if status and revision note did not change.
        if ($previousStatus === 'rejected' && $previousNotes === $document->notes) {
            return;
        }

        $registration = $document->registration;
        if (!$registration) {
            Log::warning('DocumentRevisionNotification: registration not found', [
                'document_id' => $document->id,
            ]);
            return;
        }

        $studentNumber = $registration->student_number;
        $student = Student::where('student_number', $studentNumber)->first();

        if (!$student) {
            Log::warning('DocumentRevisionNotification: student not found', [
                'registration_id' => $registration->id,
                'student_number' => $studentNumber,
                'document_id' => $document->id,
            ]);
            return;
        }

        // Resolve User from Student using the existing project pattern:
        // employee_id (NIM) ? username ? email
        $user = User::where('employee_id', $student->student_number)->first();

        if (!$user) {
            $user = User::where('username', $student->student_number)->first();
        }

        if (!$user && $student->email) {
            $user = User::where('email', $student->email)->first();
        }

        if (!$user) {
            Log::warning('DocumentRevisionNotification: user not found for student', [
                'student_number' => $student->student_number,
                'student_email' => $student->email,
                'registration_id' => $registration->id,
                'document_id' => $document->id,
            ]);
            return;
        }

        $user->notify(new DocumentRevisionNotification($document));
    }
}
