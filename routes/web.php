<?php

use App\Http\Controllers\Auth\SsoController;
use App\Http\Controllers\Admin\SidangTaFormGuideController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentCategoryController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExamScheduleController;
use App\Http\Controllers\KrsController;
use App\Http\Controllers\LecturerController;
use App\Http\Controllers\ManualUserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicDocumentController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SidangTaDraftController;
use App\Http\Controllers\SidangTaRegistrationController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentCourseController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\GradeAppealController;
use App\Http\Controllers\GraduationRegistrationController;
use App\Http\Controllers\GraduationRegistrationDocumentController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;

/**
 * Register a resource route with granular Spatie permissions.
 */
if (! function_exists('registerResourceWithPermissions')) {
    function registerResourceWithPermissions(string $name, string $controller, string $permission, ?string $param = null): void
    {
        $param ??= $permission;

        Route::get($name, [$controller, 'index'])->name("{$name}.index")->middleware("permission:{$permission}_read");
        Route::get("{$name}/create", [$controller, 'create'])->name("{$name}.create")->middleware("permission:{$permission}_create");
        Route::post($name, [$controller, 'store'])->name("{$name}.store")->middleware("permission:{$permission}_create");
        Route::get("{$name}/{{$param}}", [$controller, 'show'])->name("{$name}.show")->middleware("permission:{$permission}_read");
        Route::get("{$name}/{{$param}}/edit", [$controller, 'edit'])->name("{$name}.edit")->middleware("permission:{$permission}_update");
        Route::put("{$name}/{{$param}}", [$controller, 'update'])->name("{$name}.update")->middleware("permission:{$permission}_update");
        Route::patch("{$name}/{{$param}}", [$controller, 'update'])->middleware("permission:{$permission}_update");
        Route::delete("{$name}/{{$param}}", [$controller, 'destroy'])->name("{$name}.destroy")->middleware("permission:{$permission}_delete");
    }
}

// Root redirect
Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login')
);

// Auth (guest)
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => view('auth.login'))->name('login');
    Route::post('/auth/login-proses', [SsoController::class, 'loginApi'])->name('sso.api.login');

    // Forgot Password (Self-Service Reset)
    Route::get('/forgot-password', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'showStep1'])
        ->name('forgot-password.step1');
    Route::post('/forgot-password/verify', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'submitStep1'])
        ->middleware('throttle:3,15')
        ->name('forgot-password.verify');
    Route::get('/forgot-password/reset', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'showStep2'])
        ->name('forgot-password.step2');
    Route::post('/forgot-password/reset', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'submitStep2'])
        ->middleware('throttle:3,15')
        ->name('forgot-password.reset');

    // Backward compatibility: redirect old route to new route
    Route::get('/lupa-password', fn () => redirect()->route('forgot-password.step1'))
        ->name('lupa-password.redirect');
});

// Force Change Password (harus di luar middleware password.changed agar bisa diakses)
Route::middleware('auth')->group(function () {
    Route::get('/password/force-change', [App\Http\Controllers\Auth\PasswordController::class, 'forceChange'])
        ->name('password.force-change');
    Route::post('/password/force-change', [App\Http\Controllers\Auth\PasswordController::class, 'forceChangeUpdate'])
        ->name('password.force-change.update');
});

// Public documents / Portal Dokumen (accessible by Guest and all authenticated users)
Route::prefix('public-documents')->name('public_documents.')->group(function () {
    Route::get('/', [PublicDocumentController::class, 'index'])->name('index');
    Route::get('/{document}', [PublicDocumentController::class, 'show'])->name('show');
    Route::get('/{document}/download', [PublicDocumentController::class, 'download'])
        ->middleware('throttle:30,1')
        ->name('download');
});

// Protected routes (auth required, not under /dashboard)
Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::post('/logout', [SsoController::class, 'logout'])->name('logout');
    Route::post('/switch-role', [\App\Http\Controllers\RoleSwitchController::class, 'switch'])->name('role.switch');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // All dashboard routes
    Route::prefix('dashboard')->group(function () {
        Route::get('sidang-ta-guides/{guide}/media', [SidangTaFormGuideController::class, 'media'])
            ->name('sidang-ta-guides.media');
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::get('sidang-ta-guides', [SidangTaFormGuideController::class, 'index'])
                ->middleware('permission:sidang_ta_form_guide_read')->name('sidang-ta-guides.index');
            Route::get('sidang-ta-guides/create', [SidangTaFormGuideController::class, 'create'])
                ->middleware('permission:sidang_ta_form_guide_create')->name('sidang-ta-guides.create');
            Route::post('sidang-ta-guides', [SidangTaFormGuideController::class, 'store'])
                ->middleware('permission:sidang_ta_form_guide_create')->name('sidang-ta-guides.store');
            Route::get('sidang-ta-guides/{guide}/edit', [SidangTaFormGuideController::class, 'edit'])
                ->middleware('permission:sidang_ta_form_guide_update')->name('sidang-ta-guides.edit');
            Route::put('sidang-ta-guides/{guide}', [SidangTaFormGuideController::class, 'update'])
                ->middleware('permission:sidang_ta_form_guide_update')->name('sidang-ta-guides.update');
            Route::patch('sidang-ta-guides/{guide}', [SidangTaFormGuideController::class, 'update'])
                ->middleware('permission:sidang_ta_form_guide_update');
            Route::delete('sidang-ta-guides/{guide}', [SidangTaFormGuideController::class, 'destroy'])
                ->middleware('permission:sidang_ta_form_guide_delete')->name('sidang-ta-guides.destroy');
        });

        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Mahasiswa (Students)
        Route::post('students/preview-cookies', [StudentController::class, 'previewCookies'])
            ->middleware(['permission:student_read', 'throttle:10,1'])
            ->name('students.preview-cookies');
        Route::post('students/import-cookies', [StudentController::class, 'importFromCookies'])
            ->middleware(['permission:student_create', 'throttle:5,1'])
            ->name('students.import-cookies');
        Route::post('students/scrape-portal', [StudentController::class, 'scrapePortal'])
            ->middleware(['permission:student_create', 'throttle:5,1'])
            ->name('students.scrape-portal');
        registerResourceWithPermissions('students', StudentController::class, 'student');

        // Dosen (Lecturers)
        Route::get('lecturers/export', [LecturerController::class, 'export'])
            ->middleware('permission:lecturer_read')
            ->name('lecturers.export');
        Route::get('lecturers/template', [LecturerController::class, 'downloadTemplate'])
            ->middleware('permission:lecturer_read')
            ->name('lecturers.template');
        Route::post('lecturers/import', [LecturerController::class, 'import'])
            ->middleware('permission:lecturer_create')
            ->name('lecturers.import');
        registerResourceWithPermissions('lecturers', LecturerController::class, 'lecturer');

        // Matakuliah (Courses)
        Route::post('courses/preview-cookies', [CourseController::class, 'previewCookies'])
            ->middleware(['permission:course_read', 'throttle:10,1'])
            ->name('courses.preview-cookies');
        Route::post('courses/import-cookies', [CourseController::class, 'importFromCookies'])
            ->middleware(['permission:course_create', 'throttle:5,1'])
            ->name('courses.import-cookies');
        Route::post('courses/scrape-portal', [CourseController::class, 'scrapePortal'])
            ->middleware(['permission:course_create', 'throttle:5,1'])
            ->name('courses.scrape-portal');
        Route::get('courses/export', [CourseController::class, 'export'])
            ->middleware('permission:course_read')
            ->name('courses.export');
        registerResourceWithPermissions('courses', CourseController::class, 'course');

        // Ruangan (Rooms)
        Route::get('rooms/export', [RoomController::class, 'export'])
            ->middleware('permission:room_read')
            ->name('rooms.export');
        Route::post('rooms/import', [RoomController::class, 'import'])
            ->middleware('permission:room_create')
            ->name('rooms.import');
        registerResourceWithPermissions('rooms', RoomController::class, 'room');

        // Modul Dokumen (Kelola Dokumen/CRUD folder+file: Superadmin & Admin)
        Route::group([], function () {
            // Kategori Dokumen
            registerResourceWithPermissions('document_categories', DocumentCategoryController::class, 'document_category');
            Route::post('document_categories/{document_category}/move', [DocumentCategoryController::class, 'move'])
                ->middleware(['permission:document_category_update', 'throttle:15,1'])
                ->name('document_categories.move');
            // Throttle folder creation
            Route::post('document_categories', [DocumentCategoryController::class, 'store'])
                ->middleware(['permission:document_category_create', 'throttle:15,1'])
                ->name('document_categories.store');

            // Dokumen
            Route::get('documents/{document}/download', [DocumentController::class, 'download'])
                ->middleware(['permission:document_read', 'throttle:60,1'])
                ->name('documents.download');
            Route::get('documents/folder/{documentCategory}', [DocumentController::class, 'folder'])
                ->middleware('permission:document_read')
                ->name('documents.folder');
            // Bulk actions
            Route::post('documents/bulk-delete', [DocumentController::class, 'bulkDelete'])
                ->middleware(['permission:document_delete', 'throttle:30,1'])
                ->name('documents.bulk-delete');
            Route::post('documents/bulk-download', [DocumentController::class, 'bulkDownload'])
                ->middleware(['permission:document_read', 'throttle:20,1'])
                ->name('documents.bulk-download');
            // Throttle document upload
            Route::post('documents', [DocumentController::class, 'store'])
                ->middleware(['permission:document_create', 'throttle:10,1'])
                ->name('documents.store');
            registerResourceWithPermissions('documents', DocumentController::class, 'document');
        });

        // IT Panel: Pengaturan Role, Akun Manual & Sinkronisasi.
        // Default hanya role Akademik (bypass via Gate::before). Role lain hanya dapat mengakses
        // jika Akademik memberi permission secara eksplisit lewat Edit Role. Tiap operasi dicek terpisah.
        Route::get('role/assign', [UserRoleController::class, 'create'])
            ->middleware('permission:role_assign_user')->name('user-role.create');
        Route::post('role/assign', [UserRoleController::class, 'store'])
            ->middleware('permission:role_assign_user')->name('user-role.store');
        Route::get('role', [RoleController::class, 'index'])
            ->middleware('permission:role_read')->name('role.index');
        Route::get('role/{role}/edit', [RoleController::class, 'edit'])
            ->middleware('permission:role_read')->name('role.edit');
        Route::match(['put', 'patch'], 'role/{role}', [RoleController::class, 'update'])
            ->middleware('permission:role_update')->name('role.update');
        Route::post('role/{role}/assign-user', [RoleController::class, 'assignUser'])
            ->middleware('permission:role_assign_user')->name('role.assign-user');
        Route::post('role/{role}/revoke-user', [RoleController::class, 'revokeUser'])
            ->middleware('permission:role_revoke_user')->name('role.revoke-user');

        // Kelola Akun Manual
        Route::get('manual-users', [ManualUserController::class, 'index'])
            ->middleware('permission:manual_user_read')->name('manual-users.index');
        Route::get('manual-users/create', [ManualUserController::class, 'create'])
            ->middleware('permission:manual_user_create')->name('manual-users.create');
        Route::post('manual-users', [ManualUserController::class, 'store'])
            ->middleware('permission:manual_user_create')->name('manual-users.store');
        Route::get('manual-users/{manual_user}/edit', [ManualUserController::class, 'edit'])
            ->middleware('permission:manual_user_update')->name('manual-users.edit');
        Route::match(['put', 'patch'], 'manual-users/{manual_user}', [ManualUserController::class, 'update'])
            ->middleware('permission:manual_user_update')->name('manual-users.update');
        Route::delete('manual-users/{manual_user}', [ManualUserController::class, 'destroy'])
            ->middleware('permission:manual_user_delete')->name('manual-users.destroy');
        // Reset password butuh DUA permission agar pemegang user_reset_password lama
        // (mis. admin/Pelaksana) tidak otomatis mendapat akses setelah gerbang role dilepas.
        Route::post('manual-users/{manual_user}/reset-password', [ManualUserController::class, 'resetPassword'])
            ->middleware(['permission:manual_user_update', 'permission:user_reset_password'])
            ->name('manual-users.reset-password');

        // Sinkronisasi
        Route::get('sync', [SyncController::class, 'index'])
            ->middleware('permission:sync_read')->name('sync.index');
        Route::get('sync/settings', [SyncController::class, 'settings'])
            ->middleware('permission:sync_read')->name('sync.settings');
        Route::put('sync/settings', [SyncController::class, 'saveSettings'])
            ->middleware('permission:sync_update')->name('sync.save-settings');
        Route::get('sync/status', [SyncController::class, 'status'])
            ->middleware('permission:sync_read')->name('sync.status');
        Route::get('sync/data-stats', [SyncController::class, 'dataStats'])
            ->middleware('permission:sync_read')->name('sync.data-stats');
        Route::post('sync/scrape-portal', [SyncController::class, 'scrapePortal'])
            ->middleware(['permission:sync_execute', 'throttle:5,1'])->name('sync.scrape-portal');
        Route::post('sync/dispatch', [SyncController::class, 'dispatch'])
            ->middleware(['permission:sync_execute', 'throttle:5,1'])->name('sync.dispatch');
        Route::post('sync/import-json', [SyncController::class, 'importJson'])
            ->middleware('permission:sync_execute')->name('sync.import-json');
        Route::post('sync/import-academic-profile', [SyncController::class, 'importAcademicProfile'])
            ->middleware('permission:sync_execute')->name('sync.import-academic-profile');

        // KRS
        Route::post('krs/preview-cookies', [KrsController::class, 'previewCookies'])
            ->middleware(['permission:student_read', 'throttle:10,1'])
            ->name('krs.preview-cookies');
        Route::post('krs/import-cookies', [KrsController::class, 'importFromCookies'])
            ->middleware(['permission:student_create', 'throttle:5,1'])
            ->name('krs.import-cookies');
        Route::resource('krs', KrsController::class)
            ->middleware('permission:student_read')
            ->only(['index']);

        // Student Courses (KRS Module)
        Route::get('student_courses/template', [StudentCourseController::class, 'downloadTemplate'])
            ->middleware('permission:student_read')
            ->name('student_courses.template');
        Route::post('student_courses/import', [StudentCourseController::class, 'import'])
            ->middleware('permission:student_create')
            ->name('student_courses.import');
        Route::resource('student_courses', StudentCourseController::class)
            ->middleware('permission:student_read')
            ->only(['index', 'show']);

        // Jadwal Ujian (Exam Schedules)
        Route::get('exam_schedules/course-data', [ExamScheduleController::class, 'getCourseData'])
            ->middleware('permission:jadwal_read')
            ->name('exam_schedules.course-data');
        Route::get('exam_schedules/check-availability', [ExamScheduleController::class, 'checkAvailability'])
            ->middleware('permission:jadwal_read')
            ->name('exam_schedules.check-availability');
        Route::get('exam_schedules/template', [ExamScheduleController::class, 'downloadTemplate'])
            ->middleware('permission:jadwal_read')
            ->name('exam_schedules.template');
        Route::post('exam_schedules/import', [ExamScheduleController::class, 'import'])
            ->middleware('permission:jadwal_create')
            ->name('exam_schedules.import');
        Route::get('exam_schedules/{exam_schedule}/export-pdf', [ExamScheduleController::class, 'exportPdf'])
            ->middleware('permission:jadwal_read')
            ->name('exam_schedules.export-pdf');
        Route::get('exam-schedules/{exam_schedule}/export-pdf', [ExamScheduleController::class, 'exportPdf'])
            ->middleware('permission:jadwal_read')
            ->name('exam-schedules.export-pdf');
        registerResourceWithPermissions('exam_schedules', ExamScheduleController::class, 'jadwal', 'exam_schedule');

        // Autocomplete search
        Route::get('api/search/courses', [SearchController::class, 'courses'])->name('api.search.courses');

        // Graduation Registration
        Route::get('graduation-registrations', [GraduationRegistrationController::class, 'index'])
            ->middleware('permission:graduation_registration_read')
            ->name('graduation-registrations.index');
        Route::get('graduation-registrations/create', [GraduationRegistrationController::class, 'create'])
            ->middleware('permission:graduation_registration_create')
            ->name('graduation-registrations.create');
        Route::post('graduation-registrations', [GraduationRegistrationController::class, 'store'])
            ->middleware('permission:graduation_registration_create')
            ->name('graduation-registrations.store');
        Route::get('graduation-registrations/{graduation_registration}', [GraduationRegistrationController::class, 'show'])
            ->middleware('permission:graduation_registration_read')
            ->name('graduation-registrations.show');
        Route::get('graduation-registrations/{graduation_registration}/edit', [GraduationRegistrationController::class, 'edit'])
            ->middleware('permission:graduation_registration_update')
            ->name('graduation-registrations.edit');
        Route::put('graduation-registrations/{graduation_registration}', [GraduationRegistrationController::class, 'update'])
            ->middleware('permission:graduation_registration_update')
            ->name('graduation-registrations.update');
        // Sync campus data endpoint
        Route::post('graduation-registrations/{graduation_registration}/sync-campus-data', [GraduationRegistrationController::class, 'syncCampusData'])
            ->middleware('permission:graduation_registration_update')
            ->name('graduation-registrations.sync-campus-data');

        // Pendaftaran Sidang Tugas Akhir
        Route::get('sidang-ta-registrations', [SidangTaRegistrationController::class, 'index'])
            ->middleware('permission:sidang_ta_registration_read')->name('sidang-ta-registrations.index');
        Route::get('sidang-ta-registrations/create', [SidangTaRegistrationController::class, 'create'])
            ->middleware('permission:sidang_ta_registration_create')->name('sidang-ta-registrations.create');
        Route::post('sidang-ta-registrations', [SidangTaRegistrationController::class, 'store'])
            ->middleware('permission:sidang_ta_registration_create')->name('sidang-ta-registrations.store');
        Route::get('sidang-ta-registrations/{sidang_ta_registration}/edit', [SidangTaRegistrationController::class, 'edit'])
            ->middleware('permission:sidang_ta_registration_update')->name('sidang-ta-registrations.edit');
        Route::put('sidang-ta-registrations/{sidang_ta_registration}', [SidangTaRegistrationController::class, 'update'])
            ->middleware('permission:sidang_ta_registration_update')->name('sidang-ta-registrations.update');
        Route::get('sidang-ta-registrations/{sidang_ta_registration}/history', [SidangTaRegistrationController::class, 'history'])
            ->middleware('permission:sidang_ta_registration_read')->name('sidang-ta-registrations.history');
        Route::get('sidang-ta-registrations/{sidang_ta_registration}', [SidangTaRegistrationController::class, 'show'])
            ->middleware('permission:sidang_ta_registration_read')->name('sidang-ta-registrations.show');
        Route::get('sidang-ta-registration-documents/{document}/download', [SidangTaRegistrationController::class, 'downloadDocument'])
            ->middleware('permission:sidang_ta_registration_read|sidang_ta_registration_review')->name('sidang-ta-registration-documents.download');

        Route::post('sidang-ta-drafts', [SidangTaDraftController::class, 'store'])
            ->middleware('permission:sidang_ta_registration_create')->name('sidang-ta-drafts.store');
        Route::get('sidang-ta-drafts/{draft}/edit', [SidangTaDraftController::class, 'edit'])
            ->middleware('permission:sidang_ta_registration_read')->name('sidang-ta-drafts.edit');
        Route::post('sidang-ta-drafts/{draft}/steps/{step}', [SidangTaDraftController::class, 'saveStep'])
            ->where('step', '[1-4]')->middleware('permission:sidang_ta_registration_create|sidang_ta_registration_update')
            ->name('sidang-ta-drafts.steps.store');
        Route::get('sidang-ta-draft-documents/{document}/preview',
            [SidangTaDraftController::class, 'previewDocument'])
            ->middleware('permission:sidang_ta_registration_read')
            ->name('sidang-ta-draft-documents.preview');
        Route::get('sidang-ta-registration-documents/{document}/preview',
            [SidangTaRegistrationController::class, 'previewDocument'])
            ->middleware('permission:sidang_ta_registration_read|sidang_ta_registration_review')
            ->name('sidang-ta-registration-documents.preview');
        Route::get('sidang-ta-draft-documents/{document}/download',
            [SidangTaDraftController::class, 'downloadDocument'])
            ->middleware('permission:sidang_ta_registration_read')
            ->name('sidang-ta-draft-documents.download');
        Route::post('sidang-ta-registrations/{registration}/draft',
            [SidangTaDraftController::class, 'startRevision'])
            ->middleware('permission:sidang_ta_registration_update')->name('sidang-ta-drafts.revision');
        Route::delete('sidang-ta-drafts/{draft}',
            [SidangTaDraftController::class, 'destroy'])
            ->middleware('permission:sidang_ta_registration_create|sidang_ta_registration_update')
            ->name('sidang-ta-drafts.destroy');
        Route::post('sidang-ta-drafts/{draft}/review',
            [SidangTaDraftController::class, 'review'])
            ->middleware('permission:sidang_ta_registration_create|sidang_ta_registration_update')
            ->name('sidang-ta-drafts.review');
        Route::get('sidang-ta-drafts/{draft}/review',
            [SidangTaDraftController::class, 'preview'])
            ->middleware('permission:sidang_ta_registration_read')->name('sidang-ta-drafts.preview');

        Route::prefix('admin')->name('admin.')->group(function () {
            Route::get('sidang-ta-registrations', [SidangTaRegistrationController::class, 'adminIndex'])
                ->middleware('permission:sidang_ta_registration_review|sidang_ta_period_read')
                ->name('sidang-ta-registrations.index');
            Route::get('sidang-ta-registrations/export', [SidangTaRegistrationController::class, 'exportExcel'])
                ->middleware('permission:sidang_ta_registration_review')
                ->name('sidang-ta-registrations.export');
            Route::get('sidang-ta-registrations/{sidang_ta_registration}', [SidangTaRegistrationController::class, 'adminShow'])
                ->middleware('permission:sidang_ta_registration_review')
                ->name('sidang-ta-registrations.show');
            Route::post('sidang-ta-registrations/{sidang_ta_registration}/review', [SidangTaRegistrationController::class, 'review'])
                ->middleware('permission:sidang_ta_registration_review')
                ->name('sidang-ta-registrations.review');
            Route::post('sidang-ta-registrations/{sidang_ta_registration}/documents/{document}/review',
                [SidangTaRegistrationController::class, 'reviewDocument'])
                ->middleware('permission:sidang_ta_registration_review')
                ->name('sidang-ta-registrations.documents.review');
        });

        Route::prefix('admin')->name('admin.')->group(function () {
            Route::get('sidang-ta-periods', [SidangTaRegistrationController::class, 'managePeriods'])
                ->middleware('permission:sidang_ta_period_read')->name('sidang-ta-periods.index');
            Route::post('sidang-ta-periods', [SidangTaRegistrationController::class, 'storePeriod'])
                ->middleware('permission:sidang_ta_period_create')->name('sidang-ta-periods.store');
            Route::put('sidang-ta-periods/{period}', [SidangTaRegistrationController::class, 'updatePeriod'])
                ->middleware('permission:sidang_ta_period_update')->name('sidang-ta-periods.update');
            Route::post('sidang-ta-periods/{period}/activate', [SidangTaRegistrationController::class, 'setActivePeriod'])
                ->middleware('permission:sidang_ta_period_update')->name('sidang-ta-periods.activate');
            Route::delete('sidang-ta-periods/{period}', [SidangTaRegistrationController::class, 'deletePeriod'])
                ->middleware('permission:sidang_ta_period_delete')->name('sidang-ta-periods.destroy');
        });

        // Graduation Registration
        Route::middleware('permission:graduation_registration_review')->prefix('admin')->name('admin.')->group(function () {
            Route::get('graduation-registrations', [GraduationRegistrationController::class, 'adminIndex'])
                ->name('graduation-registrations.index');
                // Export Data Excel
            Route::get('graduation-registrations-export', [GraduationRegistrationController::class, 'exportExcel'])->name('graduation-registrations.export');
            Route::get('graduation-registrations/{graduation_registration}', [GraduationRegistrationController::class, 'adminShow'])
                ->name('graduation-registrations.show');
            Route::post('graduation-registrations/{graduation_registration}/review', [GraduationRegistrationController::class, 'review'])
                ->name('graduation-registrations.review');
            Route::delete('graduation-registrations/{graduation_registration}', [GraduationRegistrationController::class, 'adminDestroy'])
                ->middleware('permission:graduation_registration_delete')
                ->name('graduation-registrations.destroy');

            // Graduation Period Settings
            Route::post('graduation-periods', [\App\Http\Controllers\Admin\GraduationPeriodController::class, 'store'])
                ->name('graduation-periods.store');
            Route::put('graduation-periods/{period}', [\App\Http\Controllers\Admin\GraduationPeriodController::class, 'update'])
                ->name('graduation-periods.update');
            Route::post('graduation-periods/{period}/set-active', [\App\Http\Controllers\Admin\GraduationPeriodController::class, 'setActive'])
                ->name('graduation-periods.set-active');
            Route::delete('graduation-periods/{period}', [\App\Http\Controllers\Admin\GraduationPeriodController::class, 'destroy'])
                ->name('graduation-periods.destroy');

            // Module Permission Settings (Super Admin only)
            Route::get('module-permissions', [\App\Http\Controllers\Admin\ModulePermissionController::class, 'index'])
                ->middleware('permission:role_read')
                ->name('module-permissions.index');
            Route::put('module-permissions/{module}', [\App\Http\Controllers\Admin\ModulePermissionController::class, 'update'])
                ->middleware('permission:role_update')
                ->name('module-permissions.update');
            Route::post('module-permissions/{module}/reset', [\App\Http\Controllers\Admin\ModulePermissionController::class, 'reset'])
                ->middleware('permission:role_update')
                ->name('module-permissions.reset');
        });

        // Graduation Registration Document download, preview & delete
        Route::get('graduation-registration-documents/{document}/preview', [GraduationRegistrationDocumentController::class, 'preview'])
            ->middleware('permission:graduation_registration_read')
            ->name('graduation-registration-documents.preview');
        Route::get('graduation-registration-documents/{document}/download', [GraduationRegistrationDocumentController::class, 'download'])
            ->middleware('permission:graduation_registration_read')
            ->name('graduation-registration-documents.download');
        Route::delete('graduation-registration-documents/{document}', [GraduationRegistrationDocumentController::class, 'destroy'])
            ->middleware('permission:graduation_registration_update')
            ->name('graduation-registration-documents.destroy');
        Route::post('graduation-registration-documents/{document}/review', [GraduationRegistrationDocumentController::class, 'review'])
            ->middleware('permission:graduation_registration_review')
            ->name('graduation-registration-documents.review');

        // Grade Appeal / Banding Nilai (Student-facing)
        Route::get('grade-appeals', [GradeAppealController::class, 'index'])
            ->middleware('permission:grade_appeal_read')
            ->name('grade-appeals.index');
        Route::get('grade-appeals/create', [GradeAppealController::class, 'create'])
            ->middleware('permission:grade_appeal_create')
            ->name('grade-appeals.create');
        Route::post('grade-appeals', [GradeAppealController::class, 'store'])
            ->middleware('permission:grade_appeal_create')
            ->name('grade-appeals.store');
        Route::get('grade-appeals/{grade_appeal}', [GradeAppealController::class, 'show'])
            ->middleware('permission:grade_appeal_read')
            ->name('grade-appeals.show');

        // Grade Appeal / Banding Nilai (Admin/Staff-facing)
        Route::middleware('permission:grade_appeal_review')->prefix('admin')->name('admin.')->group(function () {
            Route::get('grade-appeals', [GradeAppealController::class, 'adminIndex'])
                ->name('grade-appeals.index');
            Route::get('grade-appeals/{grade_appeal}', [GradeAppealController::class, 'adminShow'])
                ->name('grade-appeals.show');
            Route::post('grade-appeals/{grade_appeal}/review', [GradeAppealController::class, 'review'])
                ->name('grade-appeals.review');

            // Grade Appeal Period Management
            Route::post('grade-appeal-periods', [\App\Http\Controllers\Admin\GradeAppealPeriodController::class, 'store'])
                ->name('grade-appeal-periods.store');
            Route::put('grade-appeal-periods/{period}', [\App\Http\Controllers\Admin\GradeAppealPeriodController::class, 'update'])
                ->name('grade-appeal-periods.update');
            Route::post('grade-appeal-periods/{period}/set-active', [\App\Http\Controllers\Admin\GradeAppealPeriodController::class, 'setActive'])
                ->name('grade-appeal-periods.set-active');
            Route::delete('grade-appeal-periods/{period}', [\App\Http\Controllers\Admin\GradeAppealPeriodController::class, 'destroy'])
                ->name('grade-appeal-periods.destroy');
        });
    });

    // Chatbot Proxy Route
    Route::post('chatbot/chat', function (\Illuminate\Http\Request $request) {
        $question = $request->input('question');
        if (!$question) {
            return response()->json(['error' => 'Pertanyaan tidak boleh kosong'], 400);
        }

        $history = [];
        $rawHistory = $request->input('history', []);
        if (is_array($rawHistory)) {
            $rawHistory = array_slice($rawHistory, 0, 10);
            foreach ($rawHistory as $item) {
                if (
                    is_array($item)
                    && isset($item['role'], $item['content'])
                    && is_string($item['role'])
                    && is_string($item['content'])
                    && in_array($item['role'], ['user', 'assistant'], true)
                ) {
                    $history[] = [
                        'role' => $item['role'],
                        'content' => $item['content'],
                    ];
                }
            }
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-API-Key' => env('CHATBOT_API_KEY', 'ganti-dengan-api-key-yang-aman')
            ])->timeout(65)->post(env('CHATBOT_API_URL', 'http://127.0.0.1:8005') . '/chat', [
                'question' => $question,
                'history'  => $history,
            ]);

            if ($response->failed()) {
                return response()->json([
                    'answer' => 'Maaf, terjadi masalah pada layanan asisten akademik. Silakan coba beberapa saat lagi.',
                    'sources' => [],
                    'chunks_used' => 0
                ], 200);
            }

            $data = $response->json();
            $answer = $data['answer'] ?? '';

            $answer = preg_replace('/(?:\n+\s*)*(?:Sumber|Rujukan|📄 Sumber|📄 Rujukan):\s*.*$/i', '', $answer);
            $answer = trim($answer);

            $enrichedSources = [];
            $rawSources = $data['sources'] ?? [];
            foreach ($rawSources as $srcFilename) {
                $doc = \App\Models\Document::where('file_path', 'like', "%{$srcFilename}")->first();
                if ($doc) {
                    $enrichedSources[] = [
                        'filename' => $srcFilename,
                        'id' => $doc->id,
                        'title' => $doc->title
                    ];
                } else {
                    $cleanTitle = preg_replace('/^\d{14}_/', '', $srcFilename);
                    $cleanTitle = preg_replace('/^(PU|SK|SE|BA)_/i', '', $cleanTitle);
                    $cleanTitle = pathinfo($cleanTitle, PATHINFO_FILENAME);
                    $cleanTitle = str_replace(['_', '-'], ' ', $cleanTitle);
                    $cleanTitle = mb_strtolower($cleanTitle);
                    $cleanTitle = \Illuminate\Support\Str::title($cleanTitle);
                    $cleanTitle = preg_replace_callback(
                        '/\b(Dan|Atau|Di|Ke|Dari|Yang|Untuk|Dengan|Pada|Serta|Versi)\b/',
                        fn($m) => mb_strtolower($m[0]),
                        $cleanTitle
                    );
                    $enrichedSources[] = [
                        'filename' => $srcFilename,
                        'id' => null,
                        'title' => $cleanTitle
                    ];
                }
            }

            return response()->json([
                'answer' => $answer,
                'sources' => $enrichedSources,
                'interaction_id' => $data['interaction_id'] ?? null,
                'chunks_used' => $data['chunks_used'] ?? 0
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'answer' => 'Maaf, koneksi ke asisten akademik terputus. Silakan hubungi administrator.',
                'sources' => [],
                'chunks_used' => 0
            ], 200);
        }
    })->name('chatbot.chat');
});

// Visitor / Student Queue Routes
Route::prefix('queue')->name('queue.')->group(function () {
    Route::get('/take', [\App\Http\Controllers\QueueController::class, 'take'])->name('take');
    Route::get('/lookup-student', [\App\Http\Controllers\QueueController::class, 'lookupStudent'])
        ->middleware('throttle:60,1')
        ->name('lookup-student');
    Route::post('/take', [\App\Http\Controllers\QueueController::class, 'store'])
        ->middleware('throttle:queue-take')
        ->name('store');
    Route::get('/status', [\App\Http\Controllers\QueueController::class, 'status'])->name('status');
    Route::get('/status/json', [\App\Http\Controllers\QueueController::class, 'statusJson'])
        ->middleware('throttle:queue-status')
        ->name('status.json');
    // Monitor publik — tanpa login
    Route::get('/display', [\App\Http\Controllers\QueueController::class, 'display'])->name('display');
    Route::get('/display/json', [\App\Http\Controllers\QueueController::class, 'displayJson'])
        ->middleware('throttle:120,1')
        ->name('display.json');
});

// Admin Queue Routes
Route::middleware(['auth'])->prefix('admin/queue')->name('admin.queue.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\QueueController::class, 'index'])->middleware('permission:queue_ticket_read')->name('index');
    Route::get('/json', [\App\Http\Controllers\Admin\QueueController::class, 'indexJson'])->middleware('permission:queue_ticket_read')->name('json');
    Route::get('/stats', [\App\Http\Controllers\Admin\QueueController::class, 'stats'])->middleware('permission:queue_ticket_read')->name('stats');
    Route::post('/claim', [\App\Http\Controllers\Admin\QueueController::class, 'claim'])->middleware('permission:queue_ticket_update')->name('claim');
    Route::post('/release', [\App\Http\Controllers\Admin\QueueController::class, 'release'])->middleware('permission:queue_ticket_update')->name('release');
    Route::post('/next', [\App\Http\Controllers\Admin\QueueController::class, 'next'])->middleware('permission:queue_ticket_update')->name('next');
    Route::post('/skip', [\App\Http\Controllers\Admin\QueueController::class, 'skip'])->middleware('permission:queue_ticket_update')->name('skip');
    Route::post('/back', [\App\Http\Controllers\Admin\QueueController::class, 'back'])->middleware('permission:queue_ticket_update')->name('back');

    // Riwayat, Detail, dan Export Antrian
    Route::get('/history', [\App\Http\Controllers\Admin\QueueHistoryController::class, 'index'])->middleware('permission:queue_ticket_read')->name('history');
    Route::get('/history/export', [\App\Http\Controllers\Admin\QueueHistoryController::class, 'exportCsv'])->middleware(['permission:queue_ticket_read', 'throttle:10,1'])->name('history.export');
    Route::get('/history/exports/{filename}', [\App\Http\Controllers\Admin\QueueHistoryController::class, 'downloadExport'])->middleware('permission:queue_ticket_read')->name('history.download-export');
    Route::get('/history/{id}', [\App\Http\Controllers\Admin\QueueHistoryController::class, 'show'])->middleware('permission:queue_ticket_read')->name('history.show');
});

// Alias matching /dashboard/admin/queue/
Route::post('/dashboard/admin/queue/skip', [\App\Http\Controllers\Admin\QueueController::class, 'skip'])
    ->middleware(['auth', 'permission:queue_ticket_update']);
Route::get('/dashboard/admin/queue/history', [\App\Http\Controllers\Admin\QueueHistoryController::class, 'index'])
    ->middleware(['auth', 'permission:queue_ticket_read']);
Route::get('/dashboard/admin/queue/history/export', [\App\Http\Controllers\Admin\QueueHistoryController::class, 'exportCsv'])
    ->middleware(['auth', 'permission:queue_ticket_read', 'throttle:10,1']);
Route::get('/dashboard/admin/queue/history/exports/{filename}', [\App\Http\Controllers\Admin\QueueHistoryController::class, 'downloadExport'])
    ->middleware(['auth', 'permission:queue_ticket_read']);
Route::get('/dashboard/admin/queue/history/{id}', [\App\Http\Controllers\Admin\QueueHistoryController::class, 'show'])
    ->middleware(['auth', 'permission:queue_ticket_read']);

require __DIR__ . '/auth.php';
