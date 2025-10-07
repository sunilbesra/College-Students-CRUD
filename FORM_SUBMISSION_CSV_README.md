# Form Submission CSV Upload System

This document describes the CSV upload functionality for form submissions with student data fields.

## Overview

The Form Submission CSV Upload System allows you to:
- Upload CSV files with student data using specific field structure
- Process submissions asynchronously using Beanstalkd queues
- Monitor processing progress and handle errors
- Support create, update, and delete operations
- Maintain compatibility with the existing student management system

## Student Data Fields

The system supports the following student data fields:

### Required Fields
- **name** (string, max 255) - Student's full name
- **email** (string, valid email, unique) - Student's email address

### Optional Fields
- **phone** (string, max 20) - Phone number
- **date_of_birth** (date, YYYY-MM-DD format) - Date of birth
- **course** (string, max 255) - Course name
- **enrollment_date** (date, YYYY-MM-DD format) - Date of enrollment
- **grade** (string, max 10) - Current grade (e.g., A, B+, C-)
- **profile_image_path** (string, max 500) - Path to profile image
- **address** (string, max 1000) - Full address

### Operation-Specific Fields
- **student_id** (MongoDB ObjectId) - Required for update/delete operations

## CSV File Format

### Create Operations
```csv
name,email,phone,date_of_birth,course,enrollment_date,grade,profile_image_path,address
John Anderson,john.anderson@university.edu,+1234567890,1999-01-15,Computer Science,2024-09-01,A,uploads/john_anderson.jpg,"123 Main Street, Springfield, IL 62701"
Sarah Johnson,sarah.johnson@university.edu,+1987654321,1998-05-22,Mathematics,2024-08-25,B+,uploads/sarah_johnson.jpg,"456 Oak Avenue, Chicago, IL 60601"
```

### Update Operations
```csv
student_id,name,email,phone,course,grade
60f1b2c3d4e5f6789a0b1c2d,John Anderson Updated,john.new@university.edu,+1234567890,Computer Science,A+
60f1b2c3d4e5f6789a0b1c2e,Sarah Johnson Updated,sarah.new@university.edu,+1987654321,Mathematics,A
```

### Delete Operations
```csv
student_id
60f1b2c3d4e5f6789a0b1c2d
60f1b2c3d4e5f6789a0b1c2e
```

## System Architecture

### Components
1. **FormSubmissionController** - Handles CSV upload and creates FormSubmission records
2. **ProcessFormSubmissionData Job** - Processes individual form submissions
3. **FormSubmissionValidator** - Validates form submission data
4. **FormSubmission Model** - Stores submission metadata and status
5. **Student Model** - Final storage for validated student data

### Processing Flow
1. User uploads CSV file via web interface
2. Controller parses CSV and creates FormSubmission records for each row
3. Jobs are dispatched to `form_submission_jobs` queue
4. ProcessFormSubmissionData job validates and processes each submission
5. Validated data is stored as Student records in MongoDB
6. FormSubmission status is updated (completed/failed)

### Queue Configuration
```env
# Form submission processing queue
BEANSTALKD_FORM_SUBMISSION_QUEUE=form_submission_jobs
BEANSTALKD_FORM_SUBMISSION_TUBE=form_submission_json
```

## Usage Instructions

### 1. Start Queue Workers
```bash
# Start form submission worker
php artisan queue:work beanstalkd --queue=form_submission_jobs

# Or use the unified worker script
./start-form-submission-workers.sh
```

### 2. Access Upload Interface
Navigate to: `http://localhost:8000/form-submissions/csv/upload`

### 3. Upload CSV File
1. Select operation type (create, update, delete)
2. Choose CSV file (max 10MB)
3. Preview file content
4. Submit for processing

### 4. Monitor Progress
- View processing status at: `http://localhost:8000/form-submissions`
- Check individual submission details
- Review error messages for failed submissions

## API Endpoints

### Web Routes
- **GET** `/form-submissions/csv/upload` - Show upload form
- **POST** `/form-submissions/csv/process` - Process uploaded CSV
- **GET** `/form-submissions` - List all form submissions
- **GET** `/form-submissions/api/stats` - Get statistics (JSON)

### Form Submission CRUD
- **GET** `/form-submissions/create` - Create new form submission
- **POST** `/form-submissions` - Store form submission
- **GET** `/form-submissions/{id}` - View form submission
- **PUT** `/form-submissions/{id}` - Update form submission
- **DELETE** `/form-submissions/{id}` - Delete form submission

## Database Schema

### FormSubmission Collection (MongoDB)
```javascript
{
  _id: ObjectId,
  operation: String,           // 'create', 'update', 'delete'
  student_id: String,          // Target student ID (for update/delete)
  data: Object,               // Student data from CSV row
  status: String,             // 'queued', 'processing', 'completed', 'failed'
  error_message: String,      // Error details if failed
  source: String,             // 'form', 'api', 'csv'
  ip_address: String,         // Request IP
  user_agent: String,         // Request user agent
  created_at: Date,
  updated_at: Date
}
```

### Student Collection (MongoDB)
```javascript
{
  _id: ObjectId,
  name: String,
  email: String,
  contact: String,            // Mapped from 'phone'
  address: String,
  college: String,            // Default: 'Unknown'
  gender: String,             // Optional
  dob: Date,                  // Mapped from 'date_of_birth'
  enrollment_status: String,  // Default: 'full_time'
  course: String,
  profile_image: String,      // Mapped from 'profile_image_path'
  enrollment_date: Date,
  grade: String,
  agreed_to_terms: Boolean,   // Default: true
  created_at: Date,
  updated_at: Date
}
```

## Validation Rules

### Form Submission Fields
- **name**: required, string, max 255 characters
- **email**: required, valid email, max 255 characters, unique
- **phone**: optional, string, max 20 characters
- **date_of_birth**: optional, valid date, YYYY-MM-DD format
- **course**: optional, string, max 255 characters
- **enrollment_date**: optional, valid date, YYYY-MM-DD format
- **grade**: optional, string, max 10 characters
- **profile_image_path**: optional, string, max 500 characters
- **address**: optional, string, max 1000 characters

### Field Mapping
Form submission fields are mapped to student model fields:
- `phone` → `contact`
- `date_of_birth` → `dob`
- `profile_image_path` → `profile_image`

## Error Handling

### Common Validation Errors
1. **Missing required fields**: Ensure name and email are provided
2. **Invalid email format**: Use proper email format
3. **Duplicate email**: Email already exists in system
4. **Invalid date format**: Use YYYY-MM-DD format for dates
5. **Student not found**: For update/delete operations with invalid student_id

### Error Monitoring
- Check form submission status in web interface
- Review Laravel logs: `tail -f storage/logs/laravel.log`
- Monitor queue jobs: `php artisan queue:monitor beanstalkd:form_submission_jobs`

## Testing

### Manual Testing
1. Use the provided test CSV file: `form_submissions_student_data.csv`
2. Run the test script: `./test-form-submission-csv.sh`

### Test Script Features
- Verifies system dependencies (Laravel server, MongoDB, queue workers)
- Uploads sample CSV file
- Monitors processing progress
- Validates results in database
- Provides comprehensive status reporting

## Sample CSV Files

### Basic Student Data
**File**: `form_submissions_student_data.csv`
Contains sample student records with all supported fields for testing create operations.

### Test Scenarios
- **Create**: Upload new students with complete data
- **Update**: Modify existing student information
- **Delete**: Remove students by ID
- **Mixed**: Combine multiple operations in single CSV

## Performance Considerations

### Optimization
- Large CSV files are processed asynchronously to prevent timeouts
- Each row is processed as separate job for better error isolation
- Failed jobs can be retried individually without affecting successful records
- Progress tracking allows monitoring of large batch uploads

### Limits
- Maximum CSV file size: 10MB
- Recommended batch size: 1000 records per file
- Processing timeout: 300 seconds per job
- Retry attempts: 3 per failed job

## Troubleshooting

### Common Issues
1. **Jobs not processing**: Check if Beanstalkd and workers are running
2. **Validation errors**: Verify CSV format and required fields
3. **Memory issues**: Split large CSV files into smaller batches
4. **Timeout errors**: Increase job timeout in queue configuration

### Debug Commands
```bash
# Check queue status
php artisan queue:monitor beanstalkd:form_submission_jobs

# Process failed jobs
php artisan queue:retry all

# View recent logs
tail -f storage/logs/laravel.log

# Check MongoDB connection
php artisan tinker --execute="App\Models\FormSubmission::count()"
```

## Integration with Existing System

### Compatibility
The form submission CSV upload system:
- Uses the same Student model as the existing CRUD system
- Maintains field compatibility through mapping layer
- Preserves existing validation and business logic
- Supports both legacy and new field structures

### Migration Path
1. Existing CSV uploads continue to work with original field names
2. New form submissions use the enhanced field structure
3. Both systems coexist and share the same database
4. Gradual migration possible without system disruption