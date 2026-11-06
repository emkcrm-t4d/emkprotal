<div 
    x-data="{ 
        settingsSubmenuOpen: {{ (request()->is("crm/{$workspace['id']}/settings*") || request()->is("crm/{$workspace['id']}/platform_services*") || request()->is("crm/{$workspace['id']}/audit-logs*") || request()->is("crm/{$workspace['id']}/staff*")) ? 'true' : 'false' }}
    }" 
    class="relative"
>
    <!-- Left-Edge Hover Detection Strip (Triggers Auto-Reveal on Desktop when mouse touches left edge) -->
    <div 
        class="hidden lg:block fixed inset-y-0 left-0 w-3 z-40 cursor-pointer pointer-events-auto"
        @mouseenter="$store.sidebar && $store.sidebar.onMouseEnter()"
        title="Hover to open navigation menu"
        aria-hidden="true"
    ></div>

    <!-- Top Navigation Bar: Unified Fluid Header for Both Desktop & Mobile -->
    <header class="sticky top-0 z-40 bg-slate-950/80 backdrop-blur-2xl border-b border-white/10 text-white px-4 sm:px-6 py-2.5 flex items-center justify-between shadow-xl">
        <div class="flex items-center gap-3 sm:gap-4">
            <!-- Manual Navigation Toggle Button (Desktop & Mobile) -->
            <button 
                type="button" 
                @click="$store.sidebar ? $store.sidebar.toggleManual() : null"
                class="min-h-[44px] min-w-[44px] p-2 rounded-xl text-slate-300 hover:text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-amber-400 cursor-pointer transition-colors flex items-center justify-center group"
                aria-label="Toggle navigation menu"
                title="Toggle Sidebar Menu"
            >
                <svg class="w-6 h-6 group-hover:scale-105 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <!-- Workspace Branding & Identity -->
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full border border-amber-400/90 bg-white p-0.5 shadow-md flex items-center justify-center overflow-hidden shrink-0" aria-hidden="true">
                    <img src="{{ asset('images/emk-logo.png') }}" alt="EMK Center" class="w-full h-full object-contain rounded-full">
                </div>
                <div class="flex flex-col">
                    <span class="font-bold text-sm sm:text-base text-white tracking-tight leading-tight">{{ $workspace['name'] }}</span>
                    <span class="sr-only">{{ $workspace['id'] }}</span>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="text-[10px] uppercase tracking-wider font-bold text-amber-400 hidden sm:inline">American Spaces Hub</span>
                        @if ($workspace['is_active'])
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                Active
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side Top Controls: Mode Toggle, Profile Capsule & Sign Out -->
        <div class="flex items-center gap-2.5 sm:gap-4">
            <!-- Global SMTP Broken Server Warning Alert Button -->
            @if ($hasBrokenSmtp ?? false)
                <a 
                    href="{{ route('crm.smtp.index', $workspace['id']) }}" 
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-rose-500/20 text-rose-200 border border-rose-500/50 hover:bg-rose-500/30 transition shadow-lg shadow-rose-950/40 cursor-pointer animate-pulse"
                    title="{{ $brokenRelay ? 'SMTP Relay \'' . $brokenRelay->name . '\' failed: ' . $brokenRelay->last_error_message : 'SMTP Email server connection is broken!' }}"
                >
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                    </span>
                    <span class="font-extrabold text-white">Email Server Broken</span>
                    <svg class="w-3.5 h-3.5 text-rose-300 hidden sm:inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </a>
            @endif

            <!-- Auto-Hide vs Pinned Toggle Pill (Standard Desktop/Tablet Switch) -->
            <button 
                type="button"
                @click="$store.sidebar ? $store.sidebar.togglePin() : null"
                class="hidden sm:inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold backdrop-blur-md border transition-all duration-200 cursor-pointer shadow-sm select-none"
                :class="($store.sidebar && $store.sidebar.pinned) ? 'bg-amber-400/20 text-amber-300 border-amber-400/40 hover:bg-amber-400/30' : 'bg-white/10 text-slate-300 border-white/15 hover:bg-white/15 hover:text-white'"
                :title="($store.sidebar && $store.sidebar.pinned) ? 'Mode: Pinned (click to switch to Auto-Hide 3s)' : 'Mode: Auto-Hide 3s (click to Pin)'"
            >
                <template x-if="$store.sidebar && $store.sidebar.pinned">
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M5 4a1 1 0 00-2 0v7.268a2 2 0 000 3.464V16a1 1 0 102 0v-1.268a2 2 0 000-3.464V4zM11 4a1 1 0 10-2 0v1.268a2 2 0 000 3.464V16a1 1 0 102 0V8.732a2 2 0 000-3.464V4zM16 3a1 1 0 011 1v7.268a2 2 0 010 3.464V16a1 1 0 11-2 0v-1.268a2 2 0 010-3.464V4a1 1 0 011-1z" />
                        </svg>
                        <span>Pinned</span>
                    </span>
                </template>
                <template x-if="!($store.sidebar && $store.sidebar.pinned)">
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Auto-Hide (3s)</span>
                    </span>
                </template>
            </button>

            <!-- Staff Identity Capsule -->
            <div class="hidden md:flex items-center gap-2 px-3 py-1 rounded-full bg-white/5 border border-white/10 text-xs text-slate-300">
                <div class="w-6 h-6 rounded-full bg-gradient-to-tr from-amber-400 to-indigo-500 flex items-center justify-center font-bold text-white text-[10px] shadow-xs shrink-0">
                    {{ strtoupper(substr($user?->name ?? 'Staff', 0, 1)) }}
                </div>
                <span class="font-medium text-white max-w-[130px] truncate">{{ $user?->name ?? 'Staff' }}</span>
            </div>

            <!-- Sign Out Button -->
            <form method="POST" action="{{ Route::has('logout') ? route('logout') : url('/logout') }}">
                @csrf
                <button 
                    type="submit" 
                    wire:click.prevent="logout"
                    class="min-h-[40px] flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold text-rose-300 hover:text-white bg-rose-500/15 hover:bg-rose-500/25 border border-rose-500/30 rounded-full transition cursor-pointer"
                    title="Sign Out"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span class="hidden sm:inline">Sign Out</span>
                </button>
            </form>
        </div>
    </header>

    <!-- Mobile Sidebar Drawer Backdrop -->
    <div 
        x-show="$store.sidebar && $store.sidebar.mobileOpen" 
        x-transition:enter="transition-opacity ease-linear duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="$store.sidebar ? $store.sidebar.closeMobile() : null"
        class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 lg:hidden"
        style="display: none;"
    ></div>

    <!-- Mobile Slide-Over Drawer -->
    <div 
        x-show="$store.sidebar && $store.sidebar.mobileOpen"
        x-transition:enter="transition ease-in-out duration-300 transform"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in-out duration-300 transform"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="fixed inset-y-0 left-0 z-50 w-72 bg-slate-950/95 backdrop-blur-2xl border-r border-white/10 text-white flex flex-col lg:hidden"
        style="display: none;"
    >
        <!-- Mobile Drawer Header -->
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
                @click="$store.sidebar ? $store.sidebar.closeMobile() : null"
                class="min-h-[44px] min-w-[44px] p-2 rounded-xl text-slate-400 hover:text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-amber-400 cursor-pointer"
                aria-label="Close menu"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Mobile Module Navigation in Sequence -->
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1.5">
            <div class="px-3 pb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                Core Modules ({{ count($modules) }})
            </div>
            @foreach ($modules as $module)
                @php
                    $modulePath = match ($module->key) {
                        'dashboard_reports' => "/crm/{$workspace['id']}/reports",
                        'platform_services' => "/crm/{$workspace['id']}/settings",
                        'member_portal' => "/portal",
                        'migration' => "/crm/{$workspace['id']}/migration/reconciliation",
                        default => "/crm/{$workspace['id']}/{$module->key}",
                    };
                    $isActive = ($activeModule === $module->key) || match ($module->key) {
                        'dashboard_reports' => request()->is("crm/{$workspace['id']}/reports*") || request()->is("crm/{$workspace['id']}/dashboard*") || request()->is("crm/{$workspace['id']}/exports*") || request()->is("crm/{$workspace['id']}/data-export*"),
                        'platform_services' => request()->is("crm/{$workspace['id']}/settings*") || request()->is("crm/{$workspace['id']}/platform_services*") || request()->is("crm/{$workspace['id']}/audit-logs*") || request()->is("crm/{$workspace['id']}/staff*"),
                        'migration' => request()->is("crm/{$workspace['id']}/migration*"),
                        'member_portal' => request()->is("portal*"),
                        default => request()->is("crm/{$workspace['id']}/{$module->key}*"),
                    };
                    $moduleTitle = $displayNames[$module->key] ?? $module->name;
                @endphp
                @if ($module->key === 'platform_services')
                    <div class="space-y-1">
                        <div class="flex items-center justify-between px-3.5 py-3 min-h-[44px] rounded-xl text-xs sm:text-sm font-semibold transition-all {{ $isActive ? 'bg-gradient-to-r from-amber-500/20 to-indigo-500/20 border border-amber-400/40 text-amber-300 shadow-sm' : 'text-slate-300 hover:bg-white/5 hover:text-white border border-transparent' }}">
                            <a href="{{ $modulePath }}" class="flex items-center gap-3 min-w-0 flex-1">
                                <svg class="w-4 h-4 shrink-0 {{ $isActive ? 'text-amber-400' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span class="truncate">{{ $moduleTitle }}</span>
                                @if ($hasBrokenSmtp ?? false)
                                    <span class="relative flex h-2 w-2 ml-1" title="Email Server Connection Warning">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                                    </span>
                                @endif
                                <span class="sr-only">{{ $module->key }}</span>
                            </a>
                            <button 
                                type="button" 
                                @click.prevent.stop="settingsSubmenuOpen = !settingsSubmenuOpen" 
                                class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-transform duration-200 cursor-pointer"
                                :class="settingsSubmenuOpen ? 'rotate-180 text-amber-400' : ''" 
                                aria-label="Toggle Settings Submenu"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>
                        <div x-show="settingsSubmenuOpen" x-transition.opacity.duration.200ms class="pl-2 pr-1 py-1 space-y-1 ml-4 border-l-2 border-amber-400/30">
                            @foreach ($settingsSubmenus as $sub)
                                <a 
                                    href="{{ $sub['route'] }}" 
                                    class="group flex items-center justify-between px-3 py-2 rounded-xl text-xs font-medium transition-all {{ $sub['active'] ? 'bg-amber-400/20 text-amber-300 font-bold border border-amber-400/30 shadow-xs' : 'text-slate-300 hover:text-white hover:bg-white/5 border border-transparent' }} {{ (!empty($sub['has_error'])) ? 'border-rose-500/40 bg-rose-950/20 text-rose-200 hover:text-rose-100 hover:bg-rose-900/30' : '' }}"
                                >
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <span class="w-1.5 h-1.5 rounded-full {{ (!empty($sub['has_error'])) ? 'bg-rose-500 ring-2 ring-rose-500/50 animate-pulse' : ($sub['active'] ? 'bg-amber-400 ring-2 ring-amber-400/30' : 'bg-slate-600 group-hover:bg-amber-400') }} transition-colors"></span>
                                        <span class="truncate">{!! $sub['title'] !!}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        @if (!empty($sub['has_error']))
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-extrabold bg-rose-500/30 text-rose-200 border border-rose-500/60 shadow-xs animate-pulse">
                                                <svg class="w-2.5 h-2.5 text-rose-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                Broken
                                            </span>
                                        @elseif ($sub['active'])
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @else
                <a 
                    href="{{ $modulePath }}" 
                    class="group flex items-center gap-3 px-3.5 py-3 min-h-[44px] rounded-xl text-xs sm:text-sm font-semibold transition-all {{ $isActive ? 'bg-gradient-to-r from-amber-500/20 to-indigo-500/20 border border-amber-400/40 text-amber-300 shadow-sm' : 'text-slate-300 hover:bg-white/5 hover:text-white border border-transparent' }}"
                >
                    <!-- Icon based on module -->
                    @if ($module->key === 'leads')
                        <svg class="w-4 h-4 shrink-0 {{ $isActive ? 'text-amber-400' : 'text-slate-400 group-hover:text-amber-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                    @elseif ($module->key === 'members')
                        <svg class="w-4 h-4 shrink-0 {{ $isActive ? 'text-amber-400' : 'text-slate-400 group-hover:text-amber-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                        </svg>
                    @elseif ($module->key === 'invoices')
                        <svg class="w-4 h-4 shrink-0 {{ $isActive ? 'text-amber-400' : 'text-slate-400 group-hover:text-amber-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                        </svg>
                    @elseif ($module->key === 'dashboard_reports')
                        <svg class="w-4 h-4 shrink-0 {{ $isActive ? 'text-amber-400' : 'text-slate-400 group-hover:text-amber-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    @elseif ($module->key === 'forms')
                        <svg class="w-4 h-4 shrink-0 {{ $isActive ? 'text-amber-400' : 'text-slate-400 group-hover:text-amber-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                    @elseif ($module->key === 'member_portal')
                        <svg class="w-4 h-4 shrink-0 {{ $isActive ? 'text-amber-400' : 'text-slate-400 group-hover:text-amber-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                    @elseif ($module->key === 'migration')
                        <svg class="w-4 h-4 shrink-0 {{ $isActive ? 'text-amber-400' : 'text-slate-400 group-hover:text-amber-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                        </svg>
                    @endif

                    <span class="flex-1 truncate">{{ $moduleTitle }}</span>
                    <span class="sr-only">{{ $module->key }}</span>
                </a>
                @endif
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
            <span class="sr-only">guard: web</span>
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

    <!-- Desktop Auto-Hide & Pinned Sidebar Panel in 3D Frosted Dark Glass Theme -->
    <aside 
        class="hidden lg:flex fixed inset-y-0 left-0 z-50 w-64 flex-col bg-slate-950/90 backdrop-blur-2xl border-r border-white/15 text-white shadow-2xl transition-transform duration-300 ease-in-out"
        :class="($store.sidebar && ($store.sidebar.pinned || $store.sidebar.open)) ? 'translate-x-0 pointer-events-auto' : '-translate-x-full pointer-events-none'"
        @mouseenter="$store.sidebar && $store.sidebar.onMouseEnter()"
        @mouseleave="$store.sidebar && $store.sidebar.onMouseLeave()"
    >
        <!-- Desktop Sidebar Tenancy Header -->
        <div class="p-4 border-b border-white/10 bg-slate-950/60 flex items-center justify-between">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-full border-2 border-amber-400/90 ring-2 ring-amber-400/25 bg-white p-0.5 shadow-md flex items-center justify-center overflow-hidden shrink-0" aria-hidden="true">
                    <img src="{{ asset('images/emk-logo.png') }}" alt="EMK Center Logo" class="w-full h-full object-contain rounded-full">
                </div>
                <div class="min-w-0">
                    <h1 class="text-xs font-bold text-white tracking-tight leading-snug truncate">{{ $workspace['name'] }}</h1>
                    <span class="sr-only">{{ $workspace['id'] }}</span>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span class="text-[9px] uppercase tracking-wider font-bold text-amber-400 truncate">American Spaces</span>
                        @if ($workspace['is_active'])
                            <span class="text-[9px] text-emerald-400 font-semibold">● Active</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Quick Pin/Unpin Toggle Button in Sidebar Header -->
            <button 
                type="button" 
                @click="$store.sidebar ? $store.sidebar.togglePin() : null" 
                class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer shrink-0"
                :title="($store.sidebar && $store.sidebar.pinned) ? 'Sidebar is Pinned (click to enable Auto-Hide 3s)' : 'Sidebar is Auto-Hide (click to Pin)'"
            >
                <svg class="w-4 h-4" :class="($store.sidebar && $store.sidebar.pinned) ? 'text-amber-400' : 'text-slate-400'" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M5 4a1 1 0 00-2 0v7.268a2 2 0 000 3.464V16a1 1 0 102 0v-1.268a2 2 0 000-3.464V4zM11 4a1 1 0 10-2 0v1.268a2 2 0 000 3.464V16a1 1 0 102 0V8.732a2 2 0 000-3.464V4zM16 3a1 1 0 011 1v7.268a2 2 0 010 3.464V16a1 1 0 11-2 0v-1.268a2 2 0 010-3.464V4a1 1 0 011-1z" />
                </svg>
            </button>
        </div>

        <!-- Desktop Module Navigation Links in Requested Sequence & Renaming -->
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
            <div class="px-3 pb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400 flex items-center justify-between">
                <span>Core Modules ({{ count($modules) }})</span>
                <span class="text-[10px] text-slate-500 lowercase font-normal" x-show="!($store.sidebar && $store.sidebar.pinned)">(auto-hide)</span>
            </div>

            @foreach ($modules as $module)
                @php
                    $modulePath = match ($module->key) {
                        'dashboard_reports' => "/crm/{$workspace['id']}/reports",
                        'platform_services' => "/crm/{$workspace['id']}/settings",
                        'member_portal' => "/portal",
                        'migration' => "/crm/{$workspace['id']}/migration/reconciliation",
                        default => "/crm/{$workspace['id']}/{$module->key}",
                    };
                    $isActive = ($activeModule === $module->key) || match ($module->key) {
                        'dashboard_reports' => request()->is("crm/{$workspace['id']}/reports*") || request()->is("crm/{$workspace['id']}/dashboard*") || request()->is("crm/{$workspace['id']}/exports*") || request()->is("crm/{$workspace['id']}/data-export*"),
                        'platform_services' => request()->is("crm/{$workspace['id']}/settings*") || request()->is("crm/{$workspace['id']}/platform_services*") || request()->is("crm/{$workspace['id']}/audit-logs*") || request()->is("crm/{$workspace['id']}/staff*"),
                        'migration' => request()->is("crm/{$workspace['id']}/migration*"),
                        'member_portal' => request()->is("portal*"),
                        default => request()->is("crm/{$workspace['id']}/{$module->key}*"),
                    };
                    $moduleTitle = $displayNames[$module->key] ?? $module->name;
                @endphp
                @if ($module->key === 'platform_services')
                    <div class="space-y-1">
                        <div class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-medium transition-all {{ $isActive ? 'bg-gradient-to-r from-amber-500/20 to-indigo-500/20 border border-amber-400/40 text-white shadow-sm' : 'text-slate-300 hover:bg-white/5 hover:text-white border border-transparent' }}">
                            <a href="{{ $modulePath }}" class="flex items-center gap-3 min-w-0 flex-1">
                                <svg class="w-4 h-4 shrink-0 {{ $isActive ? 'text-amber-400' : 'text-slate-400 group-hover:text-amber-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span class="truncate">{{ $moduleTitle }}</span>
                                @if ($hasBrokenSmtp ?? false)
                                    <span class="relative flex h-2 w-2 ml-1" title="Email Server Connection Warning">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                                    </span>
                                @endif
                                <span class="sr-only">{{ $module->key }}</span>
                            </a>
                            <div class="flex items-center gap-1">
                                @if ($isActive)
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse shrink-0"></span>
                                @endif
                                <button 
                                    type="button" 
                                    @click.prevent.stop="settingsSubmenuOpen = !settingsSubmenuOpen" 
                                    class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-transform duration-200 cursor-pointer"
                                    :class="settingsSubmenuOpen ? 'rotate-180 text-amber-400' : ''" 
                                    aria-label="Toggle Settings Submenu"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Submenu Tree for Mobile -->
                        <div x-show="settingsSubmenuOpen" x-transition.opacity.duration.200ms class="pl-2 pr-1 py-1 space-y-0.5 ml-4 border-l border-amber-400/30">
                            @foreach ($settingsSubmenus as $sub)
                                <a 
                                    href="{{ $sub['route'] }}" 
                                    class="group flex items-center justify-between px-3 py-1.5 rounded-lg text-xs font-medium transition-all {{ $sub['active'] ? 'bg-amber-400/20 text-amber-300 font-bold border border-amber-400/30 shadow-xs' : 'text-slate-300 hover:text-white hover:bg-white/5 border border-transparent' }} {{ (!empty($sub['has_error'])) ? 'border-rose-500/40 bg-rose-950/20 text-rose-200 hover:text-rose-100 hover:bg-rose-900/30' : '' }}"
                                >
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="w-1.5 h-1.5 rounded-full {{ (!empty($sub['has_error'])) ? 'bg-rose-500 ring-2 ring-rose-500/50 animate-pulse' : ($sub['active'] ? 'bg-amber-400 ring-2 ring-amber-400/30' : 'bg-slate-600 group-hover:bg-amber-400') }} transition-colors"></span>
                                        <span class="truncate">{!! $sub['title'] !!}</span>
                                    </div>
                                    <div class="flex items-center gap-1 shrink-0">
                                        @if (!empty($sub['has_error']))
                                            <span class="inline-flex items-center gap-0.5 px-1 py-0.5 rounded text-[9px] font-bold bg-rose-500/30 text-rose-200 border border-rose-500/60 animate-pulse">
                                                Broken
                                            </span>
                                        @elseif ($sub['active'])
                                            <span class="w-1 h-1 rounded-full bg-amber-400 animate-pulse shrink-0"></span>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @else
                <a 
                    href="{{ $modulePath }}" 
                    class="group flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-medium transition-all {{ $isActive ? 'bg-gradient-to-r from-amber-500/20 to-indigo-500/20 border border-amber-400/40 text-white shadow-sm' : 'text-slate-300 hover:bg-white/5 hover:text-white border border-transparent' }}"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <!-- Module Icon -->
                        @if ($module->key === 'leads')
                            <svg class="w-4 h-4 shrink-0 {{ $isActive ? 'text-amber-400' : 'text-slate-400 group-hover:text-amber-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                            </svg>
                        @elseif ($module->key === 'members')
                            <svg class="w-4 h-4 shrink-0 {{ $isActive ? 'text-amber-400' : 'text-slate-400 group-hover:text-amber-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                            </svg>
                        @elseif ($module->key === 'invoices')
                            <svg class="w-4 h-4 shrink-0 {{ $isActive ? 'text-amber-400' : 'text-slate-400 group-hover:text-amber-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                            </svg>
                        @elseif ($module->key === 'dashboard_reports')
                            <svg class="w-4 h-4 shrink-0 {{ $isActive ? 'text-amber-400' : 'text-slate-400 group-hover:text-amber-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        @elseif ($module->key === 'forms')
                            <svg class="w-4 h-4 shrink-0 {{ $isActive ? 'text-amber-400' : 'text-slate-400 group-hover:text-amber-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>
                        @elseif ($module->key === 'member_portal')
                            <svg class="w-4 h-4 shrink-0 {{ $isActive ? 'text-amber-400' : 'text-slate-400 group-hover:text-amber-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        @elseif ($module->key === 'migration')
                            <svg class="w-4 h-4 shrink-0 {{ $isActive ? 'text-amber-400' : 'text-slate-400 group-hover:text-amber-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                            </svg>
                        @endif

                        <span class="truncate">{{ $moduleTitle }}</span>
                    </div>

                    @if ($isActive)
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse shrink-0"></span>
                    @endif

                    <span class="sr-only">{{ $module->key }}</span>
                </a>
                @endif
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

            <span class="sr-only">guard: web</span>

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