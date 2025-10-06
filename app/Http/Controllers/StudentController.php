<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use App\Events\StudentCreated;
use App\Jobs\ProcessFormData;
use Pheanstalk\Pheanstalk;
use Illuminate\Support\Facades\Log;
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
    // Use helper-based validator
        if (! function_exists('student_validate')) {
            $msg = 'Validation helper "student_validate" not available. Please run composer dump-autoload and ensure app/helpers.php is loaded.';
            Log::error($msg);
            return redirect()->back()->with('error', $msg)->withInput();
        }
        $validated = \student_validate($request->all());

        // Use helper-based validator but dispatch processing to background job
        if ($request->hasFile('profile_image')) {
            $image = $request->file('profile_image');
            $imageName = time().'_'.$image->getClientOriginalName();
            $image->move(public_path('uploads'), $imageName);
            $validated['profile_image'] = 'uploads/' . $imageName;
        }

        // If a profile image was uploaded, create the student synchronously so the image is saved immediately.
        $apply = array_filter($validated, function ($v) { return $v !== null; });
        $student = null;
        try {
            $student = Student::create($apply);
        } catch (\Throwable $e) {
            // If creation fails (rare), log and continue to dispatch job which will try to upsert
            Log::warning('Immediate student create failed: ' . $e->getMessage());
        }

        // Dispatch background job to validate/process/store (job will upsert by email/id)
        ProcessFormData::dispatch($validated, $student ? (string)($student->_id ?? $student->id) : null)
            ->onQueue(config('queue.connections.beanstalkd.queue') ?? env('BEANSTALKD_QUEUE', 'csv_jobs'));

        // Mirror JSON to beanstalk for Aurora
        try {
            $pheanstalkHost = env('BEANSTALKD_QUEUE_HOST', '127.0.0.1');
            $pheanstalkPort = env('BEANSTALKD_PORT', 11300) ?: env('BEANSTALKD_PORT', 11300);
            $pheanstalk = Pheanstalk::create($pheanstalkHost, $pheanstalkPort);
            $mirrorTube = env('BEANSTALKD_JSON_TUBE', 'csv_jobs_json');
            $payload = json_encode([
                'operation' => 'create',
                'data' => $apply,
                'queued_at' => now()->toDateTimeString(),
            ], JSON_UNESCAPED_UNICODE);
            $pheanstalk->useTube($mirrorTube)->put($payload);
        } catch (\Throwable $e) {
            Log::warning('Failed to write JSON mirror to beanstalk (student create): ' . $e->getMessage());
        }

        return redirect()->route('students.index')->with('success', 'Your data is being validated and processed in the background!');
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
        // Use helper-based validator and ignore current student's id for unique email rule
        if (! function_exists('student_validate')) {
            $msg = 'Validation helper "student_validate" not available. Please run composer dump-autoload and ensure app/helpers.php is loaded.';
            Log::error($msg);
            return redirect()->back()->with('error', $msg)->withInput();
        }
        // Use helper-based validator but process in background via job
        $validated = \student_validate($request->all(), $student->id);

        if ($request->hasFile('profile_image')) {
            $image = $request->file('profile_image');
            $imageName = time().'_'.$image->getClientOriginalName();
            $image->move(public_path('uploads'), $imageName);
            $validated['profile_image'] = 'uploads/' . $imageName;
        }

        // If a profile image was uploaded, update it immediately on the student record to reflect the change.
        $apply = array_filter($validated, function ($v) { return $v !== null; });
        try {
            $student->update($apply);
        } catch (\Throwable $e) {
            Log::warning('Immediate student update failed: ' . $e->getMessage());
        }

        // Ensure job knows the student id for upsert
        $validated['id'] = (string) ($student->_id ?? $student->id);

        ProcessFormData::dispatch($validated, $validated['id'])
            ->onQueue(config('queue.connections.beanstalkd.queue') ?? env('BEANSTALKD_QUEUE', 'csv_jobs'));

        try {
            $pheanstalkHost = env('BEANSTALKD_QUEUE_HOST', '127.0.0.1');
            $pheanstalkPort = env('BEANSTALKD_PORT', 11300) ?: env('BEANSTALKD_PORT', 11300);
            $pheanstalk = Pheanstalk::create($pheanstalkHost, $pheanstalkPort);
            $mirrorTube = env('BEANSTALKD_JSON_TUBE', 'csv_jobs_json');
            $payload = json_encode([
                'operation' => 'update',
                'id' => $validated['id'] ?? null,
                'data' => $apply,
                'queued_at' => now()->toDateTimeString(),
            ], JSON_UNESCAPED_UNICODE);
            $pheanstalk->useTube($mirrorTube)->put($payload);
        } catch (\Throwable $e) {
            Log::warning('Failed to write JSON mirror to beanstalk (student update): ' . $e->getMessage());
        }

        return redirect()->route('students.index')->with('success', 'Your update is being processed in the background!');
    }


    public function destroy(Student $student)
    {
        $student->delete();
        return redirect()->route('students.index')->with('success', 'Student deleted successfully.');
    }

}
