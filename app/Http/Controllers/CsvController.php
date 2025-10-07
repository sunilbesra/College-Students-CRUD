<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessStudentData;
use Pheanstalk\Pheanstalk;
use App\Models\CsvJob;
use App\Events\CsvBatchQueued;

class CsvController extends Controller
{
    /**
     * Show CSV upload form.
     */
  public function showForm()
{
    $progress = [
        'queued' => CsvJob::where('status', 'queued')->count(),
        'processing' => CsvJob::where('status', 'processing')->count(),
        'completed' => CsvJob::where('status', 'completed')->count(),
        'failed' => CsvJob::where('status', 'failed')->count(),
    ];

    // Paginate CSV jobs, 10 per page
    $csvJobs = CsvJob::orderBy('created_at', 'desc')->paginate(10);

    return view('upload_csv', compact('progress', 'csvJobs'));
}



    /**
     * Handle CSV upload and dispatch jobs to Beanstalkd.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|mimes:csv,txt',
        ]);

        $file = $request->file('csv_file');
        $fileName = $file->getClientOriginalName();
        
        // Log upload start
        Log::info('CSV upload started', [
            'file' => $fileName,
            'ip' => $request->ip(),
            'user_id' => auth()->id() ?? null,
        ]);

        if (($handle = fopen($file->getRealPath(), 'r')) === false) {
            return back()->with('error', 'Could not open CSV file.');
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            Log::warning('CSV file empty or invalid', ['file' => $fileName]);
            return back()->with('error', 'CSV file is empty or invalid.');
        }

        $jobIds = [];
        $rowCount = 0;

        // Process each CSV row and create CsvJob records for queue processing
        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);
            $rowCount++;

            try {
                // Create a CsvJob record for this row
                $csvJob = CsvJob::create([
                    'file_name' => $fileName,
                    'row_identifier' => $rowCount,
                    'data' => $data,
                    'status' => 'queued',
                ]);

                $jobIds[] = $csvJob->id;
                
                Log::debug('Created CsvJob for row', [
                    'file' => $fileName,
                    'row' => $rowCount,
                    'job_id' => $csvJob->id
                ]);

            } catch (\Throwable $e) {
                Log::error('Failed creating CSV job for row: ' . $e->getMessage(), [
                    'file' => $fileName, 
                    'row' => $data, 
                    'row_number' => $rowCount
                ]);
            }
        }

        fclose($handle);

        // Dispatch batch of jobs to the queue
        if (!empty($jobIds)) {
            $this->dispatchBatch($jobIds, $fileName);
            
            Log::info('CSV upload queued for processing', [
                'file' => $fileName, 
                'total_rows' => $rowCount, 
                'jobs_created' => count($jobIds)
            ]);
            
            $message = "CSV uploaded successfully! {$rowCount} rows queued for processing. Check progress above.";
            return back()->with('success', $message);
        } else {
            Log::warning('No valid jobs created from CSV', ['file' => $fileName]);
            return back()->with('error', 'No valid rows found in CSV file.');
        }
    }    /**
     * Dispatch CSV row jobs to Beanstalkd queue.
     */
    private function dispatchBatch(array $jobIds, string $fileName = null)
    {
        if (!empty($jobIds)) {
            // Fire the CsvBatchQueued event which will be handled by DispatchCsvJobsListener
            event(new CsvBatchQueued($fileName, $jobIds));
            
            Log::info('CsvBatchQueued event fired', [
                'file_name' => $fileName,
                'job_count' => count($jobIds),
                'queue' => config('queue.connections.beanstalkd.queue', 'csv_jobs')
            ]);
        }
    }
}