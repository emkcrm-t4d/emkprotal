<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#0b0f17]">
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
</head>
<body class="min-h-full font-sans antialiased text-slate-100 bg-[#0b0f17] selection:bg-amber-400 selection:text-slate-950 flex flex-col justify-between relative overflow-x-hidden">
    <!-- Ambient 3D Glass Theme Backdrop Lighting -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none -z-10" aria-hidden="true">
        <div class="absolute -top-32 -left-32 w-[34rem] h-[34rem] bg-indigo-500/15 rounded-full blur-[140px]"></div>
        <div class="absolute top-1/4 -right-24 w-[36rem] h-[36rem] bg-amber-500/10 rounded-full blur-[150px]"></div>
        <div class="absolute -bottom-32 left-1/3 w-[38rem] h-[38rem] bg-teal-600/10 rounded-full blur-[160px]"></div>
        <div class="absolute inset-0 bg-[radial-gradient(rgba(255,255,255,0.05)_1px,transparent_1px)] [background-size:24px_24px] opacity-40"></div>
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

        <!-- Main Content Canvas -->
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
