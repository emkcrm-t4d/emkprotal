<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#070b12]">
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
<body class="min-h-full font-sans antialiased text-slate-100 bg-[#070b12] selection:bg-amber-400 selection:text-slate-950 flex flex-col justify-between relative overflow-x-hidden">
    <!-- Atmospheric EMK Center Background with Glass Blur & Dark Scrim -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none -z-20" aria-hidden="true">
        <img 
            src="{{ asset('images/emk-bg.webp') }}" 
            alt="EMK Center Background" 
            class="w-full h-full object-cover object-center scale-105 filter blur-[2px] opacity-30 contrast-125 brightness-90"
        >
        <div class="absolute inset-0 bg-[#070b12]/85 backdrop-blur-xs"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-[#070b12] via-transparent to-[#070b12]/90"></div>
        <!-- Ambient Glass Theme Lighting Accents matching hanging lamps -->
        <div class="absolute top-10 right-1/4 w-96 h-96 bg-amber-500/15 rounded-full blur-[140px]"></div>
        <div class="absolute -top-32 -left-32 w-[34rem] h-[34rem] bg-indigo-500/15 rounded-full blur-[140px]"></div>
        <div class="absolute -bottom-32 left-1/3 w-[38rem] h-[38rem] bg-teal-600/10 rounded-full blur-[160px]"></div>
        <div class="absolute inset-0 bg-[radial-gradient(rgba(255,255,255,0.04)_1px,transparent_1px)] [background-size:24px_24px] opacity-40"></div>
    </div>

    <!-- Skip to main content link for accessibility -->
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:p-4 focus:bg-indigo-600 focus:text-white focus:z-50 focus:rounded-lg focus:shadow-lg focus:outline-none">Skip to main content</a>

    <!-- Header: Official Branding (Auto-adjusts to Device Screen) -->
    <header role="banner" class="bg-slate-950/70 backdrop-blur-2xl border-b border-white/10 sticky top-0 z-40 shadow-lg text-white">
        <div class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12">
            <div class="flex items-center justify-between h-16 sm:h-20">
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full border-2 border-amber-400/90 ring-2 ring-amber-400/25 bg-white shadow-md p-0.5 flex items-center justify-center overflow-hidden shrink-0" aria-hidden="true">
                        <img src="{{ asset('images/emk-logo.png') }}" alt="EMK Center Logo" class="w-full h-full object-contain rounded-full">
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-300 bg-amber-400/10 px-2 py-0.5 rounded-full border border-amber-400/20">
                                Staff Console
                            </span>
                        </div>
                        <h1 class="text-base sm:text-lg font-bold text-white tracking-tight flex items-center gap-2">
                            American Spaces Bangladesh
                            <span class="text-white/30 font-normal">|</span>
                            <span class="text-amber-400 font-semibold">EMK Center CRM</span>
                        </h1>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('portal.login') }}" class="min-h-[44px] inline-flex items-center text-xs font-semibold text-slate-200 hover:text-white transition-colors px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10">
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
    <footer class="py-6 border-t border-white/10 bg-slate-950/60 backdrop-blur-xl text-xs text-slate-400">
        <div class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 flex flex-col sm:flex-row items-center justify-between gap-3 text-center sm:text-left">
            <p>&copy; {{ date('Y') }} EMK Center. All rights reserved.•T4D </p>
            <p class="text-slate-400">Phone: <a href="tel:+8809606513103" class="hover:underline font-medium text-amber-300">+8809606513103</a> | <a href="tel:+8801926666995" class="hover:underline font-medium text-amber-300">+8801926666995</a> • <a href="mailto:info@emkcenter.org" class="hover:underline font-medium text-amber-300">info@emkcenter.org</a></p>
        </div>
    </footer>

    @livewireScripts
    @stack('scripts')
</body>
</html>
