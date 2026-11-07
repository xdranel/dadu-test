<?php

use App\Models\{SidangTaPeriod, SidangTaRegistration, SidangTaRegistrationDraft, Student, StudentAcademicProfile, User};
use App\Services\SidangTaAcademicSnapshot;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create(['username'=>'TASK4-001', 'status'=>'active', 'auth_source'=>'manual', 'must_change_password'=>false]);
    $this->student = Student::create(['student_number'=>'TASK4-001', 'name'=>'Mahasiswa Task Empat', 'email'=>$this->user->email, 'academic_year'=>2022, 'study_program'=>'Informatika', 'status'=>'Aktif']);
    foreach (['sidang_ta_registration_read','sidang_ta_registration_create','sidang_ta_registration_update','sidang_ta_registration_review','sidang_ta_period_read'] as $name) {
        $this->user->givePermissionTo(Permission::firstOrCreate(['name'=>$name,'guard_name'=>'web']));
    }
    $this->period=SidangTaPeriod::create(['name'=>'Periode Empat','is_active'=>true,'open_at'=>now()->subDay(),'close_at'=>now()->addDay()]);
    $this->actingAs($this->user);
    $this->draft = function (bool $accurate = true) {
        $draft=SidangTaRegistrationDraft::create(['owner_user_id'=>$this->user->id,'student_number'=>$this->student->student_number,'sidang_ta_period_id'=>$this->period->id,'status'=>'active','version'=>1,'reviewed_version'=>1,'requirements_version'=>2,'payload'=>['student_phone'=>'081234567890','parent_phone'=>'081234567891','graduation_payment_date'=>today()->toDateString(),'diploma_biodata_accurate'=>$accurate,'pdm_submission_date'=>$accurate?null:today()->toDateString()]]);
        foreach ([2,3,4] as $step) foreach (\App\Support\SidangTaMeetRequirements::typesForStep($step,$accurate,'unknown') as $type) {
            $path='task4/'.$draft->id.'/'.$type.'.pdf'; Storage::disk('local')->put($path,'%PDF test');
            $draft->documents()->create(['document_type'=>$type,'file_path'=>$path,'file_name'=>$type.'.pdf','file_type'=>'pdf','file_size'=>9]);
        }
        return $draft;
    };
});

test('meeting wizard has approved stages editable WA and static supervisors',function(){
    $this->student->finalProject()->create(['thesis_title_id'=>'Judul Empat','advisor_1'=>'Pembimbing Satu','advisor_2'=>'Pembimbing Dua']);
    $response=$this->get(route('sidang-ta-registrations.create'))->assertOk();
    $c=new Crawler($response->getContent());
    foreach (['Data Mahasiswa','Data TA di Igracias','Pembayaran & Nilai Bahasa','Pernyataan Biodata Dummy Ijazah','Pembimbing Satu','Pembimbing Dua'] as $text) $response->assertSee($text);
    foreach (['student_phone','parent_phone'] as $field) {
        $input=$c->filter('input[name="'.$field.'"]');
        expect($input->attr('pattern'))->toBe('[0-9]{8,15}');
        expect($input->attr('maxlength'))->toBe('15');
        expect($input->getNode(0)->hasAttribute('readonly'))->toBeFalse();
    }
    expect(SidangTaRegistrationDraft::count())->toBe(0);
});

test('meeting uploads are four ordered PDFs and do not repeat automatic cards',function(){
    $draft=($this->draft)();
    $c=new Crawler($this->get(route('sidang-ta-drafts.edit',['draft'=>$draft,'step'=>2]))->assertOk()->getContent());
    expect($c->filter('[data-upload-type]')->each(fn($n)=>$n->attr('data-upload-type')))->toBe(['ta_data_capture','online_supervision_capture','ta_supervisor_sk','thesis_draft']);
    foreach($c->filter('input[type="file"]') as $input) expect($input->getAttribute('accept'))->toBe('.pdf');
    expect($c->filter('[data-automatic-card]')->count())->toBe(0);
});

test('meeting PDM date is conditional and declaration is required in final modal',function(){
    $draft=($this->draft)(false);
    $c=new Crawler($this->get(route('sidang-ta-drafts.edit',['draft'=>$draft,'step'=>4]))->assertOk()->getContent());
    $date=$c->filter('input[name="pdm_submission_date"]');
    expect($date->count())->toBe(1);
    expect($date->attr(':disabled'))->toBe("accurate !== '0'");
    expect($date->attr('max'))->toBe(today()->toDateString());
    $c=new Crawler($this->get(route('sidang-ta-drafts.preview',$draft))->assertOk()->getContent());
    $checkbox=$c->filter('dialog input[name="declaration_accepted"]');
    expect($checkbox->attr('type'))->toBe('checkbox');
    expect($checkbox->getNode(0)->hasAttribute('required'))->toBeTrue();
    expect($checkbox->getNode(0)->hasAttribute('checked'))->toBeFalse();
    expect($c->filter('dialog')->text())->toContain('Saya MENYATAKAN bahwa saya telah memastikan dan memeriksa kembali seluruh berkas telah lengkap dan sesuai dengan ketentuan.');
});

test('meeting official BPP and sidang approval never borrow BASILA or manual values',function(){
    $this->student->bppPayment()->create(['status'=>'paid']);
    $this->student->basilaApproval()->create(['status'=>'approved','photo_approval'=>true]);
    StudentAcademicProfile::create(['student_number'=>$this->student->student_number,'groups'=>['bpp'=>['status'=>'KREDIT','period'=>'2627/1'],'sidang_approval'=>['status'=>'Disetujui','approved'=>true]],'bpp_periods'=>['2526/2'=>['status'=>'LUNAS']]]);
    $a=app(SidangTaAcademicSnapshot::class)->forStudent($this->student);
    expect($a['bpp_status'])->toBe('KREDIT');
    expect($a['bpp_period'])->toBe('2627/1');
    expect($a['sidang_approval_status'])->toBe('Disetujui');
    $page=$this->get(route('sidang-ta-registrations.create'))->assertOk();
    $page->assertSee('2627/1')->assertSee('2526/2')->assertSee('KREDIT')->assertDontSee('Periode pembayaran belum terkonfirmasi.');
    StudentAcademicProfile::query()->update(['groups'=>[]]);
    $a=app(SidangTaAcademicSnapshot::class)->forStudent($this->student);
    expect($a['bpp_status'])->toBe('Belum tersedia');
    expect($a['sidang_approval_status'])->toBe('Belum tersedia');
});

test('meeting staff controls are placed in filter and period cards with honest countdown',function(){
    $c=new Crawler($this->get(route('admin.sidang-ta-registrations.index'))->assertOk()->getContent());
    expect($c->filter('[data-ta-review-hub] > header a')->count())->toBe(0);
    expect($c->filter('[data-ta-filters] [data-ta-export-empty]')->count())->toBe(1);
    expect($c->filter('[data-ta-period-card] [data-manage-ta-periods]')->count())->toBe(1);
    expect($c->filter('[data-ta-period-banner] [data-period-countdown]')->count())->toBe(1);
    expect($c->filter('[data-ta-period-banner]')->getNode(0)->hasAttribute('x-data'))->toBeTrue();
    expect($c->filter('[data-ta-period-banner]')->attr('x-data'))->toContain('sidangTaPeriodCountdown');
    expect($c->filter('[data-ta-period-banner] [data-period-status]')->attr('x-text'))->toBe('status');
    expect($c->filter('[data-period-countdown]')->getNode(0)->hasAttribute('x-data'))->toBeFalse();
    $this->period->update(['is_active'=>false]);
    $c=new Crawler($this->get(route('admin.sidang-ta-registrations.index'))->assertOk()->getContent());
    expect($c->filter('[data-period-countdown]')->count())->toBe(0);
    expect($c->filter('[data-ta-period-card] [data-manage-ta-periods]')->count())->toBe(1);
});


test('meeting unknown scale 600 stays unknown and ECCT comma score dates stay truthful',function(){
    StudentAcademicProfile::create(['student_number'=>$this->student->student_number,'groups'=>['language'=>['score'=>600,'type'=>null,'attempts'=>3,'history'=>[['jenis_tes'=>'ECCT','nilai'=>'3,5','kedaluwarsa'=>'2027-10-01','status_berlaku'=>'BERLAKU']]]]]);
    $draft=($this->draft)();
    $c=new Crawler($this->get(route('sidang-ta-drafts.edit',['draft'=>$draft,'step'=>3]))->assertOk()->getContent());
    expect($c->filter('[data-eprt-mode]')->attr('data-gate-state'))->toBe('unknown');
    $history=$c->filter('[data-language-history]')->text(null,true);
    expect($history)->toContain('ECCT: 3,5')->toContain('Tanggal tes belum tersedia')->not->toContain('Tanggal tes 01/10/2027');
});


test('meeting revision waiver rejected borrowed file stays red until replacement', function(){
    StudentAcademicProfile::create(['student_number'=>$this->student->student_number,'groups'=>['language'=>['score'=>449,'type'=>'EPrT','attempts'=>3,'history'=>[]]]]);
    $source=SidangTaRegistration::create(['sidang_ta_period_id'=>$this->period->id,'student_number'=>$this->student->student_number,'student_name'=>$this->student->name,'student_phone'=>'081234567890','parent_phone'=>'081234567891','graduation_payment_date'=>today(),'diploma_biodata_accurate'=>true,'status'=>'needs_review','requirements_version'=>2,'academic_snapshot'=>['admission'=>['eprt'=>['mode'=>'waiver']]]]);
    foreach(\App\Support\SidangTaMeetRequirements::allTypes(true,'waiver') as $type) {
        $path='final4/'.$type.'.pdf'; Storage::disk('local')->put($path,'%PDF source');
        $source->documents()->create(['document_type'=>$type,'file_path'=>$path,'file_name'=>$type.'.pdf','file_type'=>'pdf','file_size'=>11,'status'=>$type==='eprt_waiver_letter'?'rejected':'verified','notes'=>$type==='eprt_waiver_letter'?'Ganti surat.':null]);
    }
    $id=$this->postJson(route('sidang-ta-drafts.revision',$source))->assertCreated()->json('data.id');
    $draft=SidangTaRegistrationDraft::findOrFail($id);
    $url=route('sidang-ta-drafts.edit',['draft'=>$draft,'step'=>3]);
    $c=new Crawler($this->get($url)->assertOk()->getContent());
    expect($c->filter('[data-eprt-mode]')->attr('data-gate-state'))->toBe('failed');
    expect($c->filter('[data-eprt-mode]')->text(null,true))->not->toContain('Surat permakluman terlampir');
    $c=new Crawler($this->get(route('sidang-ta-drafts.edit',['draft'=>$draft,'step'=>1]))->assertOk()->getContent());
    expect($c->filter('input[name="student_phone"]')->getNode(0)->hasAttribute('readonly'))->toBeFalse();
    $this->postJson(route('sidang-ta-drafts.steps.store',['draft'=>$draft,'step'=>3]),['version'=>$draft->version,'documents'=>['eprt_waiver_letter'=>\Illuminate\Http\UploadedFile::fake()->create('pengganti.pdf',30,'application/pdf')]])->assertOk();
    $c=new Crawler($this->get($url)->assertOk()->getContent());
    expect($c->filter('[data-eprt-mode]')->attr('data-gate-state'))->toBe('passed');
    expect($c->filter('[data-eprt-mode]')->text(null,true))->toContain('449')->toContain('Surat permakluman terlampir');
});

test('meeting historical PDM stays missing for student and staff without a fabricated date',function(){
    $registration=SidangTaRegistration::create(['sidang_ta_period_id'=>$this->period->id,'student_number'=>$this->student->student_number,'student_name'=>$this->student->name,'student_phone'=>'081234567890','parent_phone'=>'081234567891','graduation_payment_date'=>today(),'diploma_biodata_accurate'=>false,'status'=>'submitted','requirements_version'=>2,'academic_snapshot'=>['admission'=>['eprt'=>['mode'=>'automatic']]]]);
    foreach(['sidang-ta-registrations.show','admin.sidang-ta-registrations.show'] as $route) {
        $c=new Crawler($this->get(route($route,$registration))->assertOk()->getContent());
        expect($c->filter('[data-pdm-date]')->text(null,true))->toBe('Belum tersedia');
    }
});

test('meeting historical details do not dump internal snapshot metadata as extra fields',function(){
    $registration=SidangTaRegistration::create(['sidang_ta_period_id'=>$this->period->id,'student_number'=>$this->student->student_number,'student_name'=>$this->student->name,'student_phone'=>'081234567890','parent_phone'=>'081234567891','graduation_payment_date'=>today(),'diploma_biodata_accurate'=>true,'status'=>'submitted','requirements_version'=>2,'academic_snapshot'=>array_replace(app(SidangTaAcademicSnapshot::class)->forStudent($this->student),['admission'=>['eprt'=>['mode'=>'automatic']]])]);
    foreach(['sidang-ta-registrations.show','admin.sidang-ta-registrations.show'] as $route) {
        $c=new Crawler($this->get(route($route,$registration))->assertOk()->getContent());
        expect($c->filter('[data-academic-group="extra"]')->count())->toBe(0);
    }
});
