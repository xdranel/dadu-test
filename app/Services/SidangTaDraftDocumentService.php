<?php

namespace App\Services;

use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use App\Support\SidangTaRevisionContract as Revision;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SidangTaDraftDocumentService
{
    public function __construct(private SidangTaStudentContext $context,
                                private SidangTaDraftAccess $access
    ) {}

    private function clean(array $paths): void
    {
        foreach (array_unique($paths) as $path) {
            try {
                if (!Storage::disk('local')->delete($path)) {
                    Log::warning('Cleanup file draft belum berhasil.', ['path' => $path]);
                }
            } catch (Throwable $exception) {
                Log::warning('Cleanup file draft gagal.', ['path' => $path, 'error' => $exception->getMessage()]);
            }
        }
    }

    public function save(User $user, SidangTaRegistrationDraft $draft, int $step,
                         int $expectedVersion, array $validated): SidangTaRegistrationDraft
    {
        $this->access->assertCanWrite($user, $draft);
        abort_unless(in_array($step, [2, 3, 4], true), 422);
        abort_unless((string) $draft->owner_user_id === (string) $user->id, 403);
        $student = $this->context->studentFor($user);
        $newPaths = [];
        $retiredPaths = [];
        try {
            $result = DB::transaction(function () use ($user, $draft, $step, $expectedVersion,
                $validated, $student, &$newPaths, &$retiredPaths) {
                Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
                $locked = SidangTaRegistrationDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
                $this->access->check($user, $locked, true);
                abort_unless($locked->version === $expectedVersion, 409, 'Draft berubah. Muat ulang.');
                $first = Requirements::firstIncompleteStep($locked);
                abort_if($first !== null && $step > $first, 409, 'Lengkapi tahap sebelumnya dahulu.');

                $payload = array_replace($locked->payload ?? [],
                    array_intersect_key($validated, Requirements::draftScalarRules($step)));
                Validator::make($payload, Requirements::draftScalarRules($step, $payload))->validate();
                $targeted = Revision::isTargeted($locked);
                if ($targeted) Revision::assertPayloadUnchanged($locked, $payload);
                if ($step === 4 && array_key_exists('diploma_biodata_accurate', $payload)) {
                    $choice = $payload['diploma_biodata_accurate'];
                    $payload['diploma_biodata_accurate'] = $choice === null ? null : (bool) $choice;
                }
                $accurate = $payload['diploma_biodata_accurate'] ?? null;
                $types = Requirements::typesForDraftStep($locked, $step, $payload);
                $allowedTypes = $targeted ? array_values(array_intersect($types, Revision::editableTypes($locked))) : $types;
                $files = $validated['documents'] ?? [];
                $fileRules = ['documents' => $allowedTypes ? ['array:'.implode(',', $allowedTypes)] : ['prohibited']];
                foreach ($allowedTypes as $type) $fileRules["documents.{$type}"] = Requirements::fileRulesForDraft($locked, $type);
                Validator::make(['documents' => $files], $fileRules)->validate();

                foreach ($types as $type) {
                    $file = $files[$type] ?? null;
                    if (!$file instanceof UploadedFile) continue;
                    $old = $locked->documents()->where('document_type', $type)->first();
                    $directory = "sidang_ta/drafts/{$locked->id}";
                    $name = Str::uuid().'.'.$file->guessExtension();
                    $newPaths[] = "{$directory}/{$name}";
                    $path = $file->storeAs($directory, $name, 'local');
                    if (!$path) throw new RuntimeException('Penyimpanan file draft gagal. Silakan coba lagi.');
                    $locked->documents()->updateOrCreate(['document_type' => $type], [
                        'file_name' => $file->getClientOriginalName(), 'file_path' => $path,
                        'file_size' => $file->getSize(), 'file_type' => $file->guessExtension(),
                        'source_document_id' => null,
                    ]);
                    if ($old && $old->source_document_id === null) $retiredPaths[] = $old->file_path;
                }
                // Missing/uncertain EPRT data must not destroy a previously uploaded waiver.
                if (!$targeted && $step === 3
                    && app(\App\Services\SidangTaAdmissionFacts::class)->forStudent($student)['eprt']['mode'] === 'automatic') {
                    foreach ($locked->documents()->where('document_type', \App\Support\SidangTaMeetRequirements::WAIVER)->get() as $old) {
                        if ($old->source_document_id === null) $retiredPaths[] = $old->file_path;
                        $old->delete();
                    }
                }
                if (!$targeted && $step === 4 && $accurate !== null) {
                    $obsolete = $accurate ? Requirements::PDM : Requirements::DIPLOMA;
                    foreach ($locked->documents()->whereIn('document_type', $obsolete)->get() as $old) {
                        if ($old->source_document_id === null) $retiredPaths[] = $old->file_path;
                        $old->delete();
                    }
                }
                $locked->payload = $payload;
                $locked->version++;
                $locked->reviewed_version = null;
                $locked->save();
                return $locked->load('documents');
            });
        } catch (Throwable $exception) {
            $this->clean($newPaths);
            throw $exception;
        }
        $this->clean($retiredPaths);
        return $result;
    }
}
