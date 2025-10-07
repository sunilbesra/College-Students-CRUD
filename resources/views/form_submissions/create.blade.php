@extends('layouts.app')

@section('title', 'Create Form Submission')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Create New Form Submission</h4>
                    <a href="{{ route('form_submissions.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>

                <div class="card-body">
                    <form action="{{ route('form_submissions.store') }}" method="POST" enctype="multipart/form-data" id="submissionForm">
                        @csrf

                        <!-- Operation Type -->
                        <div class="mb-3">
                            <label for="operation" class="form-label">Operation Type <span class="text-danger">*</span></label>
                            <select class="form-select @error('operation') is-invalid @enderror" 
                                    id="operation" 
                                    name="operation" 
                                    required>
                                <option value="">Select Operation</option>
                                <option value="create" {{ old('operation') === 'create' ? 'selected' : '' }}>Create Student</option>
                                <option value="update" {{ old('operation') === 'update' ? 'selected' : '' }}>Update Student</option>
                                <option value="delete" {{ old('operation') === 'delete' ? 'selected' : '' }}>Delete Student</option>
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
                                <option value="form" {{ old('source') === 'form' ? 'selected' : '' }}>Form</option>
                                <option value="api" {{ old('source') === 'api' ? 'selected' : '' }}>API</option>
                                <option value="csv" {{ old('source') === 'csv' ? 'selected' : '' }}>CSV</option>
                            </select>
                            @error('source')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Student ID (for update/delete operations) -->
                        <div class="mb-3" id="student_id_section" style="display: none;">
                            <label for="student_id" class="form-label">Student ID</label>
                            <input type="text" 
                                   class="form-control @error('student_id') is-invalid @enderror" 
                                   id="student_id" 
                                   name="student_id" 
                                   value="{{ old('student_id') }}"
                                   placeholder="Enter student ID (for update/delete operations)">
                            @error('student_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Required for update and delete operations. Optional for create (will be generated).</small>
                        </div>

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
                                               value="{{ old('data.name') }}"
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
                                               value="{{ old('data.email') }}"
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
                                               value="{{ old('data.phone') }}">
                                        @error('data.phone')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Gender -->
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Gender <span class="text-danger">*</span></label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input class="form-check-input @error('data.gender') is-invalid @enderror" 
                                                       type="radio" 
                                                       name="data[gender]" 
                                                       id="gender_male" 
                                                       value="male" 
                                                       {{ old('data.gender') === 'male' ? 'checked' : '' }}
                                                       required>
                                                <label class="form-check-label" for="gender_male">
                                                    <i class="fas fa-mars text-primary"></i> Male
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input @error('data.gender') is-invalid @enderror" 
                                                       type="radio" 
                                                       name="data[gender]" 
                                                       id="gender_female" 
                                                       value="female" 
                                                       {{ old('data.gender') === 'female' ? 'checked' : '' }}
                                                       required>
                                                <label class="form-check-label" for="gender_female">
                                                    <i class="fas fa-venus text-danger"></i> Female
                                                </label>
                                            </div>
                                        </div>
                                        @error('data.gender')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Date of Birth -->
                                    <div class="col-md-6 mb-3">
                                        <label for="data_date_of_birth" class="form-label">Date of Birth</label>
                                        <input type="date" 
                                               class="form-control @error('data.date_of_birth') is-invalid @enderror" 
                                               id="data_date_of_birth" 
                                               name="data[date_of_birth]" 
                                               value="{{ old('data.date_of_birth') }}">
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
                                               value="{{ old('data.course') }}">
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
                                               value="{{ old('data.enrollment_date') }}">
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
                                               value="{{ old('data.grade') }}">
                                        @error('data.grade')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Profile Image Upload -->
                                    <div class="col-md-6 mb-3">
                                        <label for="profile_image_upload" class="form-label">Profile Image</label>
                                        <input type="file" 
                                               class="form-control @error('profile_image') is-invalid @enderror" 
                                               id="profile_image_upload" 
                                               name="profile_image" 
                                               accept="image/*"
                                               onchange="previewImage(this)">
                                        @error('profile_image')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="form-text text-muted">Accepted formats: JPG, PNG, GIF (max 2MB)</small>
                                        
                                        <!-- Image Preview -->
                                        <div id="image-preview" class="mt-2" style="display: none;">
                                            <img id="preview-img" src="" alt="Preview" class="img-thumbnail" style="max-width: 150px; max-height: 150px;">
                                            <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="clearImagePreview()">Remove</button>
                                        </div>
                                    </div>

                                    <!-- Address -->
                                    <div class="col-12 mb-3">
                                        <label for="data_address" class="form-label">Address</label>
                                        <textarea class="form-control @error('data.address') is-invalid @enderror" 
                                                  id="data_address" 
                                                  name="data[address]" 
                                                  rows="3">{{ old('data.address') }}</textarea>
                                        @error('data.address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('form_submissions.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Create Submission
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

// Image preview functions
function previewImage(input) {
    const file = input.files[0];
    const preview = document.getElementById('image-preview');
    const previewImg = document.getElementById('preview-img');
    
    if (file) {
        // Validate file size (2MB = 2 * 1024 * 1024 bytes)
        if (file.size > 2 * 1024 * 1024) {
            alert('File size must be less than 2MB');
            input.value = '';
            preview.style.display = 'none';
            return;
        }
        
        // Validate file type
        if (!file.type.startsWith('image/')) {
            alert('Please select a valid image file');
            input.value = '';
            preview.style.display = 'none';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        preview.style.display = 'none';
    }
}

function clearImagePreview() {
    const input = document.getElementById('profile_image_upload');
    const preview = document.getElementById('image-preview');
    
    input.value = '';
    preview.style.display = 'none';
}
</script>
@endsection