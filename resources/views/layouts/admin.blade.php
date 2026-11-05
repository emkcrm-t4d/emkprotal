<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#0a0f1d]">
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
        /* ==========================================================================
           MODERN 3D LIQUID FROSTED GLASS DESIGN SYSTEM (Aesthetic Sample Spec)
           ========================================================================== */

        /* Primary Glass Canvas & Container Cards across all CRM views */
        main .bg-white,
        main [class*="bg-slate-900/70"],
        main [class*="bg-slate-900/80"],
        main [class*="bg-slate-900/85"],
        main [class*="bg-slate-900/90"],
        main > div > div.space-y-6 > div.rounded-3xl,
        main > div > div.space-y-6 > div.rounded-2xl {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.12) 0%, rgba(255, 255, 255, 0.03) 50%, rgba(15, 23, 42, 0.55) 100%), rgba(10, 16, 30, 0.65) !important;
            backdrop-filter: blur(28px) saturate(200%) !important;
            -webkit-backdrop-filter: blur(28px) saturate(200%) !important;
            border: 1px solid rgba(255, 255, 255, 0.22) !important;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.6), inset 0 1px 1px 0 rgba(255, 255, 255, 0.35) !important;
            border-radius: 1.5rem !important;
            color: #f8fafc !important;
        }

        /* Metric Pods inside grids (3D Frosted Tiles) */
        main .grid > div[class*="bg-slate-900"],
        main .grid > div[class*="bg-white"],
        main .grid > div.rounded-2xl {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.02) 100%), rgba(12, 18, 34, 0.6) !important;
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255, 255, 255, 0.18) !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4), inset 0 1px 1px 0 rgba(255, 255, 255, 0.25) !important;
            border-radius: 1.25rem !important;
            transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease !important;
        }
        main .grid > div[class*="bg-slate-900"]:hover,
        main .grid > div[class*="bg-white"]:hover {
            transform: translateY(-2px) !important;
            border-color: rgba(255, 255, 255, 0.3) !important;
            box-shadow: 0 15px 30px -5px rgba(0, 0, 0, 0.5), inset 0 1px 1px 0 rgba(255, 255, 255, 0.4) !important;
        }

        /* High-Contrast Crisp Typography */
        main h1, main h2, main h3 {
            color: #ffffff !important;
            font-weight: 800 !important;
            letter-spacing: -0.02em !important;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5) !important;
        }
        main .text-slate-900 {
            color: #ffffff !important;
            font-weight: 700 !important;
        }
        main .text-slate-800 {
            color: #f8fafc !important;
            font-weight: 600 !important;
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
        main label {
            color: #cbd5e1 !important;
            font-weight: 700 !important;
            letter-spacing: 0.05em !important;
            text-transform: uppercase !important;
            font-size: 0.75rem !important;
        }

        /* Frosted Glass Data Tables */
        main table {
            border-collapse: separate !important;
            border-spacing: 0 !important;
            width: 100% !important;
        }
        main table thead,
        main table thead tr {
            background: rgba(255, 255, 255, 0.06) !important;
            backdrop-filter: blur(16px) !important;
        }
        main table thead th {
            color: #f1f5f9 !important;
            font-weight: 800 !important;
            font-size: 0.72rem !important;
            letter-spacing: 0.06em !important;
            text-transform: uppercase !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.16) !important;
            padding: 0.85rem 1rem !important;
        }
        main table tbody tr {
            background: transparent !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.07) !important;
            transition: background-color 0.15s ease !important;
        }
        main table tbody tr:hover {
            background: rgba(255, 255, 255, 0.08) !important;
        }
        main table tbody td {
            color: #e2e8f0 !important;
            padding: 0.85rem 1rem !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.07) !important;
        }

        /* Luminous Frosted Status Badges (replaces white-paper pastel badges) */
        main .bg-purple-50 {
            background-color: rgba(168, 85, 247, 0.22) !important;
            border: 1px solid rgba(168, 85, 247, 0.5) !important;
            color: #f3e8ff !important;
            box-shadow: 0 2px 8px rgba(168, 85, 247, 0.25) !important;
            font-weight: 700 !important;
        }
        main .bg-emerald-50 {
            background-color: rgba(16, 185, 129, 0.22) !important;
            border: 1px solid rgba(16, 185, 129, 0.5) !important;
            color: #d1fae5 !important;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25) !important;
            font-weight: 700 !important;
        }
        main .bg-blue-50,
        main .bg-sky-50 {
            background-color: rgba(14, 165, 233, 0.22) !important;
            border: 1px solid rgba(14, 165, 233, 0.5) !important;
            color: #e0f2fe !important;
            box-shadow: 0 2px 8px rgba(14, 165, 233, 0.25) !important;
            font-weight: 700 !important;
        }
        main .bg-indigo-50:not(a) {
            background-color: rgba(99, 102, 241, 0.22) !important;
            border: 1px solid rgba(99, 102, 241, 0.5) !important;
            color: #e0e7ff !important;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.25) !important;
            font-weight: 700 !important;
        }
        main .bg-amber-50,
        main .bg-amber-100 {
            background-color: rgba(245, 158, 11, 0.22) !important;
            border: 1px solid rgba(245, 158, 11, 0.5) !important;
            color: #fef3c7 !important;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.25) !important;
            font-weight: 700 !important;
        }
        main .bg-rose-50 {
            background-color: rgba(244, 63, 94, 0.22) !important;
            border: 1px solid rgba(244, 63, 94, 0.5) !important;
            color: #ffe4e6 !important;
            box-shadow: 0 2px 8px rgba(244, 63, 94, 0.25) !important;
            font-weight: 700 !important;
        }
        main .bg-pink-50 {
            background-color: rgba(236, 72, 153, 0.22) !important;
            border: 1px solid rgba(236, 72, 153, 0.5) !important;
            color: #fce7f3 !important;
            box-shadow: 0 2px 8px rgba(236, 72, 153, 0.25) !important;
            font-weight: 700 !important;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
        }

        /* Stark White/Cream Pill Action Buttons (Sample Image Style) */
        main table a[class*="bg-indigo-50"],
        main table a[class*="bg-slate-100"],
        main table a[id^="btn-inspect-"] {
            background: #ffffff !important;
            color: #0f172a !important;
            font-weight: 800 !important;
            border-radius: 9999px !important;
            padding: 0.4rem 1.1rem !important;
            border: 1px solid #ffffff !important;
            box-shadow: 0 4px 14px rgba(255, 255, 255, 0.25) !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.35rem !important;
            text-decoration: none !important;
            font-size: 0.75rem !important;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
        }
        main table a[class*="bg-indigo-50"]:hover,
        main table a[class*="bg-slate-100"]:hover,
        main table a[id^="btn-inspect-"]:hover {
            background: #fbbf24 !important;
            border-color: #fbbf24 !important;
            color: #0f172a !important;
            box-shadow: 0 6px 18px rgba(251, 191, 36, 0.45) !important;
            transform: translateY(-1px) scale(1.02) !important;
        }

        /* Floating Capsule Reset Filter Pill */
        #btn-reset-filters-top {
            background: rgba(255, 255, 255, 0.12) !important;
            backdrop-filter: blur(16px) !important;
            -webkit-backdrop-filter: blur(16px) !important;
            border: 1px solid rgba(255, 255, 255, 0.25) !important;
            border-radius: 9999px !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3) !important;
            transition: all 0.2s ease !important;
        }
        #btn-reset-filters-top:hover {
            background: #ffffff !important;
            color: #0f172a !important;
            border-color: #ffffff !important;
            box-shadow: 0 6px 20px rgba(255, 255, 255, 0.4) !important;
            transform: translateY(-1px) !important;
        }
        #btn-reset-filters-top:hover svg {
            color: #0f172a !important;
        }

        /* Tri-State Capsule Pill in Table */
        main table .inline-flex.items-center.gap-2.bg-slate-50 {
            background: rgba(255, 255, 255, 0.08) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            border-radius: 9999px !important;
            padding: 0.25rem 0.65rem !important;
        }

        /* Frosted Glass Inputs, Dropdowns, and Textareas */
        main input:not([type="checkbox"]):not([type="radio"]),
        main select,
        main textarea {
            background-color: rgba(10, 16, 30, 0.65) !important;
            backdrop-filter: blur(16px) !important;
            -webkit-backdrop-filter: blur(16px) !important;
            border: 1px solid rgba(255, 255, 255, 0.18) !important;
            border-radius: 0.85rem !important;
            color: #ffffff !important;
            box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.35) !important;
            transition: all 0.2s ease !important;
        }
        main input:focus,
        main select:focus,
        main textarea:focus {
            background-color: rgba(10, 16, 30, 0.85) !important;
            border-color: #fbbf24 !important;
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.25) !important;
            outline: none !important;
        }
        main input::placeholder,
        main textarea::placeholder {
            color: #94a3b8 !important;
        }

        /* High-Legibility Primary Links */
        main a.text-indigo-600,
        main a.text-indigo-400 {
            color: #ffffff !important;
            font-weight: 700 !important;
            transition: color 0.2s ease !important;
        }
        main a.text-indigo-600:hover,
        main a.text-indigo-400:hover {
            color: #fbbf24 !important;
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
    <div class="min-h-screen flex flex-col relative z-10">
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
