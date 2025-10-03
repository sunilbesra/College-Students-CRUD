@extends('layouts.app')

@section('content')
<div class="container py-4">

    <!-- Header with Title and Notification -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="fw-bold">Student Directory</h1>

        <div class="d-flex align-items-center">
            <!-- Notification Bell -->
            <div class="dropdown me-3">
                <button class="btn btn-light position-relative" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-bell-fill fs-4"></i>
                    @if(session('success'))
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            1
                        </span>
                    @endif
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="notificationDropdown" style="width: 280px;">
                    <li class="dropdown-header fw-bold">Notifications</li>
                    @if(session('success'))
                        <li>
                            <div class="dropdown-item d-flex align-items-start">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                <div>
                                    <div class="fw-semibold">Student Created</div>
                                    <small class="text-muted">{{ session('success') }}</small>
                                </div>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                    @endif
                    <li class="text-center text-muted small">No more notifications</li>
                </ul>
            </div>

            <a href="{{ route('students.create') }}" class="btn btn-gradient-primary shadow">➕ Add Student</a>
        </div>
    </div>

    <!-- Flash message (for inline alert if needed) -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Student Table -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <input type="text" class="form-control" placeholder="Search by name, email, or college..." id="studentSearch" onkeyup="filterStudents()">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="studentsTable">
                    <thead class="table-dark">
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Profile</th>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Contact</th>
                            <th scope="col">College</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($students as $student)
                        <tr>
                            <td>{{ $student->id }}</td>
                            <td>
                                @if($student->profile_image)
                                    <img src="/{{ $student->profile_image }}" class="rounded-circle border border-2" width="48" height="48" style="object-fit:cover;">
                                @else
                                    <span class="badge bg-secondary">N/A</span>
                                @endif
                            </td>
                            <td class="fw-semibold">{{ $student->name }}</td>
                            <td>{{ $student->email }}</td>
                            <td>{{ $student->contact }}</td>
                            <td><span class="badge bg-info text-dark">{{ $student->college }}</span></td>
                            <td>
                                <a href="{{ route('students.show', $student) }}" class="btn btn-outline-primary btn-sm me-1" title="View"><i class="bi bi-eye"></i> View</a>
                                <a href="{{ route('students.edit', $student) }}" class="btn btn-outline-warning btn-sm me-1" title="Edit"><i class="bi bi-pencil"></i> Edit</a>
                                <form action="{{ route('students.destroy', $student) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure?')" title="Delete"><i class="bi bi-trash"></i> Delete</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap Icons CDN -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<style>
    .btn-gradient-primary {
        background: linear-gradient(90deg, #4f8cff 0%, #6f4fff 100%);
        color: #fff;
        border: none;
        transition: box-shadow 0.2s;
    }
    .btn-gradient-primary:hover {
        box-shadow: 0 4px 16px rgba(79,140,255,0.2);
        color: #fff;
    }
</style>

<script>
function filterStudents() {
    let input = document.getElementById('studentSearch');
    let filter = input.value.toLowerCase();
    let table = document.getElementById('studentsTable');
    let trs = table.getElementsByTagName('tr');
    for (let i = 1; i < trs.length; i++) {
        let tds = trs[i].getElementsByTagName('td');
        let show = false;
        for (let j = 0; j < tds.length; j++) {
            if (tds[j].innerText.toLowerCase().indexOf(filter) > -1) {
                show = true;
                break;
            }
        }
        trs[i].style.display = show ? '' : 'none';
    }
}
</script>
@endsection
