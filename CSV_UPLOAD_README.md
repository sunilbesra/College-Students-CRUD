# CSV Upload for Student Management

This Laravel application includes a robust CSV upload system for bulk student data processing using Beanstalkd queues.

## Overview

The CSV upload system allows you to:
- Upload CSV files containing student data
- Process uploads asynchronously using queues
- Monitor processing progress in real-time
- Handle validation errors gracefully
- Track job status and completion

## Configuration

### Environment Variables

The following environment variables are configured in `.env`:

```env
# Queue Configuration
QUEUE_CONNECTION=beanstalkd
BEANSTALKD_QUEUE_HOST=127.0.0.1
BEANSTALKD_PORT=11300

# CSV-specific queue settings
BEANSTALKD_QUEUE=csv_jobs
BEANSTALKD_JSON_TUBE=csv_jobs_json
BEANSTALKD_QUEUE_RETRY_AFTER=90

# Student-specific queue (for CRUD operations)
BEANSTALKD_STUDENT_QUEUE=student_jobs
BEANSTALKD_STUDENT_JSON_TUBE=student_json
```

### Required Services

1. **MongoDB**: For storing student and CSV job data
2. **Beanstalkd**: For queue management

## CSV File Format

Your CSV file should include the following columns (header row required):

```csv
name,email,contact,address,college,gender,dob,enrollment_status,course,agreed_to_terms
```

### Field Requirements

- **name**: Required, max 255 characters
- **email**: Required, must be valid email format, unique
- **contact**: Required, any format
- **address**: Required
- **college**: Required
- **gender**: Optional, values: male, female, other
- **dob**: Optional, date format (YYYY-MM-DD)
- **enrollment_status**: Optional, values: full_time, part_time
- **course**: Optional, max 255 characters
- **agreed_to_terms**: Optional, accepts: 1, true, yes, on

### Sample CSV

A sample CSV file (`sample_students.csv`) is included in the project root.

## Usage

### 1. Start the Queue Worker

Before uploading CSV files, start the queue worker:

```bash
# Option 1: Use the custom script
./start-csv-worker.sh

# Option 2: Use the Artisan command directly
php artisan csv:process-queue

# Option 3: Use Laravel's queue worker
php artisan queue:work beanstalkd --queue=csv_jobs
```

### 2. Upload CSV File

1. Navigate to `/upload-csv` in your browser
2. Select your CSV file
3. Click "Upload"
4. Monitor progress on the same page

### 3. Monitor Progress

The upload page shows:
- **Queued**: Jobs waiting to be processed
- **Processing**: Jobs currently being processed
- **Completed**: Successfully processed jobs
- **Failed**: Jobs that encountered errors

The page auto-refreshes every 10 seconds when there are active jobs.

## System Architecture

### Components

1. **CsvController**: Handles file upload and creates CsvJob records
2. **ProcessCsvRow Job**: Processes individual CSV rows
3. **CsvJob Model**: Stores job metadata and status
4. **DispatchCsvJobsListener**: Listens for batch events and dispatches jobs
5. **CsvBatchQueued Event**: Fired when a batch of jobs is ready

### Workflow

1. User uploads CSV file
2. File is parsed and CsvJob records are created for each row
3. CsvBatchQueued event is fired
4. DispatchCsvJobsListener queues ProcessCsvRow jobs
5. Queue worker processes jobs asynchronously
6. Job status is updated in database
7. Frontend polls for completion notifications

### Queue Configuration

Jobs are processed using the `csv_jobs` queue on Beanstalkd with:
- **Timeout**: 60 seconds per job
- **Retry**: 3 attempts
- **Delay**: 1 second between job dispatches
- **Sleep**: 3 seconds between polling

## Error Handling

### Validation Errors

If a CSV row fails validation:
- The job status is set to "failed"
- Error details are stored in the `error_message` field
- Processing continues with remaining rows

### Common Issues

1. **Invalid email format**: Check email addresses in CSV
2. **Duplicate emails**: Ensure email uniqueness in CSV
3. **Missing required fields**: Verify all required columns are present
4. **Invalid date format**: Use YYYY-MM-DD format for dates
5. **Invalid gender/enrollment values**: Check allowed values

### Monitoring Logs

Check Laravel logs for detailed error information:

```bash
tail -f storage/logs/laravel.log
```

## API Endpoints

### CSV Upload
- **POST** `/upload-csv`: Upload CSV file
- **GET** `/upload-csv`: Show upload form and progress

### CSV Notifications
- **GET** `/csv/last-batch`: Get last batch completion status (JSON)

## Database Schema

### CsvJob Collection (MongoDB)

```javascript
{
  _id: ObjectId,
  file_name: String,
  row_identifier: Number,
  data: Object,        // Original CSV row data
  status: String,      // queued, processing, completed, failed
  error_message: String,
  created_at: Date,
  updated_at: Date
}
```

## Performance Considerations

- Large CSV files are processed asynchronously to avoid timeouts
- Each row is processed as a separate job for better error isolation
- Failed jobs can be retried individually
- Progress tracking allows users to monitor large uploads

## Troubleshooting

### Queue Worker Not Processing Jobs

1. Ensure Beanstalkd is running:
   ```bash
   sudo systemctl status beanstalkd
   ```

2. Check queue connection:
   ```bash
   php artisan queue:monitor beanstalkd
   ```

3. Verify environment variables in `.env`

### Jobs Failing Consistently

1. Check validation rules in `StudentValidator`
2. Verify CSV format matches expected columns
3. Review error logs for specific issues

### Performance Issues

1. Consider increasing worker timeout for large files
2. Monitor memory usage during processing
3. Adjust `max-jobs` parameter for queue worker

## Extensions

The system can be extended to:
- Support different file formats (Excel, JSON)
- Add email notifications for completion
- Implement progress bars with WebSockets
- Add data transformation rules
- Include duplicate detection strategies

## Testing

Run the CSV upload system tests:

```bash
php artisan test --filter=CsvUpload
```

Test with the provided sample CSV file to verify the system works correctly.