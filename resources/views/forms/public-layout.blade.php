<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'American Spaces Bangladesh & EMK Center - Membership Application' }}</title>

    <!-- Styles & Scripts via Vite -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    @livewireStyles
    @stack('styles')
</head>
<body class="min-h-full font-sans antialiased text-slate-900 bg-slate-50 flex flex-col justify-between">
    <!-- Skip to main content link for keyboard & screen reader accessibility -->
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:p-4 focus:bg-indigo-600 focus:text-white focus:z-50 focus:rounded-lg focus:shadow-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Skip to main content</a>

    <!-- Header: Official American Spaces / EMK Center Branding (Decoupled from Admin Shell) -->
    <header role="banner" class="bg-white border-b border-slate-200 shadow-sm sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <!-- Branding -->
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-indigo-700 via-blue-600 to-sky-500 flex items-center justify-center text-white shadow-md font-bold text-lg tracking-wider" aria-hidden="true">
                        AS
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-blue-700 bg-blue-50 px-2.5 py-0.5 rounded-full border border-blue-200">
                                U.S. Embassy Partner
                            </span>
                            <span class="text-xs text-slate-600 hidden sm:inline">Official Application Portal</span>
                        </div>
                        <h1 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                            American Spaces Bangladesh
                            <span class="text-slate-400 font-normal" aria-hidden="true">|</span>
                            <span class="text-indigo-600 font-semibold">EMK Center</span>
                        </h1>
                    </div>
                </div>

                <!-- Secure TLS Indicator -->
                <div class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-800 px-3.5 py-1.5 rounded-full text-xs font-medium shadow-xs" role="status" aria-label="Security Status: 256-Bit TLS Secured Application">
                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" />
                    </svg>
                    <span class="hidden md:inline">256-Bit TLS Secured Application</span>
                    <span class="md:hidden">TLS Secure</span>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Body -->
    <main id="main-content" role="main" class="flex-1 py-10 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto">
            {{ $slot ?? '' }}
            @yield('content')
        </div>
    </main>

    <!-- Public Footer: Contact & Legal Guidelines (No Internal Admin Links) -->
    <footer role="contentinfo" class="bg-white border-t border-slate-200 py-10 mt-12 text-slate-600 text-sm">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 pb-8 border-b border-slate-100">
                <!-- Column 1: Locations -->
                <div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-3">American Spaces in Bangladesh</h2>
                    <ul class="space-y-1.5 text-xs text-slate-600">
                        <li>• <strong class="text-slate-800">EMK Center:</strong> SKS Tower, Level 6, Mohakhali, Dhaka</li>
                        <li>• <strong class="text-slate-800">American Center:</strong> US Embassy, Baridhara, Dhaka</li>
                        <li>• <strong class="text-slate-800">American Corners:</strong> Barishal, Chattogram, Rajshahi, Sylhet</li>
                    </ul>
                </div>

                <!-- Column 2: Contact Information -->
                <div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-3">Contact & Support</h2>
                    <ul class="space-y-1.5 text-xs text-slate-600">
                        <li>Email: <a href="mailto:membership@emk.com.bd" class="text-indigo-600 hover:underline focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded">membership@emk.com.bd</a></li>
                        <li>Information Desk: +880 1977-109012</li>
                        <li>Hours: Saturday – Thursday, 9:30 AM – 6:00 PM</li>
                        <li>Official Website: <a href="https://www.emk.com.bd" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded">www.emk.com.bd</a></li>
                    </ul>
                </div>

                <!-- Column 3: Privacy & Security Statement -->
                <div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-3">Privacy & Trust Notice</h2>
                    <p class="text-xs leading-relaxed text-slate-600">
                        Applicant data is encrypted in transit and stored in protected private storage. Information collected is utilized exclusively for membership administration, verification, and American Spaces program eligibility in accordance with U.S. Embassy Dhaka partnership standards.
                    </p>
                </div>
            </div>

            <nav aria-label="Footer Legal Links" class="pt-6 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-4">
                <p>&copy; {{ date('Y') }} Edward M. Kennedy Center for Public Service and the Arts. All rights reserved.</p>
                <div class="flex items-center gap-4">
                    <span>Privacy Notice</span>
                    <span aria-hidden="true">•</span>
                    <span>Terms of Membership</span>
                    <span aria-hidden="true">•</span>
                    <span>U.S. Embassy Dhaka Partner</span>
                </div>
            </nav>
        </div>
    </footer>

    @livewireScripts
    @stack('scripts')
</body>
</html>
