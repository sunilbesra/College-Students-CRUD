@extends('layouts.app')

@section('title', 'Upload CSV for Form Submissions')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Upload CSV for Form Submissions</h4>
                    <a href="{{ route('form_submissions.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>

                <div class="card-body">
                    <!-- Instructions -->
                    <div class="alert alert-info">
                        <h6 class="alert-heading">
                            <i class="fas fa-info-circle"></i> CSV Upload Instructions
                        </h6>
                        <ul class="mb-0">
                            <li>CSV file should have headers in the first row</li>
                            <li>Maximum file size: 10MB</li>
                            <li>Supported columns: name, email, phone, gender, address, date_of_birth, course, enrollment_date, grade, profile_image_path (URL or path), student_id</li>
                            <li>Required columns: <strong>name</strong> and <strong>email</strong> (except for delete operations)</li>
                            <li>For update/delete operations, include <strong>student_id</strong> column</li>
                        </ul>
                    </div>

                    <!-- Upload Form -->
                    <form action="{{ route('form_submissions.process_csv') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <!-- Operation Type -->
                        <div class="mb-3">
                            <label for="operation" class="form-label">Operation Type <span class="text-danger">*</span></label>
                            <select class="form-select @error('operation') is-invalid @enderror" 
                                    id="operation" 
                                    name="operation" 
                                    required>
                                <option value="">Select Operation</option>
                                <option value="create" {{ old('operation') === 'create' ? 'selected' : '' }}>Create Students</option>
                                <option value="update" {{ old('operation') === 'update' ? 'selected' : '' }}>Update Students</option>
                                <option value="delete" {{ old('operation') === 'delete' ? 'selected' : '' }}>Delete Students</option>
                            </select>
                            @error('operation')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">This operation will be applied to all rows in the CSV file.</small>
                        </div>

                        <!-- CSV File Upload -->
                        <div class="mb-3">
                            <label for="csv_file" class="form-label">CSV File <span class="text-danger">*</span></label>
                            <input type="file" 
                                   class="form-control @error('csv_file') is-invalid @enderror" 
                                   id="csv_file" 
                                   name="csv_file" 
                                   accept=".csv,.txt"
                                   required>
                            @error('csv_file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Accepted formats: CSV, TXT (comma-separated values)</small>
                        </div>

                        <!-- File Preview Area -->
                        <div class="mb-3" id="file-preview" style="display: none;">
                            <label class="form-label">File Preview</label>
                            <div class="card">
                                <div class="card-body">
                                    <div id="preview-content"></div>
                                    
                                    <!-- Validation Warnings -->
                                    <div id="validation-warnings" style="display: none;">
                                        <hr>
                                        <div class="alert alert-warning mb-0">
                                            <h6 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Validation Issues Detected</h6>
                                            <div id="warning-list"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('form_submissions.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-upload"></i> Upload & Process CSV
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sample CSV Format -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">Sample CSV Format</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- For Create Operations -->
                        <div class="col-md-6">
                            <h6 class="text-primary">For Create Operations:</h6>
                            <pre class="bg-light p-3 small"><code>name,email,phone,gender,course,grade
John Doe,john@example.com,+1234567890,male,Computer Science,A
Jane Smith,jane@example.com,+0987654321,female,Mathematics,B+
Mike Johnson,mike@example.com,+1122334455,male,Physics,A-</code></pre>
                        </div>

                        <!-- For Update/Delete Operations -->
                        <div class="col-md-6">
                            <h6 class="text-warning">For Update/Delete Operations:</h6>
                            <pre class="bg-light p-3 small"><code>student_id,name,email,phone,gender,course,grade
60f1b2c3d4e5f6789a0b1c2d,John Doe Updated,john.new@example.com,+1234567890,male,Computer Science,A+
60f1b2c3d4e5f6789a0b1c2e,Jane Smith Updated,jane.new@example.com,+0987654321,female,Mathematics,A</code></pre>
                        </div>
                    </div>

                    <div class="mt-3">
                        <h6 class="text-info">All Available Columns:</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <ul class="list-unstyled small">
                                    <li><code>student_id</code> - MongoDB ObjectId</li>
                                    <li><code>name</code> - Full name *</li>
                                    <li><code>email</code> - Email address *</li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <ul class="list-unstyled small">
                                    <li><code>phone</code> - Phone number</li>
                                    <li><code>gender</code> - male or female *</li>
                                    <li><code>address</code> - Full address</li>
                                    <li><code>date_of_birth</code> - YYYY-MM-DD</li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <ul class="list-unstyled small">
                                    <li><code>course</code> - Course name</li>
                                    <li><code>enrollment_date</code> - YYYY-MM-DD</li>
                                    <li><code>grade</code> - Current grade</li>
                                    <li><code>profile_image_path</code> - Image path</li>
                                </ul>
                            </div>
                        </div>
                        <small class="text-muted">* Required fields (except for delete operations)</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const csvFileInput = document.getElementById('csv_file');
    const filePreview = document.getElementById('file-preview');
    const previewContent = document.getElementById('preview-content');
    const validationWarnings = document.getElementById('validation-warnings');
    const warningList = document.getElementById('warning-list');

    csvFileInput.addEventListener('change', function(event) {
        const file = event.target.files[0];
        
        if (file && (file.type === 'text/csv' || file.name.endsWith('.csv') || file.name.endsWith('.txt'))) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const csv = e.target.result;
                const allLines = csv.split('\n').filter(line => line.trim());
                const previewLines = allLines.slice(0, 6); // Show first 5 lines + header
                
                if (allLines.length > 1) {
                    let preview = '<table class="table table-sm table-bordered">';
                    const warnings = [];
                    const emails = new Set();
                    const duplicates = new Set();
                    
                    // Check for duplicates in the entire file
                    if (allLines.length > 1) {
                        const headers = allLines[0].split(',').map(h => h.trim().toLowerCase());
                        const emailIndex = headers.indexOf('email');
                        
                        if (emailIndex !== -1) {
                            for (let i = 1; i < allLines.length; i++) {
                                const cells = allLines[i].split(',');
                                if (cells[emailIndex]) {
                                    const email = cells[emailIndex].trim().toLowerCase();
                                    if (email && email !== 'email') {
                                        if (emails.has(email)) {
                                            duplicates.add(email);
                                        } else {
                                            emails.add(email);
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    // Generate preview table
                    previewLines.forEach((line, index) => {
                        if (line.trim()) {
                            const cells = line.split(',');
                            const tag = index === 0 ? 'th' : 'td';
                            let className = index === 0 ? ' class="table-dark"' : '';
                            
                            // Highlight duplicate email rows
                            if (index > 0 && cells.length > 1) {
                                const email = cells[1] ? cells[1].trim().toLowerCase() : '';
                                if (duplicates.has(email)) {
                                    className = ' class="table-warning"';
                                }
                            }
                            
                            preview += `<tr${className}>`;
                            cells.forEach((cell, cellIndex) => {
                                let cellContent = cell.trim();
                                // Mark duplicate emails in preview
                                if (index > 0 && cellIndex === 1 && duplicates.has(cellContent.toLowerCase())) {
                                    cellContent = `<span class="text-warning"><i class="fas fa-exclamation-triangle"></i> ${cellContent}</span>`;
                                }
                                preview += `<${tag}>${cellContent}</${tag}>`;
                            });
                            preview += '</tr>';
                        }
                    });
                    
                    preview += '</table>';
                    
                    if (allLines.length > 6) {
                        preview += `<small class="text-muted">Showing first 5 rows... File contains ${allLines.length - 1} data rows total.</small>`;
                    }
                    
                    previewContent.innerHTML = preview;
                    
                    // Show validation warnings
                    if (duplicates.size > 0) {
                        let warningHtml = '<p class="mb-2"><strong>Duplicate emails detected:</strong></p><ul class="mb-0">';
                        duplicates.forEach(email => {
                            warningHtml += `<li><code>${email}</code></li>`;
                        });
                        warningHtml += '</ul>';
                        warningHtml += '<p class="mt-2 mb-0"><small><strong>Note:</strong> Duplicate rows will be skipped during processing.</small></p>';
                        
                        warningList.innerHTML = warningHtml;
                        validationWarnings.style.display = 'block';
                    } else {
                        validationWarnings.style.display = 'none';
                    }
                    
                    filePreview.style.display = 'block';
                } else {
                    previewContent.innerHTML = '<p class="text-muted">Unable to preview file content.</p>';
                    validationWarnings.style.display = 'none';
                    filePreview.style.display = 'block';
                }
            };
            
            reader.readAsText(file);
        } else {
            filePreview.style.display = 'none';
            validationWarnings.style.display = 'none';
        }
    });
});
</script>

<style>
pre {
    font-size: 0.8rem;
    line-height: 1.3;
}
.table-sm th,
.table-sm td {
    padding: 0.25rem;
    font-size: 0.8rem;
}
#file-preview .table {
    margin-bottom: 0;
}
</style>
@endsection