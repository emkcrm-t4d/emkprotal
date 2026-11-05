<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
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
<body x-data="{ mobileMenuOpen: false }" @keydown.escape.window="mobileMenuOpen = false" class="min-h-full font-sans antialiased {{ $isLoginPage ? 'text-slate-100 bg-[#061d16] selection:bg-amber-400 selection:text-slate-950' : 'text-slate-900 bg-slate-50' }} flex flex-col justify-between relative overflow-x-hidden">
    @if ($isLoginPage)
        <!-- Ambient Glass Theme Backdrop Lighting -->
        <div class="fixed inset-0 overflow-hidden pointer-events-none -z-10" aria-hidden="true">
            <div class="absolute -top-32 -left-32 w-[34rem] h-[34rem] bg-emerald-500/20 rounded-full blur-[130px]"></div>
            <div class="absolute top-1/4 -right-24 w-[36rem] h-[36rem] bg-amber-500/15 rounded-full blur-[150px]"></div>
            <div class="absolute -bottom-32 left-1/3 w-[38rem] h-[38rem] bg-teal-600/15 rounded-full blur-[160px]"></div>
            <div class="absolute inset-0 bg-[radial-gradient(rgba(255,255,255,0.06)_1px,transparent_1px)] [background-size:24px_24px] opacity-40"></div>
        </div>
    @endif
    <!-- Skip to main content link for keyboard & screen reader accessibility -->
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:p-4 focus:bg-indigo-600 focus:text-white focus:z-50 focus:rounded-lg focus:shadow-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Skip to main content</a>

    <!-- Toast & Notification Container -->
    <div id="notification-container" role="region" aria-label="Notifications" class="fixed top-4 right-4 z-50 flex flex-col gap-2 max-w-sm pointer-events-none" aria-live="polite">
        @if (session('status') || session('success'))
            <div class="pointer-events-auto bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg shadow-sm text-sm flex items-start gap-2.5">
                <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="flex-1">
                    <p class="font-medium">Success</p>
                    <p class="text-xs text-emerald-700 mt-0.5">{{ session('status') ?? session('success') }}</p>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="pointer-events-auto bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-lg shadow-sm text-sm flex items-start gap-2.5">
                <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="flex-1">
                    <p class="font-medium">Error</p>
                    <p class="text-xs text-rose-700 mt-0.5">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        {{ $notifications ?? '' }}
    </div>

    <!-- Portal Navigation Bar -->
    <header role="banner" class="{{ $isLoginPage ? 'bg-slate-950/40 backdrop-blur-xl border-b border-white/10 shadow-lg text-white' : 'bg-white border-b border-slate-200 shadow-xs' }} sticky top-0 z-40 transition-colors">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <!-- Portal Branding -->
                <div class="flex items-center gap-3">
                    <!-- Mobile Hamburger Button -->
                    <button
                        type="button"
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        :aria-expanded="mobileMenuOpen.toString()"
                        aria-controls="mobile-navigation-menu"
                        aria-label="Toggle mobile navigation menu"
                        class="md:hidden inline-flex items-center justify-center min-h-[44px] min-w-[44px] p-2 rounded-lg {{ $isLoginPage ? 'text-slate-300 hover:text-white hover:bg-white/10' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }} focus:outline-none focus:ring-2 focus:ring-amber-400 cursor-pointer"
                    >
                        <span class="sr-only">Toggle navigation menu</span>
                        <svg x-show="!mobileMenuOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg x-show="mobileMenuOpen" x-cloak class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <a href="{{ route('portal.profile') }}" class="flex items-center gap-3.5 group">
                        <div class="w-11 h-11 rounded-xl bg-gradient-to-tr {{ $isLoginPage ? 'from-amber-400 to-yellow-500 text-slate-950 font-black shadow-amber-500/20' : 'from-indigo-700 via-blue-600 to-sky-500 text-white font-bold' }} flex items-center justify-center shadow-sm text-base tracking-wider group-hover:shadow-md transition-shadow" aria-hidden="true">
                            EMK
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-bold uppercase tracking-wider {{ $isLoginPage ? 'text-amber-300 bg-amber-400/10 border-amber-400/20' : 'text-blue-700 bg-blue-50 border-blue-200' }} px-2 py-0.5 rounded-full border">
                                    Member Portal
                                </span>
                            </div>
                            <h1 class="text-base sm:text-lg font-bold {{ $isLoginPage ? 'text-white' : 'text-slate-900' }} tracking-tight flex items-center gap-1.5 mt-0.5">
                                EMK Center
                                <span class="{{ $isLoginPage ? 'text-white/30' : 'text-slate-300' }} font-normal" aria-hidden="true">|</span>
                                <span class="{{ $isLoginPage ? 'text-emerald-200/90' : 'text-slate-600' }} font-medium text-xs sm:text-sm">American Spaces</span>
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
                                <span class="text-sm font-semibold text-slate-900 leading-tight">
                                    {{ $currentMemberUser->name }}
                                </span>
                                @if ($linkedMember?->member_id)
                                    <span class="text-[11px] font-mono font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded self-end mt-0.5">
                                        {{ $linkedMember->member_id }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-500">{{ $currentMemberUser->email }}</span>
                                @endif
                            </div>

                            <form method="POST" action="{{ route('portal.logout') }}" class="inline">
                                @csrf
                                <button type="submit" class="inline-flex items-center justify-center gap-1.5 min-h-[44px] px-3.5 py-2 text-xs font-medium text-slate-700 hover:text-rose-700 bg-slate-100 hover:bg-rose-50 border border-slate-200 hover:border-rose-200 rounded-lg transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-indigo-500" title="Log out of member portal" aria-label="Sign Out">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    <span class="hidden md:inline">Sign Out</span>
                                </button>
                            </form>
                        </div>
                    @else
                        <div class="flex items-center gap-2 {{ $isLoginPage ? 'bg-emerald-500/15 border-emerald-400/30 text-emerald-300 backdrop-blur-md' : 'bg-emerald-50 border-emerald-200 text-emerald-800' }} border px-3 py-1 rounded-full text-xs font-medium" role="status" aria-label="Security Status: Secure Member Gateway">
                            <svg class="w-3.5 h-3.5 {{ $isLoginPage ? 'text-emerald-400' : 'text-emerald-600' }} shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
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
            class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs"
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
            class="relative max-w-xs w-full bg-white h-full shadow-2xl p-6 flex flex-col justify-between overflow-y-auto"
        >
            <div class="space-y-6">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-700 via-blue-600 to-sky-500 flex items-center justify-center text-white font-bold text-sm" aria-hidden="true">
                            EMK
                        </div>
                        <span class="font-bold text-slate-900 text-sm">Member Portal</span>
                    </div>
                    <button
                        type="button"
                        @click="mobileMenuOpen = false"
                        aria-label="Close navigation menu"
                        class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer"
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
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                        <p class="text-xs font-semibold text-slate-900">{{ $currentMemberUser->name }}</p>
                        @if ($linkedMember?->member_id)
                            <p class="text-[11px] font-mono text-indigo-700 mt-0.5">{{ $linkedMember->member_id }}</p>
                        @else
                            <p class="text-xs text-slate-500 mt-0.5">{{ $currentMemberUser->email }}</p>
                        @endif
                    </div>
                @endif

                <div class="space-y-2">
                    <a
                        href="{{ route('portal.profile') }}"
                        class="flex items-center gap-3 px-3 py-2.5 min-h-[44px] text-sm font-semibold rounded-xl text-slate-700 hover:text-indigo-600 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>Personal Profile</span>
                    </a>
                </div>
            </div>

            <div class="pt-6 border-t border-slate-100">
                @if (Auth::guard('member')->check())
                    <form method="POST" action="{{ route('portal.logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="w-full flex items-center justify-center gap-2 px-4 py-2.5 min-h-[44px] text-sm font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 rounded-xl border border-rose-200 transition focus:outline-none focus:ring-2 focus:ring-rose-500 cursor-pointer"
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

    <!-- Main Content Body -->
    <main id="main-content" role="main" class="flex-1 py-6 sm:py-10 px-4 sm:px-6 lg:px-8 flex items-center justify-center">
        <div class="{{ $isLoginPage ? 'max-w-5xl' : 'max-w-4xl' }} mx-auto w-full">
            {{ $slot ?? '' }}
            @yield('content')
        </div>
    </main>

    <!-- Portal Footer -->
    <footer role="contentinfo" class="{{ $isLoginPage ? 'bg-slate-950/40 backdrop-blur-xl border-t border-white/10 text-slate-400' : 'bg-white border-t border-slate-200 text-slate-600' }} py-8 text-xs transition-colors">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2 {{ $isLoginPage ? 'text-slate-400' : 'text-slate-500' }}">
                    <span>&copy; {{ date('Y') }} EMK Center. All rights reserved.</span>
                    <span aria-hidden="true">•</span>
                    <span>American Spaces Bangladesh</span>
                </div>
                <div class="flex items-center gap-4 {{ $isLoginPage ? 'text-slate-400' : 'text-slate-500' }}">
                    <span>Help Desk: +880 1977-109012</span>
                    <span aria-hidden="true">•</span>
                    <a href="mailto:membership@emk.com.bd" class="{{ $isLoginPage ? 'text-amber-300 hover:text-amber-200 font-medium' : 'text-indigo-600 hover:underline' }} focus:outline-none focus:ring-2 focus:ring-amber-400 rounded">membership@emk.com.bd</a>
                </div>
            </div>
        </div>
    </footer>

    @livewireScripts
    @stack('scripts')
</body>
</html>
