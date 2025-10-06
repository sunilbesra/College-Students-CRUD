<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CsvNotificationController extends Controller
{
    public function lastBatch(Request $request)
    {
        $batch = Cache::get('csv_last_batch');
        return response()->json([ 'data' => $batch ]);
    }
}
