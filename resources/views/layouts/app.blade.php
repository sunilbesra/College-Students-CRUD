<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Student Management System')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="{{ asset('resources/css/app.css') }}" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
        <div class="container">
            <a class="navbar-brand" href="{{ url('/') }}">Student Management System</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('students.index') }}">
                            <i class="fas fa-users"></i> Students
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('form_submissions.index') }}">
                            <i class="fas fa-file-alt"></i> Form Submissions
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="csvDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-upload"></i> CSV Upload
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ url('upload-csv') }}">Student CSV Upload</a></li>
                            <li><a class="dropdown-item" href="{{ route('form_submissions.upload_csv') }}">Form Submission CSV</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <main>
        @yield('content')
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- CSV upload notification toast -->
    <div aria-live="polite" aria-atomic="true" class="position-relative">
        <div id="csvToast" class="toast position-fixed bottom-0 end-0 m-3" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto">CSV Upload</strong>
                <small class="text-muted" id="csvToastTime"></small>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="csvToastBody">New CSV batch uploaded.</div>
        </div>
    </div>

    <script>
        // Polling approach: fetch last CSV batch cached by the listener
        (function () {
            let lastSeen = null;
            const toastEl = document.getElementById('csvToast');
            const toastBody = document.getElementById('csvToastBody');
            const toastTime = document.getElementById('csvToastTime');
            const toast = new bootstrap.Toast(toastEl);

            async function checkLastBatch() {
                try {
                    const res = await fetch('/csv/last-batch');
                    if (!res.ok) return;
                    const json = await res.json();
                    const batch = json.data;
                    if (!batch) return;

                    if (lastSeen !== batch.timestamp) {
                        lastSeen = batch.timestamp;
                        toastBody.textContent = `File: ${batch.fileName} — ${batch.count} rows queued`;
                        toastTime.textContent = batch.timestamp;
                        toast.show();
                    }
                } catch (err) {
                    // silent fail — polling shouldn't break the app
                    console.debug('csv poll error', err);
                }
            }

            // start polling every 5 seconds
            setInterval(checkLastBatch, 5000);
            // check once on load
            checkLastBatch();
        })();
    </script>
</body>
</html>
