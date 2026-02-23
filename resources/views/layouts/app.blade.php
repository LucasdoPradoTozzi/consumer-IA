<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Job Applications Dashboard')</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(255, 255, 255, 0.2);
            --premium-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
        }

        body {
            background-color: #f3f4f6;
            font-family: 'Inter', sans-serif;
            color: #1f2937;
        }

        .navbar {
            box-shadow: 0 2px 4px rgba(0, 0, 0, .1);
        }

        .card {
            border: none;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .1);
        }

        .stat-card {
            border-left: 4px solid;
        }

        .stat-card.total {
            border-left-color: #6c757d;
        }

        .stat-card.pending {
            border-left-color: #0d6efd;
        }

        .stat-card.processing {
            border-left-color: #0dcaf0;
        }

        .stat-card.completed {
            border-left-color: #198754;
        }

        .stat-card.rejected {
            border-left-color: #343a40;
        }

        .stat-card.failed {
            border-left-color: #dc3545;
        }

        .score-badge {
            font-size: 1.2rem;
            padding: 0.5rem 1rem;
        }

        /* Fix conflict between Bootstrap .collapse and Tailwind .collapse */
        .navbar-collapse.collapse {
            visibility: visible !important;
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('job-applications.index') }}">
                <i class="bi bi-briefcase-fill"></i> Job Applications
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('job-applications.index') }}">
                            <i class="bi bi-list-ul"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('candidate-profile.index') }}">
                            <i class="bi bi-person-badge"></i> My Candidate Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('skills.index') }}">
                            <i class="bi bi-gear-fill"></i> Manage Skills
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('languages.index') }}">
                            <i class="bi bi-translate"></i> Manage Languages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('logs.index') }}">
                            <i class="bi bi-terminal"></i> Worker Logs
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    @if (View::hasSection('header'))
        <header class="bg-white shadow-sm mb-4">
            <div class="container-fluid py-3">
                @yield('header')
            </div>
        </header>
    @endif

    <div class="container-fluid">
        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        @yield('content')
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>

</html>