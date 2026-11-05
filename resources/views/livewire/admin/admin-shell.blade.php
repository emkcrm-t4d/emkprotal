<div x-data="{ mobileMenuOpen: false }" class="relative">
    <!-- Mobile top navigation bar -->
    <header class="lg:hidden bg-slate-950/80 backdrop-blur-2xl border-b border-white/10 text-white px-4 py-3 flex items-center justify-between sticky top-0 z-40">
        <div class="flex items-center gap-3">
            <button 
                type="button" 
                @click="mobileMenuOpen = !mobileMenuOpen"
                class="min-h-[44px] min-w-[44px] p-2 rounded-xl text-slate-300 hover:text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-amber-400 cursor-pointer transition-colors"
                aria-label="Toggle navigation menu"
            >
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full border border-amber-400/90 bg-white p-0.5 shadow-sm flex items-center justify-center overflow-hidden shrink-0" aria-hidden="true">
                    <img src="{{ asset('images/emk-logo.png') }}" alt="EMK Center" class="w-full h-full object-contain rounded-full">
                </div>
                <div class="flex flex-col">
                    <span class="font-bold text-sm text-white tracking-tight leading-tight">{{ $workspace['name'] }}</span>
                    <span class="text-[11px] font-mono text-amber-300">{{ $workspace['id'] }}</span>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if ($workspace['is_active'])
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
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
        class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 lg:hidden"
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
        class="fixed inset-y-0 left-0 z-50 w-72 bg-slate-950/95 backdrop-blur-2xl border-r border-white/10 text-white flex flex-col lg:hidden"
        style="display: none;"
    >
        <!-- Mobile Drawer Tenancy Header -->
        <div class="p-4 border-b border-white/10 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full border-2 border-amber-400/90 ring-2 ring-amber-400/25 bg-white p-0.5 shadow-md flex items-center justify-center overflow-hidden shrink-0" aria-hidden="true">
                    <img src="{{ asset('images/emk-logo.png') }}" alt="EMK Center" class="w-full h-full object-contain rounded-full">
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white tracking-tight leading-tight">{{ $workspace['name'] }}</h2>
                    <span class="sr-only">{{ $workspace['id'] }}</span>
                    @if ($workspace['is_active'])
                        <div class="mt-0.5">
                            <span class="text-[10px] text-emerald-400 font-medium">● Active</span>
                        </div>
                    @endif
                </div>
            </div>
            <button 
                type="button" 
                @click="mobileMenuOpen = false"
                class="min-h-[44px] min-w-[44px] p-2 rounded-xl text-slate-400 hover:text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-amber-400 cursor-pointer"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Mobile Module Navigation -->
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1.5">
            <div class="px-3 pb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">
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
                    class="group flex items-center gap-3 px-3.5 py-3 min-h-[44px] rounded-xl text-xs sm:text-sm font-semibold transition-all {{ $isActive ? 'bg-gradient-to-r from-amber-500/20 to-indigo-500/20 border border-amber-400/40 text-amber-300 shadow-sm' : 'text-slate-300 hover:bg-white/5 hover:text-white border border-transparent' }}"
                >
                    <span class="w-2 h-2 rounded-full {{ $isActive ? 'bg-amber-400 animate-pulse' : 'bg-slate-500 group-hover:bg-amber-400' }}"></span>
                    <span class="flex-1 truncate">{{ $module->name }}</span>
                    <span class="sr-only">{{ $module->key }}</span>
                </a>
            @endforeach
        </nav>

        <!-- Mobile User Session Widget -->
        <div class="p-4 border-t border-white/10 bg-slate-950/80">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-amber-400 to-indigo-500 flex items-center justify-center font-bold text-white text-sm shadow-xs shrink-0">
                    {{ strtoupper(substr($user?->name ?? 'Staff', 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-white truncate">{{ $user?->name ?? 'Staff Member' }}</p>
                    <p class="text-xs text-slate-400 truncate">{{ $user?->email ?? 'staff@emkcenter.org' }}</p>
                </div>
            </div>
            <div class="flex items-center justify-between mb-3 text-xs">
                <span class="px-2 py-0.5 rounded bg-white/10 text-slate-300 border border-white/10 font-mono">guard: web</span>
                <span class="px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 font-medium">Workspace Staff</span>
            </div>
            <form method="POST" action="{{ Route::has('logout') ? route('logout') : url('/logout') }}" class="w-full">
                @csrf
                <button 
                    type="submit" 
                    wire:click.prevent="logout"
                    class="w-full min-h-[44px] flex items-center justify-center gap-2 px-3 py-2 text-xs font-semibold text-rose-300 bg-rose-500/15 hover:bg-rose-500/25 border border-rose-500/30 rounded-xl transition cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span>Sign Out</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Desktop fixed sidebar in 3D Frosted Dark Glass Theme -->
    <aside class="hidden lg:fixed lg:inset-y-0 lg:left-0 lg:z-30 lg:flex lg:w-64 lg:flex-col bg-slate-950/80 backdrop-blur-2xl border-r border-white/10 text-white shadow-2xl">
        <!-- Desktop Tenancy Header per DEC-01 with Circular EMK Logo -->
        <div class="p-5 border-b border-white/10 bg-slate-950/60">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] uppercase tracking-wider font-bold text-amber-400">American Spaces Hub</span>
                @if ($workspace['is_active'])
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        Active
                    </span>
                @endif
            </div>

            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full border-2 border-amber-400/90 ring-2 ring-amber-400/25 bg-white p-0.5 shadow-md flex items-center justify-center overflow-hidden shrink-0" aria-hidden="true">
                    <img src="{{ asset('images/emk-logo.png') }}" alt="EMK Center Logo" class="w-full h-full object-contain rounded-full">
                </div>
                <div>
                    <h1 class="text-sm font-bold text-white tracking-tight leading-snug">{{ $workspace['name'] }}</h1>
                    <span class="sr-only">{{ $workspace['id'] }}</span>
                </div>
            </div>
        </div>

        <!-- Desktop Module Navigation Links in 3D Frosted Glass Pill Design -->
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1.5">
            <div class="px-3 pb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">
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
                    class="group flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-medium transition-all {{ $isActive ? 'bg-gradient-to-r from-amber-500/20 to-indigo-500/20 border border-amber-400/40 text-white shadow-sm' : 'text-slate-300 hover:bg-white/5 hover:text-white border border-transparent' }}"
                >
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="w-2 h-2 rounded-full shrink-0 {{ $isActive ? 'bg-amber-400 animate-pulse' : 'bg-slate-500 group-hover:bg-amber-400' }}"></span>
                        <span class="truncate">{{ $module->name }}</span>
                    </div>
                    <span class="sr-only">{{ $module->key }}</span>
                </a>
            @endforeach
        </nav>

        <!-- Desktop Staff User Profile Widget per DEC-06 (D-06) -->
        <div class="p-4 border-t border-white/10 bg-slate-950/70">
            <div class="flex items-center gap-3 mb-2.5">
                <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-amber-400 to-indigo-500 flex items-center justify-center font-bold text-white text-xs shadow-xs shrink-0">
                    {{ strtoupper(substr($user?->name ?? 'Staff', 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-white truncate">{{ $user?->name ?? 'Staff Member' }}</p>
                    <p class="text-[11px] text-slate-400 truncate">{{ $user?->email ?? 'staff@emkcenter.org' }}</p>
                </div>
            </div>

            <div class="flex items-center justify-between mb-3 text-[11px]">
                <span class="px-2 py-0.5 rounded bg-white/10 text-slate-300 border border-white/10 font-mono">guard: web</span>
                <span class="px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 font-medium">Workspace Staff</span>
            </div>

            <form method="POST" action="{{ Route::has('logout') ? route('logout') : url('/logout') }}" class="w-full">
                @csrf
                <button 
                    type="submit" 
                    wire:click.prevent="logout"
                    class="w-full flex items-center justify-center gap-2 px-3 py-2 text-xs font-semibold text-rose-300 hover:text-white bg-rose-500/15 hover:bg-rose-500/25 border border-rose-500/30 rounded-xl transition cursor-pointer"
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
