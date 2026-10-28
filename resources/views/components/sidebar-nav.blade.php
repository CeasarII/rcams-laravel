@php
    $user = auth()->user();
    $isActive = fn (...$patterns) => collect($patterns)->contains(fn ($p) => request()->routeIs($p));
@endphp

<nav class="sidebar-nav">

    <div class="sidebar-group">
        <div class="sidebar-group-label">Fund Requests</div>

        {{-- One entry point to the list; the page's "View" dropdown does the per-role filtering.
             Admin has no queue of its own, so it gets the unfiltered "All Requests". --}}
        <a href="{{ $user->hasRole('ADMIN') ? route('fund-requests.index', ['filter' => 'all']) : route('fund-requests.index') }}"
           class="sidebar-link {{ $isActive('fund-requests.index', 'fund-requests.show') ? 'active' : '' }}">
            <span class="icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3h6l4 4v14a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/><path d="M14 3v4h4"/><path d="M9 13h6M9 17h4"/></svg>
            </span>
            <span class="label">
                @if ($user->hasRole('ADMIN'))
                    All Requests
                @elseif ($user->hasRole('REQUESTOR') && ! $user->hasRole('OE') && ! $user->hasRole('ACCT_STAFF') && ! $user->hasRole('ACCT_HEAD') && ! $user->hasRole('TREASURY'))
                    My Requests
                @else
                    Request Queue
                @endif
            </span>
        </a>

        @if ($user->hasRole('REQUESTOR'))
            <a href="{{ route('fund-requests.create') }}"
               class="sidebar-link {{ $isActive('fund-requests.create', 'fund-requests.edit') ? 'active' : '' }}">
                <span class="icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                </span>
                <span class="label">New Request</span>
            </a>
        @endif
    </div>

    <div class="sidebar-group">
        <div class="sidebar-group-label">Liquidation</div>

            <a href="{{ route('liquidations.index') }}"
               class="sidebar-link {{ $isActive('liquidations.*') ? 'active' : '' }}">
                <span class="icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 13l2 2 4-4"/></svg>
                </span>
                <span class="label">Liquidations</span>
            </a>

            <a href="{{ route('questionable-expenses.index') }}"
               class="sidebar-link {{ $isActive('questionable-expenses.*') ? 'active' : '' }}">
                <span class="icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l9 16H3z"/><path d="M12 10v4M12 17h.01"/></svg>
                </span>
                <span class="label">Questionable Expenses</span>
            </a>
    </div>

    @if ($user->can('viewAny', \App\Models\RevolvingFund::class))
        <div class="sidebar-group">
            <div class="sidebar-group-label">Revolving Fund</div>

            <a href="{{ route('revolving-funds.index') }}"
               class="sidebar-link {{ $isActive('revolving-funds.*') ? 'active' : '' }}">
                <span class="icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M9.5 9.5h4a1.8 1.8 0 0 1 0 3.5h-3a1.8 1.8 0 0 0 0 3.5H15"/></svg>
                </span>
                <span class="label">Revolving Funds</span>
            </a>

            <a href="{{ route('revolving-fund-expenses.index') }}"
               class="sidebar-link {{ $isActive('revolving-fund-expenses.*') ? 'active' : '' }}">
                <span class="icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l2 3h14l2-3"/><path d="M5 17l1.5-6h11L19 17"/><path d="M12 11V4l4 3"/></svg>
                </span>
                <span class="label">Voyage Expenses</span>
            </a>
        </div>
    @endif

    @if ($user->hasRole('ADMIN'))
        <div class="sidebar-group">
            <div class="sidebar-group-label">Administration</div>

            <a href="{{ route('admin.users.index') }}"
               class="sidebar-link {{ $isActive('admin.users.*') ? 'active' : '' }}">
                <span class="icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="8" r="2.5"/><path d="M21 20c0-2.6-1.7-4.8-4-5.6"/></svg>
                </span>
                <span class="label">Users</span>
            </a>
        </div>

        <div class="sidebar-group">
            <div class="sidebar-group-label">Master Data</div>

            <a href="{{ route('admin.companies.index') }}"
               class="sidebar-link {{ $isActive('admin.companies.*') ? 'active' : '' }}">
                <span class="icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21V5a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v16"/><path d="M14 9h5a1 1 0 0 1 1 1v11"/><path d="M8 8h2M8 12h2M8 16h2"/></svg>
                </span>
                <span class="label">Companies</span>
            </a>

            <a href="{{ route('admin.departments.index') }}"
               class="sidebar-link {{ $isActive('admin.departments.*') ? 'active' : '' }}">
                <span class="icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3" width="6" height="5" rx="1"/><rect x="3" y="16" width="6" height="5" rx="1"/><rect x="15" y="16" width="6" height="5" rx="1"/><path d="M12 8v4M6 16v-4h12v4"/></svg>
                </span>
                <span class="label">Departments</span>
            </a>

            <a href="{{ route('admin.vessel-types.index') }}"
               class="sidebar-link {{ $isActive('admin.vessel-types.*') ? 'active' : '' }}">
                <span class="icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
                </span>
                <span class="label">Vessel Types</span>
            </a>

            <a href="{{ route('admin.vessels.index') }}"
               class="sidebar-link {{ $isActive('admin.vessels.*') ? 'active' : '' }}">
                <span class="icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l2 3h14l2-3"/><path d="M5 17l1.5-6h11L19 17"/><path d="M12 11V4l4 3"/></svg>
                </span>
                <span class="label">Vessels</span>
            </a>

            <a href="{{ route('admin.nature-of-costs.index') }}"
               class="sidebar-link {{ $isActive('admin.nature-of-costs.*') ? 'active' : '' }}">
                <span class="icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12l-8 8-9-9V4h7z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>
                </span>
                <span class="label">Nature of Cost</span>
            </a>

            <a href="{{ route('admin.document-types.index') }}"
               class="sidebar-link {{ $isActive('admin.document-types.*') ? 'active' : '' }}">
                <span class="icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3h6l4 4v14H8z"/><path d="M14 3v4h4M11 13h4M11 17h4"/></svg>
                </span>
                <span class="label">Document Types</span>
            </a>
        </div>
    @endif

</nav>
