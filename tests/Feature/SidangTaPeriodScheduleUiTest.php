<?php

use App\Models\{SidangTaPeriod, SidangTaRegistration, SidangTaRegistrationDraft, Student, User};
use Spatie\Permission\Models\Permission;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    $this->user = User::factory()->create(['username'=>'SCHEDULE-85', 'status'=>'active', 'auth_source'=>'manual', 'must_change_password'=>false]);
    $this->student = Student::create(['student_number'=>'SCHEDULE-85','name'=>'Mahasiswa Jadwal','email'=>$this->user->email,'academic_year'=>2022,'study_program'=>'Informatika','status'=>'Aktif']);
    foreach (['sidang_ta_registration_read','sidang_ta_registration_create','sidang_ta_registration_update','sidang_ta_registration_review','sidang_ta_period_read','sidang_ta_period_create','sidang_ta_period_update'] as $name) {
        $this->user->givePermissionTo(Permission::firstOrCreate(['name'=>$name,'guard_name'=>'web']));
    }
    $this->period = SidangTaPeriod::create(['name'=>'Jadwal Legacy','is_active'=>true,'open_at'=>now()->subDay(),'close_at'=>now()->addDay()]);
    $this->actingAs($this->user);
});

test('student index displays both schedules and honest unconfigured verification', function () {
    $response=$this->get(route('sidang-ta-registrations.index'))->assertOk();
    $response->assertSee('Pendaftaran Sidang TA')->assertSee('Tanggal Verifikasi Berkas')->assertSee('Belum diatur');
    $c=new Crawler($response->getContent());
    expect($c->filter('[data-period-schedule="'.$this->period->id.'"] [data-schedule="registration"]')->count())->toBe(1);
    expect($c->filter('[data-period-schedule="'.$this->period->id.'"] [data-schedule="revision"]')->count())->toBe(1);
    expect($c->filter('[data-schedule="registration"] [data-countdown-unit]')->each(fn($n)=>$n->attr('data-countdown-unit')))->toBe(['days','hours','minutes','seconds']);
});

test('period forms require verification dates below registration dates', function () {
    $response=$this->get(route('admin.sidang-ta-periods.index'))->assertOk();
    $response->assertSee('Tanggal Verifikasi Berkas');
    $c=new Crawler($response->getContent());
    foreach (['data-ta-period-create','data-ta-period-update'] as $form) {
        foreach (['revision_open_at','revision_close_at'] as $field) {
            $node=$c->filter('['.$form.'] input[name="'.$field.'"]');
            expect($node->count())->toBe(1);
            expect($node->getNode(0)->hasAttribute('required'))->toBeTrue();
        }
    }
});

test('wizard carries both schedules when selected period changes', function () {
    $response=$this->get(route('sidang-ta-registrations.create'))->assertOk();
    $response->assertSee('Tanggal Verifikasi Berkas');
    $c=new Crawler($response->getContent());
    expect($c->filter('select[name="sidang_ta_period_id"] option[data-period-id="'.$this->period->id.'"]')->count())->toBe(1);
    expect($c->filter('[data-period-schedule="'.$this->period->id.'"]')->attr('x-show'))->toContain('selectedPeriodId');
});


function scheduleUiRegistration85($test, string $status = 'needs_review'): SidangTaRegistration {
    return SidangTaRegistration::create(['student_number'=>$test->student->student_number,'student_name'=>$test->student->name,
        'sidang_ta_period_id'=>$test->period->id,'student_phone'=>'081234567890','parent_phone'=>'081234567891',
        'graduation_payment_date'=>today(),'diploma_biodata_accurate'=>true,'status'=>$status,'requirements_version'=>2,
        'academic_snapshot'=>['admission'=>['eprt'=>['mode'=>'automatic']]]])->fresh();
}

test('actual inactive registration period retains revision countdown on index and recap', function () {
    $this->period->update(['is_active'=>false,'open_at'=>now()->subDays(4),'close_at'=>now()->subDay(),
        'revision_open_at'=>now()->subHours(2),'revision_close_at'=>now()->addDay()]);
    $registration=scheduleUiRegistration85($this);
    $page=$this->get(route('sidang-ta-registrations.index'))->assertOk();
    $c=new Crawler($page->getContent());
    expect($c->filter('[data-registration-active]')->count())->toBe(0);
    expect($c->filter('[data-registration-revise]')->count())->toBe(1);
    expect($c->filter('[data-period-schedule="'.$this->period->id.'"] [data-schedule="revision"] [data-countdown-unit]')->count())->toBe(4);
    expect($c->filter('[data-schedule="registration"] [data-period-status]')->text())->toBe('Tidak aktif');
    expect($c->filter('[data-schedule="registration"] [data-period-countdown]')->count())->toBe(0);
    $recap=$this->get(route('sidang-ta-registrations.edit',$registration))->assertOk();
    $recap->assertSee($this->period->revision_close_at->format('d/m/Y H:i'));
    $c=new Crawler($recap->getContent());
    expect($c->filter('form[action="'.route('sidang-ta-drafts.revision',$registration).'"] button[type="submit"]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
});

test('before verification starts recap and saved draft remain viewable while writes are disabled', function () {
    $this->period->update(['revision_open_at'=>now()->addDays(2),'revision_close_at'=>now()->addDays(4)]);
    $registration=scheduleUiRegistration85($this);
    $page=$this->get(route('sidang-ta-registrations.edit',$registration))->assertOk()->assertSee('Verifikasi berkas belum dibuka');
    $c=new Crawler($page->getContent());
    expect($c->filter('[data-revision-not-started]')->count())->toBe(1);
    expect($c->filter('form[action="'.route('sidang-ta-drafts.revision',$registration).'"] button[type="submit"]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    $draft=SidangTaRegistrationDraft::create(['owner_user_id'=>$this->user->id,'student_number'=>$this->student->student_number,
        'sidang_ta_period_id'=>$this->period->id,'source_registration_id'=>$registration->id,'source_version'=>$registration->lock_version,
        'status'=>'active','version'=>1,'requirements_version'=>2,'payload'=>['student_phone'=>'081234567890','parent_phone'=>'081234567891','diploma_biodata_accurate'=>true]]);
    $page=$this->get(route('sidang-ta-drafts.edit',$draft))->assertOk()->assertSee('Verifikasi berkas belum dibuka');
    $c=new Crawler($page->getContent());
    expect($c->filter('[data-revision-write-fields]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
    expect($c->filter('[data-save-draft]')->getNode(0)->hasAttribute('disabled'))->toBeTrue();
});

test('submitted application retains the active application button', function () {
    $registration=scheduleUiRegistration85($this,'submitted');
    $c=new Crawler($this->get(route('sidang-ta-registrations.index'))->assertOk()->getContent());
    expect($c->filter('[data-registration-active]')->attr('href'))->toBe(route('sidang-ta-registrations.show',$registration));
    expect($c->filter('[data-registration-revise]')->count())->toBe(0);
});
