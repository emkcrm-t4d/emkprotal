<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#0a0f1d]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'EMK Membership CRM - Admin Console' }}</title>

    <!-- Browser Top Tab Icon (Favicon) - EMK Circular Logo -->
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/emk-favicon-32.png') }}?v=3">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/emk-favicon-16.png') }}?v=3">
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('images/emk-favicon-64.png') }}?v=3">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/emk-circle-logo.png') }}?v=3">

    <!-- Styles & Scripts via Vite -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    @livewireStyles
    @stack('styles')

    <style>
        /* ==========================================================================
           MODERN 3D LIQUID FROSTED GLASS DESIGN SYSTEM (DARK MODE DEFAULT)
           ========================================================================== */

        /* Primary Glass Canvas & Container Cards across all CRM views */
        html:not(.theme-light) main .bg-white,
        html:not(.theme-light) main [class*="bg-slate-900/70"],
        html:not(.theme-light) main [class*="bg-slate-900/80"],
        html:not(.theme-light) main [class*="bg-slate-900/85"],
        html:not(.theme-light) main [class*="bg-slate-900/90"],
        html:not(.theme-light) main > div > div.space-y-6 > div.rounded-3xl,
        html:not(.theme-light) main > div > div.space-y-6 > div.rounded-2xl {
            background: linear-gradient(180deg, rgba(17, 24, 39, 0.96) 0%, rgba(10, 15, 26, 0.97) 100%) !important;
            backdrop-filter: blur(32px) !important;
            -webkit-backdrop-filter: blur(32px) !important;
            border: 1px solid rgba(255, 255, 255, 0.16) !important;
            box-shadow: 0 20px 50px -10px rgba(0, 0, 0, 0.8), inset 0 1px 1px 0 rgba(255, 255, 255, 0.2) !important;
            border-radius: 1.5rem !important;
            color: #f8fafc !important;
        }

        /* Metric Pods inside grids (3D Frosted Tiles) */
        html:not(.theme-light) main .grid > div[class*="bg-slate-900"],
        html:not(.theme-light) main .grid > div[class*="bg-white"],
        html:not(.theme-light) main .grid > div.rounded-2xl {
            background: linear-gradient(180deg, rgba(22, 31, 49, 0.95) 0%, rgba(13, 19, 32, 0.97) 100%) !important;
            backdrop-filter: blur(24px) !important;
            -webkit-backdrop-filter: blur(24px) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            box-shadow: 0 8px 24px -4px rgba(0, 0, 0, 0.6), inset 0 1px 1px 0 rgba(255, 255, 255, 0.18) !important;
            border-radius: 1.25rem !important;
            transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease !important;
        }
        html:not(.theme-light) main .grid > div[class*="bg-slate-900"]:hover,
        html:not(.theme-light) main .grid > div[class*="bg-white"]:hover {
            transform: translateY(-2px) !important;
            border-color: rgba(255, 255, 255, 0.3) !important;
            box-shadow: 0 15px 30px -5px rgba(0, 0, 0, 0.7), inset 0 1px 1px 0 rgba(255, 255, 255, 0.3) !important;
        }

        /* High-Contrast Crisp Typography */
        html:not(.theme-light) main h1, html:not(.theme-light) main h2, html:not(.theme-light) main h3 {
            color: #ffffff !important;
            font-weight: 800 !important;
            letter-spacing: -0.02em !important;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5) !important;
        }
        html:not(.theme-light) main .text-slate-900 {
            color: #ffffff !important;
            font-weight: 700 !important;
        }
        html:not(.theme-light) main .text-slate-800 {
            color: #f8fafc !important;
            font-weight: 600 !important;
        }
        html:not(.theme-light) main .text-slate-700 {
            color: #e2e8f0 !important;
        }
        html:not(.theme-light) main .text-slate-600 {
            color: #cbd5e1 !important;
        }
        html:not(.theme-light) main .text-slate-500 {
            color: #94a3b8 !important;
        }
        html:not(.theme-light) main label {
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
        main .bg-purple-50,
        main .bg-purple-100 {
            background-color: rgba(168, 85, 247, 0.22) !important;
            border: 1px solid rgba(168, 85, 247, 0.5) !important;
            color: #f3e8ff !important;
            box-shadow: 0 2px 8px rgba(168, 85, 247, 0.25) !important;
            font-weight: 700 !important;
        }
        main .bg-emerald-50,
        main .bg-emerald-100 {
            background-color: rgba(16, 185, 129, 0.22) !important;
            border: 1px solid rgba(16, 185, 129, 0.5) !important;
            color: #d1fae5 !important;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25) !important;
            font-weight: 700 !important;
        }
        main .bg-blue-50,
        main .bg-sky-50,
        main .bg-blue-100,
        main .bg-sky-100 {
            background-color: rgba(14, 165, 233, 0.22) !important;
            border: 1px solid rgba(14, 165, 233, 0.5) !important;
            color: #e0f2fe !important;
            box-shadow: 0 2px 8px rgba(14, 165, 233, 0.25) !important;
            font-weight: 700 !important;
        }
        main .bg-indigo-50:not(a),
        main .bg-indigo-100:not(a) {
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
        main .bg-rose-50,
        main .bg-rose-100 {
            background-color: rgba(244, 63, 94, 0.22) !important;
            border: 1px solid rgba(244, 63, 94, 0.5) !important;
            color: #ffe4e6 !important;
            box-shadow: 0 2px 8px rgba(244, 63, 94, 0.25) !important;
            font-weight: 700 !important;
        }
        main .bg-pink-50,
        main .bg-pink-100 {
            background-color: rgba(236, 72, 153, 0.22) !important;
            border: 1px solid rgba(236, 72, 153, 0.5) !important;
            color: #fce7f3 !important;
            box-shadow: 0 2px 8px rgba(236, 72, 153, 0.25) !important;
            font-weight: 700 !important;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
        }

        /* Glass Badges & Tags (Overriding Tailwind light-mode slate-50/100/200 on dark glass) */
        main .bg-slate-50:not(a),
        main span.bg-slate-50,
        main div.bg-slate-50,
        main span.bg-slate-100,
        main span.bg-slate-200,
        main [class*="bg-slate-50"]:not(a):not(input):not(select):not(textarea):not(table):not(thead):not(tbody):not(tr),
        main [class*="bg-slate-100"]:not(a):not(input):not(select):not(textarea):not(table):not(thead):not(tbody):not(tr),
        main [class*="bg-slate-200"]:not(a):not(input):not(select):not(textarea):not(table):not(thead):not(tbody):not(tr) {
            background-color: rgba(13, 19, 34, 0.94) !important;
            border-color: rgba(255, 255, 255, 0.15) !important;
            color: #f8fafc !important;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.4) !important;
        }

        /* Card Section Header Bars across all CRM views */
        main div[class*="border-b"][class*="bg-slate-50"],
        main div.px-5.py-3\.5.bg-slate-50,
        main div.px-6.py-4.bg-slate-50 {
            background: rgba(255, 255, 255, 0.05) !important;
            backdrop-filter: blur(16px) !important;
            -webkit-backdrop-filter: blur(16px) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12) !important;
        }
        main div[class*="border-b"][class*="bg-slate-50"] h1,
        main div[class*="border-b"][class*="bg-slate-50"] h2,
        main div[class*="border-b"][class*="bg-slate-50"] h3,
        main div[class*="border-b"][class*="bg-slate-50"] h4,
        main div.px-5.py-3\.5.bg-slate-50 h2,
        main div.px-6.py-4.bg-slate-50 h3 {
            color: #ffffff !important;
            font-weight: 800 !important;
            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.6) !important;
        }

        /* Attachment Cards & Media Tiles */
        main div[id^="summary-attachment-card-"],
        main div[id^="attachment-card-"] {
            background: rgba(15, 23, 42, 0.85) !important;
            border: 1px solid rgba(255, 255, 255, 0.18) !important;
            color: #f8fafc !important;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4), inset 0 1px 1px rgba(255, 255, 255, 0.15) !important;
        }
        main div[id^="summary-attachment-card-"] p,
        main div[id^="attachment-card-"] p {
            color: #ffffff !important;
        }
        main div[id^="summary-attachment-card-"] p.text-slate-500,
        main div[id^="attachment-card-"] p.text-slate-500,
        main div[id^="attachment-card-"] div.text-slate-500 {
            color: #94a3b8 !important;
        }

        /* Data list divider lines */
        main .divide-slate-100 > :not([hidden]) ~ :not([hidden]),
        main .divide-slate-200 > :not([hidden]) ~ :not([hidden]) {
            border-color: rgba(255, 255, 255, 0.08) !important;
        }

        /* Member ID & Identifier Mono Badges: High Contrast 3D Dark Glass Pods */
        main td span.font-mono,
        main span.font-mono {
            background-color: rgba(10, 16, 30, 0.8) !important;
            border: 1px solid rgba(255, 255, 255, 0.25) !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.4) !important;
            border-radius: 0.5rem !important;
            padding: 0.25rem 0.65rem !important;
            display: inline-block !important;
            letter-spacing: 0.04em !important;
        }

        /* Workspace Pill Badges */
        main [id="badge-workspace"],
        main span[class*="font-mono"][class*="bg-amber"],
        main span[class*="font-mono"]:is([class*="bg-amber-400"]) {
            background-color: rgba(245, 158, 11, 0.18) !important;
            border: 1px solid rgba(245, 158, 11, 0.45) !important;
            color: #fde68a !important;
            font-weight: 700 !important;
        }

        /* ==========================================================================
           UNIVERSAL HIGH-CONTRAST NOTIFICATION & ALERT BANNERS
           ========================================================================== */
        main [role="alert"],
        main [id^="banner-"],
        main [id^="alert-"],
        main [id^="flash-"] {
            border-radius: 1rem !important;
            backdrop-filter: blur(20px) saturate(180%) !important;
            -webkit-backdrop-filter: blur(20px) saturate(180%) !important;
            box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.5), inset 0 1px 1px rgba(255, 255, 255, 0.25) !important;
        }

        /* Success / Pass Banners & Alerts */
        main [role="alert"].bg-emerald-50,
        main [role="alert"][class*="bg-emerald-"],
        main [id^="banner-"][class*="bg-emerald-"],
        main [id^="banner-"].bg-emerald-50,
        main [id^="alert-"][class*="bg-emerald-"],
        main [id^="alert-"].bg-emerald-50,
        main [id^="flash-"][class*="bg-emerald-"],
        main [id^="flash-"].bg-emerald-50 {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.28) 0%, rgba(5, 150, 105, 0.18) 100%), rgba(10, 24, 20, 0.85) !important;
            border: 1px solid rgba(52, 211, 153, 0.45) !important;
            color: #ecfdf5 !important;
        }
        main [role="alert"].bg-emerald-50 h1, main [role="alert"].bg-emerald-50 h2, main [role="alert"].bg-emerald-50 h3, main [role="alert"].bg-emerald-50 h4,
        main [id^="banner-"][class*="bg-emerald-"] h1, main [id^="banner-"][class*="bg-emerald-"] h2, main [id^="banner-"][class*="bg-emerald-"] h3, main [id^="banner-"][class*="bg-emerald-"] h4,
        main [id^="alert-"][class*="bg-emerald-"] h1, main [id^="alert-"][class*="bg-emerald-"] h2, main [id^="alert-"][class*="bg-emerald-"] h3, main [id^="alert-"][class*="bg-emerald-"] h4 {
            color: #ffffff !important;
            font-weight: 800 !important;
            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.6) !important;
        }
        main [role="alert"].bg-emerald-50 p, main [role="alert"].bg-emerald-50 span,
        main [id^="banner-"][class*="bg-emerald-"] p, main [id^="banner-"][class*="bg-emerald-"] span,
        main [id^="alert-"][class*="bg-emerald-"] p, main [id^="alert-"][class*="bg-emerald-"] span,
        main [id^="flash-"][class*="bg-emerald-"] {
            color: #d1fae5 !important;
            font-weight: 500 !important;
        }
        main [role="alert"].bg-emerald-50 svg,
        main [id^="banner-"][class*="bg-emerald-"] svg,
        main [id^="alert-"][class*="bg-emerald-"] svg {
            color: #34d399 !important;
        }

        /* Danger / Failure Banners & Alerts */
        main [role="alert"].bg-rose-50,
        main [role="alert"][class*="bg-rose-"],
        main [id^="banner-"][class*="bg-rose-"],
        main [id^="banner-"].bg-rose-50,
        main [id^="alert-"][class*="bg-rose-"],
        main [id^="alert-"].bg-rose-50,
        main [id^="flash-"][class*="bg-rose-"],
        main [id^="flash-"].bg-rose-50 {
            background: linear-gradient(135deg, rgba(244, 63, 94, 0.28) 0%, rgba(225, 29, 72, 0.18) 100%), rgba(28, 12, 16, 0.85) !important;
            border: 1px solid rgba(251, 113, 133, 0.45) !important;
            color: #fff1f2 !important;
        }
        main [role="alert"].bg-rose-50 h1, main [role="alert"].bg-rose-50 h2, main [role="alert"].bg-rose-50 h3, main [role="alert"].bg-rose-50 h4,
        main [id^="banner-"][class*="bg-rose-"] h1, main [id^="banner-"][class*="bg-rose-"] h2, main [id^="banner-"][class*="bg-rose-"] h3, main [id^="banner-"][class*="bg-rose-"] h4,
        main [id^="alert-"][class*="bg-rose-"] h1, main [id^="alert-"][class*="bg-rose-"] h2, main [id^="alert-"][class*="bg-rose-"] h3, main [id^="alert-"][class*="bg-rose-"] h4 {
            color: #ffffff !important;
            font-weight: 800 !important;
            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.6) !important;
        }
        main [role="alert"].bg-rose-50 p, main [role="alert"].bg-rose-50 span,
        main [id^="banner-"][class*="bg-rose-"] p, main [id^="banner-"][class*="bg-rose-"] span,
        main [id^="alert-"][class*="bg-rose-"] p, main [id^="alert-"][class*="bg-rose-"] span,
        main [id^="flash-"][class*="bg-rose-"] {
            color: #ffe4e6 !important;
            font-weight: 500 !important;
        }
        main [role="alert"].bg-rose-50 svg,
        main [id^="banner-"][class*="bg-rose-"] svg,
        main [id^="alert-"][class*="bg-rose-"] svg {
            color: #fb7185 !important;
        }

        /* Warning Banners & Alerts */
        main [role="alert"].bg-amber-50,
        main [role="alert"][class*="bg-amber-"],
        main [id^="banner-"][class*="bg-amber-"],
        main [id^="banner-"].bg-amber-50,
        main [id^="alert-"][class*="bg-amber-"],
        main [id^="alert-"].bg-amber-50,
        main [id^="flash-"][class*="bg-amber-"],
        main [id^="flash-"].bg-amber-50 {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.28) 0%, rgba(217, 119, 6, 0.18) 100%), rgba(28, 20, 10, 0.85) !important;
            border: 1px solid rgba(251, 191, 36, 0.45) !important;
            color: #fffbeb !important;
        }
        main [role="alert"].bg-amber-50 h1, main [role="alert"].bg-amber-50 h2, main [role="alert"].bg-amber-50 h3, main [role="alert"].bg-amber-50 h4,
        main [id^="banner-"][class*="bg-amber-"] h1, main [id^="banner-"][class*="bg-amber-"] h2, main [id^="banner-"][class*="bg-amber-"] h3, main [id^="banner-"][class*="bg-amber-"] h4,
        main [id^="alert-"][class*="bg-amber-"] h1, main [id^="alert-"][class*="bg-amber-"] h2, main [id^="alert-"][class*="bg-amber-"] h3, main [id^="alert-"][class*="bg-amber-"] h4 {
            color: #ffffff !important;
            font-weight: 800 !important;
            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.6) !important;
        }
        main [role="alert"].bg-amber-50 p, main [role="alert"].bg-amber-50 span,
        main [id^="banner-"][class*="bg-amber-"] p, main [id^="banner-"][class*="bg-amber-"] span,
        main [id^="alert-"][class*="bg-amber-"] p, main [id^="alert-"][class*="bg-amber-"] span,
        main [id^="flash-"][class*="bg-amber-"] {
            color: #fef3c7 !important;
            font-weight: 500 !important;
        }
        main [role="alert"].bg-amber-50 svg,
        main [id^="banner-"][class*="bg-amber-"] svg,
        main [id^="alert-"][class*="bg-amber-"] svg {
            color: #fbbf24 !important;
        }

        /* High-Contrast Color Token Overrides for Dark Glass (Prevents illegible dark pastel shades) */
        main div[class*="bg-emerald-50"],
        main div[class*="bg-emerald-100"] {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.22) 0%, rgba(5, 150, 105, 0.12) 100%), rgba(10, 24, 20, 0.88) !important;
            border-color: rgba(52, 211, 153, 0.45) !important;
            color: #ecfdf5 !important;
        }
        main div[class*="bg-amber-50"],
        main div[class*="bg-amber-100"] {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.22) 0%, rgba(217, 119, 6, 0.12) 100%), rgba(28, 20, 10, 0.88) !important;
            border-color: rgba(251, 191, 36, 0.45) !important;
            color: #fffbeb !important;
        }
        main div[class*="bg-rose-50"],
        main div[class*="bg-rose-100"] {
            background: linear-gradient(135deg, rgba(244, 63, 94, 0.22) 0%, rgba(225, 29, 72, 0.12) 100%), rgba(28, 12, 16, 0.88) !important;
            border-color: rgba(251, 113, 133, 0.45) !important;
            color: #fff1f2 !important;
        }
        main div[class*="bg-blue-50"],
        main div[class*="bg-blue-100"] {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.22) 0%, rgba(37, 99, 235, 0.12) 100%), rgba(15, 23, 42, 0.88) !important;
            border-color: rgba(96, 165, 250, 0.45) !important;
            color: #eff6ff !important;
        }
        main div[class*="bg-purple-50"],
        main div[class*="bg-purple-100"] {
            background: linear-gradient(135deg, rgba(168, 85, 247, 0.22) 0%, rgba(147, 51, 234, 0.12) 100%), rgba(24, 16, 38, 0.88) !important;
            border-color: rgba(192, 132, 252, 0.45) !important;
            color: #faf5ff !important;
        }

        /* Modal Dialog Panels - Deep Frosted Glass with Zero Bleed-Through */
        div[role="dialog"] .relative.transform.overflow-hidden,
        main div[role="dialog"] .relative.transform.overflow-hidden {
            background: linear-gradient(180deg, rgba(20, 29, 47, 0.98) 0%, rgba(13, 19, 33, 0.98) 100%) !important;
            backdrop-filter: blur(32px) saturate(200%) !important;
            -webkit-backdrop-filter: blur(32px) saturate(200%) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            box-shadow: 0 25px 65px -10px rgba(0, 0, 0, 0.85), inset 0 1px 1px 0 rgba(255, 255, 255, 0.25) !important;
        }

        /* Modal Dialog Footers */
        div[role="dialog"] .border-t,
        main div[role="dialog"] .border-t {
            background: rgba(10, 16, 30, 0.9) !important;
            border-top: 1px solid rgba(255, 255, 255, 0.12) !important;
        }

        /* Modal Cancel & Neutral Action Buttons */
        div[role="dialog"] button.text-slate-700,
        div[role="dialog"] button.text-slate-600 {
            color: #cbd5e1 !important;
        }
        div[role="dialog"] button.text-slate-700:hover,
        div[role="dialog"] button.text-slate-600:hover {
            color: #ffffff !important;
            background-color: rgba(255, 255, 255, 0.1) !important;
        }

        main .text-emerald-950,
        main .text-emerald-900 {
            color: #ffffff !important;
            font-weight: 700 !important;
        }
        main .text-emerald-800 {
            color: #d1fae5 !important;
            font-weight: 500 !important;
        }
        main .text-emerald-700 {
            color: #6ee7b7 !important;
            font-weight: 600 !important;
        }
        main .text-emerald-600 {
            color: #34d399 !important;
        }

        main .text-rose-950,
        main .text-rose-900 {
            color: #ffffff !important;
            font-weight: 700 !important;
        }
        main .text-rose-800 {
            color: #ffe4e6 !important;
            font-weight: 500 !important;
        }
        main .text-rose-700 {
            color: #fda4af !important;
            font-weight: 600 !important;
        }
        main .text-rose-600 {
            color: #fb7185 !important;
        }

        main .text-amber-950,
        main .text-amber-900 {
            color: #ffffff !important;
            font-weight: 700 !important;
        }
        main .text-amber-800 {
            color: #fef3c7 !important;
            font-weight: 500 !important;
        }
        main .text-amber-700 {
            color: #fcd34d !important;
            font-weight: 600 !important;
        }
        main .text-amber-600 {
            color: #fbbf24 !important;
        }

        main .text-blue-950,
        main .text-blue-900 {
            color: #ffffff !important;
            font-weight: 700 !important;
        }
        main .text-blue-800 {
            color: #dbeafe !important;
            font-weight: 500 !important;
        }
        main .text-blue-700 {
            color: #93c5fd !important;
            font-weight: 600 !important;
        }
        main .text-blue-600 {
            color: #60a5fa !important;
        }

        /* Prevent Table Headers from Turning Solid White on Hover */
        main table thead th,
        main table thead th[class*="hover:bg-slate-100"] {
            background-color: transparent !important;
            transition: background-color 0.15s ease, color 0.15s ease !important;
        }
        main table thead th:hover,
        main table thead th[class*="hover:bg-slate-100"]:hover,
        main table thead th:hover * {
            background-color: rgba(255, 255, 255, 0.1) !important;
            color: #fbbf24 !important;
        }

        /* Metric Numbers in Light Mode Views */
        main [id^="metric-"] {
            color: #ffffff !important;
            font-weight: 800 !important;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.5) !important;
        }
        main [id="metric-paid"] {
            color: #34d399 !important;
        }
        main [id="metric-unpaid"] {
            color: #fbbf24 !important;
        }
        main [id="metric-voided"],
        main [id="metric-revoked"] {
            color: #f87171 !important;
        }
        main [id="metric-reversed"] {
            color: #c084fc !important;
        }
        main [id="metric-revenue"] {
            color: #818cf8 !important;
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

        /* Auto-Hide vs Pinned Sidebar Layout Synchronization */
        main.crm-main {
            padding-left: 0 !important;
            transition: padding-left 0.3s cubic-bezier(0.16, 1, 0.3, 1) !important;
        }
        @media (min-width: 1024px) {
            html.sidebar-pinned main.crm-main {
                padding-left: 16rem !important;
            }
        }

        /* ==========================================================================
           STANDARD LIGHT MODE DESIGN SYSTEM (AUTO-ADJUSTING CONTRAST & READABILITY)
           ========================================================================== */
        html.theme-light {
            background-color: #f1f5f9 !important;
            color: #0f172a !important;
        }
        html.theme-light body {
            background-color: #f1f5f9 !important;
            color: #1e293b !important;
        }
        html.theme-light .fixed.inset-0.bg-\[\#040711\] {
            background-color: #f1f5f9 !important;
        }
        html.theme-light picture img {
            opacity: 0.04 !important;
            filter: grayscale(1) !important;
        }
        html.theme-light .bg-\[\#060a17\]\/85 {
            background-color: rgba(241, 245, 249, 0.88) !important;
            backdrop-filter: blur(12px) !important;
        }
        html.theme-light .bg-gradient-to-b {
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.95) 0%, rgba(241, 245, 249, 0.9) 50%, rgba(226, 232, 240, 0.95) 100%) !important;
        }

        /* Top Header Navigation Bar in Light Mode */
        html.theme-light header {
            background: rgba(255, 255, 255, 0.96) !important;
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
            border-bottom: 1px solid #e2e8f0 !important;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.05) !important;
            color: #0f172a !important;
        }
        html.theme-light header h1,
        html.theme-light header h2,
        html.theme-light header span.font-bold,
        html.theme-light header .text-white {
            color: #0f172a !important;
        }
        html.theme-light header .text-slate-300,
        html.theme-light header .text-slate-400 {
            color: #475569 !important;
        }
        html.theme-light header button.text-slate-300 {
            color: #334155 !important;
        }
        html.theme-light header button.text-slate-300:hover {
            background-color: #f1f5f9 !important;
            color: #0f172a !important;
        }
        html.theme-light header div.bg-white\/5 {
            background-color: #f1f5f9 !important;
            border: 1px solid #cbd5e1 !important;
            color: #0f172a !important;
        }
        html.theme-light header div.bg-white\/5 span.text-white {
            color: #0f172a !important;
            font-weight: 700 !important;
        }
        html.theme-light header button[class*="bg-rose-500"] {
            background-color: #ffe4e6 !important;
            border: 1px solid #fecdd3 !important;
            color: #be123c !important;
            font-weight: 600 !important;
        }
        html.theme-light header button[class*="bg-rose-500"]:hover {
            background-color: #f43f5e !important;
            border-color: #f43f5e !important;
            color: #ffffff !important;
        }

        /* Sidebar in Light Mode */
        html.theme-light aside,
        html.theme-light .lg\:hidden[x-show*="mobileOpen"],
        html.theme-light div.fixed.inset-y-0.left-0.w-72 {
            background: rgba(255, 255, 255, 0.98) !important;
            backdrop-filter: blur(24px) !important;
            -webkit-backdrop-filter: blur(24px) !important;
            border-right: 1px solid #e2e8f0 !important;
            box-shadow: 4px 0 24px rgba(0, 0, 0, 0.06) !important;
            color: #1e293b !important;
        }
        html.theme-light aside .bg-slate-950\/60,
        html.theme-light aside .bg-slate-950\/80,
        html.theme-light aside .bg-slate-950\/70,
        html.theme-light aside [class*="bg-slate-950"],
        html.theme-light .lg\:hidden [class*="bg-slate-950"] {
            background-color: #f8fafc !important;
            border-color: #e2e8f0 !important;
        }
        html.theme-light aside .border-white\/10,
        html.theme-light aside .border-white\/15,
        html.theme-light .lg\:hidden .border-white\/10 {
            border-color: #e2e8f0 !important;
        }
        html.theme-light aside h1,
        html.theme-light aside h2,
        html.theme-light aside span.font-bold,
        html.theme-light aside p.font-semibold {
            color: #0f172a !important;
        }
        html.theme-light aside nav a,
        html.theme-light .lg\:hidden nav a {
            color: #334155 !important;
        }
        html.theme-light aside nav a:hover,
        html.theme-light .lg\:hidden nav a:hover {
            background-color: #f1f5f9 !important;
            color: #0f172a !important;
        }
        html.theme-light aside nav a[class*="from-amber-500\/20"],
        html.theme-light .lg\:hidden nav a[class*="from-amber-500\/20"] {
            background: linear-gradient(to right, #fef3c7, #ede9fe) !important;
            border-color: #fde68a !important;
            color: #92400e !important;
            font-weight: 700 !important;
        }
        html.theme-light aside nav svg,
        html.theme-light .lg\:hidden nav svg {
            color: #64748b !important;
        }
        html.theme-light aside nav a[class*="from-amber-500\/20"] svg,
        html.theme-light .lg\:hidden nav a[class*="from-amber-500\/20"] svg {
            color: #d97706 !important;
        }

        /* Sidebar Bottom Staff User Profile Widget in Light Mode (Box 2) */
        html.theme-light aside div.border-t,
        html.theme-light aside div[class*="bg-slate-950"],
        html.theme-light .lg\:hidden div.border-t,
        html.theme-light .lg\:hidden div[class*="bg-slate-950"] {
            background-color: #f8fafc !important;
            border-top: 1px solid #e2e8f0 !important;
        }
        html.theme-light aside div.border-t p.text-white,
        html.theme-light aside div[class*="bg-slate-950"] p.text-white,
        html.theme-light .lg\:hidden div.border-t p.text-white,
        html.theme-light .lg\:hidden div[class*="bg-slate-950"] p.text-white,
        html.theme-light aside div.border-t p.font-semibold,
        html.theme-light aside div[class*="bg-slate-950"] p.font-semibold {
            color: #0f172a !important;
            font-weight: 700 !important;
        }
        html.theme-light aside div.border-t p.text-slate-400,
        html.theme-light aside div[class*="bg-slate-950"] p.text-slate-400,
        html.theme-light .lg\:hidden div.border-t p.text-slate-400,
        html.theme-light .lg\:hidden div[class*="bg-slate-950"] p.text-slate-400 {
            color: #64748b !important;
        }
        html.theme-light aside div.border-t button,
        html.theme-light aside div[class*="bg-slate-950"] button,
        html.theme-light .lg\:hidden div.border-t button,
        html.theme-light .lg\:hidden div[class*="bg-slate-950"] button {
            background-color: #ffe4e6 !important;
            border: 1px solid #fecdd3 !important;
            color: #be123c !important;
            font-weight: 600 !important;
        }
        html.theme-light aside div.border-t button:hover,
        html.theme-light aside div[class*="bg-slate-950"] button:hover,
        html.theme-light .lg\:hidden div.border-t button:hover,
        html.theme-light .lg\:hidden div[class*="bg-slate-950"] button:hover {
            background-color: #f43f5e !important;
            border-color: #f43f5e !important;
            color: #ffffff !important;
        }

        /* Main Containers, Header Cards, and Grids in Light Mode (Box 1 & Entire Site) */
        html.theme-light main .bg-white,
        html.theme-light main [class*="bg-slate-900"],
        html.theme-light main [class*="bg-slate-950"],
        html.theme-light main [class*="bg-slate-800"],
        html.theme-light main > div > div.space-y-6 > div.rounded-3xl,
        html.theme-light main > div > div.space-y-6 > div.rounded-2xl,
        html.theme-light main > div > div > div.rounded-3xl,
        html.theme-light main > div > div > div.rounded-2xl,
        html.theme-light main .rounded-3xl,
        html.theme-light main .rounded-2xl {
            background: #ffffff !important;
            backdrop-filter: blur(16px) !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.03) !important;
            border-radius: 1.5rem !important;
            color: #1e293b !important;
        }
        html.theme-light main .grid > div[class*="bg-slate-900"],
        html.theme-light main .grid > div[class*="bg-white"],
        html.theme-light main .grid > div.rounded-2xl {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04) !important;
            color: #1e293b !important;
        }
        html.theme-light main .grid > div[class*="bg-slate-900"]:hover,
        html.theme-light main .grid > div[class*="bg-white"]:hover {
            transform: translateY(-2px) !important;
            border-color: #cbd5e1 !important;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.08) !important;
        }

        /* Top Page Header Switcher Capsules & Pods in Light Mode */
        html.theme-light main div[class*="bg-slate-950/60"],
        html.theme-light main div[class*="bg-slate-950/70"],
        html.theme-light main div[class*="bg-slate-950/80"],
        html.theme-light main div[class*="bg-slate-950/90"],
        html.theme-light main div[class*="bg-slate-950"],
        html.theme-light main div[class*="bg-slate-900/60"],
        html.theme-light main div[class*="bg-slate-900/90"] {
            background-color: #f1f5f9 !important;
            border: 1px solid #e2e8f0 !important;
            color: #1e293b !important;
        }

        /* Header Filter & Action Buttons in Light Mode */
        html.theme-light main button.text-slate-300,
        html.theme-light main button[class*="bg-white/10"],
        html.theme-light main button[class*="bg-white/5"],
        html.theme-light main a[class*="bg-white/10"],
        html.theme-light main a[class*="bg-white/5"] {
            background-color: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            color: #334155 !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
        }
        html.theme-light main button.text-slate-300:hover,
        html.theme-light main button[class*="bg-white/10"]:hover,
        html.theme-light main button[class*="bg-white/5"]:hover,
        html.theme-light main a[class*="bg-white/10"]:hover,
        html.theme-light main a[class*="bg-white/5"]:hover {
            background-color: #f8fafc !important;
            border-color: #94a3b8 !important;
            color: #0f172a !important;
        }

        /* Active Switcher & Preset Buttons in Light Mode */
        html.theme-light main button[class*="from-amber-500"] {
            background: linear-gradient(to right, #f59e0b, #d97706) !important;
            color: #0f172a !important;
            font-weight: 800 !important;
            box-shadow: 0 2px 6px rgba(245, 158, 11, 0.3) !important;
        }
        html.theme-light main button[class*="from-purple-600"] {
            background: linear-gradient(to right, #9333ea, #4f46e5) !important;
            color: #ffffff !important;
            font-weight: 800 !important;
            box-shadow: 0 2px 6px rgba(147, 51, 234, 0.3) !important;
        }
        html.theme-light main button.bg-amber-400 {
            background-color: #f59e0b !important;
            color: #0f172a !important;
            font-weight: 800 !important;
        }

        /* Typography & Headings in Light Mode */
        html.theme-light main h1,
        html.theme-light main h2,
        html.theme-light main h3,
        html.theme-light main h4,
        html.theme-light main h5,
        html.theme-light main h6,
        html.theme-light main h1.text-white,
        html.theme-light main h2.text-white,
        html.theme-light main h3.text-white {
            color: #0f172a !important;
            font-weight: 800 !important;
            text-shadow: none !important;
        }
        html.theme-light main .text-white {
            color: #0f172a !important;
        }
        html.theme-light main .text-slate-900 {
            color: #0f172a !important;
            font-weight: 700 !important;
        }
        html.theme-light main .text-slate-800 {
            color: #1e293b !important;
            font-weight: 600 !important;
        }
        html.theme-light main .text-slate-700 {
            color: #334155 !important;
        }
        html.theme-light main .text-slate-600 {
            color: #475569 !important;
        }
        html.theme-light main .text-slate-500 {
            color: #64748b !important;
        }
        html.theme-light main .text-slate-400 {
            color: #64748b !important;
        }
        html.theme-light main .text-slate-300 {
            color: #475569 !important;
        }
        html.theme-light main .text-slate-200 {
            color: #334155 !important;
        }
        html.theme-light main .text-slate-100 {
            color: #1e293b !important;
        }
        html.theme-light main label,
        html.theme-light div[role="dialog"] label {
            color: #0f172a !important;
            font-weight: 700 !important;
        }

        /* Inputs, Dropdowns, Selects, and Textareas in Light Mode */
        html.theme-light main input:not([type="checkbox"]):not([type="radio"]):not(:disabled),
        html.theme-light div[role="dialog"] input:not([type="checkbox"]):not([type="radio"]):not(:disabled),
        html.theme-light main select,
        html.theme-light div[role="dialog"] select,
        html.theme-light main textarea,
        html.theme-light div[role="dialog"] textarea,
        html.theme-light main input[type="date"],
        html.theme-light main [class*="border-white/15"] {
            background-color: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 0.75rem !important;
            color: #0f172a !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
            transition: all 0.2s ease !important;
        }
        html.theme-light main input:focus,
        html.theme-light div[role="dialog"] input:focus,
        html.theme-light main select:focus,
        html.theme-light div[role="dialog"] select:focus,
        html.theme-light main textarea:focus,
        html.theme-light div[role="dialog"] textarea:focus {
            background-color: #ffffff !important;
            border-color: #4f46e5 !important;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15) !important;
            outline: none !important;
        }
        html.theme-light main input::placeholder,
        html.theme-light div[role="dialog"] input::placeholder,
        html.theme-light main textarea::placeholder,
        html.theme-light div[role="dialog"] textarea::placeholder {
            color: #94a3b8 !important;
        }
        html.theme-light main input:disabled,
        html.theme-light div[role="dialog"] input:disabled {
            background-color: #f1f5f9 !important;
            border-color: #e2e8f0 !important;
            color: #64748b !important;
        }
        html.theme-light main select option,
        html.theme-light div[role="dialog"] select option {
            background-color: #ffffff !important;
            color: #0f172a !important;
        }

        /* Tables in Light Mode */
        html.theme-light main table thead tr {
            background-color: #f8fafc !important;
        }
        html.theme-light main table thead th {
            background-color: #f8fafc !important;
            color: #475569 !important;
            font-weight: 700 !important;
            border-bottom: 2px solid #e2e8f0 !important;
        }
        html.theme-light main table thead th:hover {
            background-color: #f1f5f9 !important;
            color: #0f172a !important;
        }
        html.theme-light main table tbody tr {
            background-color: #ffffff !important;
            border-bottom: 1px solid #f1f5f9 !important;
        }
        html.theme-light main table tbody tr:hover {
            background-color: #f8fafc !important;
        }
        html.theme-light main table tbody td {
            color: #1e293b !important;
            border-bottom: 1px solid #f1f5f9 !important;
        }
        html.theme-light main table a[class*="bg-indigo-50"],
        html.theme-light main table a[id^="btn-inspect-"] {
            background: #4f46e5 !important;
            color: #ffffff !important;
            border: 1px solid #4338ca !important;
            box-shadow: 0 2px 6px rgba(79, 70, 229, 0.25) !important;
        }
        html.theme-light main table a[class*="bg-indigo-50"]:hover,
        html.theme-light main table a[id^="btn-inspect-"]:hover {
            background: #3730a3 !important;
            border-color: #3730a3 !important;
            color: #ffffff !important;
        }

        /* Subdivisions, Card Header Bars, and Dividers in Light Mode */
        html.theme-light main div[class*="border-b"][class*="bg-slate-50"],
        html.theme-light main div.px-5.py-3\.5.bg-slate-50,
        html.theme-light main div.px-5.py-3\.5.bg-white\/5,
        html.theme-light main div.px-5.py-3\.5,
        html.theme-light main div.px-6.py-4.bg-slate-50,
        html.theme-light main div.px-6.py-4,
        html.theme-light main [class*="border-b"][class*="border-white"] {
            background: #f8fafc !important;
            border-bottom: 1px solid #e2e8f0 !important;
        }
        html.theme-light main div[class*="border-b"][class*="bg-slate-50"] h1,
        html.theme-light main div[class*="border-b"][class*="bg-slate-50"] h2,
        html.theme-light main div[class*="border-b"][class*="bg-slate-50"] h3,
        html.theme-light main div[class*="border-b"][class*="bg-slate-50"] h4,
        html.theme-light main div.px-5.py-3\.5 h2,
        html.theme-light main div.px-6.py-4 h3 {
            color: #0f172a !important;
            font-weight: 800 !important;
            text-shadow: none !important;
        }
        html.theme-light main .divide-slate-100 > :not([hidden]) ~ :not([hidden]),
        html.theme-light main .divide-slate-200 > :not([hidden]) ~ :not([hidden]),
        html.theme-light main .divide-white\/5 > :not([hidden]) ~ :not([hidden]),
        html.theme-light main .divide-white\/10 > :not([hidden]) ~ :not([hidden]) {
            border-color: #e2e8f0 !important;
        }
        html.theme-light main .bg-white\/10 {
            background-color: #f1f5f9 !important;
            border-color: #cbd5e1 !important;
            color: #334155 !important;
        }
        html.theme-light main .bg-white\/5 {
            background-color: #f8fafc !important;
            border-color: #e2e8f0 !important;
            color: #475569 !important;
        }
        html.theme-light main .border-white\/10,
        html.theme-light main .border-white\/15,
        html.theme-light main .border-white\/5 {
            border-color: #e2e8f0 !important;
        }
        html.theme-light #btn-reset-filters-top {
            background: #f1f5f9 !important;
            color: #334155 !important;
            border: 1px solid #cbd5e1 !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
        }
        html.theme-light #btn-reset-filters-top:hover {
            background: #e2e8f0 !important;
            color: #0f172a !important;
        }

        /* Badges & Mono IDs in Light Mode */
        html.theme-light main td span.font-mono,
        html.theme-light main span.font-mono {
            background-color: #f1f5f9 !important;
            border: 1px solid #cbd5e1 !important;
            color: #0f172a !important;
            font-weight: 700 !important;
        }
        html.theme-light main [id="badge-workspace"],
        html.theme-light main span[class*="font-mono"][class*="bg-amber"] {
            background-color: #fef3c7 !important;
            border: 1px solid #fde68a !important;
            color: #92400e !important;
        }

        /* Modals & Dialog Windows in Light Mode */
        html.theme-light div[role="dialog"] .relative.transform.overflow-hidden,
        html.theme-light main div[role="dialog"] .relative.transform.overflow-hidden {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.25) !important;
            color: #1e293b !important;
        }
        html.theme-light div[role="dialog"] .border-b,
        html.theme-light main div[role="dialog"] .border-b {
            background: #f8fafc !important;
            border-bottom: 1px solid #e2e8f0 !important;
            color: #0f172a !important;
        }
        html.theme-light div[role="dialog"] h1,
        html.theme-light div[role="dialog"] h2,
        html.theme-light div[role="dialog"] h3 {
            color: #0f172a !important;
        }
        html.theme-light div[role="dialog"] .border-t,
        html.theme-light main div[role="dialog"] .border-t {
            background: #f8fafc !important;
            border-top: 1px solid #e2e8f0 !important;
        }
        html.theme-light div[role="dialog"] button.text-slate-300,
        html.theme-light div[role="dialog"] button.text-slate-700,
        html.theme-light div[role="dialog"] button.text-slate-600 {
            color: #475569 !important;
            background-color: #f1f5f9 !important;
            border: 1px solid #cbd5e1 !important;
        }
        html.theme-light div[role="dialog"] button.text-slate-300:hover,
        html.theme-light div[role="dialog"] button.text-slate-700:hover {
            color: #0f172a !important;
            background-color: #e2e8f0 !important;
        }
        html.theme-light div[role="dialog"] div[class*="bg-amber-950"] {
            background: #fffbeb !important;
            border: 1px solid #fde68a !important;
            color: #92400e !important;
        }
        html.theme-light div[role="dialog"] div[class*="bg-amber-950"] p.font-bold {
            color: #78350f !important;
        }
        html.theme-light div[role="dialog"] div[class*="bg-rose-950"] {
            background: #fff1f2 !important;
            border: 1px solid #fecdd3 !important;
            color: #9f1239 !important;
        }
        html.theme-light div[role="dialog"] div[class*="bg-rose-950"] p.font-bold {
            color: #881337 !important;
        }
        html.theme-light div[role="dialog"] div[class*="bg-slate-900"],
        html.theme-light div[role="dialog"] div[class*="bg-slate-950"] {
            background: #f8fafc !important;
            border: 1px solid #e2e8f0 !important;
            color: #334155 !important;
        }
        html.theme-light div[role="dialog"] div[class*="bg-slate-900"] p.font-bold,
        html.theme-light div[role="dialog"] div[class*="bg-slate-950"] p.font-bold {
            color: #0f172a !important;
        }
        html.theme-light div[role="dialog"] div.bg-white {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            color: #0f172a !important;
        }
        html.theme-light div[role="dialog"] .text-white {
            color: #0f172a !important;
        }
        html.theme-light div[role="dialog"] .text-slate-400 {
            color: #64748b !important;
        }
        html.theme-light div[role="dialog"] .text-slate-300 {
            color: #475569 !important;
        }

        /* Alerts & Status Banners in Light Mode */
        html.theme-light main [role="alert"].bg-emerald-50,
        html.theme-light main [role="alert"][class*="bg-emerald-"],
        html.theme-light div[role="dialog"] div[class*="bg-emerald-50"] {
            background: #ecfdf5 !important;
            border: 1px solid #a7f3d0 !important;
            color: #065f46 !important;
        }
        html.theme-light main [role="alert"].bg-amber-50,
        html.theme-light main [role="alert"][class*="bg-amber-"],
        html.theme-light div[role="dialog"] div[class*="bg-amber-50"] {
            background: #fffbeb !important;
            border: 1px solid #fde68a !important;
            color: #92400e !important;
        }
        html.theme-light main [role="alert"].bg-rose-50,
        html.theme-light main [role="alert"][class*="bg-rose-"],
        html.theme-light div[role="dialog"] div[class*="bg-rose-50"] {
            background: #fff1f2 !important;
            border: 1px solid #fecdd3 !important;
            color: #9f1239 !important;
        }

        /* Metric Values in Light Mode */
        html.theme-light main [id^="metric-"] {
            color: #0f172a !important;
            text-shadow: none !important;
            font-weight: 800 !important;
        }
        html.theme-light main [id="metric-paid"] { color: #059669 !important; }
        html.theme-light main [id="metric-unpaid"] { color: #d97706 !important; }
        html.theme-light main [id="metric-voided"],
        html.theme-light main [id="metric-revoked"] { color: #dc2626 !important; }
        html.theme-light main [id="metric-reversed"] { color: #7c3aed !important; }
        html.theme-light main [id="metric-revenue"] { color: #4f46e5 !important; }
    </style>

    <script>
        // Check initial pinned state and theme state to prevent layout shift (FOUC)
        (function() {
            try {
                if (localStorage.getItem('emk_sidebar_pinned') === 'true') {
                    document.documentElement.classList.add('sidebar-pinned');
                } else {
                    document.documentElement.classList.remove('sidebar-pinned');
                }

                var savedTheme = localStorage.getItem('emk_theme');
                if (savedTheme === 'light') {
                    document.documentElement.classList.add('theme-light');
                    document.documentElement.classList.remove('theme-dark', 'dark');
                } else {
                    document.documentElement.classList.add('theme-dark', 'dark');
                    document.documentElement.classList.remove('theme-light');
                }
            } catch (e) {}
        })();

        document.addEventListener('alpine:init', function() {
            // Global Theme Store
            if (!Alpine.store('theme')) {
                Alpine.store('theme', {
                    mode: (function() {
                        try {
                            return localStorage.getItem('emk_theme') === 'light' ? 'light' : 'dark';
                        } catch (e) {
                            return 'dark';
                        }
                    })(),

                    init() {
                        this.apply();
                    },

                    toggle() {
                        this.mode = this.mode === 'light' ? 'dark' : 'light';
                        try {
                            localStorage.setItem('emk_theme', this.mode);
                        } catch (e) {}
                        this.apply();
                    },

                    apply() {
                        if (this.mode === 'light') {
                            document.documentElement.classList.add('theme-light');
                            document.documentElement.classList.remove('theme-dark', 'dark');
                        } else {
                            document.documentElement.classList.add('theme-dark', 'dark');
                            document.documentElement.classList.remove('theme-light');
                        }
                    }
                });
            }

            Alpine.store('sidebar', {
                pinned: localStorage.getItem('emk_sidebar_pinned') === 'true',
                open: localStorage.getItem('emk_sidebar_pinned') === 'true',
                mobileOpen: false,
                timer: null,

                init() {
                    if (this.pinned) {
                        this.open = true;
                        document.documentElement.classList.add('sidebar-pinned');
                    } else {
                        document.documentElement.classList.remove('sidebar-pinned');
                    }
                },

                togglePin() {
                    this.pinned = !this.pinned;
                    try {
                        localStorage.setItem('emk_sidebar_pinned', this.pinned ? 'true' : 'false');
                    } catch (e) {}
                    if (this.pinned) {
                        document.documentElement.classList.add('sidebar-pinned');
                        this.clearTimer();
                        this.open = true;
                    } else {
                        document.documentElement.classList.remove('sidebar-pinned');
                        this.startTimer();
                    }
                },

                toggleManual() {
                    if (window.innerWidth < 1024) {
                        this.mobileOpen = !this.mobileOpen;
                    } else {
                        this.clearTimer();
                        this.open = !this.open;
                    }
                },

                closeMobile() {
                    this.mobileOpen = false;
                },

                onMouseEnter() {
                    if (window.innerWidth >= 1024 && !this.pinned) {
                        this.clearTimer();
                        this.open = true;
                    }
                },

                onMouseLeave() {
                    if (window.innerWidth >= 1024 && !this.pinned) {
                        this.startTimer();
                    }
                },

                startTimer() {
                    this.clearTimer();
                    var self = this;
                    this.timer = setTimeout(function() {
                        if (!self.pinned) {
                            self.open = false;
                        }
                    }, 3000);
                },

                clearTimer() {
                    if (this.timer) {
                        clearTimeout(this.timer);
                        this.timer = null;
                    }
                }
            });
        });
    </script>
</head>
<body class="min-h-full font-sans antialiased text-slate-100 flex flex-col justify-between relative overflow-x-hidden selection:bg-amber-400 selection:text-slate-950">
    <!-- Full-Window Device-Pixel Responsive Background Image: Deep Black Canvas with Subtle Atmospheric Glow -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0 bg-[#040711]" aria-hidden="true">
        <!-- Deep Atmospheric Glow: Extremely low opacity so photo never clashes with foreground content -->
        <picture class="absolute inset-0 w-full h-full">
            <source srcset="{{ asset('images/emk-bg.webp') }}" type="image/webp">
            <img 
                src="{{ asset('images/emk-bg.jpg') }}" 
                alt="EMK Center Background" 
                class="w-full h-full object-cover object-center opacity-15 brightness-30 contrast-125 filter blur-[1px] pointer-events-none select-none transition-opacity duration-500"
                loading="eager"
                fetchpriority="high"
            >
        </picture>

        <!-- Solid Deep Black Scrim: Guarantees 100% legibility and zero visual glare on all pages -->
        <div class="absolute inset-0 bg-[#060a17]/85 backdrop-blur-md"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-[#040711]/95 via-[#060a17]/80 to-[#040711]/98"></div>
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

        <!-- Main Content Canvas (Auto-Adjusts based on Background Image Color & Sidebar Pin State) -->
        <main 
            class="crm-main flex-1 flex flex-col transition-all duration-300 ease-in-out"
            :class="$store.sidebar && $store.sidebar.pinned ? 'lg:pl-64' : 'lg:pl-0'"
        >
            <div class="py-6 px-4 sm:px-6 lg:px-8 xl:px-10 flex-1 w-full mx-auto">
                {{ $slot ?? '' }}
                @yield('content')
            </div>
        </main>
    </div>

    <!-- Session Expiration Prompt Modal (EMK-019) -->
    <div 
        x-data="{ show: false }" 
        x-cloak 
        x-on:session-expired.window="show = true"
        x-show="show" 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md"
        role="dialog"
        aria-modal="true"
    >
        <div class="bg-slate-900 border border-amber-500/40 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl text-center space-y-4">
            <div class="w-14 h-14 mx-auto rounded-full bg-amber-500/20 border border-amber-500/50 flex items-center justify-center text-amber-400">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h3 class="text-xl font-bold text-white">Session Inactivity Timeout</h3>
            <p class="text-sm text-slate-300">Your secure staff session has expired due to inactivity. Please reload to authenticate again.</p>
            <div class="pt-2 flex justify-center gap-3">
                <button type="button" onclick="window.location.reload()" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 text-slate-950 font-bold hover:brightness-110 shadow-lg">
                    Authenticate Again
                </button>
            </div>
        </div>
    </div>

    @livewireScripts
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.hook('request', ({ fail }) => {
                fail(({ status, preventDefault }) => {
                    if (status === 419) {
                        preventDefault();
                        window.dispatchEvent(new CustomEvent('session-expired'));
                    }
                });
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
