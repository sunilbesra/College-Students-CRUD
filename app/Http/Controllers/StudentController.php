<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use App\Events\StudentCreated;
use Illuminate\Support\Facades\Cache;
class StudentController extends Controller
{
  
     public function index(Request $request)
    {
        // sanitize and normalize inputs
        $q = (string) $request->query('q', '');
        $q = trim($q);

        // per-page control: allow a small set of options to avoid abuse
        $allowed = [5, 10, 25, 50];
        $perPage = (int) $request->query('per_page', 3);
        if (! in_array($perPage, $allowed, true)) {
            $perPage = 5;
        }

        // Build base query using the new scope name to avoid package conflicts
        $query = Student::searchText($q)->orderBy('created_at', 'desc');

        // simple caching for production when searching/filtering to reduce DB pressure
        if (app()->environment('production')) {
            $cacheKey = 'students:' . md5(serialize([ 'q' => $q, 'page' => $request->query('page', 1), 'per' => $perPage ]));
            $students = Cache::remember($cacheKey, now()->addMinutes(2), function () use ($query, $perPage) {
                return $query->paginate($perPage);
            });
        } else {
            $students = $query->paginate($perPage);
        }

        $students->appends(['q' => $q, 'per_page' => $perPage]);

        return view('students.index', compact('students', 'q', 'perPage'));
    }

    public function create()
    {
        return view('students.create');
    }
public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|max:255',
        'email' => 'required|email|unique:students,email',
        'contact' => 'required',
        'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        'address' => 'required',
            'college' => 'required',
            'gender' => 'nullable|in:male,female,other',
            'dob' => 'nullable|date',
            'enrollment_status' => 'nullable|in:full_time,part_time',
            'course' => 'nullable|string|max:255',
            'agreed_to_terms' => 'nullable|accepted',
    ]);

    if ($request->hasFile('profile_image')) {
        $image = $request->file('profile_image');
        $imageName = time().'_'.$image->getClientOriginalName();
        $image->move(public_path('uploads'), $imageName);
        $validated['profile_image'] = 'uploads/' . $imageName;
    }

    $student = Student::create($validated);

    // Fire event (listener will be queued automatically)
    event(new \App\Events\StudentCreated($student));

    return redirect()->route('students.index')->with('success', 'Student created successfully.');
}


    public function show(Student $student)
    {
        return view('students.show', compact('student'));
    }

    public function edit(Student $student)
    {
        return view('students.edit', compact('student'));
    }

    public function update(Request $request, Student $student)
    {
        $validated = $request->validate([
            'name' => 'required|max:255',
            'email' => 'required|email|unique:students,email,' . $student->id,
            'contact' => 'required',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'address' => 'required',
            'college' => 'required',
            'gender' => 'nullable|in:male,female,other',
            'dob' => 'nullable|date',
            'enrollment_status' => 'nullable|in:full_time,part_time',
            'course' => 'nullable|string|max:255',
            'agreed_to_terms' => 'nullable|accepted',
        ]);

        if ($request->hasFile('profile_image')) {
            $image = $request->file('profile_image');
            $imageName = time().'_'.$image->getClientOriginalName();
            $image->move(public_path('uploads'), $imageName);
            $validated['profile_image'] = 'uploads/' . $imageName;
        }

        $student->update($validated);
        return redirect()->route('students.index')->with('success', 'Student updated successfully.');
    }


    public function destroy(Student $student)
    {
        $student->delete();
        return redirect()->route('students.index')->with('success', 'Student deleted successfully.');
    }

}
