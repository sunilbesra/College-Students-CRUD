# Unified Student Data Processing Architecture

This Laravel application implements a unified queue-based architecture where both form submissions and CSV uploads follow the same processing pattern:

**Form/CSV Data → Beanstalkd Queue → Laravel Consumer → Validation → MongoDB**

## Architecture Overview

```
┌─────────────────┐    ┌──────────────┐    ┌─────────────────┐    ┌─────────────┐
│  Student Form   │    │              │    │                 │    │             │
│   Submission    │───▶│   Beanstalkd │───▶│ ProcessStudent  │───▶│   MongoDB   │
└─────────────────┘    │              │    │     Data Job    │    │             │
                       │   Queues     │    │                 │    │  (Student   │
┌─────────────────┐    │              │    │  - Validation   │    │   Model)    │
│   CSV Upload    │───▶│ • csv_jobs   │    │  - Processing   │    │             │
│    Processing   │    │ • student_jobs│    │  - Events       │    │             │
└─────────────────┘    └──────────────┘    └─────────────────┘    └─────────────┘
```

## Unified Processing Flow

### 1. Form Submissions (CRUD Operations)
- **Create**: Form → `ProcessStudentData` → Validation → MongoDB Insert → `StudentCreated` Event
- **Update**: Form → `ProcessStudentData` → Validation → MongoDB Update → `StudentUpdated` Event  
- **Delete**: Form → `ProcessStudentData` → MongoDB Delete → `StudentDeleted` Event

### 2. CSV Uploads
- **Upload**: CSV File → Parse Rows → Create `CsvJob` Records → `CsvBatchQueued` Event
- **Process**: `CsvJob` → `ProcessStudentData` → Validation → MongoDB Upsert → `StudentCreated` Event

## Queue Configuration

### Environment Variables (.env)
```env
# Main queue connection
QUEUE_CONNECTION=beanstalkd
BEANSTALKD_QUEUE_HOST=127.0.0.1
BEANSTALKD_PORT=11300

# CSV processing queue
BEANSTALKD_QUEUE=csv_jobs
BEANSTALKD_JSON_TUBE=csv_jobs_json

# Form processing queue  
BEANSTALKD_STUDENT_QUEUE=student_jobs
BEANSTALKD_STUDENT_JSON_TUBE=student_json
```

### Queue Separation
- **`csv_jobs`**: Processes CSV upload data with tracking via `CsvJob` model
- **`student_jobs`**: Processes form submissions directly without additional tracking

## Unified Job: ProcessStudentData

This single job handles all student data processing:

```php
ProcessStudentData::dispatch(
    array $data,           // Student data to process
    string $operation,     // 'create', 'update', 'delete'  
    string $source = 'form', // 'form' or 'csv'
    ?string $trackingId = null, // CsvJob ID (for CSV only)
    ?string $studentId = null   // Student ID (for update/delete)
);
```

### Job Features
- **Unified Validation**: Uses `StudentValidator` for all data
- **Smart Upserts**: Handles email-based deduplication
- **Event Broadcasting**: Fires appropriate events (`StudentCreated`, `StudentUpdated`, `StudentDeleted`)
- **Error Handling**: Tracks failures in `CsvJob` for CSV operations
- **Retry Logic**: Configurable retry attempts with exponential backoff

## Starting the Workers

### Option 1: Unified Worker (Recommended)
```bash
# Interactive menu to choose queue processing mode
./start-student-workers.sh

# Or directly via Artisan
php artisan students:process-queue --queue=both
php artisan students:process-queue --queue=csv_jobs
php artisan students:process-queue --queue=student_jobs
```

### Option 2: Legacy CSV Worker
```bash
./start-csv-worker.sh
# OR
php artisan csv:process-queue
```

## Data Flow Examples

### Form Submission Flow
1. User submits student form
2. `StudentController@store` handles file upload
3. Data dispatched to `student_jobs` queue
4. `ProcessStudentData` job validates and processes
5. Student created in MongoDB
6. `StudentCreated` event fired
7. User sees success message

### CSV Upload Flow  
1. User uploads CSV file
2. `CsvController@upload` parses file and creates `CsvJob` records
3. `CsvBatchQueued` event fired
4. `DispatchCsvJobsListener` dispatches jobs to `csv_jobs` queue
5. `ProcessStudentData` jobs process each row
6. Students created/updated in MongoDB
7. `CsvJob` records updated with status
8. User monitors progress on upload page

## External Integration

Both processing flows mirror data to external tubes for Aurora/other consumers:

```php
// Form operations mirror to: student_json tube
// CSV operations mirror to: csv_jobs_json tube

{
    "operation": "create|update|delete",
    "student_id": "...", 
    "data": {...},
    "queued_at": "2025-10-07 14:30:00",
    "source": "student_form|csv"
}
```

## Benefits of Unified Architecture

1. **Consistency**: Same validation and processing logic for all operations
2. **Maintainability**: Single job class to maintain
3. **Scalability**: Independent queue scaling based on load
4. **Reliability**: Unified retry and error handling
5. **Observability**: Consistent logging and monitoring
6. **Flexibility**: Easy to add new data sources (API, imports, etc.)

## Monitoring & Troubleshooting

### Check Queue Status
```bash
# Monitor queue activity
php artisan queue:monitor beanstalkd

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

### Logs
- **Application logs**: `storage/logs/laravel.log`
- **Queue processing**: Look for `ProcessStudentData` entries
- **CSV tracking**: Monitor `CsvJob` status changes
- **Form operations**: Track by student email/ID

### Performance Tuning
```bash
# High-throughput processing
php artisan students:process-queue --queue=both --timeout=3600

# Separate workers for different priorities  
php artisan students:process-queue --queue=student_jobs &  # Forms (high priority)
php artisan students:process-queue --queue=csv_jobs &      # CSV (batch processing)
```

## Testing the System

### Test Form Submissions
1. Navigate to `/students/create`
2. Fill out the form and submit
3. Check logs for `ProcessStudentData` job execution
4. Verify student appears in database

### Test CSV Upload
1. Navigate to `/upload-csv` 
2. Upload the provided `sample_students.csv`
3. Monitor progress on the same page
4. Check `CsvJob` records for status tracking

### Verify External Mirroring
Check Beanstalk tubes for mirrored JSON data:
```bash
# Check student operations tube
beanstalkc stats-tube student_json

# Check CSV operations tube  
beanstalkc stats-tube csv_jobs_json
```

## Future Enhancements

The unified architecture supports easy extension:
- **API endpoints**: Add REST API that uses same `ProcessStudentData` job
- **Bulk operations**: Batch multiple form submissions 
- **Real-time updates**: WebSocket notifications for job completion
- **Advanced validation**: Custom validation rules per data source
- **Data transformations**: Source-specific data mapping
- **Audit trails**: Enhanced tracking and compliance logging