<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <span id="notificationBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display: none;">
                                0
                            </span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end notification-dropdown" style="width: 350px; max-height: 400px; overflow-y: auto;">
                            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                                <h6 class="mb-0">Notifications</h6>
                                <button class="btn btn-sm btn-outline-secondary" id="markAllReadBtn">Mark All Read</button>
                            </div>
                            <div id="notificationsList">
                                <div class="px-3 py-2 text-center text-muted">Loading notifications...</div>
                            </div>
                        </div>
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

        // Notification System
        (function() {
            const notificationsList = document.getElementById('notificationsList');
            const notificationBadge = document.getElementById('notificationBadge');
            const markAllReadBtn = document.getElementById('markAllReadBtn');
            
            let notifications = [];
            let unreadCount = 0;

            async function fetchNotifications() {
                try {
                    const response = await fetch('/notifications?show_read=true&limit=20');
                    if (!response.ok) throw new Error('Failed to fetch notifications');
                    
                    const data = await response.json();
                    console.log('API Response:', data); // Debug log
                    notifications = data.notifications || [];
                    updateNotificationDisplay();
                } catch (error) {
                    console.error('Failed to fetch notifications:', error);
                    notificationsList.innerHTML = '<div class="px-3 py-2 text-center text-danger">Failed to load notifications</div>';
                }
            }

            function updateNotificationDisplay() {
                unreadCount = notifications.filter(n => !n.is_read).length;
                
                // Update badge
                if (unreadCount > 0) {
                    notificationBadge.textContent = unreadCount > 99 ? '99+' : unreadCount;
                    notificationBadge.style.display = 'block';
                } else {
                    notificationBadge.style.display = 'none';
                }

                // Update notifications list
                if (notifications.length === 0) {
                    notificationsList.innerHTML = '<div class="px-3 py-2 text-center text-muted">No notifications</div>';
                    return;
                }

                const notificationsHtml = notifications.map(notification => {
                    const timeAgo = getTimeAgo(new Date(notification.created_at));
                    const isUnread = !notification.is_read;
                    
                    return `
                        <div class="notification-item px-3 py-2 border-bottom ${isUnread ? 'bg-light' : ''}" data-id="${notification.id}">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 ${isUnread ? 'fw-bold' : ''}">${escapeHtml(notification.title)}</h6>
                                    <p class="mb-1 small text-muted">${escapeHtml(notification.message)}</p>
                                    <small class="text-muted">${timeAgo}</small>
                                </div>
                                <div class="d-flex align-items-center">
                                    ${isUnread ? '<span class="badge bg-primary rounded-pill me-2">New</span>' : ''}
                                    <button class="btn btn-sm btn-outline-secondary mark-read-btn" data-id="${notification.id}" ${isUnread ? '' : 'style="display: none;"'}>
                                        <i class="fas fa-check"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');

                notificationsList.innerHTML = notificationsHtml;

                // Add click handlers for mark as read buttons
                document.querySelectorAll('.mark-read-btn').forEach(btn => {
                    btn.addEventListener('click', async (e) => {
                        e.stopPropagation();
                        const notificationId = btn.getAttribute('data-id');
                        await markAsRead(notificationId);
                    });
                });
            }

            async function markAsRead(notificationId) {
                try {
                    const response = await fetch(`/notifications/${notificationId}/read`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        }
                    });

                    if (response.ok) {
                        // Update local state
                        const notification = notifications.find(n => n.id == notificationId);
                        if (notification) {
                            notification.is_read = true;
                            updateNotificationDisplay();
                        }
                    }
                } catch (error) {
                    console.error('Failed to mark notification as read:', error);
                }
            }

            async function markAllAsRead() {
                try {
                    const response = await fetch('/notifications/read-all', {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        }
                    });

                    if (response.ok) {
                        notifications.forEach(n => n.is_read = true);
                        updateNotificationDisplay();
                    }
                } catch (error) {
                    console.error('Failed to mark all notifications as read:', error);
                }
            }

            function getTimeAgo(date) {
                const now = new Date();
                const diffInMs = now - date;
                const diffInMinutes = Math.floor(diffInMs / (1000 * 60));
                const diffInHours = Math.floor(diffInMs / (1000 * 60 * 60));
                const diffInDays = Math.floor(diffInMs / (1000 * 60 * 60 * 24));

                if (diffInMinutes < 1) return 'Just now';
                if (diffInMinutes < 60) return `${diffInMinutes}m ago`;
                if (diffInHours < 24) return `${diffInHours}h ago`;
                if (diffInDays < 7) return `${diffInDays}d ago`;
                return date.toLocaleDateString();
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Event listeners
            markAllReadBtn.addEventListener('click', markAllAsRead);

            // Auto-refresh notifications every 30 seconds
            setInterval(fetchNotifications, 30000);
            
            // Initial load
            fetchNotifications();
        })();
    </script>
</body>
</html>
