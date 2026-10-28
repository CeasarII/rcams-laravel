<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Cash Advance Monitoring System')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body>
<div class="app-shell" id="app-shell">

    <aside class="sidebar">
        <div class="sidebar-brand">
            <span class="mark">CA</span>
            <span class="label">CAMS</span>
            <button type="button" class="sidebar-toggle" id="sidebar-collapse-btn" aria-label="Collapse sidebar">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/></svg>
            </button>
        </div>

        <button type="button" class="sidebar-expand-toggle" id="sidebar-expand-btn" aria-label="Expand sidebar">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/></svg>
        </button>

        @auth
            <x-sidebar-nav />
        @endauth

        @auth
            <div class="sidebar-footer">
                <form method="POST" action="{{ route('logout') }}" class="mb-0">
                    @csrf
                    <button type="submit" class="sidebar-link w-100 border-0 bg-transparent text-start">
                        <span class="icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
                        </span>
                        <span class="label">Log Out</span>
                    </button>
                </form>
            </div>
        @endauth
    </aside>

    <div class="app-main">
        <header class="topbar">
            <button type="button" class="sidebar-expand-toggle d-md-none" id="sidebar-mobile-btn" aria-label="Open menu" style="display:flex;">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <span class="topbar-title">@yield('title', 'Cash Advance Monitoring System')</span>
            @auth
                <span class="topbar-user">{{ auth()->user()->full_name }}</span>
            @endauth
        </header>

        <main class="content-area">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script>
(function () {
    const shell = document.getElementById('app-shell');
    const collapseBtn = document.getElementById('sidebar-collapse-btn');
    const expandBtn = document.getElementById('sidebar-expand-btn');
    const mobileBtn = document.getElementById('sidebar-mobile-btn');
    const STORAGE_KEY = 'cams-sidebar-collapsed';

    if (localStorage.getItem(STORAGE_KEY) === '1') {
        shell.classList.add('sidebar-collapsed');
    }

    collapseBtn?.addEventListener('click', function () {
        shell.classList.add('sidebar-collapsed');
        localStorage.setItem(STORAGE_KEY, '1');
    });

    expandBtn?.addEventListener('click', function () {
        shell.classList.remove('sidebar-collapsed');
        localStorage.setItem(STORAGE_KEY, '0');
    });

    // Mobile: sidebar is an off-canvas overlay, toggled independently of
    // the desktop collapsed/expanded state.
    mobileBtn?.addEventListener('click', function () {
        shell.classList.toggle('sidebar-open');
    });

    document.addEventListener('click', function (e) {
        if (shell.classList.contains('sidebar-open')
            && !e.target.closest('.sidebar')
            && !e.target.closest('#sidebar-mobile-btn')) {
            shell.classList.remove('sidebar-open');
        }
    });
})();
</script>
</body>
</html>
