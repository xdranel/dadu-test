<?php

use App\Models\{SidangTaPeriod,SidangTaRegistration,SidangTaRegistrationDraft,Student,User};
use Spatie\Permission\Models\Permission;

beforeEach(function(){
    $this->user=User::factory()->create(['username'=>'HISTORY-4','status'=>'active','auth_source'=>'manual','must_change_password'=>false]);
    $this->student=Student::create(['student_number'=>'HISTORY-4','name'=>'Pelaku Mahasiswa','email'=>$this->user->email,'academic_year'=>2022,'study_program'=>'Informatika','status'=>'Aktif']);
    foreach(['sidang_ta_registration_read','sidang_ta_registration_update','sidang_ta_registration_review'] as $name) $this->user->givePermissionTo(Permission::firstOrCreate(['name'=>$name,'guard_name'=>'web']));
    $period=SidangTaPeriod::create(['name'=>'History','is_active'=>true,'open_at'=>now()->subDay(),'close_at'=>now()->addDay()]);
    $this->registration=SidangTaRegistration::create(['student_number'=>'HISTORY-4','student_name'=>'Pelaku Mahasiswa','sidang_ta_period_id'=>$period->id,'student_phone'=>'081234567890','parent_phone'=>'081234567891','graduation_payment_date'=>today(),'diploma_biodata_accurate'=>true,'status'=>'needs_review','academic_snapshot'=>['admission'=>['eprt'=>['mode'=>'automatic']]]]);
    $this->actingAs($this->user);
});

test('student timeline whitelists events for dual role owner and keeps staff audit separate',function(){
    foreach(['submitted','document_verified','needs_review','document_rejected','resubmitted','rejected','approved','auto-rejected'] as $i=>$action) {
        $this->registration->logs()->create(['action'=>$action,'notes'=>'Catatan '.$action,'performed_by'=>$this->user->id,'performed_at'=>\Carbon\Carbon::parse('2026-11-07 08:00:00',config('app.timezone'))->addMinutes($i)]);
    }
    $response=$this->getJson(route('sidang-ta-registrations.history',$this->registration))->assertOk();
    expect(array_column($response->json('data'),'action'))->toBe(['approved','rejected','resubmitted','needs_review','submitted']);
    expect($response->json('data.0.time'))->toContain('08:06 WIB');
    expect(array_keys($response->json('data.0')))->toBe(['action','label','time','notes']);
    $this->get(route('sidang-ta-registrations.history',$this->registration))->assertOk()->assertSee('Pengajuan disetujui admin')->assertDontSee('document_verified');
    $this->get(route('admin.sidang-ta-registrations.show',$this->registration))->assertOk()->assertSee('document_verified')->assertSee('document_rejected');
});

test('student timeline keeps manual and automatic rejection reasons with a red marker', function () {
    $actor = User::factory()->create(['name' => 'Reviewer Identity Hidden']);
    foreach ([$actor->id, null] as $i => $actorId) {
        $this->registration->logs()->create([
            'action' => 'rejected',
            'notes' => $i === 0 ? 'Berkas belum lengkap <script>alert("reason")</script>' : 'Batas waktu revisi berakhir.',
            'performed_by' => $actorId,
            'performed_at' => '2026-11-07 08:0'.$i.':00',
        ]);
    }

    $response = $this->getJson(route('sidang-ta-registrations.history', $this->registration))->assertOk();
    expect(array_column($response->json('data'), 'action'))->toBe(['rejected', 'rejected']);
    expect(array_column($response->json('data'), 'notes'))->toBe([
        'Batas waktu revisi berakhir.',
        'Berkas belum lengkap <script>alert("reason")</script>',
    ]);
    foreach ($response->json('data') as $event) {
        expect($event)->not->toHaveKey('actor');
    }

    $html = $this->get(route('sidang-ta-registrations.history', $this->registration))->assertOk()
        ->assertSee('Pengajuan ditolak')
        ->assertSee('Batas waktu revisi berakhir.')
        ->assertSee('Berkas belum lengkap <script>alert("reason")</script>')
        ->assertDontSee('<script>alert("reason")</script>', false);
    $timeline = (new \Symfony\Component\DomCrawler\Crawler($html->getContent()))->filter('[data-student-timeline]');
    expect($timeline->text())->not->toContain('Pelaku:', $actor->name);
    expect($timeline->filter('li > span.bg-rose-100.text-rose-800')->count())->toBe(2);
});

test('student timeline omits actor for ordinary submission events', function () {
    $actor = User::factory()->create(['name' => 'Submission Identity Hidden']);
    $this->registration->logs()->create([
        'action' => 'submitted', 'notes' => 'Catatan pengajuan tetap terlihat.',
        'performed_by' => $actor->id, 'performed_at' => now(),
    ]);
    $response = $this->getJson(route('sidang-ta-registrations.history', $this->registration))->assertOk();
    expect($response->json('data.0'))->not->toHaveKey('actor');
    $html = $this->get(route('sidang-ta-registrations.history', $this->registration))->assertOk()
        ->assertSee('Catatan pengajuan tetap terlihat.');
    $timeline = (new \Symfony\Component\DomCrawler\Crawler($html->getContent()))->filter('[data-student-timeline]');
    expect($timeline->text())->not->toContain('Pelaku:', $actor->name);
});

test('student timeline rejects another student even for dual role reviewer',function(){
    $this->registration->update(['student_number'=>'OTHER-STUDENT']);
    $this->getJson(route('sidang-ta-registrations.history',$this->registration))->assertForbidden();
});

test('needs review student detail redirects to recap without GET creating draft',function(){
    $this->get(route('sidang-ta-registrations.show',$this->registration))->assertRedirect(route('sidang-ta-registrations.edit',$this->registration));
    $this->get(route('sidang-ta-registrations.edit',$this->registration))->assertOk()->assertSee('Perbaikan Pengajuan Anda')->assertSee('Detail Revisi');
    $page = $this->get(route('sidang-ta-registrations.index'))->assertOk()
        ->assertSee(route('sidang-ta-registrations.history',$this->registration),false);
    $index = new \Symfony\Component\DomCrawler\Crawler($page->getContent());
    $active = $index->filter('[data-registration-active]');
    $detail = $index->filter('table tbody tr td:last-child a:not([data-registration-history])');
    expect($detail->text(null, true))->toBe('Detail Revisi');
    expect($active->count())->toBe(0);
    $revise = $index->filter('[data-registration-revise]');
    expect($revise->text(null, true))->toBe('Mulai revisi');
    foreach ([$revise, $detail] as $link) {
        expect($link->attr('href'))->toBe(route('sidang-ta-registrations.edit',$this->registration));
        $this->get($link->attr('href'))->assertOk()->assertSee('Perbaikan Pengajuan Anda');
    }
    expect(SidangTaRegistrationDraft::count())->toBe(0);
});
