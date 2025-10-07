@extends('layouts.app')

@section('title', 'Edit Form Submission')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Edit Form Submission</h4>
                    <div class="btn-group">
                        <a href="{{ route('form_submissions.show', $formSubmission) }}" class="btn btn-outline-info">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <a href="{{ route('form_submissions.index') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <form action="{{ route('form_submissions.update', $formSubmission) }}" method="POST" id="submissionForm">
                        @csrf
                        @method('PUT')

                        <!-- Operation Type -->
                        <div class="mb-3">
                            <label for="operation" class="form-label">Operation Type <span class="text-danger">*</span></label>
                            <select class="form-select @error('operation') is-invalid @enderror" 
                                    id="operation" 
                                    name="operation" 
                                    required>
                                <option value="">Select Operation</option>
                                <option value="create" {{ old('operation', $formSubmission->operation) === 'create' ? 'selected' : '' }}>Create Student</option>
                                <option value="update" {{ old('operation', $formSubmission->operation) === 'update' ? 'selected' : '' }}>Update Student</option>
                                <option value="delete" {{ old('operation', $formSubmission->operation) === 'delete' ? 'selected' : '' }}>Delete Student</option>
                            </select>
                            @error('operation')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Source -->
                        <div class="mb-3">
                            <label for="source" class="form-label">Source <span class="text-danger">*</span></label>
                            <select class="form-select @error('source') is-invalid @enderror" 
                                    id="source" 
                                    name="source" 
                                    required>
                                <option value="">Select Source</option>
                                <option value="form" {{ old('source', $formSubmission->source) === 'form' ? 'selected' : '' }}>Form</option>
                                <option value="api" {{ old('source', $formSubmission->source) === 'api' ? 'selected' : '' }}>API</option>
                                <option value="csv" {{ old('source', $formSubmission->source) === 'csv' ? 'selected' : '' }}>CSV</option>
                            </select>
                            @error('source')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Status -->
                        <div class="mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select @error('status') is-invalid @enderror" 
                                    id="status" 
                                    name="status" 
                                    required>
                                <option value="">Select Status</option>
                                <option value="queued" {{ old('status', $formSubmission->status) === 'queued' ? 'selected' : '' }}>Queued</option>
                                <option value="processing" {{ old('status', $formSubmission->status) === 'processing' ? 'selected' : '' }}>Processing</option>
                                <option value="completed" {{ old('status', $formSubmission->status) === 'completed' ? 'selected' : '' }}>Completed</option>
                                <option value="failed" {{ old('status', $formSubmission->status) === 'failed' ? 'selected' : '' }}>Failed</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Setting status to "queued" will reprocess the submission.</small>
                        </div>

                        <!-- Student ID -->
                        <div class="mb-3" id="student_id_section">
                            <label for="student_id" class="form-label">Student ID</label>
                            <input type="text" 
                                   class="form-control @error('student_id') is-invalid @enderror" 
                                   id="student_id" 
                                   name="student_id" 
                                   value="{{ old('student_id', $formSubmission->student_id) }}"
                                   placeholder="Enter student ID">
                            @error('student_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Required for update and delete operations. Optional for create.</small>
                        </div>

                        <!-- Error Message -->
                        @if($formSubmission->status === 'failed')
                            <div class="mb-3">
                                <label for="error_message" class="form-label">Error Message</label>
                                <textarea class="form-control @error('error_message') is-invalid @enderror" 
                                          id="error_message" 
                                          name="error_message" 
                                          rows="3"
                                          readonly>{{ old('error_message', $formSubmission->error_message) }}</textarea>
                                @error('error_message')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">Read-only field showing the last error.</small>
                            </div>
                        @endif

                        <!-- Student Data Section -->
                        <div class="card mb-3" id="student_data_section">
                            <div class="card-header">
                                <h6 class="mb-0">Student Data</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <!-- Name -->
                                    <div class="col-md-6 mb-3">
                                        <label for="data_name" class="form-label">Name <span class="text-danger">*</span></label>
                                        <input type="text" 
                                               class="form-control @error('data.name') is-invalid @enderror" 
                                               id="data_name" 
                                               name="data[name]" 
                                               value="{{ old('data.name', $formSubmission->data['name'] ?? '') }}"
                                               required>
                                        @error('data.name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Email -->
                                    <div class="col-md-6 mb-3">
                                        <label for="data_email" class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" 
                                               class="form-control @error('data.email') is-invalid @enderror" 
                                               id="data_email" 
                                               name="data[email]" 
                                               value="{{ old('data.email', $formSubmission->data['email'] ?? '') }}"
                                               required>
                                        @error('data.email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Phone -->
                                    <div class="col-md-6 mb-3">
                                        <label for="data_phone" class="form-label">Phone</label>
                                        <input type="tel" 
                                               class="form-control @error('data.phone') is-invalid @enderror" 
                                               id="data_phone" 
                                               name="data[phone]" 
                                               value="{{ old('data.phone', $formSubmission->data['phone'] ?? '') }}">
                                        @error('data.phone')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Date of Birth -->
                                    <div class="col-md-6 mb-3">
                                        <label for="data_date_of_birth" class="form-label">Date of Birth</label>
                                        <input type="date" 
                                               class="form-control @error('data.date_of_birth') is-invalid @enderror" 
                                               id="data_date_of_birth" 
                                               name="data[date_of_birth]" 
                                               value="{{ old('data.date_of_birth', $formSubmission->data['date_of_birth'] ?? '') }}">
                                        @error('data.date_of_birth')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Course -->
                                    <div class="col-md-6 mb-3">
                                        <label for="data_course" class="form-label">Course</label>
                                        <input type="text" 
                                               class="form-control @error('data.course') is-invalid @enderror" 
                                               id="data_course" 
                                               name="data[course]" 
                                               value="{{ old('data.course', $formSubmission->data['course'] ?? '') }}">
                                        @error('data.course')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Enrollment Date -->
                                    <div class="col-md-6 mb-3">
                                        <label for="data_enrollment_date" class="form-label">Enrollment Date</label>
                                        <input type="date" 
                                               class="form-control @error('data.enrollment_date') is-invalid @enderror" 
                                               id="data_enrollment_date" 
                                               name="data[enrollment_date]" 
                                               value="{{ old('data.enrollment_date', $formSubmission->data['enrollment_date'] ?? '') }}">
                                        @error('data.enrollment_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Grade -->
                                    <div class="col-md-6 mb-3">
                                        <label for="data_grade" class="form-label">Grade</label>
                                        <input type="text" 
                                               class="form-control @error('data.grade') is-invalid @enderror" 
                                               id="data_grade" 
                                               name="data[grade]" 
                                               value="{{ old('data.grade', $formSubmission->data['grade'] ?? '') }}">
                                        @error('data.grade')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Profile Image -->
                                    <div class="col-md-6 mb-3">
                                        <label for="data_profile_image" class="form-label">Profile Image Path</label>
                                        <input type="text" 
                                               class="form-control @error('data.profile_image') is-invalid @enderror" 
                                               id="data_profile_image" 
                                               name="data[profile_image]" 
                                               value="{{ old('data.profile_image', $formSubmission->data['profile_image'] ?? '') }}"
                                               placeholder="e.g., uploads/image.jpg">
                                        @error('data.profile_image')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        @if(!empty($formSubmission->data['profile_image']) && file_exists(public_path($formSubmission->data['profile_image'])))
                                            <div class="mt-2">
                                                <img src="{{ asset($formSubmission->data['profile_image']) }}" 
                                                     alt="Current Profile" 
                                                     style="max-width: 100px; max-height: 100px;">
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Address -->
                                    <div class="col-12 mb-3">
                                        <label for="data_address" class="form-label">Address</label>
                                        <textarea class="form-control @error('data.address') is-invalid @enderror" 
                                                  id="data_address" 
                                                  name="data[address]" 
                                                  rows="3">{{ old('data.address', $formSubmission->data['address'] ?? '') }}</textarea>
                                        @error('data.address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('form_submissions.show', $formSubmission) }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Submission
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const operationSelect = document.getElementById('operation');
    const studentIdSection = document.getElementById('student_id_section');
    const studentDataSection = document.getElementById('student_data_section');
    const nameField = document.getElementById('data_name');
    const emailField = document.getElementById('data_email');

    function toggleSections() {
        const operation = operationSelect.value;
        
        if (operation === 'update' || operation === 'delete') {
            studentIdSection.style.display = 'block';
        } else {
            studentIdSection.style.display = 'none';
        }

        if (operation === 'delete') {
            studentDataSection.style.display = 'none';
            nameField.required = false;
            emailField.required = false;
        } else {
            studentDataSection.style.display = 'block';
            nameField.required = true;
            emailField.required = true;
        }
    }

    operationSelect.addEventListener('change', toggleSections);
    toggleSections(); // Initial call
});
</script>
@endsection