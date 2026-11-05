<div x-data="{ mobileMenuOpen: false }" class="relative">
    <!-- Mobile top navigation bar -->
    <header class="lg:hidden bg-slate-900 border-b border-slate-800 text-white px-4 py-3 flex items-center justify-between sticky top-0 z-40">
        <div class="flex items-center gap-3">
            <button 
                type="button" 
                @click="mobileMenuOpen = !mobileMenuOpen"
                class="p-2 -ml-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                aria-label="Toggle navigation menu"
            >
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <div class="flex flex-col">
                <span class="font-bold text-sm text-white tracking-tight">{{ $workspace['name'] }}</span>
                <span class="text-xs font-mono text-emerald-400">{{ $workspace['id'] }}</span>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if ($workspace['is_active'])
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-950 text-emerald-400 border border-emerald-800">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Active
                </span>
            @endif
        </div>
    </header>

    <!-- Mobile sidebar drawer backdrop -->
    <div 
        x-show="mobileMenuOpen" 
        x-transition:enter="transition-opacity ease-linear duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="mobileMenuOpen = false"
        class="fixed inset-0 bg-slate-950/80 backdrop-blur-xs z-50 lg:hidden"
        style="display: none;"
    ></div>

    <!-- Mobile slide-over drawer -->
    <div 
        x-show="mobileMenuOpen"
        x-transition:enter="transition ease-in-out duration-300 transform"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in-out duration-300 transform"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="fixed inset-y-0 left-0 z-50 w-72 bg-slate-900 border-r border-slate-800 text-white flex flex-col lg:hidden"
        style="display: none;"
    >
        <!-- Mobile Drawer Tenancy Header -->
        <div class="p-4 border-b border-slate-800 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-white tracking-tight">{{ $workspace['name'] }}</h2>
                <div class="flex items-center gap-2 mt-1">
                    <span class="text-xs font-mono px-1.5 py-0.5 rounded bg-slate-800 text-slate-300">{{ $workspace['id'] }}</span>
                    @if ($workspace['is_active'])
                        <span class="text-xs text-emerald-400 font-medium">● Active Tenant</span>
                    @endif
                </div>
            </div>
            <button 
                type="button" 
                @click="mobileMenuOpen = false"
                class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Mobile Module Navigation -->
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
            <div class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                Core Modules ({{ count($modules) }})
            </div>
            @foreach ($modules as $module)
                @php
                    $modulePath = match ($module->key) {
                        'dashboard_reports' => "/crm/{$workspace['id']}/reports",
                        'platform_services' => "/crm/{$workspace['id']}/settings",
                        default => "/crm/{$workspace['id']}/{$module->key}",
                    };
                    $isActive = ($activeModule === $module->key) || match ($module->key) {
                        'dashboard_reports' => request()->is("crm/{$workspace['id']}/reports*") || request()->is("crm/{$workspace['id']}/dashboard*") || request()->is("crm/{$workspace['id']}/exports*") || request()->is("crm/{$workspace['id']}/data-export*"),
                        'platform_services' => request()->is("crm/{$workspace['id']}/settings*") || request()->is("crm/{$workspace['id']}/platform_services*") || request()->is("crm/{$workspace['id']}/audit-logs*") || request()->is("crm/{$workspace['id']}/staff*"),
                        default => request()->is("crm/{$workspace['id']}/{$module->key}*"),
                    };
                @endphp
                <a 
                    href="{{ $modulePath }}" 
                    class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ $isActive ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}"
                >
                    <span class="w-2 h-2 rounded-full {{ $isActive ? 'bg-white' : 'bg-slate-500 group-hover:bg-indigo-400' }}"></span>
                    <span class="flex-1 truncate">{{ $module->name }}</span>
                    <span class="text-xs font-mono text-slate-400 group-hover:text-slate-300">{{ $module->key }}</span>
                </a>
            @endforeach
        </nav>

        <!-- Mobile User Session Widget -->
        <div class="p-4 border-t border-slate-800 bg-slate-950/50">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-9 h-9 rounded-full bg-indigo-600 flex items-center justify-center font-bold text-white text-sm">
                    {{ strtoupper(substr($user?->name ?? 'Staff', 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-white truncate">{{ $user?->name ?? 'Staff Member' }}</p>
                    <p class="text-xs text-slate-400 truncate">{{ $user?->email ?? 'staff@emkcenter.org' }}</p>
                </div>
            </div>
            <div class="flex items-center justify-between mb-3 text-xs">
                <span class="px-2 py-0.5 rounded bg-slate-800 text-slate-300 border border-slate-700 font-mono">guard: web</span>
                <span class="px-2 py-0.5 rounded bg-indigo-950 text-indigo-300 border border-indigo-800 font-medium">Workspace Staff</span>
            </div>
            <form method="POST" action="{{ Route::has('logout') ? route('logout') : url('/logout') }}" class="w-full">
                @csrf
                <button 
                    type="submit" 
                    wire:click.prevent="logout"
                    class="w-full px-3 py-1.5 text-xs font-medium text-slate-300 hover:text-white bg-slate-800 hover:bg-slate-700 rounded-md transition-colors text-center"
                >
                    Sign Out
                </button>
            </form>
        </div>
    </div>

    <!-- Desktop fixed sidebar -->
    <aside class="hidden lg:fixed lg:inset-y-0 lg:left-0 lg:z-30 lg:flex lg:w-64 lg:flex-col bg-slate-900 border-r border-slate-800 text-white shadow-xl">
        <!-- Desktop Tenancy Header per DEC-01 -->
        <div class="p-5 border-b border-slate-800 bg-slate-950/40">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-xs uppercase tracking-wider font-semibold text-indigo-400">American Spaces Hub</span>
                @if ($workspace['is_active'])
                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-medium bg-emerald-950 text-emerald-400 border border-emerald-800">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        Active
                    </span>
                @endif
            </div>
            <h1 class="text-base font-bold text-white tracking-tight leading-snug">{{ $workspace['name'] }}</h1>
            <div class="mt-1 flex items-center gap-2">
                <span class="text-xs font-mono px-2 py-0.5 rounded bg-slate-800/80 text-slate-300 border border-slate-700/60">{{ $workspace['id'] }}</span>
            </div>
        </div>

        <!-- Desktop Module Navigation Links -->
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
            <div class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                Core Modules ({{ count($modules) }})
            </div>

            @foreach ($modules as $module)
                @php
                    $modulePath = match ($module->key) {
                        'dashboard_reports' => "/crm/{$workspace['id']}/reports",
                        'platform_services' => "/crm/{$workspace['id']}/settings",
                        default => "/crm/{$workspace['id']}/{$module->key}",
                    };
                    $isActive = ($activeModule === $module->key) || match ($module->key) {
                        'dashboard_reports' => request()->is("crm/{$workspace['id']}/reports*") || request()->is("crm/{$workspace['id']}/dashboard*") || request()->is("crm/{$workspace['id']}/exports*") || request()->is("crm/{$workspace['id']}/data-export*"),
                        'platform_services' => request()->is("crm/{$workspace['id']}/settings*") || request()->is("crm/{$workspace['id']}/platform_services*") || request()->is("crm/{$workspace['id']}/audit-logs*") || request()->is("crm/{$workspace['id']}/staff*"),
                        default => request()->is("crm/{$workspace['id']}/{$module->key}*"),
                    };
                @endphp
                <a 
                    href="{{ $modulePath }}" 
                    class="group flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ $isActive ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}"
                >
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="w-2 h-2 rounded-full shrink-0 {{ $isActive ? 'bg-white' : 'bg-slate-500 group-hover:bg-indigo-400' }}"></span>
                        <span class="truncate">{{ $module->name }}</span>
                    </div>
                    <span class="text-xs font-mono shrink-0 {{ $isActive ? 'text-indigo-200' : 'text-slate-500 group-hover:text-slate-400' }}">{{ $module->key }}</span>
                </a>
            @endforeach
        </nav>

        <!-- Desktop Staff User Profile Widget per DEC-06 (D-06) -->
        <div class="p-4 border-t border-slate-800 bg-slate-950/60">
            <div class="flex items-center gap-3 mb-2.5">
                <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center font-bold text-white text-sm shadow-xs shrink-0">
                    {{ strtoupper(substr($user?->name ?? 'Staff', 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-white truncate">{{ $user?->name ?? 'Staff Member' }}</p>
                    <p class="text-xs text-slate-400 truncate">{{ $user?->email ?? 'staff@emkcenter.org' }}</p>
                </div>
            </div>

            <div class="flex items-center justify-between mb-3 text-xs">
                <span class="px-2 py-0.5 rounded bg-slate-800 text-slate-300 border border-slate-700 font-mono">guard: web</span>
                <span class="px-2 py-0.5 rounded bg-indigo-950 text-indigo-300 border border-indigo-800 font-medium">Workspace Staff</span>
            </div>

            <form method="POST" action="{{ Route::has('logout') ? route('logout') : url('/logout') }}" class="w-full">
                @csrf
                <button 
                    type="submit" 
                    wire:click.prevent="logout"
                    class="w-full flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-300 hover:text-white bg-slate-800 hover:bg-slate-700 border border-slate-700/80 rounded-md transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span>Sign Out</span>
                </button>
            </form>
        </div>
    </aside>
</div>
