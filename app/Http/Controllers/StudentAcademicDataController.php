<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateStudentAcademicDataRequest;
use App\Models\Student;
use App\Services\StudentAcademicDataEditor;
use Illuminate\Support\Facades\Gate;

class StudentAcademicDataController extends Controller
{
    public function edit(Student $student, StudentAcademicDataEditor $editor)
    {
        Gate::authorize('update', $student);
        return view('students.academic-data-edit', $editor->prepare($student));
    }

    public function update(UpdateStudentAcademicDataRequest $request, Student $student, StudentAcademicDataEditor $editor)
    {
        Gate::authorize('update', $student);
        $editor->save($student, $request->validated(), $request->user());
        return redirect()->route('students.show', ['student' => $student, 'tab' => 'yudisium'])
            ->with('success', 'Data Yudisium & Tugas Akhir berhasil diperbarui.');
    }
}
