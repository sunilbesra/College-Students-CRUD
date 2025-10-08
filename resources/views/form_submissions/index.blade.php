@extends('layouts.app')

@section('title', 'Form Submissions')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <h4 class="mb-0 me-3">Form Submissions</h4>
                    </div>
                    <div class="btn-group">
                        <!-- Refresh controls -->
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="manualRefresh()" id="manual-refresh-btn" title="Manual Refresh">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <button type="button" class="btn btn-outline-warning btn-sm" onclick="pausePolling()" id="pause-polling-btn" title="Pause Auto-refresh">
                            <i class="fas fa-pause"></i> Pause
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm" onclick="resumePolling()" id="resume-polling-btn" style="display: none;" title="Resume Auto-refresh">
                            <i class="fas fa-play"></i> Resume
                        </button>
                        
                        <!-- Original buttons -->
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
                                        <!-- <th>Status</th> -->
                                        <th>Operation</th>
                                        <th>Source</th>
                                        <th style="width: 400px;">Full Data Preview</th>
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
                                            <!-- <td>
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
                                            </td> -->
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
                                                        <!-- Enhanced Profile Image Display -->
                                                        <div class="me-2 flex-shrink-0 position-relative">
                                                            @if(isset($submission->data['profile_image_path']) && $submission->data['profile_image_path'])
                                                                <img src="{{ asset($submission->data['profile_image_path']) }}" 
                                                                     alt="Profile Image for {{ $submission->data['name'] ?? 'Student' }}" 
                                                                     class="rounded-circle border border-2 border-light shadow-sm" 
                                                                     style="width: 50px; height: 50px; object-fit: cover; cursor: pointer;"
                                                                     onerror="handleImageError(this)"
                                                                     data-bs-toggle="modal" 
                                                                     data-bs-target="#imageModal{{ $submission->_id }}"
                                                                     title="Click to view full image">
                                                                <!-- Status indicator for image -->
                                                                <div class="position-absolute bottom-0 end-0">
                                                                    <i class="fas fa-camera text-success bg-white rounded-circle p-1" 
                                                                       style="font-size: 10px; border: 1px solid #dee2e6;"></i>
                                                                </div>
                                                            @else
                                                                <!-- Default avatar when no image -->
                                                                <div class="rounded-circle bg-light border border-2 border-secondary d-flex align-items-center justify-content-center"
                                                                     style="width: 50px; height: 50px;">
                                                                    <i class="fas fa-user text-muted"></i>
                                                                </div>
                                                                <!-- No image indicator -->
                                                                <div class="position-absolute bottom-0 end-0">
                                                                    <i class="fas fa-times text-danger bg-white rounded-circle p-1" 
                                                                       style="font-size: 10px; border: 1px solid #dee2e6;"></i>
                                                                </div>
                                                            @endif
                                                        </div>

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

<!-- Image Modal for Full-Size Preview -->
@foreach($submissions as $submission)
    @if(isset($submission->data['profile_image_path']) && $submission->data['profile_image_path'])
        <div class="modal fade" id="imageModal{{ $submission->_id }}" tabindex="-1" aria-labelledby="imageModalLabel{{ $submission->_id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="imageModalLabel{{ $submission->_id }}">
                            Profile Image - {{ $submission->data['name'] ?? 'Student' }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img src="{{ asset($submission->data['profile_image_path']) }}" 
                             alt="Full Profile Image for {{ $submission->data['name'] ?? 'Student' }}" 
                             class="img-fluid rounded border"
                             style="max-height: 70vh; width: auto;">
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> 
                                Profile image for {{ $submission->data['name'] ?? 'Student' }}
                                <br>
                                <span class="badge bg-light text-dark">{{ $submission->data['email'] ?? 'No email' }}</span>
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="{{ asset($submission->data['profile_image_path']) }}" 
                           target="_blank" 
                           class="btn btn-primary btn-sm">
                            <i class="fas fa-external-link-alt"></i> Open in New Tab
                        </a>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endforeach

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

/* Enhanced Profile Image Styles */
.profile-image-container {
    position: relative;
    display: inline-block;
}

.profile-image-container img {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.profile-image-container img:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.profile-status-indicator {
    position: absolute;
    bottom: -2px;
    right: -2px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.default-avatar {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 2px dashed #dee2e6;
    transition: all 0.2s ease;
}

.default-avatar:hover {
    background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
    border-color: #adb5bd;
}

/* Modal Image Styles */
.modal .img-fluid {
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.modal-body .badge {
    font-size: 0.8em;
}
</style>

<script>
function handleImageError(img) {
    img.src = '{{ asset("images/default-avatar.svg") }}';
    img.onerror = null; // Prevent infinite loop
    img.title = 'Default avatar - Image not found';
}

// Simple and reliable auto-refresh system
let currentCount = 0;
let isAutoRefreshOn = true;
let refreshInterval = null;

console.log('🔄 Auto-refresh system loading...');

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Auto-refresh system starting...');
    
    // Get initial count
    getCurrentCount();
    
    // Start auto-refresh
    startAutoRefresh();
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', stopAutoRefresh);
});

function getCurrentCount() {
    fetch('{{ route("form_submissions.latest") }}')
        .then(response => response.json())
        .then(data => {
            currentCount = data.total_count;
            console.log('📊 Current total count:', currentCount);
        })
        .catch(error => console.log('Count check failed:', error));
}

function startAutoRefresh() {
    if (refreshInterval) return;
    
    isAutoRefreshOn = true;
    console.log('✅ Auto-refresh started');
    
    // Check every 1 second for new data
    refreshInterval = setInterval(checkForNewData, 100);
}

function stopAutoRefresh() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
    isAutoRefreshOn = false;
    console.log('⏹️ Auto-refresh stopped');
}

function checkForNewData() {
    fetch('{{ route("form_submissions.latest") }}')
        .then(response => response.json())
        .then(data => {
            const newCount = data.total_count;
            
            if (newCount > currentCount) {
                console.log('🆕 New data detected! Old count:', currentCount, 'New count:', newCount);
                currentCount = newCount;
                
                // Show notification and refresh
                showUpdateNotification();
                setTimeout(() => {
                    window.location.reload();
                }, 1500); // Small delay to show the notification
            }
        })
        .catch(error => {
            console.log('Check failed (normal):', error);
        });
}

function showUpdateNotification() {
    // Remove any existing notification
    const existing = document.getElementById('update-notification');
    if (existing) existing.remove();
    
    // Create notification
    const notification = document.createElement('div');
    notification.id = 'update-notification';
    notification.className = 'alert alert-success alert-dismissible fade show position-fixed';
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 350px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);';
    notification.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas fa-check-circle me-2"></i>
            <div>
                <strong>New CSV Data Detected!</strong><br>
                <small>Refreshing page automatically...</small>
            </div>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 3000);
}

// Manual controls
function manualRefresh() {
    console.log('🔄 Manual refresh triggered');
    const btn = document.getElementById('manual-refresh-btn');
    if (btn) {
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
        btn.disabled = true;
    }
    
    showUpdateNotification();
    setTimeout(() => {
        window.location.reload();
    }, 1000);
}

function pauseAutoRefresh() {
    stopAutoRefresh();
    document.getElementById('pause-polling-btn').style.display = 'none';
    document.getElementById('resume-polling-btn').style.display = 'inline-block';
    console.log('⏸️ Auto-refresh paused by user');
}

function resumeAutoRefresh() {
    startAutoRefresh();
    document.getElementById('resume-polling-btn').style.display = 'none';
    document.getElementById('pause-polling-btn').style.display = 'inline-block';
    console.log('▶️ Auto-refresh resumed by user');
}

console.log('📝 Auto-refresh system loaded. Open browser console to see activity.');

// Alias functions to match button onclick handlers
function pausePolling() {
    pauseAutoRefresh();
}

function resumePolling() {
    resumeAutoRefresh();
}

async function checkForNewSubmissions() {
    try {
        // Use the efficient API endpoint
        const apiUrl = '{{ route("form_submissions.latest") }}';
        
        const response = await fetch(apiUrl);
        if (!response.ok) {
            console.log('API response not OK:', response.status);
            return;
        }
        
        const data = await response.json();
        const currentCount = data.total_count || 0;
        
        console.log('Checking submissions - Current:', currentCount, 'Last:', lastSubmissionCount);
        
        // If count increased, we have new submissions
        if (currentCount > lastSubmissionCount) {
            console.log('New submissions detected! Refreshing table...');
            
            try {
                await refreshTable();
                showNewDataNotification();
            } catch (error) {
                console.log('AJAX refresh failed, falling back to page reload:', error);
                showNewDataNotification();
                // Fallback: reload page after showing notification
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            }
            
            lastSubmissionCount = currentCount;
        }
    } catch (error) {
        console.log('Polling error:', error.message);
    }
}

async function refreshTable() {
    try {
        console.log('Refreshing table...');
        
        // Show loading indicator
        showLoadingOverlay();
        
        // Get current URL to maintain filters and pagination
        const currentUrl = window.location.href;
        console.log('Fetching data from:', currentUrl);
        
        const response = await fetch(currentUrl);
        
        if (!response.ok) {
            console.log('Failed to fetch page:', response.status);
            hideLoadingOverlay();
            return;
        }
        
        const html = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // Update the table content
        const newTableContainer = doc.querySelector('.table-responsive');
        const currentTableContainer = document.querySelector('.table-responsive');
        
        if (newTableContainer && currentTableContainer) {
            console.log('Updating table content...');
            currentTableContainer.innerHTML = newTableContainer.innerHTML;
        } else {
            console.log('Table containers not found');
        }
        
        // Update pagination
        const newPagination = doc.querySelector('.d-flex.justify-content-center');
        const currentPagination = document.querySelector('.d-flex.justify-content-center');
        
        if (newPagination && currentPagination) {
            currentPagination.innerHTML = newPagination.innerHTML;
        }
        
        // Update results summary
        const newSummary = doc.querySelector('.mb-3 small.text-muted');
        const currentSummary = document.querySelector('.mb-3 small.text-muted');
        
        if (newSummary && currentSummary) {
            currentSummary.innerHTML = newSummary.innerHTML;
        }
        
        // Hide loading indicator
        hideLoadingOverlay();
        console.log('Table refresh completed');
        
    } catch (error) {
        console.error('Error refreshing table:', error);
        hideLoadingOverlay();
    }
}

function showLoadingOverlay() {
    // Remove existing overlay if any
    const existingOverlay = document.getElementById('loading-overlay');
    if (existingOverlay) {
        existingOverlay.remove();
    }
    
    // Create loading overlay
    const overlay = document.createElement('div');
    overlay.id = 'loading-overlay';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.3);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 10000;
    `;
    
    overlay.innerHTML = `
        <div class="text-center text-white">
            <div class="spinner-border text-light mb-2" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div>Refreshing table data...</div>
        </div>
    `;
    
    document.body.appendChild(overlay);
}

function hideLoadingOverlay() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.remove();
    }
}

function showNewDataNotification() {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = 'alert alert-info alert-dismissible fade show position-fixed';
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 350px;';
    notification.innerHTML = `
        <i class="fas fa-sync-alt"></i> <strong>Table Updated!</strong> New CSV data has been loaded automatically.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Manual refresh button functionality
function manualRefresh() {
    const refreshBtn = document.getElementById('manual-refresh-btn');
    if (refreshBtn) {
        refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
        refreshBtn.disabled = true;
    }
    
    refreshTable().then(() => {
        if (refreshBtn) {
            refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
            refreshBtn.disabled = false;
        }
        showNewDataNotification();
    });
}

// Pause/Resume polling functions
function pausePolling() {
    stopPolling();
    document.getElementById('pause-polling-btn').style.display = 'none';
    document.getElementById('resume-polling-btn').style.display = 'inline-block';
}

function resumePolling() {
    startPolling();
    document.getElementById('resume-polling-btn').style.display = 'none';
    document.getElementById('pause-polling-btn').style.display = 'inline-block';
}
</script>
@endsection