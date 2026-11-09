<?php

use App\Models\{SidangTaPeriod,SidangTaRegistration,SidangTaRegistrationDraft,Student,User};
use App\Services\{SidangTaExpiredDraftCleanup,SidangTaRevisionExpiry};
use App\Support\SidangTaRevisionDeadline;
use Illuminate\Support\Facades\{DB,Storage};
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-12 12:00:00', config('app.timezone')));
    Storage::fake('local');
    $this->user85 = User::factory()->create(['username'=>'WINDOW85','status'=>'active','auth_source'=>'manual','must_change_password'=>false]);
    $this->student85 = Student::create(['student_number'=>'WINDOW85','name'=>'Window Student','email'=>$this->user85->email,'academic_year'=>2022,'study_program'=>'Informatika','status'=>'Aktif']);
    foreach (['sidang_ta_period_create','sidang_ta_period_update','sidang_ta_registration_create','sidang_ta_registration_read','sidang_ta_registration_update'] as $name) {
        $this->user85->givePermissionTo(Permission::firstOrCreate(['name'=>$name,'guard_name'=>'web']));
    }
    $this->actingAs($this->user85);
    $this->period85 = SidangTaPeriod::create(['name'=>'Periode 9–11','open_at'=>'2026-11-09 00:00:00','close_at'=>'2026-11-11 23:59:59','is_active'=>true]);
    $this->source85 = SidangTaRegistration::create(['sidang_ta_period_id'=>$this->period85->id,'student_number'=>'WINDOW85','student_name'=>'Window Student','student_phone'=>'081234567890','parent_phone'=>'081234567891','graduation_payment_date'=>today(),'diploma_biodata_accurate'=>true,'status'=>'needs_review']);
    $this->schedule85 = function ($start='2026-11-12 00:00:00',$end='2026-11-20 23:59:59') {
        $this->period85->update(['revision_open_at'=>$start,'revision_close_at'=>$end]);
    };
    $this->draft85 = function (bool $revision=true) {
        $draft=SidangTaRegistrationDraft::create(['owner_user_id'=>$this->user85->id,'student_number'=>'WINDOW85','sidang_ta_period_id'=>$this->period85->id,'source_registration_id'=>$revision?$this->source85->id:null,'source_version'=>$revision?$this->source85->lock_version:null,'payload'=>[]]);
        $path='window85/'.$draft->id.'/owned.pdf';Storage::disk('local')->put($path,'%PDF');
        $draft->documents()->create(['document_type'=>'ta_data_capture','file_name'=>'owned.pdf','file_path'=>$path]);
        return $draft;
    };
});
afterEach(function () { $this->travelBack(); });

test('window85 legacy missing schedule holds revision expiry and cleanup while initial draft expires',function(){
    $revision=($this->draft85)();$initial=($this->draft85)(false);
    expect(SidangTaRevisionDeadline::hasExpired($this->source85))->toBeFalse();
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(0);
    expect(app(SidangTaExpiredDraftCleanup::class)->run()['deleted_drafts'])->toBe(1);
    expect($revision->fresh())->not->toBeNull();expect($initial->fresh())->toBeNull();
    expect($this->source85->fresh()->status)->toBe('needs_review');
});

test('window85 revision stays open after registration closes including inactive registration period',function(){
    ($this->schedule85)();$this->period85->update(['is_active'=>false]);
    expect(SidangTaRevisionDeadline::hasExpired($this->source85))->toBeFalse();
    SidangTaRevisionDeadline::ensureOpen($this->source85);
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(0);
    $revision=($this->draft85)();
    expect(app(SidangTaExpiredDraftCleanup::class)->run()['deleted_drafts'])->toBe(0);
    expect($revision->fresh())->not->toBeNull();
    $this->postJson(route('sidang-ta-drafts.store'),['sidang_ta_period_id'=>$this->period85->id,'student_phone'=>'081234567890','parent_phone'=>'081234567891'])->assertConflict();
    // Existing needs_review submission prevents another initial application.
});

test('window85 revision start and exact end boundaries use independent dates',function(){
    ($this->schedule85)('2026-11-13 12:00:00','2026-11-20 12:00:00');
    expect(SidangTaRevisionDeadline::hasExpired($this->source85))->toBeFalse();
    expect(fn()=>SidangTaRevisionDeadline::ensureOpen($this->source85))->toThrow(\Illuminate\Validation\ValidationException::class);
    $this->travelTo(\Carbon\Carbon::parse('2026-11-13 12:00:00',config('app.timezone')));
    SidangTaRevisionDeadline::ensureOpen($this->source85);
    $this->travelTo(\Carbon\Carbon::parse('2026-11-20 12:00:00',config('app.timezone')));
    $draft=($this->draft85)();SidangTaRevisionDeadline::ensureOpen($this->source85);
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(0);
    expect(app(SidangTaExpiredDraftCleanup::class)->run()['deleted_drafts'])->toBe(0);
    $this->travel(1)->seconds();
    expect(SidangTaRevisionDeadline::hasExpired($this->source85))->toBeTrue();
    expect(app(SidangTaRevisionExpiry::class)->run())->toBe(1);
    expect(app(SidangTaExpiredDraftCleanup::class)->run()['deleted_drafts'])->toBe(1);
    expect($draft->fresh())->toBeNull();expect($this->source85->fresh()->status)->toBe('rejected');
});

test('window85 cleanup keeps borrowed final files and submitted data when revision draft expires',function(){
    ($this->schedule85)('2026-11-09 00:00:00','2026-11-11 23:59:59');
    $draft=($this->draft85)();$before=$this->source85->fresh()->getAttributes();
    Storage::disk('local')->put('window85/final.pdf','%PDF');
    $final=$this->source85->documents()->create(['document_type'=>'thesis_draft','file_name'=>'final.pdf','file_path'=>'window85/final.pdf','status'=>'verified']);
    $draft->documents()->create(['document_type'=>'thesis_draft','file_name'=>'final.pdf','file_path'=>$final->file_path,'source_document_id'=>$final->id]);
    expect(app(SidangTaExpiredDraftCleanup::class)->run()['deleted_drafts'])->toBe(1);
    Storage::disk('local')->assertExists($final->file_path);
    expect($this->source85->fresh()->getAttributes())->toBe($before);
    expect($this->source85->documents()->count())->toBe(1);
});

test('window85 cleanup rechecks extended revision deadline after candidate selection',function(){
    ($this->schedule85)('2026-11-09 00:00:00','2026-11-11 23:59:59');$draft=($this->draft85)();
    $event='eloquent.retrieved: '.Student::class;
    \Illuminate\Support\Facades\Event::listen($event,function(){DB::table('sidang_ta_periods')->where('id',$this->period85->id)->update(['revision_close_at'=>'2026-11-20 23:59:59']);});
    try {expect(app(SidangTaExpiredDraftCleanup::class)->run()['deleted_drafts'])->toBe(0);expect($draft->fresh())->not->toBeNull();}
    finally {\Illuminate\Support\Facades\Event::forget($event);}
});

test('window85 existing submitted period gets schedule without changing registration identity or snapshots',function(){
    $this->source85->update(['status'=>'submitted','academic_snapshot'=>['sentinel'=>'unchanged']]);$before=$this->source85->fresh()->getAttributes();
    $payload=['name'=>$this->period85->name,'open_at'=>'2026-11-09 00:00:00','close_at'=>'2026-11-11 23:59:59','revision_open_at'=>'2026-11-09 00:00:00','revision_close_at'=>'2026-11-20 23:59:59','is_active'=>true];
    $this->put(route('admin.sidang-ta-periods.update',$this->period85),$payload)->assertSessionHasNoErrors()->assertRedirect();
    expect($this->period85->fresh()->revision_open_at?->format('Y-m-d H:i:s'))->toBe('2026-11-09 00:00:00');
    expect($this->period85->fresh()->revision_close_at?->format('Y-m-d H:i:s'))->toBe('2026-11-20 23:59:59');
    expect($this->source85->fresh()->getAttributes())->toBe($before);
    expect(SidangTaRegistration::count())->toBe(1);
});

test('window85 period form requires both revision dates and validates closing order',function($overrides,$field){
    $payload=array_replace(['name'=>'Window new','open_at'=>'2026-11-09 00:00:00','close_at'=>'2026-11-11 23:59:59','revision_open_at'=>'2026-11-09 00:00:00','revision_close_at'=>'2026-11-20 23:59:59','is_active'=>true],$overrides);
    $this->postJson(route('admin.sidang-ta-periods.store'),$payload)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([[['revision_open_at'=>null],'revision_open_at'],[['revision_close_at'=>null],'revision_close_at'],[['revision_open_at'=>'2026-11-21 00:00:00'],'revision_close_at'],[['revision_close_at'=>'2026-11-10 23:59:59'],'revision_close_at']]);
