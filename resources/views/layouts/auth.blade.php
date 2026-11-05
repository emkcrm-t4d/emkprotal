<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#0a0f1d]">
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
        /* 3D Liquid Frosted Glass Theme for Staff Login */
        main .bg-white,
        main [class*="bg-slate-900"],
        main .rounded-3xl,
        main .rounded-2xl {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.12) 0%, rgba(255, 255, 255, 0.03) 50%, rgba(15, 23, 42, 0.6) 100%), rgba(10, 16, 30, 0.7) !important;
            backdrop-filter: blur(28px) saturate(200%) !important;
            -webkit-backdrop-filter: blur(28px) saturate(200%) !important;
            border: 1px solid rgba(255, 255, 255, 0.22) !important;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.65), inset 0 1px 1px 0 rgba(255, 255, 255, 0.35) !important;
            border-radius: 1.5rem !important;
            color: #f8fafc !important;
        }
        main .bg-slate-50 {
            background-color: rgba(10, 16, 30, 0.6) !important;
            backdrop-filter: blur(16px) !important;
            border: 1px solid rgba(255, 255, 255, 0.12) !important;
            color: #cbd5e1 !important;
        }
        main .text-slate-900,
        main .text-slate-800 {
            color: #ffffff !important;
            font-weight: 700 !important;
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
            border-color: rgba(255, 255, 255, 0.15) !important;
        }
        main input:not([type="checkbox"]):not([type="radio"]) {
            background-color: rgba(10, 16, 30, 0.65) !important;
            backdrop-filter: blur(16px) !important;
            border: 1px solid rgba(255, 255, 255, 0.18) !important;
            border-radius: 0.85rem !important;
            color: #ffffff !important;
            box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.35) !important;
            transition: all 0.2s ease !important;
        }
        main input:focus {
            background-color: rgba(10, 16, 30, 0.85) !important;
            border-color: #fbbf24 !important;
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.25) !important;
        }
    </style>
</head>
<body class="min-h-full font-sans antialiased text-slate-100 flex flex-col justify-between relative overflow-x-hidden selection:bg-amber-400 selection:text-slate-950">
    <!-- Atmospheric EMK Center Background: 100% Fit, Never Cropped, Device-Pixel Responsive -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0 bg-[#0a0f1d]" aria-hidden="true">
        <!-- Ambient Atmospheric Underlay: Fills any screen aspect ratio with warm lighting -->
        <img 
            src="{{ asset('images/emk-bg.jpg') }}" 
            alt="" 
            class="absolute inset-0 w-full h-full object-cover filter blur-3xl opacity-60 scale-110 pointer-events-none"
            aria-hidden="true"
        >

        <!-- Uncropped Fit Layer: Intact 100%, 0% cropped, responsive to device pixels -->
        <div class="absolute inset-0 flex items-center justify-center">
            <picture class="w-full h-full flex items-center justify-center">
                <source srcset="{{ asset('images/emk-bg.webp') }}" type="image/webp">
                <img 
                    src="{{ asset('images/emk-bg.jpg') }}" 
                    alt="EMK Center Background" 
                    class="w-full h-full object-contain object-center opacity-90 brightness-100 contrast-105 filter blur-[0.5px] pointer-events-none select-none transition-opacity duration-500"
                    loading="eager"
                    fetchpriority="high"
                >
            </picture>
        </div>

        <!-- Atmospheric Liquid Glass Scrim (Permits warm photography to illuminate page with subtle depth) -->
        <div class="absolute inset-0 bg-[#0a0f1d]/35"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-[#0a0f1d]/40 via-transparent to-[#0a0f1d]/75"></div>
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
