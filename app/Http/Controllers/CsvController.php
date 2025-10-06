<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessCsvRow;
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

        $batchSize = 500;  // Number of rows per batch
        $batch = [];
        $rowCount = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);

            try {
                // Save CSV row to MongoDB collection
                $jobRecord = CsvJob::create([
                    'file_name' => $fileName,
                    'row_identifier' => $data['id'] ?? $rowCount + 1, // fallback if no 'id'
                    'data' => $data,
                    'status' => 'queued',
                ]);

                // store id as string to avoid BSON/ObjectId serialization issues
                $batch[] = (string) $jobRecord->_id;
                Log::debug('CSV row queued', ['file' => $fileName, 'row_identifier' => $jobRecord->row_identifier, 'job_id' => (string)$jobRecord->_id]);
                $rowCount++;

                // Dispatch batch via event if reached batch size
                if (count($batch) >= $batchSize) {
                    Log::info('Dispatching CSV batch', ['file' => $fileName, 'batch_count' => count($batch)]);
                    event(new CsvBatchQueued($fileName, $batch));
                    $batch = [];
                }
            } catch (\Exception $e) {
                Log::error("Failed to save CSV row: " . $e->getMessage(), ['row' => $data, 'file' => $fileName]);
            }
        }

        // Dispatch remaining rows via event
        if (!empty($batch)) {
            Log::info('Dispatching remaining CSV batch', ['file' => $fileName, 'batch_count' => count($batch)]);
            event(new CsvBatchQueued($fileName, $batch));
        }

        fclose($handle);

        Log::info('CSV upload finished', ['file' => $fileName, 'rows' => $rowCount]);
        return back()->with('success', "CSV uploaded ($rowCount rows) and jobs dispatched!");
    }

    /**
     * Dispatch CSV row jobs to Beanstalkd queue.
     */
    private function dispatchBatch(array $jobIds)
    {
        // This method is now deprecated in favor of firing the CsvBatchQueued event.
        // Keep it for backward compatibility by firing the event.
        if (!empty($jobIds)) {
            event(new CsvBatchQueued(null, $jobIds));
        }
    }
}