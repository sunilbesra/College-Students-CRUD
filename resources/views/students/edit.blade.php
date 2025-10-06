@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-gradient text-white" style="background: linear-gradient(90deg, #4f8cff 0%, #6f4fff 100%);">
                    <h3 class="mb-0">Edit Student</h3>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('students.update', $student) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" name="name" class="form-control" id="name" value="{{ old('name', $student->name) }}">
                                @error('name')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" id="email" value="{{ old('email', $student->email) }}">
                                @error('email')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="contact" class="form-label">Contact</label>
                                <input type="text" name="contact" class="form-control" id="contact" value="{{ old('contact', $student->contact) }}">
                                @error('contact')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="college" class="form-label">College</label>
                                <input type="text" name="college" class="form-control" id="college" value="{{ old('college', $student->college) }}">
                                @error('college')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-12">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" name="address" class="form-control" id="address" value="{{ old('address', $student->address) }}">
                                @error('address')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="gender" class="form-label">Gender</label>
                                <select name="gender" id="gender" class="form-select">
                                    <option value="">Select</option>
                                    <option value="male" {{ old('gender', $student->gender)=='male' ? 'selected' : '' }}>Male</option>
                                    <option value="female" {{ old('gender', $student->gender)=='female' ? 'selected' : '' }}>Female</option>
                                    <option value="other" {{ old('gender', $student->gender)=='other' ? 'selected' : '' }}>Other</option>
                                </select>
                                @error('gender') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="dob" class="form-label">Date of Birth</label>
                                <input type="date" name="dob" id="dob" class="form-control" value="{{ old('dob', optional($student->dob)->format('Y-m-d') ?? $student->dob) }}">
                                @error('dob') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Enrollment Status</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <div class="form-check d-flex align-items-center">
                                        <input class="form-check-input" type="radio" name="enrollment_status" id="enrollment_full" value="full_time" {{ old('enrollment_status', $student->enrollment_status)=='full_time' ? 'checked' : '' }}>
                                        <label class="form-check-label mb-0 ms-2" for="enrollment_full">Full-time</label>
                                    </div>
                                    <div class="form-check d-flex align-items-center">
                                        <input class="form-check-input" type="radio" name="enrollment_status" id="enrollment_part" value="part_time" {{ old('enrollment_status', $student->enrollment_status)=='part_time' ? 'checked' : '' }}>
                                        <label class="form-check-label mb-0 ms-2" for="enrollment_part">Part-time</label>
                                    </div>
                                </div>
                                @error('enrollment_status') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-8 mt-2">
                                <label for="course" class="form-label">Course</label>
                                <select name="course" id="course" class="form-select">
                                    <option value="">Select Course</option>
                                    <option value="bsc" {{ old('course', $student->course)=='bsc' ? 'selected' : '' }}>B.Sc</option>
                                    <option value="ba" {{ old('course', $student->course)=='ba' ? 'selected' : '' }}>B.A</option>
                                    <option value="bcom" {{ old('course', $student->course)=='bcom' ? 'selected' : '' }}>B.Com</option>
                                    <option value="mca" {{ old('course', $student->course)=='mca' ? 'selected' : '' }}>MCA</option>
                                    <option value="msc" {{ old('course', $student->course)=='msc' ? 'selected' : '' }}>M.Sc</option>
                                </select>
                                @error('course') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4 mt-4">
                                <div class="form-check d-flex align-items-center">
                                    <input class="form-check-input" type="checkbox" name="agreed_to_terms" id="agreed_to_terms" value="1" {{ old('agreed_to_terms', $student->agreed_to_terms) ? 'checked' : '' }}>
                                    <label class="form-check-label mb-0 ms-2" for="agreed_to_terms">Agree to terms</label>
                                </div>
                                @error('agreed_to_terms') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-12">
                                <label for="profile_image" class="form-label">Profile Image</label>
                                <div class="mb-2">
                                    @if($student->profile_image)
                                        <img id="profileImagePreview" src="/{{ $student->profile_image }}" class="rounded-circle border border-2" width="80" height="80" style="object-fit:cover;">
                                    @else
                                        <img id="profileImagePreview" src="#" style="display:none;max-width:120px;max-height:120px;border-radius:50%;object-fit:cover;border:2px solid #eee;" />
                                    @endif
                                </div>
                                <input type="file" name="profile_image" class="form-control" id="profile_image" accept="image/*" onchange="previewProfileImage(event)">
                                @error('profile_image')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="mt-4 d-flex justify-content-between">
                            <a href="{{ route('students.index') }}" class="btn btn-secondary">Back</a>
                            <button type="submit" class="btn btn-gradient-primary">Update Student</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
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
    function previewProfileImage(event) {
        const input = event.target;
        const preview = document.getElementById('profileImagePreview');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        } else {
            preview.src = '#';
            preview.style.display = 'none';
        }
    }
    </script>
@endsection
