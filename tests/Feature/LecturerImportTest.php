<?php

namespace Tests\Feature;

use App\Models\Lecturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class LecturerImportTest extends TestCase
{
    use RefreshDatabase;

    private function upload(): UploadedFile
    {
        return new UploadedFile(realpath(__DIR__ . '/_dosen_sample.xlsx'), 'dosen.xlsx', null, null, true);
    }

    public function test_import_xlsx_menyimpan_semua_kolom_termasuk_hp_coe_kk(): void
    {
        $this->actingAs(User::factory()->create());

        $this->withoutMiddleware(\Spatie\Permission\Middleware\PermissionMiddleware::class)->post(route('lecturers.import'), ['file' => $this->upload()])
            ->assertSessionHas('success');

        $l = Lecturer::where('nidn', '0728016901')->first();
        $this->assertNotNull($l);
        $this->assertSame('ORO', $l->lecturer_code);
        $this->assertSame('25620001', $l->nip_ypt);
        $this->assertSame('08123222469', $l->phone);
        $this->assertSame('Circular Ecosystem and Sustainable Technology', $l->coe);
        $this->assertStringStartsWith('ETHES', $l->kk);
        $this->assertSame('Dosen S1 Teknik Telekomunikasi', $l->academic_interest);
        $this->assertSame('Ir.', $l->front_title);
    }

    public function test_import_ulang_dengan_kode_berubah_mengupdate_bukan_duplikat(): void
    {
        $this->actingAs(User::factory()->create());
        Lecturer::create(['name' => 'Lama', 'lecturer_code' => 'OLD', 'nidn' => '0728016901']);

        $this->withoutMiddleware(\Spatie\Permission\Middleware\PermissionMiddleware::class)->post(route('lecturers.import'), ['file' => $this->upload()]);

        $this->assertSame(1, Lecturer::count());
        $this->assertSame('ORO', Lecturer::first()->lecturer_code);
        $this->assertSame('08123222469', Lecturer::first()->phone);
    }

    public function test_profil_dan_export_menampilkan_kolom_baru(): void
    {
        $this->actingAs(User::factory()->create());
        $l = Lecturer::create(['name' => 'A', 'lecturer_code' => 'AAA', 'phone' => '0811', 'coe' => 'COE-X', 'kk' => 'KK-Y']);

        $this->withoutMiddleware(\Spatie\Permission\Middleware\PermissionMiddleware::class)->get(route('lecturers.show', $l))
            ->assertOk()->assertSee('0811')->assertSee('COE-X')->assertSee('KK-Y');
        $this->withoutMiddleware(\Spatie\Permission\Middleware\PermissionMiddleware::class)->get(route('lecturers.export'))->assertOk();
    }
}


