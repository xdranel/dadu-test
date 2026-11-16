<?php

use App\Models\Lecturer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Rap2hpoutre\FastExcel\FastExcel;
use Spatie\Permission\Models\Permission;

test('lecturer111_real_headers_update_identity_without_empty_overwrite_and_old_headers_work', function () {
    $actor = User::factory()->create(['status' => 'active']);
    $actor->givePermissionTo(Permission::findOrCreate('lecturer_create', 'web'));
    $l = Lecturer::create(['nidn' => '11199999', 'name' => 'Dosen Awal', 'lecturer_code' => 'OLD', 'email_domain' => 'existing@example.test', 'study_program' => 'Informatika']);
    $path = tempnam(sys_get_temp_dir(), 'lecturer111').'.xlsx';
    (new FastExcel(collect([['NIDN' => '11199999', 'NAMA' => 'Dosen Diperbarui', 'Kode Dosen Baru' => 'NEW', 'Email Domain Telkom University' => '', 'STATUS' => 'Aktif'], ['NIDN' => '11299999', 'NAMA' => 'Dosen Lama', 'KODE DOSEN' => 'DML']])))->export($path);
    // All rows share the same worksheet headers; use a separate old-format workbook below.
    $this->actingAs($actor)->post(route('lecturers.import'), ['file' => new UploadedFile($path, 'dosen.xlsx', null, null, true)])->assertSessionHasNoErrors();
    expect($l->fresh()->lecturer_code)->toBe('NEW')->and($l->fresh()->email_domain)->toBe('existing@example.test')->and($l->fresh()->active_status)->toBe('Aktif')->and($l->fresh()->study_program)->toBe('Informatika');
    $path2 = tempnam(sys_get_temp_dir(), 'lecturer111').'.xlsx';
    (new FastExcel(collect([['NIDN' => '11399999', 'NAMA' => 'Dosen Format Lama', 'KODE DOSEN' => 'DML', 'Email Domain' => 'dml@example.test']])))->export($path2);
    $this->post(route('lecturers.import'), ['file' => new UploadedFile($path2, 'dosen-lama.xlsx', null, null, true)])->assertSessionHasNoErrors();
    expect(Lecturer::where('nidn', '11399999')->sole()->lecturer_code)->toBe('DML')->and(Lecturer::where('nidn', '11199999')->count())->toBe(1);
    unlink($path);
    unlink($path2);
});
