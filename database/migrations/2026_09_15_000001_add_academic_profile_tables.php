<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Perubahan schema:
     * 1. courses: drop unique course_code → composite unique (academic_year, semester_term, course_code)
     * 2. student_courses: add unique composite (student_number, academic_year, semester, course_code)
     * 3. 9 tabel baru untuk data profil akademik gabungan (iGracias + BASILA)
     */
    public function up(): void
    {
        // ─── 1. COURSES: Ubah unique key ke composite ─────────────────────
        // course_code saat ini punya unique index + FK dari 4 tabel.
        // Drop FK child tables lalu ubah unique course_code menjadi composite unique.
        // CATATAN: FK dari child tables (student_courses, student_attendances, student_grades, course_lecturers)
        // tidak dapat di-readd ke courses.course_code karena MySQL mewajibkan kolom target FK harus UNIQUE.
        // Karena courses kini composite unique (academic_year, semester_term, course_code), relasi antar tabel
        // mengandalkan plain index 'course_code' untuk performa query dan Eloquent relationships.

        $dropFkIfExists = function (string $table, string $column) {
            if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
                return;
            }
            $fks = Schema::getForeignKeys($table);
            foreach ($fks as $fk) {
                if (in_array($column, $fk['columns'] ?? [])) {
                    Schema::table($table, function (Blueprint $tableBlueprint) use ($fk) {
                        $tableBlueprint->dropForeign($fk['name']);
                    });
                }
            }
        };

        // Drop FK constraints dari child tables yang reference courses.course_code (jika ada)
        $dropFkIfExists('student_courses', 'course_code');
        $dropFkIfExists('student_attendances', 'course_code');
        $dropFkIfExists('student_grades', 'course_code');
        $dropFkIfExists('course_lecturers', 'course_code');

        // Drop unique course_code jika masih ada
        if (Schema::hasIndex('courses', 'courses_course_code_unique')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropUnique('courses_course_code_unique');
            });
        }

        // Add composite unique jika belum ada
        if (!Schema::hasIndex('courses', 'courses_composite_unique')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->unique(['academic_year', 'semester_term', 'course_code'], 'courses_composite_unique');
            });
        }

        // Add plain index pada course_code supaya pencarian/join child tables tetap cepat
        if (!Schema::hasIndex('courses', 'courses_course_code_index')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->index('course_code', 'courses_course_code_index');
            });
        }

        // ─── 2. STUDENT_COURSES: Naikkan index biasa ke UNIQUE composite ──
        if (Schema::hasIndex('student_courses', ['student_number', 'academic_year', 'semester'])) {
            Schema::table('student_courses', function (Blueprint $table) {
                $table->dropIndex(['student_number', 'academic_year', 'semester']);
            });
        }

        if (!Schema::hasIndex('student_courses', 'student_courses_composite_unique')) {
            Schema::table('student_courses', function (Blueprint $table) {
                $table->unique(
                    ['student_number', 'academic_year', 'semester', 'course_code'],
                    'student_courses_composite_unique'
                );
            });
        }

        // ─── 3. TABEL BARU: student_final_projects ────────────────────────
        Schema::create('student_final_projects', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 15);
            $table->string('advisor_1')->nullable();       // pembimbing 1
            $table->string('advisor_2')->nullable();       // pembimbing 2
            $table->string('examiner_1')->nullable();      // penguji 1
            $table->string('examiner_2')->nullable();      // penguji 2
            $table->date('exam_date')->nullable();         // tanggal sidang
            $table->string('exam_time', 20)->nullable();   // waktu sidang
            $table->string('exam_location')->nullable();   // tempat sidang
            $table->timestamps();

            $table->foreign('student_number')
                ->references('student_number')->on('students')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->unique('student_number');
        });

        // ─── 4. TABEL BARU: student_taks ──────────────────────────────────
        Schema::create('student_taks', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 15);
            $table->decimal('total_points', 8, 2)->nullable(); // total poin TAK
            $table->timestamps();

            $table->foreign('student_number')
                ->references('student_number')->on('students')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->unique('student_number');
        });

        // ─── 5. TABEL BARU: student_skpis (placeholder) ──────────────────
        Schema::create('student_skpis', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 15);
            $table->json('raw_data')->nullable(); // payload asli, struktur belum pasti
            $table->timestamps();

            $table->foreign('student_number')
                ->references('student_number')->on('students')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->unique('student_number');
        });

        // ─── 6. TABEL BARU: student_sidang_tingkats ──────────────────────
        Schema::create('student_sidang_tingkats', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 15);
            $table->boolean('tingkat_1')->default(false);
            $table->boolean('tingkat_2')->default(false);
            $table->boolean('tingkat_3')->default(false);
            $table->boolean('tingkat_4')->default(false);
            $table->timestamps();

            $table->foreign('student_number')
                ->references('student_number')->on('students')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->unique('student_number');
        });

        // ─── 7. TABEL BARU: student_basila_approvals ─────────────────────
        Schema::create('student_basila_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 15);
            $table->boolean('photo_approval')->default(false);
            $table->timestamps();

            $table->foreign('student_number')
                ->references('student_number')->on('students')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->unique('student_number');
        });

        // ─── 8. TABEL BARU: student_final_project_grades ─────────────────
        Schema::create('student_final_project_grades', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 15);
            $table->string('grade', 5)->nullable(); // A, AB, B, BC, C, dst
            $table->timestamps();

            $table->foreign('student_number')
                ->references('student_number')->on('students')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->unique('student_number');
        });

        // ─── 9. TABEL BARU: student_course_issues ────────────────────────
        Schema::create('student_course_issues', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 15);
            $table->json('retaking_courses')->nullable();   // MK mengulang (array kode MK)
            $table->json('failed_courses')->nullable();     // MK belum lulus (array kode MK)
            $table->unsignedSmallInteger('credits_passed')->nullable();     // SKS lulus
            $table->unsignedSmallInteger('credits_remaining')->nullable();  // SKS belum lulus
            $table->timestamps();

            $table->foreign('student_number')
                ->references('student_number')->on('students')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->unique('student_number');
        });

        // ─── 10. TABEL BARU: student_graduation_payments (placeholder) ───
        // Sumber: file Excel — format/kolom belum ada contohnya
        Schema::create('student_graduation_payments', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 15);
            $table->string('status', 50)->nullable();    // TODO: sesuaikan begitu ada contoh file
            $table->json('raw_data')->nullable();         // TODO: sesuaikan begitu ada contoh file
            $table->timestamps();

            $table->foreign('student_number')
                ->references('student_number')->on('students')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->unique('student_number');
        });

        // ─── 11. TABEL BARU: student_bpp_payments ────────────────────────
        // Sumber: field `status_keuangan` di JSON iGracias+BASILA
        Schema::create('student_bpp_payments', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 15);
            $table->string('status', 100)->nullable(); // mapping dari status_keuangan
            $table->timestamps();

            $table->foreign('student_number')
                ->references('student_number')->on('students')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->unique('student_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop tabel baru (urutan terbalik)
        Schema::dropIfExists('student_bpp_payments');
        Schema::dropIfExists('student_graduation_payments');
        Schema::dropIfExists('student_course_issues');
        Schema::dropIfExists('student_final_project_grades');
        Schema::dropIfExists('student_basila_approvals');
        Schema::dropIfExists('student_sidang_tingkats');
        Schema::dropIfExists('student_skpis');
        Schema::dropIfExists('student_taks');
        Schema::dropIfExists('student_final_projects');

        // Kembalikan student_courses: drop unique composite, kembalikan index biasa
        if (Schema::hasIndex('student_courses', 'student_courses_composite_unique')) {
            Schema::table('student_courses', function (Blueprint $table) {
                $table->dropUnique('student_courses_composite_unique');
            });
        }
        if (!Schema::hasIndex('student_courses', ['student_number', 'academic_year', 'semester'])) {
            Schema::table('student_courses', function (Blueprint $table) {
                $table->index(['student_number', 'academic_year', 'semester']);
            });
        }

        // Kembalikan courses: drop composite unique + plain index, kembalikan unique course_code
        if (Schema::hasIndex('courses', 'courses_course_code_index')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropIndex('courses_course_code_index');
            });
        }
        if (Schema::hasIndex('courses', 'courses_composite_unique')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropUnique('courses_composite_unique');
            });
        }
        if (!Schema::hasIndex('courses', 'courses_course_code_unique')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->unique('course_code');
            });
        }

        // Re-add FK dari child tables (hanya setelah courses.course_code kembali UNIQUE)
        $addFkIfNotExists = function (string $table, string $fkName, \Closure $callback) {
            if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
                return;
            }
            $fks = collect(Schema::getForeignKeys($table))->pluck('name')->all();
            if (!in_array($fkName, $fks)) {
                Schema::table($table, $callback);
            }
        };

        $addFkIfNotExists('student_courses', 'student_courses_course_code_foreign', function (Blueprint $table) {
            $table->foreign('course_code')
                ->references('course_code')->on('courses')
                ->restrictOnDelete()->cascadeOnUpdate();
        });
        $addFkIfNotExists('student_attendances', 'student_attendances_course_code_foreign', function (Blueprint $table) {
            $table->foreign('course_code')
                ->references('course_code')->on('courses')
                ->restrictOnDelete()->cascadeOnUpdate();
        });
        $addFkIfNotExists('student_grades', 'student_grades_course_code_foreign', function (Blueprint $table) {
            $table->foreign('course_code')
                ->references('course_code')->on('courses')
                ->restrictOnDelete()->cascadeOnUpdate();
        });
        $addFkIfNotExists('course_lecturers', 'course_lecturers_course_code_foreign', function (Blueprint $table) {
            $table->foreign('course_code')
                ->references('course_code')->on('courses')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });
    }
};
