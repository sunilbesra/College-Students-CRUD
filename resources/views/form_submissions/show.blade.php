@extends('layouts.app')

@section('title', 'Form Submission Details')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Form Submission Details</h4>
                    <div class="btn-group">
                        <a href="{{ route('form_submissions.edit', $formSubmission) }}" class="btn btn-outline-primary">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="{{ route('form_submissions.index') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row">
                        <!-- Basic Information -->
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0">Basic Information</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <td class="fw-bold">ID:</td>
                                            <td><code>{{ $formSubmission->_id }}</code></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Status:</td>
                                            <td>
                                                @php
                                                    $statusClass = match($formSubmission->status) {
                                                        'queued' => 'warning',
                                                        'processing' => 'info',
                                                        'completed' => 'success',
                                                        'failed' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                @endphp
                                                <span class="badge bg-{{ $statusClass }}">
                                                    {{ ucfirst($formSubmission->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Operation:</td>
                                            <td><span class="badge bg-primary">{{ ucfirst($formSubmission->operation) }}</span></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Source:</td>
                                            <td>
                                                @php
                                                    $sourceClass = match($formSubmission->source) {
                                                        'form' => 'info',
                                                        'api' => 'success',
                                                        'csv' => 'warning',
                                                        default => 'secondary'
                                                    };
                                                @endphp
                                                <span class="badge bg-{{ $sourceClass }}">{{ strtoupper($formSubmission->source) }}</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Student ID:</td>
                                            <td>
                                                @if($formSubmission->student_id)
                                                    <code>{{ $formSubmission->student_id }}</code>
                                                @else
                                                    <span class="text-muted">Not assigned</span>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Timestamps and Meta -->
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0">Timestamps & Meta</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <td class="fw-bold">Created:</td>
                                            <td>{{ $formSubmission->created_at->format('M j, Y H:i:s') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Updated:</td>
                                            <td>{{ $formSubmission->updated_at->format('M j, Y H:i:s') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">IP Address:</td>
                                            <td>{{ $formSubmission->ip_address ?? 'Unknown' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">User Agent:</td>
                                            <td>
                                                @if($formSubmission->user_agent)
                                                    <small class="text-muted" title="{{ $formSubmission->user_agent }}">
                                                        {{ Str::limit($formSubmission->user_agent, 50) }}
                                                    </small>
                                                @else
                                                    <span class="text-muted">Unknown</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @if($formSubmission->user_id)
                                            <tr>
                                                <td class="fw-bold">User ID:</td>
                                                <td><code>{{ $formSubmission->user_id }}</code></td>
                                            </tr>
                                        @endif
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Error Message (if any) -->
                    @if($formSubmission->error_message)
                        <div class="card mb-3">
                            <div class="card-header bg-danger text-white">
                                <h6 class="mb-0">Error Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-danger mb-0">
                                    <pre class="mb-0">{{ $formSubmission->error_message }}</pre>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Student Data -->
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Student Data</h6>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#rawDataCollapse">
                                <i class="fas fa-code"></i> View Raw JSON
                            </button>
                        </div>
                        <div class="card-body">
                            @if(is_array($formSubmission->data) && count($formSubmission->data) > 0)
                                <!-- Profile Image Section (if exists) -->
                                @if(isset($formSubmission->data['profile_image_path']) && $formSubmission->data['profile_image_path'])
                                    <div class="row mb-4">
                                        <div class="col-12">
                                            <div class="card bg-light">
                                                <div class="card-header">
                                                    <h6 class="mb-0 text-primary">
                                                        <i class="fas fa-user-circle"></i> Profile Image
                                                    </h6>
                                                </div>
                                                <div class="card-body text-center">
                                                    <div class="d-inline-block position-relative">
                                                        <img src="{{ asset($formSubmission->data['profile_image_path']) }}" 
                                                             alt="Profile Image for {{ $formSubmission->data['name'] ?? 'Student' }}" 
                                                             class="rounded border border-3 border-primary shadow"
                                                             style="max-width: 200px; max-height: 200px; object-fit: cover; cursor: pointer;"
                                                             onerror="handleImageError(this)"
                                                             data-bs-toggle="modal" 
                                                             data-bs-target="#profileImageModal"
                                                             title="Click to view full size">
                                                        <!-- Image status indicator -->
                                                        <div class="position-absolute top-0 end-0 bg-success text-white rounded-circle p-1 shadow-sm" style="margin-top: -5px; margin-right: -5px;">
                                                            <i class="fas fa-check" style="font-size: 12px;"></i>
                                                        </div>
                                                    </div>
                                                    <div class="mt-2">
                                                        <small class="text-muted">
                                                            <i class="fas fa-info-circle"></i> 
                                                            Profile image for {{ $formSubmission->data['name'] ?? 'Student' }}
                                                            <br>
                                                            <code>{{ $formSubmission->data['profile_image_path'] }}</code>
                                                        </small>
                                                    </div>
                                                    <div class="mt-2">
                                                        <a href="{{ asset($formSubmission->data['profile_image_path']) }}" 
                                                           target="_blank" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-external-link-alt"></i> Open in New Tab
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <div class="row">
                                    @foreach($formSubmission->data as $key => $value)
                                        @if($key !== 'profile_image_path') {{-- Skip profile image path as we display it separately --}}
                                            <div class="col-md-6 mb-2">
                                                <div class="d-flex">
                                                    <strong class="text-muted me-2" style="min-width: 120px;">
                                                        {{ ucwords(str_replace('_', ' ', $key)) }}:
                                                    </strong>
                                                    <span class="flex-grow-1">
                                                        @if(is_array($value))
                                                            <code>{{ json_encode($value) }}</code>
                                                        @elseif(is_bool($value))
                                                            <span class="badge bg-{{ $value ? 'success' : 'secondary' }}">
                                                                {{ $value ? 'True' : 'False' }}
                                                            </span>
                                                        @elseif(is_null($value))
                                                            <span class="text-muted">null</span>
                                                        @elseif($key === 'profile_image' && !empty($value))
                                                            <div>
                                                                {{ $value }}
                                                                @if(file_exists(public_path($value)))
                                                                    <br><img src="{{ asset($value) }}" alt="Profile" style="max-width: 100px; max-height: 100px;" class="mt-1">
                                                                @endif
                                                            </div>
                                                        @elseif(in_array($key, ['email']))
                                                            <a href="mailto:{{ $value }}" class="text-decoration-none">
                                                                <i class="fas fa-envelope text-muted me-1"></i>{{ $value }}
                                                            </a>
                                                        @elseif(in_array($key, ['phone']) && !empty($value))
                                                            <a href="tel:{{ $value }}" class="text-decoration-none">
                                                                <i class="fas fa-phone text-muted me-1"></i>{{ $value }}
                                                            </a>
                                                        @elseif($key === 'gender')
                                                            <span class="badge bg-{{ $value === 'male' ? 'info' : 'pink' }}">
                                                                <i class="fas fa-{{ $value === 'male' ? 'mars' : 'venus' }}"></i> {{ ucfirst($value) }}
                                                            </span>
                                                        @elseif(in_array($key, ['date_of_birth', 'enrollment_date']))
                                                            <span class="text-dark">
                                                                <i class="fas fa-{{ $key === 'date_of_birth' ? 'birthday-cake' : 'calendar-plus' }} text-muted me-1"></i>
                                                                {{ $value }}
                                                            </span>
                                                        @elseif($key === 'grade')
                                                            <span class="badge bg-success">
                                                                <i class="fas fa-graduation-cap"></i> Grade: {{ $value }}
                                                            </span>
                                                        @elseif($key === 'course')
                                                            <span class="badge bg-primary">
                                                                <i class="fas fa-book"></i> {{ $value }}
                                                            </span>
                                                        @elseif($key === 'address')
                                                            <span class="text-dark">
                                                                <i class="fas fa-map-marker-alt text-muted me-1"></i>{{ $value }}
                                                            </span>
                                                        @else
                                                            {{ $value }}
                                                        @endif
                                                    </span>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>

                                <!-- Raw JSON Data (Collapsible) -->
                                <div class="collapse mt-3" id="rawDataCollapse">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <pre class="mb-0 small"><code>{{ json_encode($formSubmission->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="text-center text-muted">
                                    <i class="fas fa-info-circle"></i> No data available
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="btn-group">
                                <a href="{{ route('form_submissions.edit', $formSubmission) }}" class="btn btn-primary">
                                    <i class="fas fa-edit"></i> Edit Submission
                                </a>
                                
                                @if($formSubmission->status === 'failed')
                                    <form action="{{ route('form_submissions.update', $formSubmission) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="operation" value="{{ $formSubmission->operation }}">
                                        <input type="hidden" name="student_id" value="{{ $formSubmission->student_id }}">
                                        <input type="hidden" name="source" value="{{ $formSubmission->source }}">
                                        <input type="hidden" name="status" value="queued">
                                        @foreach($formSubmission->data as $key => $value)
                                            <input type="hidden" name="data[{{ $key }}]" value="{{ is_array($value) ? json_encode($value) : $value }}">
                                        @endforeach
                                        <button type="submit" class="btn btn-warning" onclick="return confirm('Retry processing this submission?')">
                                            <i class="fas fa-redo"></i> Retry Processing
                                        </button>
                                    </form>
                                @endif

                                <form action="{{ route('form_submissions.destroy', $formSubmission) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this submission?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Profile Image Modal -->
@if(isset($formSubmission->data['profile_image_path']) && $formSubmission->data['profile_image_path'])
    <div class="modal fade" id="profileImageModal" tabindex="-1" aria-labelledby="profileImageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="profileImageModalLabel">
                        <i class="fas fa-user-circle text-primary"></i>
                        Profile Image - {{ $formSubmission->data['name'] ?? 'Student' }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="{{ asset($formSubmission->data['profile_image_path']) }}" 
                         alt="Full Profile Image for {{ $formSubmission->data['name'] ?? 'Student' }}" 
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
                                    <strong>Grade:</strong> {{ $formSubmission->data['grade'] ?? 'N/A' }}
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
pre {
    font-size: 0.875rem;
    line-height: 1.4;
}
.table-borderless td {
    padding: 0.25rem 0.5rem;
}
.bg-pink {
    background-color: #e91e63 !important;
    color: white;
}
.profile-image-container {
    position: relative;
    display: inline-block;
}
.profile-image-container img {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.profile-image-container img:hover {
    transform: scale(1.02);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}
</style>

<script>
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
</script>
@endsection