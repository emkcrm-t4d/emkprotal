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
<body class="min-h-full font-sans antialiased text-slate-900 bg-slate-50 flex flex-col justify-between">
    <!-- Toast & Notification Container -->
    <div id="notification-container" class="fixed top-4 right-4 z-50 flex flex-col gap-2 max-w-sm pointer-events-none" aria-live="polite">
        @if (session('status') || session('success'))
            <div class="pointer-events-auto bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg shadow-sm text-sm flex items-start gap-2.5">
                <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
    <header class="bg-white border-b border-slate-200 shadow-xs sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <!-- Portal Branding -->
                <a href="{{ route('portal.profile') }}" class="flex items-center gap-3.5 group">
                    <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-indigo-700 via-blue-600 to-sky-500 flex items-center justify-center text-white shadow-sm font-bold text-base tracking-wider group-hover:shadow-md transition-shadow">
                        EMK
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-blue-700 bg-blue-50 px-2 py-0.5 rounded-full border border-blue-200">
                                Member Portal
                            </span>
                        </div>
                        <h1 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight flex items-center gap-1.5 mt-0.5">
                            EMK Center
                            <span class="text-slate-300 font-normal">|</span>
                            <span class="text-slate-600 font-medium text-xs sm:text-sm">American Spaces</span>
                        </h1>
                    </div>
                </a>

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
                                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-700 hover:text-rose-700 bg-slate-100 hover:bg-rose-50 border border-slate-200 hover:border-rose-200 rounded-lg transition-colors cursor-pointer" title="Log out of member portal">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    <span class="hidden md:inline">Sign Out</span>
                                </button>
                            </form>
                        </div>
                    @else
                        <div class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-800 px-3 py-1 rounded-full text-xs font-medium">
                            <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" />
                            </svg>
                            <span>Secure Member Gateway</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Body -->
    <main class="flex-1 py-8 sm:py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto">
            {{ $slot ?? '' }}
            @yield('content')
        </div>
    </main>

    <!-- Portal Footer -->
    <footer class="bg-white border-t border-slate-200 py-8 text-slate-600 text-xs">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2 text-slate-500">
                    <span>&copy; {{ date('Y') }} EMK Center. All rights reserved.</span>
                    <span>•</span>
                    <span>American Spaces Bangladesh</span>
                </div>
                <div class="flex items-center gap-4 text-slate-500">
                    <span>Help Desk: +880 1977-109012</span>
                    <span>•</span>
                    <a href="mailto:membership@emk.com.bd" class="text-indigo-600 hover:underline">membership@emk.com.bd</a>
                </div>
            </div>
        </div>
    </footer>

    @livewireScripts
    @stack('scripts')
</body>
</html>
