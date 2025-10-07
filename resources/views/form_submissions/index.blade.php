@extends('layouts.app')

@section('title', 'Form Submissions')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Form Submissions</h4>
                    <div class="btn-group">
                        <a href="{{ route('form_submissions.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> New Submission
                        </a>
                        <a href="{{ route('form_submissions.upload_csv') }}" class="btn btn-success">
                            <i class="fas fa-upload"></i> Upload CSV
                        </a>
                        <a href="{{ route('form_submissions.stats') }}" class="btn btn-info" target="_blank">
                            <i class="fas fa-chart-bar"></i> Stats
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Flash Messages -->
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if(session('warning'))
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> {{ session('warning') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-times-circle"></i> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <!-- Duplicate Emails Details -->
                    @if(session('duplicate_emails'))
                        <div class="alert alert-warning" role="alert">
                            <h6 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Duplicate Emails Detected</h6>
                            <p class="mb-2">The following email addresses were found to be duplicates and were skipped:</p>
                            <ul class="mb-0">
                                @foreach(session('duplicate_emails') as $email)
                                    <li><code>{{ $email }}</code></li>
                                @endforeach
                            </ul>
                            <hr>
                            <small class="mb-0">
                                <strong>Note:</strong> For create operations, existing emails in the database are automatically skipped. 
                                For update operations, provide the student_id to update existing records.
                            </small>
                        </div>
                    @endif

                    <!-- Detailed CSV Errors -->
                    @if(session('csv_errors'))
                        <div class="alert alert-danger" role="alert">
                            <h6 class="alert-heading"><i class="fas fa-times-circle"></i> CSV Processing Errors</h6>
                            <p class="mb-2">The following errors occurred during CSV processing:</p>
                            <div style="max-height: 200px; overflow-y: auto;">
                                <ul class="mb-0 small">
                                    @foreach(session('csv_errors') as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            <hr>
                            <small class="mb-0">
                                <strong>Tip:</strong> Check your CSV file format and ensure all required fields are present and valid.
                            </small>
                        </div>
                    @endif

                    <!-- Search and Filter Form -->
                    <form method="GET" action="{{ route('form_submissions.index') }}" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="q" class="form-label">Search</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="q" 
                                       name="q" 
                                       value="{{ $q }}" 
                                       placeholder="Search in data, errors, student ID...">
                            </div>
                            <div class="col-md-2">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="queued" {{ $status === 'queued' ? 'selected' : '' }}>Queued</option>
                                    <option value="processing" {{ $status === 'processing' ? 'selected' : '' }}>Processing</option>
                                    <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Completed</option>
                                    <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>Failed</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="operation" class="form-label">Operation</label>
                                <select class="form-select" id="operation" name="operation">
                                    <option value="">All Operations</option>
                                    <option value="create" {{ $operation === 'create' ? 'selected' : '' }}>Create</option>
                                    <option value="update" {{ $operation === 'update' ? 'selected' : '' }}>Update</option>
                                    <option value="delete" {{ $operation === 'delete' ? 'selected' : '' }}>Delete</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="source" class="form-label">Source</label>
                                <select class="form-select" id="source" name="source">
                                    <option value="">All Sources</option>
                                    <option value="form" {{ $source === 'form' ? 'selected' : '' }}>Form</option>
                                    <option value="api" {{ $source === 'api' ? 'selected' : '' }}>API</option>
                                    <option value="csv" {{ $source === 'csv' ? 'selected' : '' }}>CSV</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="per_page" class="form-label">Per Page</label>
                                <select class="form-select" id="per_page" name="per_page">
                                    <option value="5" {{ $perPage == 5 ? 'selected' : '' }}>5</option>
                                    <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10</option>
                                    <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                                    <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                                </select>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Results Summary -->
                    <div class="mb-3">
                        <small class="text-muted">
                            Showing {{ $submissions->firstItem() ?? 0 }} to {{ $submissions->lastItem() ?? 0 }} 
                            of {{ $submissions->total() }} results
                        </small>
                    </div>

                    @if($submissions->count() > 0)
                        <!-- Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Status</th>
                                        <th>Operation</th>
                                        <th>Source</th>
                                        <th style="width: 400px;">Full Data Preview</th>
                                        <th>Error</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($submissions as $submission)
                                        <tr>
                                            <td>
                                                <small class="text-muted">{{ substr($submission->_id, -8) }}</small>
                                            </td>
                                            <td>
                                                @php
                                                    $statusClass = match($submission->status) {
                                                        'queued' => 'warning',
                                                        'processing' => 'info',
                                                        'completed' => 'success',
                                                        'failed' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                @endphp
                                                <span class="badge bg-{{ $statusClass }}">
                                                    {{ ucfirst($submission->status) }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary">{{ ucfirst($submission->operation) }}</span>
                                            </td>
                                            <td>
                                                @php
                                                    $sourceClass = match($submission->source) {
                                                        'form' => 'info',
                                                        'api' => 'success',
                                                        'csv' => 'warning',
                                                        default => 'secondary'
                                                    };
                                                @endphp
                                                <span class="badge bg-{{ $sourceClass }}">{{ strtoupper($submission->source) }}</span>
                                            </td>

                                            <td style="max-width: 400px;">
                                                @if(is_array($submission->data))
                                                    <div class="d-flex align-items-start">
                                                        @if(isset($submission->data['profile_image_path']) && $submission->data['profile_image_path'])
                                                            <img src="{{ asset($submission->data['profile_image_path']) }}" 
                                                                 alt="Profile" 
                                                                 class="rounded-circle me-2 flex-shrink-0" 
                                                                 style="width: 40px; height: 40px; object-fit: cover;"
                                                                 onerror="this.style.display='none'">
                                                        @endif
                                                        <div class="flex-grow-1">
                                                            <div class="mb-1">
                                                                <strong class="text-primary">{{ $submission->data['name'] ?? 'N/A' }}</strong>
                                                                @if(isset($submission->data['gender']))
                                                                    <span class="badge bg-{{ $submission->data['gender'] === 'male' ? 'info' : 'pink' }} ms-1">
                                                                        {{ ucfirst($submission->data['gender']) }}
                                                                    </span>
                                                                @endif
                                                            </div>
                                                            <div class="small text-muted mb-1">
                                                                <i class="fas fa-envelope"></i> {{ $submission->data['email'] ?? 'N/A' }}
                                                            </div>
                                                            @if(isset($submission->data['phone']))
                                                                <div class="small text-muted mb-1">
                                                                    <i class="fas fa-phone"></i> {{ $submission->data['phone'] }}
                                                                </div>
                                                            @endif
                                                            <div class="small">
                                                                @if(isset($submission->data['course']))
                                                                    <span class="badge bg-secondary me-1">{{ $submission->data['course'] }}</span>
                                                                @endif
                                                                @if(isset($submission->data['grade']))
                                                                    <span class="badge bg-success me-1">Grade: {{ $submission->data['grade'] }}</span>
                                                                @endif
                                                            </div>
                                                            @if(isset($submission->data['date_of_birth']) || isset($submission->data['enrollment_date']))
                                                                <div class="small text-muted mt-1">
                                                                    @if(isset($submission->data['date_of_birth']))
                                                                        <i class="fas fa-birthday-cake"></i> {{ $submission->data['date_of_birth'] }}
                                                                    @endif
                                                                    @if(isset($submission->data['enrollment_date']))
                                                                        | <i class="fas fa-calendar-plus"></i> {{ $submission->data['enrollment_date'] }}
                                                                    @endif
                                                                </div>
                                                            @endif
                                                            @if(isset($submission->data['address']))
                                                                <div class="small text-muted mt-1">
                                                                    <i class="fas fa-map-marker-alt"></i> {{ Str::limit($submission->data['address'], 50) }}
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @else
                                                    <small class="text-muted">—</small>
                                                @endif
                                            </td>
                                            <td>
                                                @if($submission->error_message)
                                                    <small class="text-danger" title="{{ $submission->error_message }}">
                                                        {{ Str::limit($submission->error_message, 30) }}
                                                    </small>
                                                @else
                                                    <small class="text-muted">—</small>
                                                @endif
                                            </td>
                                            <td>
                                                <small class="text-muted">{{ $submission->created_at->format('M j, Y H:i') }}</small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="{{ route('form_submissions.show', $submission) }}" 
                                                       class="btn btn-outline-primary btn-sm" 
                                                       title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="{{ route('form_submissions.edit', $submission) }}" 
                                                       class="btn btn-outline-secondary btn-sm" 
                                                       title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form action="{{ route('form_submissions.destroy', $submission) }}" 
                                                          method="POST" 
                                                          class="d-inline"
                                                          onsubmit="return confirm('Are you sure you want to delete this submission?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" 
                                                                class="btn btn-outline-danger btn-sm" 
                                                                title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="d-flex justify-content-center">
                            {{ $submissions->links() }}
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No form submissions found</h5>
                            <p class="text-muted">Try adjusting your search criteria or create a new submission.</p>
                            <a href="{{ route('form_submissions.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Create New Submission
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.table th {
    white-space: nowrap;
}
.badge {
    font-size: 0.75em;
}
.btn-group-sm > .btn {
    padding: 0.125rem 0.25rem;
}
.bg-pink {
    background-color: #e91e63 !important;
    color: white;
}
.table td {
    vertical-align: middle;
}
.flex-shrink-0 {
    flex-shrink: 0;
}
.flex-grow-1 {
    flex-grow: 1;
}
</style>
@endsection