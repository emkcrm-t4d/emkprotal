<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#0b0f17]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'EMK Center - Member Portal' }}</title>

    <!-- Styles & Scripts via Vite -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    @livewireStyles
    @stack('styles')
</head>
@php
    $isLoginPage = request()->routeIs('portal.login');
@endphp
<body x-data="{ mobileMenuOpen: false }" @keydown.escape.window="mobileMenuOpen = false" class="min-h-full font-sans antialiased text-slate-100 flex flex-col justify-between relative overflow-x-hidden selection:bg-amber-400 selection:text-slate-950">
    <!-- Atmospheric EMK Center Background: 100% Fit, Never Cropped, Device-Pixel Responsive -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0 bg-[#070b12]" aria-hidden="true">
        <!-- Ambient Atmospheric Underlay: Fills any screen aspect ratio with warm lighting -->
        <img 
            src="{{ asset('images/emk-bg.jpg') }}" 
            alt="" 
            class="absolute inset-0 w-full h-full object-cover filter blur-3xl opacity-50 scale-110 pointer-events-none"
            aria-hidden="true"
        >

        <!-- Uncropped Fit Layer: Intact 100%, 0% cropped, responsive to device pixels -->
        <div class="absolute inset-0 flex items-center justify-center">
            <picture class="w-full h-full flex items-center justify-center">
                <source srcset="{{ asset('images/emk-bg.webp') }}" type="image/webp">
                <img 
                    src="{{ asset('images/emk-bg.jpg') }}" 
                    alt="EMK Center Background" 
                    class="w-full h-full object-contain object-center opacity-75 brightness-95 contrast-105 pointer-events-none select-none transition-opacity duration-500"
                    loading="eager"
                    fetchpriority="high"
                >
            </picture>
        </div>

        <!-- Atmospheric Dark Glass Scrim (Permits photography to be clearly visible, center clear, edges soft) -->
        <div class="absolute inset-0 bg-[#070b12]/45 backdrop-blur-[1px]"></div>
        <div class="absolute inset-0 bg-radial-[at_center] from-transparent via-[#070b12]/15 to-[#070b12]/75"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-[#070b12]/80 via-transparent to-[#070b12]/60"></div>
    </div>
    <!-- Skip to main content link for keyboard & screen reader accessibility -->
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:p-4 focus:bg-indigo-600 focus:text-white focus:z-50 focus:rounded-lg focus:shadow-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Skip to main content</a>

    <!-- Toast & Notification Container -->
    <div id="notification-container" role="region" aria-label="Notifications" class="fixed top-4 right-4 z-50 flex flex-col gap-2 max-w-sm pointer-events-none" aria-live="polite">
        @if (session('status') || session('success'))
            <div class="pointer-events-auto bg-slate-900/90 border border-emerald-500/40 text-emerald-300 px-4 py-3 rounded-2xl shadow-xl text-sm flex items-start gap-2.5 backdrop-blur-md">
                <svg class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="flex-1">
                    <p class="font-bold text-white">Success</p>
                    <p class="text-xs text-emerald-300/90 mt-0.5">{{ session('status') ?? session('success') }}</p>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="pointer-events-auto bg-slate-900/90 border border-rose-500/40 text-rose-300 px-4 py-3 rounded-2xl shadow-xl text-sm flex items-start gap-2.5 backdrop-blur-md">
                <svg class="w-5 h-5 text-rose-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="flex-1">
                    <p class="font-bold text-white">Error</p>
                    <p class="text-xs text-rose-300/90 mt-0.5">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        {{ $notifications ?? '' }}
    </div>

    <!-- Portal Navigation Bar -->
    <header role="banner" class="bg-slate-950/70 backdrop-blur-2xl border-b border-white/10 shadow-lg text-white sticky top-0 z-40 transition-colors">
        <div class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12">
            <div class="flex items-center justify-between h-20">
                <!-- Portal Branding (Auto-adjusts to Device Screen / Marked Location) -->
                <div class="flex items-center gap-3">
                    <!-- Mobile Hamburger Button Sized for Fingers (Min 44x44px Touch Target) -->
                    <button
                        type="button"
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        :aria-expanded="mobileMenuOpen.toString()"
                        aria-controls="mobile-navigation-menu"
                        aria-label="Toggle mobile navigation menu"
                        class="md:hidden inline-flex items-center justify-center min-h-[44px] min-w-[44px] p-2.5 rounded-xl text-slate-300 hover:text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-amber-400 cursor-pointer transition-colors"
                    >
                        <span class="sr-only">Toggle navigation menu</span>
                        <svg x-show="!mobileMenuOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg x-show="mobileMenuOpen" x-cloak class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <!-- Exact EMK Logo in Circular Border -->
                    <a href="{{ route('portal.profile') }}" class="flex items-center gap-3 group">
                        <div class="w-11 h-11 sm:w-13 sm:h-13 rounded-full border-2 border-amber-400/90 ring-2 ring-amber-400/25 bg-white shadow-md p-0.5 flex items-center justify-center overflow-hidden shrink-0 transition-all duration-300 group-hover:scale-105" aria-hidden="true">
                            <img src="{{ asset('images/emk-logo.png') }}" alt="EMK Center Logo" class="w-full h-full object-contain rounded-full">
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-300 bg-amber-400/10 border-amber-400/20 px-2 py-0.5 rounded-full border">
                                    Member Portal
                                </span>
                            </div>
                            <h1 class="text-base sm:text-lg font-bold text-white tracking-tight flex items-center gap-1.5 mt-0.5">
                                EMK Center
                                <span class="text-white/30 font-normal" aria-hidden="true">|</span>
                                <span class="text-emerald-300 font-medium text-xs sm:text-sm">American Spaces</span>
                            </h1>
                        </div>
                    </a>
                </div>

                <!-- User Session Identity / Action Bar -->
                <div class="flex items-center gap-4">
                    @if (Auth::guard('member')->check())
                        @php
                            $currentMemberUser = Auth::guard('member')->user();
                            $linkedMember = $currentMemberUser?->member;
                        @endphp
                        <div class="flex items-center gap-3">
                            <div class="hidden sm:flex flex-col text-right">
                                <span class="text-sm font-semibold text-white leading-tight">
                                    {{ $currentMemberUser->name }}
                                </span>
                                @if ($linkedMember?->member_id)
                                    <button
                                        type="button"
                                        @click="$dispatch('open-member-id-card-modal')"
                                        class="text-[11px] font-mono font-medium text-amber-300 bg-amber-400/15 border border-amber-400/30 hover:bg-amber-400/25 px-2.5 py-0.5 rounded-lg self-end mt-0.5 cursor-pointer transition shadow-xs flex items-center gap-1.5 group"
                                        title="Click to view 3D Digital Member ID Card"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                                        <span>{{ $linkedMember->member_id }}</span>
                                        <svg class="w-3.5 h-3.5 text-amber-300/80 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                @else
                                    <span class="text-xs text-slate-400">{{ $currentMemberUser->email }}</span>
                                @endif
                            </div>

                            <form method="POST" action="{{ route('portal.logout') }}" class="inline">
                                @csrf
                                <button type="submit" class="inline-flex items-center justify-center gap-1.5 min-h-[44px] px-3.5 py-2 text-xs font-semibold text-slate-200 hover:text-rose-300 bg-white/5 hover:bg-rose-500/20 border border-white/10 hover:border-rose-500/30 rounded-xl transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-amber-400" title="Log out of member portal" aria-label="Sign Out">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    <span class="hidden md:inline">Sign Out</span>
                                </button>
                            </form>
                        </div>
                    @else
                        <div class="flex items-center gap-2 bg-emerald-500/15 border-emerald-400/30 text-emerald-300 backdrop-blur-md border px-3 py-1.5 rounded-full text-xs font-medium" role="status" aria-label="Security Status: Secure Member Gateway">
                            <svg class="w-3.5 h-3.5 text-emerald-400 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" />
                            </svg>
                            <span>Secure Member Gateway</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </header>

    <!-- Mobile Drawer Navigation -->
    <div
        id="mobile-navigation-menu"
        role="dialog"
        aria-modal="true"
        aria-label="Mobile Navigation Drawer"
        x-show="mobileMenuOpen"
        x-cloak
        class="fixed inset-0 z-50 md:hidden"
        @keydown.escape="mobileMenuOpen = false"
    >
        <!-- Backdrop Overlay -->
        <div
            x-show="mobileMenuOpen"
            x-transition:enter="transition-opacity ease-linear duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="mobileMenuOpen = false"
            class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm"
            aria-hidden="true"
        ></div>

        <!-- Drawer Panel -->
        <nav
            aria-label="Mobile Navigation"
            x-show="mobileMenuOpen"
            x-transition:enter="transition ease-in-out duration-300 transform"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in-out duration-300 transform"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="relative max-w-xs w-full bg-slate-900/95 backdrop-blur-2xl border-r border-white/10 text-white h-full shadow-2xl p-6 flex flex-col justify-between overflow-y-auto"
        >
            <div class="space-y-6">
                <div class="flex items-center justify-between pb-4 border-b border-white/10">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full border-2 border-amber-400/90 bg-white shadow-sm p-0.5 flex items-center justify-center overflow-hidden shrink-0" aria-hidden="true">
                            <img src="{{ asset('images/emk-logo.png') }}" alt="EMK Center Logo" class="w-full h-full object-contain rounded-full">
                        </div>
                        <div>
                            <span class="font-bold text-white text-sm block leading-tight">EMK Center</span>
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-amber-400">Member Portal</span>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="mobileMenuOpen = false"
                        aria-label="Close navigation menu"
                        class="min-h-[48px] min-w-[48px] inline-flex items-center justify-center rounded-xl text-slate-400 hover:text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-amber-400 cursor-pointer transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                @if (Auth::guard('member')->check())
                    @php
                        $currentMemberUser = Auth::guard('member')->user();
                        $linkedMember = $currentMemberUser?->member;
                    @endphp
                    <div class="p-3 bg-white/5 rounded-2xl border border-white/10">
                        <p class="text-xs font-semibold text-white">{{ $currentMemberUser->name }}</p>
                        @if ($linkedMember?->member_id)
                            <button
                                type="button"
                                @click="mobileMenuOpen = false; $dispatch('open-member-id-card-modal')"
                                class="text-[11px] font-mono text-amber-300 hover:text-amber-200 mt-1 flex items-center gap-1.5 cursor-pointer"
                            >
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                                <span>{{ $linkedMember->member_id }}</span>
                                <span class="text-[10px] text-amber-400/80 font-sans">(View 3D Pass)</span>
                            </button>
                        @else
                            <p class="text-xs text-slate-400 mt-0.5">{{ $currentMemberUser->email }}</p>
                        @endif
                    </div>
                @endif

                <div class="space-y-2">
                    <a
                        href="{{ route('portal.profile') }}"
                        class="flex items-center gap-3.5 px-4 py-3 min-h-[48px] text-sm font-semibold rounded-xl text-slate-200 hover:text-white hover:bg-white/10 border border-white/5 transition-colors focus:outline-none focus:ring-2 focus:ring-amber-400"
                    >
                        <svg class="w-5 h-5 text-amber-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>Personal Profile</span>
                    </a>

                    @if (!Auth::guard('member')->check())
                        <a
                            href="{{ route('portal.login') }}"
                            class="flex items-center gap-3.5 px-4 py-3 min-h-[48px] text-sm font-semibold rounded-xl text-slate-900 bg-amber-400 hover:bg-amber-300 border border-amber-300 transition-colors focus:outline-none focus:ring-2 focus:ring-amber-400"
                        >
                            <svg class="w-5 h-5 text-slate-950 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                            </svg>
                            <span>Member Login</span>
                        </a>
                    @endif

                    <div class="pt-3 pb-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 px-1">Support & Assistance</span>
                    </div>

                    <a
                        href="tel:+8809606513103"
                        class="flex items-center gap-3.5 px-4 py-3 min-h-[48px] text-xs font-medium rounded-xl text-slate-300 hover:text-white hover:bg-white/5 border border-white/5 transition-colors"
                    >
                        <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        <span>+8809606513103</span>
                    </a>

                    <a
                        href="mailto:info@emkcenter.org"
                        class="flex items-center gap-3.5 px-4 py-3 min-h-[48px] text-xs font-medium rounded-xl text-slate-300 hover:text-white hover:bg-white/5 border border-white/5 transition-colors"
                    >
                        <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span>info@emkcenter.org</span>
                    </a>
                </div>
            </div>

            <div class="pt-6 border-t border-white/10">
                @if (Auth::guard('member')->check())
                    <form method="POST" action="{{ route('portal.logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="w-full flex items-center justify-center gap-2 px-4 py-3 min-h-[48px] text-sm font-semibold text-rose-300 bg-rose-500/20 hover:bg-rose-500/30 rounded-xl border border-rose-500/30 transition focus:outline-none focus:ring-2 focus:ring-rose-500 cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            <span>Sign Out</span>
                        </button>
                    </form>
                @endif
            </div>
        </nav>
    </div>

    <!-- Main Content Body (Fluid Full Window Container per User Request) -->
    <main id="main-content" role="main" class="flex-1 py-6 sm:py-8 px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 flex flex-col justify-start relative z-10">
        <div class="w-full mx-auto {{ $isLoginPage ? 'max-w-5xl my-auto' : 'max-w-7xl' }}">
            {{ $slot ?? '' }}
            @yield('content')
        </div>
    </main>

    <!-- Portal Footer -->
    <footer role="contentinfo" class="bg-slate-950/60 backdrop-blur-xl border-t border-white/10 text-slate-400 py-6 text-xs transition-colors">
        <div class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2 text-slate-400">
                    <span>&copy; {{ date('Y') }} EMK Center. All rights reserved.•T4D </span>
                </div>
                <div class="flex flex-wrap items-center justify-center sm:justify-end gap-3 sm:gap-4 text-slate-400">
                    <span class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-amber-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        <span>Phone: <a href="tel:+8809606513103" class="text-amber-300 hover:text-amber-200 font-medium">+8809606513103</a> | <a href="tel:+8801926666995" class="text-amber-300 hover:text-amber-200 font-medium">+8801926666995</a></span>
                    </span>
                    <span aria-hidden="true" class="text-slate-600">•</span>
                    <span class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-amber-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span>Email: <a href="mailto:info@emkcenter.org" class="text-amber-300 hover:text-amber-200 font-medium">info@emkcenter.org</a></span>
                    </span>
                </div>
            </div>
        </div>
    </footer>

    @livewireScripts
    @stack('scripts')
</body>
</html>

    @livewireScripts
    @stack('scripts')
</body>
</html>
