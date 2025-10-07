<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use App\Events\StudentCreated;
use App\Jobs\ProcessStudentData;
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
        // Prepare data for processing
        $data = $request->all();
        
        // Handle file upload synchronously (file needs to be processed before queuing)
        if ($request->hasFile('profile_image')) {
            $image = $request->file('profile_image');
            $imageName = time().'_'.$image->getClientOriginalName();
            $image->move(public_path('uploads'), $imageName);
            $data['profile_image'] = 'uploads/' . $imageName;
        }

        // Dispatch to Beanstalkd queue for validation and MongoDB insertion
        // No tracking ID needed for form submissions - they go directly to processing
        ProcessStudentData::dispatch(
            $data,
            'create',
            'form'
        );

        // Mirror JSON to beanstalk for external consumers (Aurora, etc.)
        $this->mirrorToBeanstalk('create', $data);

        Log::info('Student creation queued via form', [
            'operation' => 'create',
            'queue' => env('BEANSTALKD_STUDENT_QUEUE', 'student_jobs'),
            'email' => $data['email'] ?? 'unknown'
        ]);

        return redirect()->route('students.index')
            ->with('success', 'Student data is being validated and processed. Please check back in a moment!');
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
        // Prepare data for processing
        $data = $request->all();
        
        // Handle file upload synchronously (file needs to be processed before queuing)
        if ($request->hasFile('profile_image')) {
            $image = $request->file('profile_image');
            $imageName = time().'_'.$image->getClientOriginalName();
            $image->move(public_path('uploads'), $imageName);
            $data['profile_image'] = 'uploads/' . $imageName;
        }

        $studentId = (string) ($student->_id ?? $student->id);

        // Dispatch to Beanstalkd queue for validation and MongoDB update
        ProcessStudentData::dispatch(
            $data,
            'update',
            'form',
            null, // No tracking ID for form submissions
            $studentId
        );

        // Mirror JSON to beanstalk for external consumers (Aurora, etc.)
        $this->mirrorToBeanstalk('update', $data, $studentId);

        Log::info('Student update queued via form', [
            'student_id' => $studentId,
            'operation' => 'update',
            'queue' => env('BEANSTALKD_STUDENT_QUEUE', 'student_jobs'),
            'email' => $data['email'] ?? $student->email
        ]);

        return redirect()->route('students.index')
            ->with('success', 'Student update is being processed. Please check back in a moment!');
    }


    public function destroy(Request $request, Student $student)
    {
        $studentId = (string) ($student->_id ?? $student->id);
        $studentData = [
            'id' => $studentId,
            'name' => $student->name,
            'email' => $student->email,
        ];

        // Dispatch to Beanstalkd queue for deletion
        ProcessStudentData::dispatch(
            $studentData,
            'delete',
            'form',
            null, // No tracking ID for form submissions
            $studentId
        );

        // Mirror JSON to beanstalk for external consumers (Aurora, etc.)
        $this->mirrorToBeanstalk('delete', $studentData, $studentId);

        Log::info('Student deletion queued via form', [
            'student_id' => $studentId,
            'operation' => 'delete',
            'queue' => env('BEANSTALKD_STUDENT_QUEUE', 'student_jobs'),
            'student_name' => $student->name
        ]);

        return redirect()->route('students.index')
            ->with('success', 'Student deletion is being processed. Please check back in a moment!');
    }

    /**
     * Mirror operation to Beanstalk for external consumers (Aurora, etc.)
     */
    private function mirrorToBeanstalk(string $operation, array $data, ?string $studentId = null)
    {
        try {
            $pheanstalkHost = env('BEANSTALKD_QUEUE_HOST', '127.0.0.1');
            $pheanstalkPort = env('BEANSTALKD_PORT', 11300);
            $pheanstalk = Pheanstalk::create($pheanstalkHost, $pheanstalkPort);
            
            // Use different tubes for different operations
            $mirrorTube = match($operation) {
                'create' => env('BEANSTALKD_STUDENT_JSON_TUBE', 'student_json'),
                'update' => env('BEANSTALKD_STUDENT_JSON_TUBE', 'student_json'),
                'delete' => env('BEANSTALKD_STUDENT_JSON_TUBE', 'student_json'),
                default => env('BEANSTALKD_JSON_TUBE', 'csv_jobs_json')
            };
            
            $payload = json_encode([
                'operation' => $operation,
                'student_id' => $studentId,
                'data' => $data,
                'queued_at' => now()->toDateTimeString(),
                'source' => 'student_form',
            ], JSON_UNESCAPED_UNICODE);
            
            $pheanstalk->useTube($mirrorTube)->put($payload);
            
            Log::debug('Mirrored to Beanstalk', [
                'operation' => $operation,
                'tube' => $mirrorTube,
                'student_id' => $studentId
            ]);
            
        } catch (\Throwable $e) {
            Log::warning("Failed to write JSON mirror to beanstalk (student {$operation}): " . $e->getMessage());
        }
    }

}
