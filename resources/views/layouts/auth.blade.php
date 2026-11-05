<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Staff & Admin Login - EMK Membership CRM' }}</title>

    <!-- Styles & Scripts via Vite -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    @livewireStyles
    @stack('styles')
</head>
<body class="min-h-full font-sans antialiased text-slate-900 bg-gradient-to-br from-slate-100 via-slate-50 to-indigo-50/30 flex flex-col justify-between">
    <!-- Skip to main content link for accessibility -->
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:p-4 focus:bg-indigo-600 focus:text-white focus:z-50 focus:rounded-lg focus:shadow-lg focus:outline-none">Skip to main content</a>

    <!-- Header: Official Branding (Auto-adjusts to Device Screen) -->
    <header role="banner" class="bg-white/80 backdrop-blur-md border-b border-slate-200/80 sticky top-0 z-40 shadow-xs">
        <div class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12">
            <div class="flex items-center justify-between h-16 sm:h-20">
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full border-2 border-indigo-600/70 bg-white shadow-sm p-0.5 flex items-center justify-center overflow-hidden shrink-0" aria-hidden="true">
                        <img src="{{ asset('images/emk-logo.png') }}" alt="EMK Center Logo" class="w-full h-full object-contain rounded-full">
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-full border border-indigo-200">
                                Staff Console
                            </span>
                        </div>
                        <h1 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight flex items-center gap-2">
                            American Spaces Bangladesh
                            <span class="text-slate-300 font-normal">|</span>
                            <span class="text-indigo-600 font-semibold">EMK Center CRM</span>
                        </h1>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('portal.login') }}" class="min-h-[44px] inline-flex items-center text-xs font-semibold text-slate-700 hover:text-indigo-600 transition-colors px-3.5 py-2 rounded-xl hover:bg-slate-100 border border-transparent hover:border-slate-200">
                        Member Portal &rarr;
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Canvas -->
    <main id="main-content" role="main" class="flex-1 flex items-center justify-center py-10 px-4 sm:px-6 lg:px-8">
        {{ $slot }}
    </main>

    <!-- Footer -->
    <footer class="py-6 border-t border-slate-200/60 bg-white/50 text-xs text-slate-600">
        <div class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 flex flex-col sm:flex-row items-center justify-between gap-3 text-center sm:text-left">
            <p>&copy; {{ date('Y') }} EMK Center. All rights reserved.•T4D </p>
            <p class="text-slate-500">Phone: <a href="tel:+8809606513103" class="hover:underline font-medium text-slate-700">+8809606513103</a> | <a href="tel:+8801926666995" class="hover:underline font-medium text-slate-700">+8801926666995</a> • <a href="mailto:info@emkcenter.org" class="hover:underline font-medium text-indigo-600">info@emkcenter.org</a></p>
        </div>
    </footer>

    @livewireScripts
    @stack('scripts')
</body>
</html>
