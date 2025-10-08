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
                    <form action="{{ route('form_submissions.update', $formSubmission) }}" method="POST" id="submissionForm" enctype="multipart/form-data">
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
                                        <input type="number" 
                                               class="form-control @error('data.phone') is-invalid @enderror" 
                                               id="data_phone" 
                                               name="data[phone]" 
                                               value="{{ old('data.phone', $formSubmission->data['phone'] ?? '') }}"
                                               min="1000000000"
                                               max="99999999999999"
                                               placeholder="e.g., 1234567890">
                                        @error('data.phone')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="form-text text-muted">
                                            <i class="fas fa-info-circle"></i> 
                                            Enter digits only (10-14 digits, no symbols or spaces)
                                        </small>
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
                                    <div class="col-12 mb-3">
                                        <label class="form-label">Profile Image</label>
                                        
                                        <!-- Current Image Display -->
                                        @if(isset($formSubmission->data['profile_image_path']) && $formSubmission->data['profile_image_path'])
                                            <div class="current-image-section mb-3">
                                                <h6 class="text-muted mb-2">
                                                    <i class="fas fa-image text-primary"></i> Current Profile Image
                                                </h6>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="position-relative">
                                                        <img src="{{ asset($formSubmission->data['profile_image_path']) }}" 
                                                             alt="Current Profile" 
                                                             class="rounded border border-2 border-primary shadow-sm"
                                                             style="width: 120px; height: 120px; object-fit: cover;"
                                                             onerror="handleImageError(this)"
                                                             data-bs-toggle="modal" 
                                                             data-bs-target="#currentImageModal"
                                                             title="Click to view full size">
                                                        <div class="position-absolute top-0 end-0 bg-success text-white rounded-circle p-1 shadow-sm" style="margin-top: -5px; margin-right: -5px;">
                                                            <i class="fas fa-check" style="font-size: 10px;"></i>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <small class="text-muted d-block">
                                                            <strong>Current Path:</strong> 
                                                            <code>{{ $formSubmission->data['profile_image_path'] }}</code>
                                                        </small>
                                                        <small class="text-muted d-block">
                                                            <strong>Student:</strong> {{ $formSubmission->data['name'] ?? 'N/A' }}
                                                        </small>
                                                        <div class="mt-2">
                                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeCurrentImage()">
                                                                <i class="fas fa-trash"></i> Remove Current Image
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        <!-- File Upload Section -->
                                        <div class="upload-section">
                                            <h6 class="text-muted mb-2">
                                                <i class="fas fa-upload text-success"></i> 
                                                {{ isset($formSubmission->data['profile_image_path']) ? 'Upload New Image' : 'Upload Profile Image' }}
                                            </h6>
                                            
                                            <div class="row">
                                                <!-- File Upload Input -->
                                                <div class="col-md-8">
                                                    <input type="file" 
                                                           class="form-control @error('profile_image') is-invalid @enderror" 
                                                           id="profile_image_upload" 
                                                           name="profile_image" 
                                                           accept="image/*"
                                                           onchange="previewUploadedImage(event)">
                                                    @error('profile_image')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                    <small class="form-text text-muted">
                                                        <i class="fas fa-info-circle"></i> 
                                                        Supported formats: JPG, JPEG, PNG, GIF (Max: 2MB)
                                                    </small>
                                                </div>

                                                <!-- Manual Path Input (Alternative) -->
                                                <div class="col-md-4">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="toggleManualPath()">
                                                        <i class="fas fa-keyboard"></i> Enter Path Manually
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Manual Path Input (Hidden by default) -->
                                            <div id="manual-path-section" class="mt-3" style="display: none;">
                                                <label for="data_profile_image_path" class="form-label">Profile Image Path (Manual Entry)</label>
                                                <input type="text" 
                                                       class="form-control @error('data.profile_image_path') is-invalid @enderror" 
                                                       id="data_profile_image_path" 
                                                       name="data[profile_image_path]" 
                                                       value="{{ old('data.profile_image_path', $formSubmission->data['profile_image_path'] ?? '') }}"
                                                       placeholder="e.g., uploads/profile-images/image.jpg">
                                                @error('data.profile_image_path')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                                <small class="form-text text-muted">
                                                    Enter the full path to an existing image file
                                                </small>
                                            </div>

                                            <!-- Image Preview for New Upload -->
                                            <div id="new-image-preview" class="mt-3" style="display: none;">
                                                <h6 class="text-muted mb-2">
                                                    <i class="fas fa-eye text-info"></i> New Image Preview
                                                </h6>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="position-relative">
                                                        <img id="preview-img" 
                                                             src="" 
                                                             alt="New Image Preview" 
                                                             class="rounded border border-2 border-info shadow-sm"
                                                             style="width: 120px; height: 120px; object-fit: cover;">
                                                        <div class="position-absolute top-0 end-0 bg-info text-white rounded-circle p-1 shadow-sm" style="margin-top: -5px; margin-right: -5px;">
                                                            <i class="fas fa-plus" style="font-size: 10px;"></i>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <small class="text-muted d-block">
                                                            <strong>New Image Selected:</strong> <span id="selected-filename"></span>
                                                        </small>
                                                        <small class="text-muted d-block">
                                                            <strong>Size:</strong> <span id="selected-filesize"></span>
                                                        </small>
                                                        <div class="mt-2">
                                                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="clearImagePreview()">
                                                                <i class="fas fa-times"></i> Clear Selection
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
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

<!-- Current Image Modal -->
@if(isset($formSubmission->data['profile_image_path']) && $formSubmission->data['profile_image_path'])
    <div class="modal fade" id="currentImageModal" tabindex="-1" aria-labelledby="currentImageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="currentImageModalLabel">
                        <i class="fas fa-user-circle text-primary"></i>
                        Current Profile Image - {{ $formSubmission->data['name'] ?? 'Student' }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="{{ asset($formSubmission->data['profile_image_path']) }}" 
                         alt="Full Current Profile Image" 
                         class="img-fluid rounded border shadow"
                         style="max-height: 80vh; width: auto;">
                    <div class="mt-3">
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted d-block">
                                    <strong>Student:</strong> {{ $formSubmission->data['name'] ?? 'N/A' }}
                                </small>
                                <small class="text-muted d-block">
                                    <strong>Email:</strong> {{ $formSubmission->data['email'] ?? 'N/A' }}
                                </small>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">
                                    <strong>Course:</strong> {{ $formSubmission->data['course'] ?? 'N/A' }}
                                </small>
                                <small class="text-muted d-block">
                                    <strong>Submission ID:</strong> {{ substr($formSubmission->_id, -8) }}
                                </small>
                            </div>
                        </div>
                        <div class="mt-2">
                            <code class="bg-light p-1 rounded">{{ $formSubmission->data['profile_image_path'] }}</code>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="{{ asset($formSubmission->data['profile_image_path']) }}" 
                       target="_blank" 
                       class="btn btn-primary">
                        <i class="fas fa-external-link-alt"></i> Open in New Tab
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endif

<style>
.current-image-section {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 1rem;
}

.upload-section {
    background: #fff;
    border: 2px dashed #dee2e6;
    border-radius: 0.375rem;
    padding: 1rem;
    transition: border-color 0.2s ease;
}

.upload-section:hover {
    border-color: #adb5bd;
}

#new-image-preview {
    background: #f8f9fa;
    border: 1px solid #b3d7ff;
    border-radius: 0.375rem;
    padding: 1rem;
}

.position-relative img {
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.position-relative img:hover {
    transform: scale(1.02);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}
</style>

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

// Image handling functions
function previewUploadedImage(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('new-image-preview');
    const previewImg = document.getElementById('preview-img');
    const filename = document.getElementById('selected-filename');
    const filesize = document.getElementById('selected-filesize');

    if (file) {
        // Validate file type
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!validTypes.includes(file.type)) {
            alert('Please select a valid image file (JPG, JPEG, PNG, or GIF)');
            event.target.value = '';
            return;
        }

        // Validate file size (2MB limit)
        const maxSize = 2 * 1024 * 1024; // 2MB in bytes
        if (file.size > maxSize) {
            alert('File size must be less than 2MB');
            event.target.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            filename.textContent = file.name;
            filesize.textContent = formatFileSize(file.size);
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        preview.style.display = 'none';
    }
}

function clearImagePreview() {
    const fileInput = document.getElementById('profile_image_upload');
    const preview = document.getElementById('new-image-preview');
    
    fileInput.value = '';
    preview.style.display = 'none';
}

function toggleManualPath() {
    const manualSection = document.getElementById('manual-path-section');
    const isHidden = manualSection.style.display === 'none';
    
    manualSection.style.display = isHidden ? 'block' : 'none';
    
    if (isHidden) {
        document.getElementById('data_profile_image_path').focus();
    }
}

function removeCurrentImage() {
    if (confirm('Are you sure you want to remove the current profile image? This action cannot be undone.')) {
        // Set a hidden input to indicate image removal
        const form = document.getElementById('submissionForm');
        let removeInput = document.getElementById('remove_current_image');
        if (!removeInput) {
            removeInput = document.createElement('input');
            removeInput.type = 'hidden';
            removeInput.id = 'remove_current_image';
            removeInput.name = 'remove_current_image';
            form.appendChild(removeInput);
        }
        removeInput.value = '1';
        
        // Hide the current image section
        const currentSection = document.querySelector('.current-image-section');
        if (currentSection) {
            currentSection.style.display = 'none';
        }
        
        // Show success message
        alert('Current image will be removed when you save the form.');
    }
}

function handleImageError(img) {
    img.src = '{{ asset("images/default-avatar.svg") }}';
    img.onerror = null; // Prevent infinite loop
    img.title = 'Default avatar - Image not found';
    
    // Update status indicator to show error
    const indicator = img.parentElement.querySelector('.position-absolute');
    if (indicator) {
        indicator.className = 'position-absolute top-0 end-0 bg-warning text-white rounded-circle p-1 shadow-sm';
        indicator.innerHTML = '<i class="fas fa-exclamation" style="font-size: 12px;"></i>';
        indicator.title = 'Image not found - showing default';
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}
</script>
@endsection