<?php

use App\Models\{SidangTaPeriod, SidangTaRegistration, Student, StudentAcademicProfile, StudentAcademicDataEdit, StudentBasilaApproval, StudentCourseIssue, StudentEprt, StudentFinalProject, StudentFinalProjectGrade, StudentGraduationPayment, StudentSkpi, StudentTak, User};
use App\Services\{SidangTaAdmissionFacts, StudentAcademicProfileImportService};
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-10 12:00:00','Asia/Jakarta'));
    $this->actor93=User::factory()->create(['status'=>'active','auth_source'=>'manual','must_change_password'=>false]);
    foreach (['student_read','student_update'] as $name) $this->actor93->givePermissionTo(Permission::firstOrCreate(['name'=>$name,'guard_name'=>'web']));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->student93=Student::create(['student_number'=>'1294000001','name'=>'Mahasiswa Koreksi Sintetis','email'=>'edit93@mahasiswa.test',
        'academic_year'=>2022,'study_program'=>'Informatika','status'=>'Aktif','active_semester'=>9,'gpa'=>3.4,'total_credits'=>140]);
    $this->actingAs($this->actor93);
    $this->edit93=function() { return $this->get(route('students.academic-data.edit',$this->student93))->assertOk(); };
    $this->payload93=function(array $patch=[]) {
        $page=($this->edit93)();
        return array_replace($page->viewData('values'),['edit_version'=>$page->viewData('editVersion')],$patch);
    };
    $this->save93=fn(array $data)=> $this->putJson(route('students.academic-data.update',$this->student93),$data);
    $this->official93=function(array $patch=[]) {
        $row=array_replace(['nim'=>$this->student93->student_number,'nama'=>$this->student93->name,
            'ipk'=>'3.40','sks_lulus'=>'140','sidang_tingkat_2'=>'Y','total_tak'=>'65',
            'nilai_bahasa'=>'460','jenis_tes_bahasa'=>'Online EPrT','jumlah_tes_bahasa'=>1,
            'riwayat_nilai_bahasa'=>[['jenis_tes'=>'Online EPrT','nilai'=>'460','tanggal_tes'=>'2026-10-01','kedaluwarsa'=>'2028-10-01','status_berlaku'=>'Berlaku']],
            'status_skpi'=>'Belum Disetujui','skpi_status_1'=>'Y','skpi_status_2'=>'N','skpi_status_3'=>'N',
            'persetujuan_maju_sidang'=>'BELUM DISETUJUI','periode_keuangan'=>'2627/1','status_keuangan'=>'LUNAS'], $patch);
        $result=app(StudentAcademicProfileImportService::class)->import([$row]);expect($result['errors'])->toBe([]);
    };
});

afterEach(function () { $this->travelBack(); });

test('manual93 full correction persists live tables official facts and actor audit', function () {
    ($this->official93)();
    $language=StudentAcademicProfile::where('student_number',$this->student93->student_number)->first()->groups['language'];
    $payload=($this->payload93)(['sidang_tingkat_1'=>'Y','sidang_tingkat_2'=>'Y','sidang_tingkat_3'=>'Y','sidang_tingkat_4'=>'N',
        'thesis_title_id'=>'Judul Koreksi','thesis_title_en'=>'Corrected Title','advisor_1'=>'Pembimbing Satu','advisor_2'=>'Pembimbing Dua',
        'examiner_1'=>'Penguji Satu','examiner_2'=>'Penguji Dua','exam_date'=>'2026-11-09','exam_time'=>'13:00','exam_location'=>'Ruang Uji',
        'ta_grade'=>'A','tak_score'=>'88.50','basila_status'=>'approved','credits_passed'=>144,'credits_remaining'=>0,
        'retaking_courses'=>"MK-A (D, Semester 3)\nMK-B",'failed_courses'=>'-',
        'bpp_payments'=>[['period'=>'2526/2','status'=>'BELUM LUNAS'],['period'=>'2627/1','status'=>'KREDIT']], 'bpp_current_index'=>1,
        'graduation_payment_status'=>'LUNAS','skpi_status'=>'Disetujui','skpi_status_1'=>'Y','skpi_status_2'=>'Y','skpi_status_3'=>'Y',
        'skpi_approval'=>'approved','sidang_approval_status'=>'APPROVED','edit_note'=>'Koreksi administrasi sintetis']);
    ($this->save93)($payload)->assertRedirect(route('students.show',['student'=>$this->student93,'tab'=>'yudisium']));
    $profile=StudentAcademicProfile::where('student_number',$this->student93->student_number)->firstOrFail();
    expect($profile->groups['language'])->toBe($language);
    expect($profile->groups['academic']['passed_credits'])->toBe(144);
    expect($profile->groups['bpp']['status'])->toBe('KREDIT')->and($profile->groups['bpp']['period'])->toBe('2627/1');
    expect($profile->bpp_periods)->toHaveKeys(['2526/2','2627/1']);
    $facts=app(SidangTaAdmissionFacts::class)->forStudent($this->student93->fresh());
    expect($facts['facts']['tak_score'])->toBe(88.5)->and($facts['facts']['skpi_approved'])->toBeTrue()->and($facts['facts']['passed_credits'])->toBe(144.0);
    expect(StudentFinalProject::sole()->advisor_1)->toBe('Pembimbing Satu');
    expect(StudentFinalProject::sole()->thesis_title_id)->toBe('Judul Koreksi');
    expect(StudentFinalProjectGrade::sole()->grade)->toBe('A');
    expect(StudentBasilaApproval::sole()->photo_approval)->toBeTrue();
    expect(StudentCourseIssue::sole()->retaking_courses)->toBe(['MK-A (D, Semester 3)','MK-B']);
    expect(StudentCourseIssue::sole()->failed_courses)->toBe([]);
    expect(StudentGraduationPayment::sole()->status)->toBe('LUNAS');
    expect(StudentSkpi::sole()->raw_data['approval_status'])->toBe('approved');
    $audit=StudentAcademicDataEdit::sole();
    expect($audit->user_id)->toBe($this->actor93->id)->and($audit->student_number)->toBe($this->student93->student_number);
    expect($audit->before_data)->not->toBe($audit->after_data);
    $this->get(route('students.show',['student'=>$this->student93,'tab'=>'yudisium']))->assertOk()->assertSee('Judul Koreksi')->assertSee('Pembimbing Satu')->assertSee('88.5')->assertSee('KREDIT');
});

test('manual93 next official import may replace corrected values without losing audit', function () {
    ($this->official93)();
    ($this->save93)(($this->payload93)(['tak_score'=>90]))->assertRedirect();
    ($this->official93)(['total_tak'=>'70']);
    expect(app(SidangTaAdmissionFacts::class)->forStudent($this->student93->fresh())['facts']['tak_score'])->toBe(70.0);
    expect(StudentAcademicDataEdit::count())->toBe(1);
});

test('manual93 legacy profile initialization preserves language GPA and final application snapshot', function () {
    StudentEprt::create(['student_number'=>$this->student93->student_number,'total_score'=>460,'attempt_number'=>1,'test_date'=>'2026-10-01']);
    StudentTak::create(['student_number'=>$this->student93->student_number,'total_points'=>70]);
    $period=SidangTaPeriod::create(['name'=>'Audit93','open_at'=>now()->subDay(),'close_at'=>now()->addDay(),'is_active'=>true]);
    $registration=SidangTaRegistration::create(['student_number'=>$this->student93->student_number,'student_name'=>$this->student93->name,
        'sidang_ta_period_id'=>$period->id,'student_phone'=>'081234567890','parent_phone'=>'081234567891','status'=>'approved','requirements_version'=>2,
        'graduation_payment_date'=>'2026-11-01','diploma_biodata_accurate'=>true,'academic_snapshot'=>['marker'=>'final93']]);
    $before=$registration->fresh()->getAttributes();
    ($this->save93)(($this->payload93)(['ta_grade'=>'A']))->assertRedirect();
    $facts=app(SidangTaAdmissionFacts::class)->forStudent($this->student93->fresh());
    expect($facts['eprt_facts']['score'])->toBe(460.0)->and($facts['facts']['gpa'])->toBe(3.4);
    expect($registration->fresh()->getAttributes())->toBe($before);
});

test('manual93 absent official language stays absent and cannot borrow legacy scores', function () {
    StudentEprt::create(['student_number'=>$this->student93->student_number,'total_score'=>599]);
    StudentAcademicProfile::create(['student_number'=>$this->student93->student_number,'groups'=>['academic'=>['gpa'=>3.4,'passed_credits'=>140],'tak'=>['score'=>65]]]);
    ($this->save93)(($this->payload93)(['ta_grade'=>'A']))->assertRedirect();
    $profile=StudentAcademicProfile::where('student_number',$this->student93->student_number)->firstOrFail();
    expect($profile->groups)->not->toHaveKey('language');
    expect(app(SidangTaAdmissionFacts::class)->forStudent($this->student93->fresh())['eprt_facts']['score'])->toBeNull();
});

test('manual93 stale forms cannot overwrite an intervening import', function () {
    ($this->official93)();$payload=($this->payload93)(['tak_score'=>90]);
    ($this->official93)(['total_tak'=>'75']);
    ($this->save93)($payload)->assertUnprocessable()->assertJsonValidationErrors('edit_version');
    expect(app(SidangTaAdmissionFacts::class)->forStudent($this->student93->fresh())['facts']['tak_score'])->toBe(75.0);
    expect(StudentAcademicDataEdit::count())->toBe(0);
});

test('manual93 invalid fields and unauthorized readers cannot change academic records', function () {
    $payload=($this->payload93)(['credits_passed'=>-1,'ta_grade'=>str_repeat('A',6),'exam_date'=>'invalid']);
    ($this->save93)($payload)->assertUnprocessable()->assertJsonValidationErrors(['credits_passed','ta_grade','exam_date']);
    expect(StudentAcademicDataEdit::count())->toBe(0);
    $this->actor93->revokePermissionTo('student_update');app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->get(route('students.academic-data.edit',$this->student93))->assertForbidden();
    ($this->save93)($payload)->assertForbidden();
    $this->get(route('students.show',$this->student93))->assertOk()->assertDontSee('Edit Data Yudisium & TA');
});

test('manual93 BPP duplicate periods and conflicting SKPI approval are rejected', function () {
    ($this->official93)();
    $payload=($this->payload93)(['bpp_payments'=>[['period'=>'2627/1','status'=>'LUNAS'],['period'=>'2627/1','status'=>'BELUM LUNAS']],
        'bpp_current_index'=>1,'skpi_status'=>'Disetujui','skpi_approval'=>'rejected']);
    ($this->save93)($payload)->assertUnprocessable()->assertJsonValidationErrors(['bpp_payments','skpi_approval']);
    expect(StudentAcademicDataEdit::count())->toBe(0);
});

test('manual93 audit failure rolls back all academic writes', function () {
    ($this->official93)();$before=StudentAcademicProfile::sole()->getAttributes();
    $payload=($this->payload93)(['tak_score'=>90,'advisor_1'=>'Tidak Boleh Tersimpan']);
    StudentAcademicDataEdit::creating(fn()=>throw new RuntimeException('audit93 failure'));
    try { ($this->save93)($payload)->assertServerError(); }
    finally { StudentAcademicDataEdit::flushEventListeners(); }
    expect(StudentAcademicProfile::sole()->getAttributes())->toBe($before);
    expect(StudentFinalProject::count())->toBe(0);
});

test('manual93 zero and unknown values remain distinct and unrelated identity fields are ignored', function () {
    ($this->official93)();$before=$this->student93->fresh();
    $payload=($this->payload93)(['tak_score'=>0,'credits_remaining'=>0,'retaking_courses'=>null,'failed_courses'=>'-',
        'skpi_status'=>null,'skpi_approval'=>null,'name'=>'forged name','student_number'=>'0000','gpa'=>4]);
    ($this->save93)($payload)->assertRedirect();
    expect(StudentCourseIssue::sole()->retaking_courses)->toBeNull()->and(StudentCourseIssue::sole()->failed_courses)->toBe([]);
    expect($this->student93->fresh()->name)->toBe($before->name)->and($this->student93->fresh()->gpa)->toBe($before->gpa);
    expect(app(SidangTaAdmissionFacts::class)->forStudent($this->student93->fresh())['facts']['tak_score'])->toBe(0.0);
});


test('manual93 editing visible values preserves undisplayed profile metadata', function () {
    ($this->official93)();
    $profile=StudentAcademicProfile::sole();$groups=$profile->groups;$groups['tak']['source_marker']='keep-tak';$groups['skpi']['source_marker']='keep-skpi';
    $periods=$profile->bpp_periods;$periods['2627/1']['source_marker']='keep-bpp';$profile->update(['groups'=>$groups,'bpp_periods'=>$periods]);
    ($this->save93)(($this->payload93)(['tak_score'=>90]))->assertRedirect();
    $profile->refresh();
    expect($profile->groups['tak']['source_marker'])->toBe('keep-tak');
    expect($profile->groups['skpi']['source_marker'])->toBe('keep-skpi');
    expect($profile->bpp_periods['2627/1']['source_marker'])->toBe('keep-bpp');
});

test('manual93 legacy activity total stays available after editing unrelated TA data', function () {
    \App\Models\StudentActivity::create(['student_number'=>$this->student93->student_number,'activity_name'=>'Kegiatan Uji','points'=>75]);
    $payload=($this->payload93)(['ta_grade'=>'A']);
    expect((float)$payload['tak_score'])->toBe(75.0);
    ($this->save93)($payload)->assertRedirect();
    expect(app(SidangTaAdmissionFacts::class)->forStudent($this->student93->fresh())['facts']['tak_score'])->toBe(75.0);
});



test('manual93 recognized legacy SKPI approval stays approved after unrelated correction', function ($raw) {
    StudentSkpi::create(['student_number'=>$this->student93->student_number,'raw_data'=>$raw]);
    expect(app(SidangTaAdmissionFacts::class)->forStudent($this->student93->fresh())['facts']['skpi_approved'])->toBeTrue();
    $payload=($this->payload93)(['ta_grade'=>'A']);
    expect($payload['skpi_status'])->toBe('Disetujui')->and($payload['skpi_approval'])->toBe('approved');
    ($this->save93)($payload)->assertRedirect();
    expect(app(SidangTaAdmissionFacts::class)->forStudent($this->student93->fresh())['facts']['skpi_approved'])->toBeTrue();
})->with([[['status'=>'approved']],[['approval_status'=>true]],[['is_approved'=>1]]]);

test('manual93 clearing existing level and BASILA statuses preserves unknown instead of negative', function () {
    ($this->official93)();
    ($this->save93)(($this->payload93)(['sidang_tingkat_1'=>'Y','sidang_tingkat_2'=>'Y','sidang_tingkat_3'=>'Y','sidang_tingkat_4'=>'Y','basila_status'=>'approved']))->assertRedirect();
    ($this->save93)(($this->payload93)(['sidang_tingkat_1'=>null,'sidang_tingkat_2'=>null,'sidang_tingkat_3'=>null,'sidang_tingkat_4'=>null,'basila_status'=>null]))->assertRedirect();
    $levels=\App\Models\StudentSidangTingkat::sole();
    foreach (range(1,4) as $i) expect($levels->{'tingkat_'.$i})->toBeNull();
    expect(StudentBasilaApproval::sole()->status)->toBeNull()->and(StudentBasilaApproval::sole()->photo_approval)->toBeNull();
    $page=($this->edit93)();
    expect($page->viewData('values')['sidang_tingkat_1'])->toBeNull()->and($page->viewData('values')['basila_status'])->toBeNull();
    $this->get(route('students.show',$this->student93))->assertOk()->assertSee('Belum tersedia');
});


test('manual93 legacy JSON can refresh corrected groups without clearing absent groups', function () {
    ($this->official93)();
    ($this->save93)(($this->payload93)(['tak_score'=>90,'advisor_1'=>'Pembimbing Dipertahankan']))->assertRedirect();
    $before=StudentAcademicProfile::sole()->groups['language'];
    $result=app(StudentAcademicProfileImportService::class)->import([[
        'nim'=>$this->student93->student_number,'nama'=>$this->student93->name,
        'tak'=>['total_points'=>72], 'skpi'=>['approval_status'=>'approved'], 'bpp'=>['status'=>'BELUM LUNAS'],
    ]]);
    expect($result['errors'])->toBe([]);
    $facts=app(SidangTaAdmissionFacts::class)->forStudent($this->student93->fresh());
    expect($facts['facts']['tak_score'])->toBe(72.0)->and($facts['facts']['skpi_approved'])->toBeTrue();
    expect(StudentAcademicProfile::sole()->groups['bpp']['status'])->toBe('BELUM LUNAS');
    expect(StudentAcademicProfile::sole()->groups['language'])->toBe($before);
    expect(\App\Models\StudentSidangTingkat::sole()->tingkat_2)->toBeTrue();
    expect(StudentCourseIssue::sole()->credits_passed)->toBe(140);
    expect(StudentFinalProject::sole()->advisor_1)->toBe('Pembimbing Dipertahankan');
});
