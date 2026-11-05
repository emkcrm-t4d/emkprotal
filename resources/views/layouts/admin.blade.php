<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#070b13]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'EMK Membership CRM - Admin Console' }}</title>

    <!-- Styles & Scripts via Vite -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    @livewireStyles
    @stack('styles')

    <style>
        /* Minimalist Premium Dark Glass Styling & Automatic Content Adjustments */
        main .bg-white {
            background-color: rgba(13, 18, 28, 0.72) !important;
            backdrop-filter: blur(24px) !important;
            -webkit-backdrop-filter: blur(24px) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4) !important;
            color: #f8fafc !important;
        }
        main .bg-slate-50 {
            background-color: rgba(7, 11, 19, 0.6) !important;
            backdrop-filter: blur(16px) !important;
            -webkit-backdrop-filter: blur(16px) !important;
            border-color: rgba(255, 255, 255, 0.08) !important;
        }
        main .bg-slate-100 {
            background-color: rgba(255, 255, 255, 0.08) !important;
            color: #f1f5f9 !important;
            border-color: rgba(255, 255, 255, 0.12) !important;
        }
        main .text-slate-900 {
            color: #ffffff !important;
        }
        main .text-slate-800 {
            color: #f8fafc !important;
        }
        main .text-slate-700 {
            color: #e2e8f0 !important;
        }
        main .text-slate-600 {
            color: #cbd5e1 !important;
        }
        main .text-slate-500 {
            color: #94a3b8 !important;
        }
        main .border-slate-200,
        main .border-slate-100 {
            border-color: rgba(255, 255, 255, 0.1) !important;
        }
        main .divide-slate-200 > :not([hidden]) ~ :not([hidden]),
        main .divide-slate-100 > :not([hidden]) ~ :not([hidden]) {
            border-color: rgba(255, 255, 255, 0.08) !important;
        }
        main table thead {
            background-color: rgba(7, 11, 19, 0.85) !important;
        }
        main table thead th {
            color: #cbd5e1 !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }
        main table tbody tr {
            transition: background-color 0.2s ease !important;
        }
        main table tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.04) !important;
        }
        main input:not([type="checkbox"]):not([type="radio"]),
        main select,
        main textarea {
            background-color: rgba(7, 11, 19, 0.6) !important;
            border-color: rgba(255, 255, 255, 0.15) !important;
            color: #ffffff !important;
        }
        main input::placeholder,
        main textarea::placeholder {
            color: #64748b !important;
        }
    </style>
</head>
<body class="min-h-full font-sans antialiased text-slate-100 bg-[#070b13] selection:bg-amber-400 selection:text-slate-950 flex flex-col justify-between relative overflow-x-hidden">
    <!-- Atmospheric Fitted EMK Background Canvas (Auto-Adjusts per Device Screen & Never Crops) -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none -z-20 bg-[#070b13]" aria-hidden="true">
        <!-- Ambient Extended Glow Underlay to seamlessly fill any aspect-ratio borders without clipping -->
        <img 
            src="{{ asset('images/emk-bg.webp') }}" 
            alt="" 
            class="absolute inset-0 w-full h-full object-cover object-center filter blur-2xl opacity-25 scale-110"
            aria-hidden="true"
        >
        <!-- Uncropped Fit Layer: Intact 100% of the image, auto-adjusts based on viewport pixels -->
        <img 
            src="{{ asset('images/emk-bg.webp') }}" 
            alt="EMK Center Interior" 
            class="absolute inset-0 w-full h-full object-contain object-center filter blur-[1px] opacity-40 brightness-95 contrast-105"
        >
        <!-- Minimalist Dark Glass Scrim with Subtle Vignette -->
        <div class="absolute inset-0 bg-[#070b13]/65 backdrop-blur-xs"></div>
        <div class="absolute inset-0 bg-radial-[at_center] from-transparent via-[#070b13]/40 to-[#070b13]/85"></div>
        <!-- Ambient lighting highlights -->
        <div class="absolute top-12 left-1/3 w-[30rem] h-[30rem] bg-amber-500/10 rounded-full blur-[140px]"></div>
        <div class="absolute -bottom-24 right-1/4 w-[32rem] h-[32rem] bg-indigo-500/10 rounded-full blur-[150px]"></div>
    </div>

    <!-- Notification & Toast Slot Container -->
    <div id="notification-container" class="fixed top-4 right-4 z-50 flex flex-col gap-2 max-w-sm pointer-events-none" aria-live="polite">
        @if (session('status'))
            <div class="pointer-events-auto bg-slate-900/90 border border-emerald-500/40 text-emerald-300 px-4 py-3 rounded-2xl shadow-xl text-sm flex items-start gap-2.5 backdrop-blur-md">
                <svg class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="flex-1">
                    <p class="font-bold text-white">Success</p>
                    <p class="text-xs text-emerald-300/90 mt-0.5">{{ session('status') }}</p>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="pointer-events-auto bg-slate-900/90 border border-rose-500/40 text-rose-300 px-4 py-3 rounded-2xl shadow-xl text-sm flex items-start gap-2.5 backdrop-blur-md">
                <svg class="w-5 h-5 text-rose-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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

    <!-- Administrative Shell Layout Structure (Full Window Fluid Canvas) -->
    <div class="min-h-screen flex flex-col">
        <!-- Livewire Reactive Admin Shell Component -->
        <livewire:admin.admin-shell :activeModule="$activeModule ?? null" />

        <!-- Main Content Canvas (Auto-Adjusts based on Background Image Color) -->
        <main class="flex-1 lg:pl-64 flex flex-col">
            <div class="py-6 px-4 sm:px-6 lg:px-8 xl:px-10 flex-1 w-full mx-auto">
                {{ $slot ?? '' }}
                @yield('content')
            </div>
        </main>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
