<?php

use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->sync88User = User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    foreach (['sync_execute', 'sync_read'] as $permission) {
        $this->sync88User->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->sync88User);
});

test('sync88_academic_upload_retains_every_note_with_student_identity_and_physical_source_position', function () {
    $rows = [];
    foreach (range(1, 12) as $n) $rows[] = ['nim' => '120228'.str_pad((string) $n, 4, '0', STR_PAD_LEFT), 'nama' => 'Nama '.$n, 'total_tak' => 0];
    $source = json_encode(['selected_menus' => ['tak'], 'data' => $rows], JSON_PRETTY_PRINT);
    $this->post(route('sync.import-academic-profile'), ['json_file' => UploadedFile::fake()->createWithContent('sync88.json', $source)])
        ->assertSessionHasNoErrors()->assertRedirect(route('sync.index'));
    $log = SyncLog::latest('id')->firstOrFail();
    expect($log->issue_details)->toHaveCount(12);
    $last = $log->issue_details[11];
    expect($last)->toMatchArray(['nim' => $rows[11]['nim'], 'name' => 'Nama 12', 'record' => 12, 'path' => '$.data[11]']);
    $nimOffset = strpos($source, '"nim": "'.$rows[11]['nim'].'"');
    $rowOffset = strrpos(substr($source, 0, $nimOffset), '{');
    expect($last['line'])->toBe(substr_count(substr($source, 0, $rowOffset), "\n") + 1);
    expect($log->error_message)->toContain('Baris #11');
    $this->getJson(route('sync.logs.details', $log))->assertOk()->assertJsonCount(12, 'issues');
});

test('sync88_generic_skipped_rows_get_actionable_identity_and_source_notes', function () {
    $source = "{\n \"aaData\": [\n  {\"nim\":\"1202289999\",\"nama\":\"Belum Ada\",\"kode_matakuliah\":\"X\"},\n  {\"nama\":\"Tanpa NIM\"}\n ]\n}";
    $this->post(route('sync.import-json'), ['type' => 'attendance', 'json_file' => UploadedFile::fake()->createWithContent('sync88.json', $source)])
        ->assertSessionHasNoErrors()->assertRedirect(route('sync.index'));
    $log = SyncLog::latest('id')->firstOrFail();
    expect($log->issue_details)->toHaveCount(2);
    expect($log->issue_details[0])->toMatchArray(['nim' => '1202289999', 'name' => 'Belum Ada', 'record' => 1, 'path' => '$.aaData[0]', 'line' => 3]);
    expect($log->issue_details[0]['message'])->toContain('belum terdaftar');
    expect($log->issue_details[1]['message'])->toContain('NIM');
});

test('sync88_legacy_details_are_lazy_and_permission_guarded_while_clean_logs_say_selesai', function () {
    $hostile = '<script>alert("sync88-private")</script>';
    $log = SyncLog::create(['module' => 'academic_profile', 'status' => 'completed', 'error_message' => $hostile]);
    SyncLog::create(['module' => 'student', 'status' => 'completed']);
    $page = $this->get(route('sync.index'))->assertOk()->assertSee('Lihat catatan')->assertSee('Selesai');
    $page->assertDontSee($hostile, false)->assertDontSee('sync88-private', false);
    $this->getJson(route('sync.logs.details', $log))->assertOk()->assertJsonPath('issues.0.message', $hostile);
    $this->sync88User->revokePermissionTo('sync_read');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->getJson(route('sync.logs.details', $log))->assertForbidden();
});

test('sync88_missing_optional_fields_in_partial_import_are_not_fabricated_as_errors', function () {
    $student = \App\Models\Student::create(['student_number' => '1202280001', 'name' => 'Partial', 'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    $this->post(route('sync.import-academic-profile'), ['json_file' => UploadedFile::fake()->createWithContent('partial.json', json_encode(['data' => [['nim' => $student->student_number, 'nama' => 'Partial', 'total_tak' => 0]]]))])
        ->assertSessionHasNoErrors()->assertRedirect(route('sync.index'));
    $log = SyncLog::latest('id')->firstOrFail();
    expect($log->error_message)->toBeNull();
    expect($log->issue_details)->toBe([]);
});


test('sync88_historical_skips_without_details_offer_an_honest_fallback_note', function () {
    $log = SyncLog::create(['module' => 'student', 'status' => 'completed', 'skipped_records' => 3]);
    $this->get(route('sync.index'))->assertOk()->assertSee('Lihat catatan');
    $this->getJson(route('sync.logs.details', $log))->assertOk()
        ->assertJsonPath('issues.0.message', '3 data dilewati; rincian belum tersedia untuk log ini.')
        ->assertJsonPath('issues.0.line', null);
});

test('sync88_successful_partial_import_still_reports_a_failed_source_menu_without_raw_tokens', function () {
    $student = \App\Models\Student::create(['student_number' => '1202280001', 'name' => 'Partial', 'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    $row = ['nim' => $student->student_number, 'nama' => 'Partial', 'total_tak' => 0, 'error' => 'bahasa: private-token'];
    $this->post(route('sync.import-academic-profile'), ['json_file' => UploadedFile::fake()->createWithContent('partial.json', json_encode(['data' => [$row]]))])
        ->assertSessionHasNoErrors()->assertRedirect(route('sync.index'));
    $log = SyncLog::latest('id')->firstOrFail();
    expect($log->issue_details)->toHaveCount(1);
    expect($log->issue_details[0])->toMatchArray(['nim' => $student->student_number, 'record' => 1, 'message' => 'Pengambilan data menu bahasa gagal.']);
    expect($log->error_message)->not->toContain('private-token');
});
