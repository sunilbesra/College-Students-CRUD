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
                                <div class="row">
                                    @foreach($formSubmission->data as $key => $value)
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
                                                        <a href="mailto:{{ $value }}">{{ $value }}</a>
                                                    @elseif(in_array($key, ['phone']) && !empty($value))
                                                        <a href="tel:{{ $value }}">{{ $value }}</a>
                                                    @else
                                                        {{ $value }}
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
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

<style>
pre {
    font-size: 0.875rem;
    line-height: 1.4;
}
.table-borderless td {
    padding: 0.25rem 0.5rem;
}
</style>
@endsection