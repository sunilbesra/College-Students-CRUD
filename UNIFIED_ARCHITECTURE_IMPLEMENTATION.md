# Unified Architecture Implementation

## Overview

The system now follows a unified architecture pattern where both form submissions and CSV uploads go through the same processing pipeline:

```
Form Filled → Beanstalk → Validation by Laravel Consumer → Consumer inserts data into MongoDB
CSV Upload → Beanstalk → Validation by Laravel Consumer → Consumer inserts data into MongoDB
```

## Architecture Flow

### 1. Data Entry Points
- **Web Form**: User fills out form and submits
- **CSV Upload**: User uploads CSV file with multiple records

### 2. Controller Layer
- **FormSubmissionController**: Handles both form submissions and CSV uploads
- **No pre-processing**: Data sent directly to Beanstalk queue
- **No FormSubmission records created upfront**

### 3. Beanstalk Queue
- **Single Queue**: `form_submission_jobs`
- **Raw Data**: Contains unvalidated submission data
- **Job Payload**: Includes operation, data, source, metadata

### 4. Laravel Consumer (ProcessFormSubmissionData Job)
- **Receives from Beanstalk**: Gets raw submission data
- **Creates FormSubmission record**: For tracking and audit
- **Validates data**: Using FormSubmissionValidator
- **Processes operation**: Create/Update/Delete student
- **Updates MongoDB**: Student collection
- **Updates status**: FormSubmission tracking record

## Implementation Details

### Controller Changes

#### Form Submission (store method)
```php
// OLD: Create FormSubmission first, then dispatch job
$formSubmission = FormSubmission::create($data);
ProcessFormSubmissionData::dispatch($formSubmission->_id, $data);

// NEW: Send raw data directly to Beanstalk
ProcessFormSubmissionData::dispatch(null, $submissionData);
```

#### CSV Processing (processCsv method)
```php
// OLD: Create FormSubmission for each row, then dispatch jobs
foreach ($rows as $row) {
    $formSubmission = FormSubmission::create($rowData);
    ProcessFormSubmissionData::dispatch($formSubmission->_id, $rowData);
}

// NEW: Send each row directly to Beanstalk
foreach ($rows as $row) {
    ProcessFormSubmissionData::dispatch(null, $rowData);
}
```

### Job Changes

#### ProcessFormSubmissionData Job
```php
public function handle(): void
{
    // Step 1: Create FormSubmission record from Beanstalk data
    $formSubmission = FormSubmission::create([
        'operation' => $this->submissionData['operation'],
        'data' => $this->submissionData['data'],
        'source' => $this->submissionData['source'],
        'status' => 'processing'
    ]);

    // Step 2: Validate data
    $validatedData = FormSubmissionValidator::validateAndPrepareForStudent($data);

    // Step 3: Process operation (Create/Update/Delete student)
    $result = match($operation) {
        'create' => $this->createStudent($validatedData),
        'update' => $this->updateStudent($validatedData, $studentId),
        'delete' => $this->deleteStudent($studentId)
    };

    // Step 4: Update FormSubmission status
    $formSubmission->update([
        'status' => 'completed',
        'student_id' => $result['student_id']
    ]);
}
```

## Data Flow Diagrams

### Form Submission Flow
```
User Form Input
       ↓
FormSubmissionController::store()
       ↓
Raw data → Beanstalk Queue (form_submission_jobs)
       ↓
ProcessFormSubmissionData Job (Consumer)
       ↓
Create FormSubmission record (tracking)
       ↓
FormSubmissionValidator::validate()
       ↓
Student::create/update/delete (MongoDB)
       ↓
Update FormSubmission status → 'completed'
```

### CSV Upload Flow
```
CSV File Upload
       ↓
FormSubmissionController::processCsv()
       ↓
Parse CSV rows → Multiple raw data payloads
       ↓ (for each row)
Raw data → Beanstalk Queue (form_submission_jobs)
       ↓
ProcessFormSubmissionData Job (Consumer)
       ↓
Create FormSubmission record (tracking)
       ↓
FormSubmissionValidator::validate()
       ↓
Student::create/update/delete (MongoDB)
       ↓
Update FormSubmission status → 'completed'
```

## Benefits of Unified Architecture

### 1. **Consistency**
- Same validation logic for all data sources
- Identical error handling and logging
- Uniform processing pipeline

### 2. **Scalability**
- Beanstalk handles load balancing
- Horizontal scaling of consumer workers
- No bottlenecks in web controllers

### 3. **Reliability**
- Queue-based processing prevents data loss
- Automatic retry on failures
- Dead letter queue for problematic jobs

### 4. **Observability**
- All operations tracked in FormSubmission collection
- Consistent logging across all sources
- Easy monitoring and debugging

### 5. **Maintainability**
- Single validation codebase
- Centralized business logic
- Easier testing and updates

## Queue Configuration

### Environment Variables
```env
# Primary queue for all form submissions
BEANSTALKD_FORM_SUBMISSION_QUEUE=form_submission_jobs

# Queue connection settings
QUEUE_CONNECTION=beanstalkd
BEANSTALKD_QUEUE_HOST=127.0.0.1
BEANSTALKD_PORT=11300
```

### Job Properties
```php
class ProcessFormSubmissionData implements ShouldQueue
{
    public $timeout = 300;    // 5 minutes per job
    public $tries = 3;        // Retry failed jobs 3 times
    public $queue = 'form_submission_jobs';
}
```

## Consumer Processing Logic

### 1. **Receive from Beanstalk**
- Job picks up raw submission data
- No pre-existing database records
- Fresh processing for each job

### 2. **Create Tracking Record**
```php
$formSubmission = FormSubmission::create([
    'operation' => $data['operation'],
    'data' => $data['data'],
    'source' => $data['source'],
    'status' => 'processing'
]);
```

### 3. **Validate and Transform**
```php
$validatedData = FormSubmissionValidator::validateAndPrepareForStudent($data);
```

### 4. **Execute Operation**
```php
$result = match($operation) {
    'create' => Student::create($validatedData),
    'update' => $student->update($validatedData),
    'delete' => $student->delete()
};
```

### 5. **Update Tracking**
```php
$formSubmission->update([
    'status' => 'completed',
    'student_id' => $result['student_id']
]);
```

## Error Handling

### Validation Errors
- Consumer validates data using FormSubmissionValidator
- Invalid data marked as 'failed' in FormSubmission
- Processing continues with next job

### Processing Errors
- Database errors, network issues, etc.
- Job retried according to retry policy
- Permanent failures recorded in FormSubmission

### Duplicate Email Handling
- Pre-validation in controller (for CSV)
- Consumer-level validation as backup
- Consistent error messages across sources

## Monitoring and Debugging

### FormSubmission Collection
```javascript
{
  _id: ObjectId,
  operation: "create|update|delete",
  data: {...},              // Original submission data
  source: "form|csv|api",   // Data source
  status: "processing|completed|failed",
  student_id: ObjectId,     // Created/updated student ID
  error_message: String,    // If failed
  created_at: Date,         // When consumer created record
  updated_at: Date
}
```

### Logging
- Controller: "Data sent to Beanstalk"
- Consumer: "Processing started/completed/failed"
- Validation: Detailed error information
- Operations: Student create/update/delete results

### Queue Monitoring
```bash
# Monitor queue status
php artisan queue:monitor beanstalkd:form_submission_jobs

# Process jobs
php artisan queue:work beanstalkd --queue=form_submission_jobs

# Check job statistics
php artisan tinker --execute="
App\Models\FormSubmission::selectRaw('
    source,
    status,
    COUNT(*) as count
')->groupBy(['source', 'status'])->get();
"
```

## Testing the Unified Architecture

### Test Form Submission
```bash
# Submit via web form
curl -X POST http://localhost:8000/form-submissions \
  -F "operation=create" \
  -F "source=form" \
  -F "data[name]=Test User" \
  -F "data[email]=test@example.com"
```

### Test CSV Upload
```bash
# Upload CSV file
curl -X POST http://localhost:8000/form-submissions/csv/process \
  -F "operation=create" \
  -F "csv_file=@test.csv"
```

### Monitor Results
```bash
# Check queue processing
php artisan queue:work beanstalkd --queue=form_submission_jobs --once

# Check results
php artisan tinker --execute="
echo 'Recent submissions: ' . App\Models\FormSubmission::where('created_at', '>=', now()->subMinutes(10))->count();
echo 'Students created: ' . App\Models\Student::where('created_at', '>=', now()->subMinutes(10))->count();
"
```

This unified architecture ensures consistent, reliable, and scalable processing of all form submission data regardless of the entry point.