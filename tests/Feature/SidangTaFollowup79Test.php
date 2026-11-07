<?php

use App\Models\{AcademicPeriod, Course, SidangTaPeriod, SidangTaRegistration, SidangTaRegistrationDraft, Student, StudentAcademicProfile, StudentCourse, User};
use App\Services\{SidangTaAcademicSnapshot, SidangTaAdmissionFacts};
use App\Support\SidangTaMeetRequirements as Meet;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-07 10:00:00', config('app.timezone')));
    Storage::fake('local');
    $this->user = User::factory()->create(['username'=>'FOLLOW79','status'=>'active','auth_source'=>'manual','must_change_password'=>false]);
    $this->student = Student::create(['student_number'=>'FOLLOW79','name'=>'Mahasiswa Followup','email'=>$this->user->email,'academic_year'=>2022,'study_program'=>'Informatika','status'=>'Aktif','gpa'=>3.50]);
    foreach (['read','create','update'] as $action) $this->user->givePermissionTo(Permission::firstOrCreate(['name'=>'sidang_ta_registration_'.$action,'guard_name'=>'web']));
    $this->actingAs($this->user);
    $this->period = SidangTaPeriod::create(['name'=>'Followup79','is_active'=>true,'open_at'=>now()->subDay(),'close_at'=>now()->addDay()]);
    AcademicPeriod::query()->update(['is_active'=>false]);
    $academic=AcademicPeriod::create(['year'=>'2026/2027','semester'=>1,'label'=>'Follow79','start_date'=>'2026-08-01','end_date'=>'2027-01-31','is_active'=>true]);
    Course::create(['course_code'=>'FOLLOW79','name'=>'TUGAS AKHIR 2','academic_year'=>'2026/2027','semester_term'=>'GANJIL','study_program'=>'Informatika','class'=>'A','semester'=>8,'credits'=>4]);
    StudentCourse::create(['student_number'=>$this->student->student_number,'course_code'=>'FOLLOW79','course_name'=>'TUGAS AKHIR 2','course_class'=>'A','academic_year'=>'2026/2027','semester'=>8,'academic_period_id'=>$academic->id]);
    $this->profile=StudentAcademicProfile::create(['student_number'=>$this->student->student_number,'groups'=>[
        'academic'=>['gpa'=>3.12,'passed_credits'=>130,'level_2_passed'=>true], 'skpi'=>['status'=>'Disetujui','approved'=>true],
        'tak'=>['score'=>80], 'language'=>['score'=>470,'type'=>'EPrT','attempts'=>1,'history'=>[]],
        'bpp'=>['status'=>'LUNAS','period'=>'2627/1'],'sidang_approval'=>['status'=>'APPROVED','approved'=>true],
    ]]);
    $this->draft79=function(bool $accurate=true) {
        $draft=SidangTaRegistrationDraft::create(['owner_user_id'=>$this->user->id,'student_number'=>$this->student->student_number,'sidang_ta_period_id'=>$this->period->id,'status'=>'active','version'=>1,'reviewed_version'=>1,'requirements_version'=>2,'payload'=>['student_phone'=>'081234567890','parent_phone'=>'628123456789','graduation_payment_date'=>today()->toDateString(),'diploma_biodata_accurate'=>$accurate,'pdm_submission_date'=>$accurate?null:today()->toDateString()]]);
        foreach(Meet::allTypes($accurate,'automatic') as $type) {
            $path='follow79/'.$draft->id.'/'.$type.'.pdf';Storage::disk('local')->put($path,'%PDF test');
            $draft->documents()->create(['document_type'=>$type,'file_path'=>$path,'file_name'=>$type.'.pdf','file_type'=>'pdf','file_size'=>9]);
        }
        return $draft;
    };
    $this->gpa79=function($value) { $groups=$this->profile->fresh()->groups;$groups['academic']['gpa']=$value;$this->profile->update(['groups'=>$groups]); };
});
afterEach(function(){ $this->travelBack(); });

test('follow79 rejects non Indonesian WA prefixes for each contact',function($field,$value){
    $this->postJson(route('sidang-ta-drafts.store'),['sidang_ta_period_id'=>$this->period->id,'student_phone'=>'081234567890','parent_phone'=>'628123456789',$field=>$value])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(SidangTaRegistrationDraft::count())->toBe(0);
})->with([['student_phone','55555555'],['parent_phone','620812345678'],['student_phone','071234567890']]);

test('follow79 accepts both supported prefixes without dropping leading zero',function(){
    $response=$this->postJson(route('sidang-ta-drafts.store'),['sidang_ta_period_id'=>$this->period->id,'student_phone'=>'081234567890','parent_phone'=>'628123456789'])->assertCreated();
    $payload=SidangTaRegistrationDraft::findOrFail($response->json('data.id'))->payload;
    expect($payload['student_phone'])->toBe('081234567890')->and($payload['parent_phone'])->toBe('628123456789');
});

test('follow79 GPA gate uses official value and exact minimum',function($gpa,$state){
    ($this->gpa79)($gpa);$facts=app(SidangTaAdmissionFacts::class)->forStudent($this->student);
    expect($facts['academic']['gates'])->toHaveKey('gpa');
    expect($facts['academic']['gates']['gpa']['state'])->toBe($state);
    expect($facts['academic']['academic_passed'])->toBe($state==='passed');
    $snapshot=app(SidangTaAcademicSnapshot::class)->forStudent($this->student,$facts);
    expect($snapshot['gpa'])->toBe($gpa===null?null:(float)$gpa);
})->with([[1.99,'failed'],[2,'passed'],[2.01,'passed'],[null,'unknown']]);

test('follow79 final submit rechecks GPA after review',function(){
    $draft=($this->draft79)();($this->gpa79)(1.99);
    $this->postJson(route('sidang-ta-registrations.store'),['draft_id'=>$draft->id,'version'=>1,'action'=>'submit','declaration_accepted'=>true])
        ->assertUnprocessable()->assertJsonValidationErrors('eligibility.gpa');
    expect(SidangTaRegistration::count())->toBe(0);
});

test('follow79 final submit rechecks WA prefix in saved payload',function(){
    $draft=($this->draft79)();$draft->update(['payload'=>array_replace($draft->payload,['parent_phone'=>'55555555'])]);
    $this->postJson(route('sidang-ta-registrations.store'),['draft_id'=>$draft->id,'version'=>1,'action'=>'submit','declaration_accepted'=>true])
        ->assertUnprocessable()->assertJsonValidationErrors('parent_phone');
});

test('follow79 renders red mandatory markers and colored financial approval cards',function(){
    $draft=($this->draft79)(false);
    foreach([1=>['sidang_ta_period_id','student_phone','parent_phone'],3=>['graduation_payment_date'],4=>['pdm_submission_date']] as $step=>$fields){
        $c=new Crawler($this->get(route('sidang-ta-drafts.edit',['draft'=>$draft,'step'=>$step]))->assertOk()->getContent());
        foreach($fields as $field){
            $ids=['sidang_ta_period_id'=>'sidang-ta-period-id','student_phone'=>'student-phone','parent_phone'=>'parent-phone','graduation_payment_date'=>'graduation-payment-date','pdm_submission_date'=>'pdm-submission-date'];
            $label=$c->filter('label[for="'.$ids[$field].'"]');expect($label->count())->toBe(1);
            expect($label->filter('span.text-rose-600')->count())->toBe(1);
            expect($label->filter('span.text-rose-600')->text())->toBe('*');
        }
        if($step===4)expect($c->filter('legend span.text-rose-600')->text())->toBe('*');
    }
    $c=new Crawler($this->get(route('sidang-ta-drafts.edit',['draft'=>$draft,'step'=>1]))->assertOk()->getContent());
    expect($c->filter('[data-admission-gate="gpa"]')->text())->toContain('3,12')->toContain('IPK minimal 2');
    expect($c->filter('[data-information-card="bpp"]')->attr('data-state'))->toBe('passed');
    expect($c->filter('[data-information-card="sidang_approval"]')->attr('data-state'))->toBe('passed');
    expect($c->filter('[data-academic-panel]')->text())->toContain('2627/1')->not->toContain('Periode pembayaran belum terkonfirmasi.');
});

test('follow79 blockers appear by send action instead of repeated panel warning',function(){
    $groups=$this->profile->groups;$groups['skpi']['status']='Belum disetujui';$groups['skpi']['approved']=false;$groups['academic']['gpa']=1.99;$this->profile->update(['groups'=>$groups]);
    $draft=($this->draft79)();$c=new Crawler($this->get(route('sidang-ta-drafts.preview',$draft))->assertOk()->getContent());
    expect($c->filter('[data-academic-panel] [data-academic-blockers]')->count())->toBe(0);
    $footer=$c->filter('[data-review-actions]');expect($footer->text())->toContain('SKPI')->toContain('IPK minimal 2');
    expect($footer->filter('[data-open-registration]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
});

test('follow79 confirmation starts disabled and declaration text has no mandatory star',function(){
    $draft=($this->draft79)();$c=new Crawler($this->get(route('sidang-ta-drafts.preview',$draft))->assertOk()->getContent());
    $checkbox=$c->filter('input[name="declaration_accepted"]');expect($checkbox->attr('x-model'))->toBe('accepted');
    expect($checkbox->ancestors()->first()->text())->not->toContain('*');
    $confirm=$c->filter('[data-confirm-registration]');expect($confirm->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($confirm->attr(':disabled'))->toBe('!canConfirm');
    expect($c->filter('[data-open-registration]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
});

test('follow79 profile then finance upload keeps period through snapshot and panel',function($routeName){
    $this->user->givePermissionTo(Permission::firstOrCreate(['name'=>'sync_execute','guard_name'=>'web']));
    $row=['nim'=>$this->student->student_number,'nama'=>$this->student->name,'semester'=>'8','ipk'=>'3.12','sks_lulus'=>'130','sidang_tingkat_2'=>'Y','total_tak'=>'80','status_skpi'=>'Disetujui','persetujuan_maju_sidang'=>'APPROVED','nilai_bahasa'=>'470','jenis_tes_bahasa'=>'EPrT','jumlah_tes_bahasa'=>1,'riwayat_nilai_bahasa'=>[],'status_keuangan'=>'LUNAS'];
    $upload=function($menus,$rows)use($routeName){
        $fields=['json_file'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('demo79.json',json_encode(['selected_menus'=>$menus,'data'=>$rows]))];
        if($routeName==='sync.import-json')$fields['type']='academic_profile';
        return $this->from(route('sync.index'))->post(route($routeName),$fields)->assertRedirect(route('sync.index'))->assertSessionHasNoErrors();
    };
    $upload(['akademik','bahasa','tak','skpi','maju_sidang','keuangan'],[$row]);
    expect($this->profile->fresh()->groups['bpp']['period'])->toBeNull();
    $upload(['keuangan'],[['nim'=>$row['nim'],'nama'=>$row['nama'],'semester'=>'8','status_keuangan'=>'LUNAS','periode_keuangan'=>'2627/1']]);
    expect($this->profile->fresh()->groups['bpp']['period'])->toBe('2627/1');
    expect(app(SidangTaAcademicSnapshot::class)->forStudent($this->student->fresh())['bpp_period'])->toBe('2627/1');
    $page=$this->get(route('sidang-ta-registrations.create'))->assertOk();
    $c=new Crawler($page->getContent());expect($c->filter('[data-information-card="bpp"]')->text())->toContain('2627/1')->not->toContain('belum tersedia');
})->with(['sync.import-json','sync.import-academic-profile']);

test('follow79 language only import displays actual test date and preserves BPP period',function(){
    $this->user->givePermissionTo(Permission::firstOrCreate(['name'=>'sync_execute','guard_name'=>'web']));
    $row=['nim'=>$this->student->student_number,'nama'=>$this->student->name,'nilai_bahasa'=>'470','jenis_tes_bahasa'=>'EPrT','jumlah_tes_bahasa'=>1,'riwayat_nilai_bahasa'=>[
        ['jenis_tes'=>'EPrT','kategori_tes_valid'=>'EPrT','nilai'=>null,'tanggal_tes'=>'23 April 2025','kedaluwarsa'=>null,'status_berlaku'=>'Tidak Berlaku'],
        ['jenis_tes'=>'EPrT','kategori_tes_valid'=>'EPrT','nilai'=>'470','tanggal_tes'=>'28 Mei 2025','kedaluwarsa'=>'28 Mei 2027','status_berlaku'=>'Berlaku'],
    ]];
    $this->post(route('sync.import-academic-profile'),['json_file'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('bahasa79.json',json_encode(['selected_menus'=>['bahasa'],'data'=>[$row]]))])->assertSessionHasNoErrors();
    $draft=($this->draft79)();$page=$this->get(route('sidang-ta-drafts.edit',['draft'=>$draft,'step'=>3]))->assertOk();
    $c=new Crawler($page->getContent());expect($c->filter('[data-language-history]')->text())->toContain('Tanggal tes 23/04/2025')->toContain('Tanggal tes 28/05/2025')->toContain('Kedaluwarsa 28/05/2027');
    expect($this->profile->fresh()->groups['bpp']['period'])->toBe('2627/1');
});
