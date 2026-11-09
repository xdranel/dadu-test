# Pemisahan Jadwal Sidang TA — Implementation Plan

> **For agentic workers:** use superpowers:executing-plans inline; primary owns schedule/UI and verification, bounded independent history helper, one whole-change independent final review.

**Goal:** Pisahkan tenggat pendaftaran dan verifikasi/revisi, tampilkan jadwal/countdown bagi mahasiswa/petugas, serta rapikan aksi revisi dan timeline mahasiswa.
**Architecture:** Add nullable revision_open_at/revision_close_at to the existing period ID. Shared period predicates drive deadline, scheduler selection+locked rechecks, and Blade schedules. Preserve existing stack and submitted data.
**Tech Stack:** Laravel13, Blade/Alpine, existing MySQL8.4 test DB, Vite6.
**Spec:** docs/84-rancangan-jadwal-pendaftaran-dan-revisi-sidang-ta.md

## Global constraints
- Registration open_at/close_at and active flag govern new registration only.
- Explicit revision schedule may overlap registration; enforce end>start and end>=registration close so last registrants retain a review interval.
- Existing periods lacking either revision date do not expire revisions or purge revision drafts; show schedule not configured. New/updated period forms require both dates.
- Global revision end governs start/save/upload/submit and needs_review auto-rejection; revision start governs opening write operations. Final records/documents remain stored.
- Initial drafts purge after registration close; revision drafts purge only after configured revision close; strict now>end, not equality. Lock/recheck selection and reference-protected file cleanup remain.
- Do not fresh/seed/import app DB, mutate SSO/accounts, push/deploy or change postponedproduction permissions. User owns commit/push.

## Review focus
1. Registration closed but revision open: updates/submit work, initial registration fails, cleanup keeps revision drafts.
2. Missing legacy schedule: no automatic revision rejection/deletion and existing IDs/snapshots unchanged.
3. Scheduler sees candidate then deadline is extended/status consumed: locked recheck skips stale deletion; never delete borrowed/final files.
4. Before revision start and exact end: write gates match server and countdown; inactive registration does not suppress revision rights.
5. Student sees own actual period after registration closes; history keeps reasons, hides actors, limits owner access.

### Task1 — Schema, period management and deadline
**Files:** migration new, app/Models/SidangTaPeriod.php, app/Support/SidangTaRevisionDeadline.php, SidangTaRegistrationController period validation, tests/Feature/SidangTaRevisionWindowTest.php, existing SidangTaPeriodTest/RevisionDeadlineTest.
**Interfaces:** Period revisionIsConfigured():bool, revisionIsOpen():bool, revisionHasExpired():bool, scopeRevisionExpired(Builder):Builder; Deadline hasNotStarted(registration,lockPeriod=false):bool/hasExpired and ensureOpen.
- [x] Write boundary+legacy+overlap+validation+existingperiod ID tests; observe RED.
- [x] Add nullable columns/casts/predicates; update validations and deadline helper; observe GREEN.

### Task2 — Draft lifecycle and scheduler
**Files:** SidangTaExpiredDraftCleanup.php, SidangTaRevisionExpiry.php, SidangTaDraftController.php, tests/Feature/SidangTaExpiredDraftCleanupTest.php and RevisionWindowTest.
- [x] RED: initial close vs revision close, legacy missing, exact boundary, fresh extension checks and final-file protection.
- [x] Split candidate queries and locked rechecks; common fresh predicates; update automatic-rejection reason and UIcontext flags. GREEN affected tests.

### Task3 — Schedule UI, countdown and action buttons
**Files:** shared period-schedule/countdown partials; admin periods/index/show, student index/wizard/review/show/revision-start; resources/js/sidang-ta-wizard.js; Js tests and schedule UItest.
- [x] RED: fields grouped, student dates after registration closed, numeric countdown values/transitions, no duplicate active button in revision.
- [x] Shared date/countdown partial; wizard selected mapping updates both windows; revision_not_started disables writing actions; keep view access. GREEN render/JS/build.

### Task4 — Student history and integration
**Files:** SidangTaHistoryPresenter/historyview/HistoryTest via bounded helper; docs84/85 and results86.
- [x] RED rejected event with reason+no actor, owner-only; correct needs_review navigation test.
- [x] GREEN history, then run all related PHP, fullsuite compared to baseline83, JS, lint and Vitebuild; independentreview; address materialfindings withtests.
- [x] Hash-guarded integration into originaldevelopment and Dockerrepo; preserve4intentionalconfigdiffs and privatefixtures; no push/deploy. Document update/migration and how to edit existingperiod before registration closes.
