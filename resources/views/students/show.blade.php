@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-gradient text-white d-flex align-items-center" style="background: linear-gradient(90deg, #4f8cff 0%, #6f4fff 100%);">
                    @if($student->profile_image)
                        <img src="/{{ $student->profile_image }}" class="rounded-circle border border-2 me-3" width="64" height="64" style="object-fit:cover;">
                    @else
                        <span class="badge bg-secondary me-3" style="height:64px;width:64px;display:inline-flex;align-items:center;justify-content:center;font-size:1.5rem;">N/A</span>
                    @endif
                    <h3 class="mb-0">{{ $student->name }}</h3>
                </div>
                <div class="card-body p-4">
                    <ul class="list-group list-group-flush mb-3">
                        <li class="list-group-item"><strong>Email:</strong> {{ $student->email }}</li>
                        <li class="list-group-item"><strong>Contact:</strong> {{ $student->contact }}</li>
                        <li class="list-group-item"><strong>Address:</strong> {{ $student->address }}</li>
                        <li class="list-group-item"><strong>College:</strong> <span class="badge bg-info text-dark">{{ $student->college }}</span></li>
                    </ul>
                    <a href="{{ route('students.index') }}" class="btn btn-secondary">Back</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
