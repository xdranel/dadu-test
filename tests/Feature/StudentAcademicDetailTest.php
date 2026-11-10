<?php

use App\Models\{AcademicPeriod, Student, StudentAcademicProfile, StudentActivity, StudentBppPayment, StudentCourse, StudentEprt, StudentGrade, StudentSkpi, StudentTak, User};
use App\Services\StudentAcademicProfileImportService;
use Spatie\Permission\Models\Permission;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-10 12:00:00','Asia/Jakarta'));
    $this->user=User::factory()->create(['status'=>'active','auth_source'=>'manual','must_change_password'=>false]);
    Permission::firstOrCreate(['name'=>'student_update','guard_name'=>'web']);
    $this->user->givePermissionTo(Permission::firstOrCreate(['name'=>'student_read','guard_name'=>'web']));
    $this->student=Student::create(['student_number'=>'1202288001','name'=>'Mahasiswa Detail Sintetis',
        'email'=>'detail88@mahasiswa.test','academic_year'=>2022,'study_program'=>'Informatika','status'=>'Aktif']);
    $this->actingAs($this->user);
    $this->detailPage=fn()=> $this->get(route('students.show',$this->student))->assertOk();
    $this->officialDetail=function (array $overrides=[]) {
        $row=array_replace(['nim'=>$this->student->student_number,'nama'=>$this->student->name,
            'nilai_bahasa'=>'480','jenis_tes_bahasa'=>'Online EPrT','jumlah_tes_bahasa'=>2,
            'riwayat_nilai_bahasa'=>[
                ['jenis_tes'=>'Online EPrT','nilai'=>'480','tanggal_tes'=>'2026-10-20','kedaluwarsa'=>'2028-10-20','status_berlaku'=>'Berlaku'],
                ['jenis_tes'=>'Online EPrT','nilai'=>'510','tanggal_tes'=>'2023-10-20','kedaluwarsa'=>'2025-10-20','status_berlaku'=>'Tidak berlaku']],
            'total_tak'=>'80','status_skpi'=>'Disetujui','skpi_status_1'=>'Y','skpi_status_2'=>'N','skpi_status_3'=>null,
            'persetujuan_maju_sidang'=>'APPROVED','periode_keuangan'=>'2627/1','status_keuangan'=>'LUNAS'], $overrides);
        $result=app(StudentAcademicProfileImportService::class)->import([$row]);
        expect($result['errors'])->toBe([]);
    };
});

afterEach(function () { $this->travelBack(); });

test('detail88 official JSON supplies language dates validity TAK BPP SKPI and sidang approval', function () {
    ($this->officialDetail)();
    ($this->officialDetail)(['periode_keuangan'=>'2526/2','status_keuangan'=>'BELUM LUNAS']);
    $page=($this->detailPage)()->assertSee('Data Yudisium & Tugas Akhir');
    $c=new Crawler($page->getContent());
    $language=$c->filter('[data-student-language]');
    expect($language->text())->toContain('480')->toContain('20/10/2026')->toContain('20/10/2028')->toContain('Berlaku')->toContain('Tidak berlaku');
    expect($language->filter('[data-language-score]')->text())->toContain('480')->not->toContain('510');
    expect($c->filter('[data-student-tak-summary]')->text())->toContain('80');
    $bpp=$c->filter('[data-student-bpp-history]');
    expect($bpp->text())->toContain('2627/1')->toContain('2526/2')->toContain('LUNAS')->toContain('BELUM LUNAS');
    $skpi=$c->filter('[data-student-skpi]');
    expect($skpi->text())->toContain('Disetujui')->toContain('Status 1')->toContain('Status 2')->toContain('Status 3')->toContain('Approval')->toContain('approved')->toContain('Belum tersedia');
    expect($skpi->filter('pre')->count())->toBe(0);
    expect($skpi->filter('[data-skpi-status="1"]')->text())->toContain('Y');
    expect($skpi->filter('[data-skpi-status="2"]')->text())->toContain('N');
    expect($skpi->filter('[data-skpi-status="1"] .badge-success')->count())->toBe(1);
    expect($skpi->filter('[data-skpi-status="2"] .badge-danger')->count())->toBe(1);
    expect($c->filter('[data-student-sidang-approval]')->text())->toContain('APPROVED');
});

test('detail88 active courses active course grade history all courses and all grades stay separate', function () {
    $active=AcademicPeriod::create(['year'=>'2026/2027','semester'=>1,'label'=>'Ganjil 2026/2027','start_date'=>'2026-08-01','end_date'=>'2027-01-31','is_active'=>true]);
    $old=AcademicPeriod::create(['year'=>'2025/2026','semester'=>2,'label'=>'Genap 2025/2026','start_date'=>'2026-02-01','end_date'=>'2026-07-31','is_active'=>false]);
    foreach ([[$active,'TA88','TUGAS AKHIR 2',7],[$old,'OLD88','MATA KULIAH LAMA',6]] as [$period,$code,$name,$semester]) {
        StudentCourse::create(['student_number'=>$this->student->student_number,'academic_period_id'=>$period->id,
            'course_code'=>$code,'course_name'=>$name,'course_class'=>'A','semester'=>$semester,'academic_year'=>$period->year]);
    }
    foreach ([['TA88','B','2025/2026'],['TA88','A','2026/2027'],['OLD88','AB','2025/2026']] as [$code,$grade,$year]) {
        StudentGrade::create(['student_number'=>$this->student->student_number,'course_code'=>$code,'course_name'=>$code.' nama',
            'grade'=>$grade,'academic_year'=>$year,'level'=>4]);
    }
    $c=new Crawler(($this->detailPage)()->getContent());
    expect($c->filter('[data-study-records] details')->count())->toBe(4);
    foreach (['active-courses','active-grades','all-courses','all-grades'] as $section) {
        expect($c->filter('details[data-study-section="'.$section.'"] summary svg')->count())->toBe(1);
    }
    $activeCourses=$c->filter('[data-study-section="active-courses"]');
    expect($activeCourses->getNode(0)->hasAttribute('open'))->toBeTrue();
    expect($activeCourses->text())->toContain('TA88')->toContain('Ganjil 2026/2027')->not->toContain('OLD88');
    $activeGrades=$c->filter('[data-study-section="active-grades"]');
    expect($activeGrades->filter('tbody tr')->count())->toBe(2);
    expect($activeGrades->text())->toContain('2025/2026')->toContain('2026/2027')->not->toContain('OLD88');
    expect($c->filter('[data-study-section="all-courses"]')->text())->toContain('TA88')->toContain('OLD88')->toContain('Genap 2025/2026');
    expect($c->filter('[data-study-section="all-grades"] tbody tr')->count())->toBe(3);
});

test('detail88 official missing groups do not borrow legacy scores or approval', function () {
    StudentEprt::create(['student_number'=>$this->student->student_number,'total_score'=>599,'attempt_number'=>1,'test_date'=>'2026-10-01']);
    StudentTak::create(['student_number'=>$this->student->student_number,'total_points'=>88]);
    StudentBppPayment::create(['student_number'=>$this->student->student_number,'status'=>'LUNAS']);
    StudentSkpi::create(['student_number'=>$this->student->student_number,'raw_data'=>['approval_status'=>'approved','skpi_status_1'=>'Y']]);
    StudentAcademicProfile::create(['student_number'=>$this->student->student_number,'groups'=>[]]);
    $c=new Crawler(($this->detailPage)()->getContent());
    expect($c->filter('[data-student-language]')->text())->toContain('Belum tersedia')->not->toContain('599');
    expect($c->filter('[data-student-tak-summary]')->text())->toContain('Belum tersedia')->not->toContain('88');
    expect($c->filter('[data-student-skpi]')->text())->not->toContain('approved');
    expect($c->filter('[data-student-bpp-history]')->text())->not->toContain('LUNAS');
});

test('detail88 zero official TAK remains zero despite old higher scores and local activities', function () {
    StudentTak::create(['student_number'=>$this->student->student_number,'total_points'=>999,'test_date'=>'2020-01-01','expiry_date'=>'2021-01-01']);
    StudentActivity::create(['student_number'=>$this->student->student_number,'activity_name'=>'Kegiatan lokal sintetis','points'=>100]);
    ($this->officialDetail)(['total_tak'=>'0']);
    $c=new Crawler(($this->detailPage)()->getContent());
    expect(trim($c->filter('[data-student-tak-summary] [data-tak-score]')->text()))->toBe('0');
    expect($c->filter('[data-student-tak-history]')->text())->toContain('999')->toContain('Tidak berlaku');
    expect($c->filter('[data-student-local-activities]')->text())->toContain('Kegiatan lokal sintetis')->toContain('100');
});

test('detail88 legacy student language TAK and unknown-period BPP remain readable', function () {
    StudentEprt::create(['student_number'=>$this->student->student_number,'total_score'=>460,'attempt_number'=>1,'test_date'=>'2026-10-01']);
    StudentTak::create(['student_number'=>$this->student->student_number,'total_points'=>70,'test_date'=>'2026-01-01','expiry_date'=>'2027-01-01']);
    StudentBppPayment::create(['student_number'=>$this->student->student_number,'status'=>'LUNAS']);
    $c=new Crawler(($this->detailPage)()->getContent());
    expect($c->filter('[data-student-language]')->text())->toContain('460')->toContain('01/10/2026');
    expect($c->filter('[data-student-tak-summary]')->text())->toContain('70');
    expect($c->filter('[data-student-bpp-history]')->text())->toContain('LUNAS')->toContain('Periode belum tersedia');
});

test('detail88 empty grades keep four sections and do not invent attendance', function () {
    $c=new Crawler(($this->detailPage)()->getContent());
    expect($c->filter('[data-study-records] details')->count())->toBe(4);
    expect($c->filter('[data-study-section="active-courses"]')->text())->toContain('Periode akademik aktif belum ditentukan');
    expect($c->filter('[data-study-section="active-grades"]')->text())->toContain('Belum ada');
    expect($c->filter('[data-study-section="all-courses"]')->text())->toContain('Belum ada');
    expect($c->filter('[data-study-section="all-grades"]')->text())->toContain('Belum ada');
    expect($c->filter('[data-student-attendance]')->text())->toContain('Belum ada data kehadiran')->not->toContain('0%');
});

test('detail88 student detail still enforces permission', function () {
    $user=User::factory()->create(['status'=>'active','auth_source'=>'manual','must_change_password'=>false]);
    $this->actingAs($user)->get(route('students.show',$this->student))->assertForbidden();
});


test('detail88 null official TAK history and SKPI approval remain unknown', function () {
    ($this->officialDetail)(['total_tak'=>null,'status_skpi'=>null]);
    $c=new Crawler(($this->detailPage)()->getContent());
    expect($c->filter('[data-student-tak-history] tbody tr td')->first()->text())->toBe('Belum tersedia');
    expect($c->filter('[data-student-skpi]')->text())->not->toContain('rejected');
});
