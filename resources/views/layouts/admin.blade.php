<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
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
<body class="h-full font-sans antialiased text-slate-900 bg-slate-100">
    <!-- Notification & Toast Slot Container -->
    <div id="notification-container" class="fixed top-4 right-4 z-50 flex flex-col gap-2 max-w-sm pointer-events-none" aria-live="polite">
        @if (session('status'))
            <div class="pointer-events-auto bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg shadow-sm text-sm flex items-start gap-2.5">
                <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="flex-1">
                    <p class="font-medium">Success</p>
                    <p class="text-xs text-emerald-700 mt-0.5">{{ session('status') }}</p>
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

    <!-- Administrative Shell Layout Structure -->
    <div class="min-h-screen flex flex-col">
        <!-- Livewire Reactive Admin Shell Component -->
        <livewire:admin.admin-shell :activeModule="$activeModule ?? null" />

        <!-- Main Content Canvas -->
        <main class="flex-1 lg:pl-64 flex flex-col">
            <div class="py-6 px-4 sm:px-6 lg:px-8 flex-1 max-w-7xl w-full mx-auto">
                {{ $slot ?? '' }}
                @yield('content')
            </div>
        </main>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
