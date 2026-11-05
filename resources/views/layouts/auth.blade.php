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

    <style>
        /* 3D Frosted Glass Theme for Staff Login */
        main .bg-white {
            background-color: rgba(15, 23, 42, 0.78) !important;
            backdrop-filter: blur(24px) !important;
            -webkit-backdrop-filter: blur(24px) !important;
            border: 1px solid rgba(255, 255, 255, 0.12) !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6) !important;
            color: #f8fafc !important;
        }
        main .bg-slate-50 {
            background-color: rgba(2, 6, 23, 0.6) !important;
            backdrop-filter: blur(16px) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            color: #cbd5e1 !important;
        }
        main .text-slate-900,
        main .text-slate-800 {
            color: #ffffff !important;
        }
        main .text-slate-700,
        main .text-slate-600 {
            color: #cbd5e1 !important;
        }
        main .text-slate-500,
        main .text-slate-400 {
            color: #94a3b8 !important;
        }
        main .border-slate-200,
        main .border-slate-300,
        main [class*="border-slate-200"] {
            border-color: rgba(255, 255, 255, 0.12) !important;
        }
        main input:not([type="checkbox"]):not([type="radio"]) {
            background-color: rgba(2, 6, 23, 0.65) !important;
            border-color: rgba(255, 255, 255, 0.18) !important;
            color: #ffffff !important;
        }
        main input:focus {
            border-color: #f59e0b !important;
            outline: none !important;
            box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.25) !important;
        }
    </style>
</head>
<body class="min-h-full font-sans antialiased text-slate-100 flex flex-col justify-between relative overflow-x-hidden selection:bg-amber-400 selection:text-slate-950">
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
    <main id="main-content" role="main" class="flex-1 flex items-center justify-center py-10 px-4 sm:px-6 lg:px-8 relative z-10">
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
