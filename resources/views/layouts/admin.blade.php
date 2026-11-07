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
            background: linear-gradient(180deg, rgba(17, 24, 39, 0.96) 0%, rgba(10, 15, 26, 0.97) 100%) !important;
            backdrop-filter: blur(32px) !important;
            -webkit-backdrop-filter: blur(32px) !important;
            border: 1px solid rgba(255, 255, 255, 0.16) !important;
            box-shadow: 0 20px 50px -10px rgba(0, 0, 0, 0.8), inset 0 1px 1px 0 rgba(255, 255, 255, 0.2) !important;
            border-radius: 1.5rem !important;
            color: #f8fafc !important;
        }

        /* Metric Pods inside grids (3D Frosted Tiles) */
        main .grid > div[class*="bg-slate-900"],
        main .grid > div[class*="bg-white"],
        main .grid > div.rounded-2xl {
            background: linear-gradient(180deg, rgba(22, 31, 49, 0.95) 0%, rgba(13, 19, 32, 0.97) 100%) !important;
            backdrop-filter: blur(24px) !important;
            -webkit-backdrop-filter: blur(24px) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            box-shadow: 0 8px 24px -4px rgba(0, 0, 0, 0.6), inset 0 1px 1px 0 rgba(255, 255, 255, 0.18) !important;
            border-radius: 1.25rem !important;
            transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease !important;
        }
        main .grid > div[class*="bg-slate-900"]:hover,
        main .grid > div[class*="bg-white"]:hover {
            transform: translateY(-2px) !important;
            border-color: rgba(255, 255, 255, 0.3) !important;
            box-shadow: 0 15px 30px -5px rgba(0, 0, 0, 0.7), inset 0 1px 1px 0 rgba(255, 255, 255, 0.3) !important;
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
    </style>

    <script>
        // Check initial pinned state to prevent layout shift (FOUC)
        (function() {
            try {
                if (localStorage.getItem('emk_sidebar_pinned') === 'true') {
                    document.documentElement.classList.add('sidebar-pinned');
                } else {
                    document.documentElement.classList.remove('sidebar-pinned');
                }
            } catch (e) {}
        })();

        document.addEventListener('alpine:init', function() {
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
