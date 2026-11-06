<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#0a0f1d]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>EMK Center - American Spaces Bangladesh</title>

    <!-- Styles & Scripts via Vite -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    <style>
        /* 3D Liquid Frosted Glass Styling */
        .glass-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.12) 0%, rgba(255, 255, 255, 0.03) 50%, rgba(15, 23, 42, 0.6) 100%), rgba(10, 16, 30, 0.7) !important;
            backdrop-filter: blur(28px) saturate(200%) !important;
            -webkit-backdrop-filter: blur(28px) saturate(200%) !important;
            border: 1px solid rgba(255, 255, 255, 0.22) !important;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.7), inset 0 1px 1px 0 rgba(255, 255, 255, 0.35) !important;
        }
    </style>
</head>
<body class="min-h-full font-sans antialiased text-slate-100 flex flex-col justify-between relative overflow-x-hidden selection:bg-amber-400 selection:text-slate-950">
    <!-- Full-Window Device-Pixel Responsive Background Image: 100% Fit -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0 bg-[#0a0f1d]" aria-hidden="true">
        <!-- Ambient Atmospheric Underlay -->
        <img 
            src="{{ asset('images/emk-bg.jpg') }}" 
            alt="" 
            class="absolute inset-0 w-full h-full object-cover filter blur-3xl opacity-60 scale-110 pointer-events-none"
            aria-hidden="true"
        >

        <!-- Full-Window Fit Layer -->
        <picture class="absolute inset-0 w-full h-full">
            <source srcset="{{ asset('images/emk-bg.webp') }}" type="image/webp">
            <img 
                src="{{ asset('images/emk-bg.jpg') }}" 
                alt="EMK Center Background" 
                class="w-full h-full object-cover object-center opacity-85 brightness-95 contrast-105 filter blur-[0.5px] pointer-events-none select-none"
                loading="eager"
            >
        </picture>

        <!-- Atmospheric Liquid Glass Scrim -->
        <div class="absolute inset-0 bg-slate-950/55 backdrop-blur-[1.5px]"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-950/35 to-slate-950/80"></div>
    </div>

    <!-- Top Navigation Header -->
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
                                U.S. Embassy Partner
                            </span>
                        </div>
                        <h1 class="text-base sm:text-lg font-bold text-white tracking-tight flex items-center gap-2">
                            American Spaces Bangladesh
                            <span class="text-white/30 font-normal">|</span>
                            <span class="text-amber-400 font-semibold">EMK Center</span>
                        </h1>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <a href="{{ url('/apply') }}" class="min-h-[44px] inline-flex items-center gap-2 text-xs font-bold text-slate-950 bg-gradient-to-r from-amber-400 via-amber-500 to-amber-600 hover:from-amber-300 hover:to-amber-500 transition-all px-4 py-2 rounded-xl shadow-md shadow-amber-500/25 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                        <span>Become a Member</span>
                    </a>
                    <a href="{{ route('portal.login') }}" class="min-h-[44px] inline-flex items-center text-xs font-semibold text-slate-200 hover:text-white transition-colors px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10">
                        Member Portal &rarr;
                    </a>
                    <a href="{{ route('login') }}" class="min-h-[44px] inline-flex items-center text-xs font-semibold text-slate-300 hover:text-white transition-colors px-3.5 py-2 rounded-xl hover:bg-white/5">
                        Staff Sign In
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Canvas -->
    <main role="main" class="flex-1 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="w-full max-w-3xl glass-card rounded-3xl p-8 sm:p-12 text-center space-y-8">
            <div class="flex flex-col items-center">
                <div class="w-20 h-20 rounded-full border-2 border-amber-400 ring-4 ring-amber-400/20 bg-white p-1 shadow-2xl mb-5 overflow-hidden">
                    <img src="{{ asset('images/emk-logo.png') }}" alt="EMK Center Logo" class="w-full h-full object-contain rounded-full">
                </div>
                <span class="text-xs font-bold uppercase tracking-widest text-amber-400 mb-2">American Spaces Bangladesh</span>
                <h2 class="text-2xl sm:text-4xl font-extrabold text-white tracking-tight">
                    Edward M. Kennedy Center
                </h2>
                <p class="text-sm sm:text-base text-slate-300 max-w-xl mx-auto mt-3 leading-relaxed">
                    Connecting youth, professionals, and innovators through open knowledge, leadership, American literature, and cross-cultural exchange.
                </p>
            </div>

            <!-- Portal Navigation Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4 text-left">
                <!-- Option 1: Become a Member -->
                <a href="{{ url('/apply') }}" class="group p-5 rounded-2xl bg-gradient-to-b from-amber-500/20 to-amber-500/5 hover:from-amber-500/30 hover:to-amber-500/10 border border-amber-400/50 hover:border-amber-400 transition-all duration-200 shadow-lg shadow-amber-500/10 flex flex-col justify-between">
                    <div>
                        <div class="w-10 h-10 rounded-xl bg-amber-400/20 border border-amber-400/30 flex items-center justify-center text-amber-300 mb-3 group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-white group-hover:text-amber-300 transition-colors">Become a Member</h3>
                        <p class="text-xs text-slate-300 mt-1.5 leading-relaxed">Submit your online membership application and join EMK Center.</p>
                    </div>
                    <div class="pt-4 flex items-center gap-1.5 text-xs font-bold text-amber-400 group-hover:translate-x-1 transition-transform">
                        <span>Apply Online</span>
                        <span>&rarr;</span>
                    </div>
                </a>

                <!-- Option 2: Member Portal -->
                <a href="{{ route('portal.login') }}" class="group p-5 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 hover:border-emerald-400/40 transition-all duration-200 shadow-md flex flex-col justify-between">
                    <div>
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center text-emerald-300 mb-3 group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-white group-hover:text-emerald-300 transition-colors">Member Portal</h3>
                        <p class="text-xs text-slate-300 mt-1.5 leading-relaxed">Access your digital Member ID, renewal status, and account profile.</p>
                    </div>
                    <div class="pt-4 flex items-center gap-1.5 text-xs font-bold text-emerald-400 group-hover:translate-x-1 transition-transform">
                        <span>Sign In</span>
                        <span>&rarr;</span>
                    </div>
                </a>

                <!-- Option 3: Staff Console -->
                <a href="{{ route('login') }}" class="group p-5 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 hover:border-indigo-400/40 transition-all duration-200 shadow-md flex flex-col justify-between">
                    <div>
                        <div class="w-10 h-10 rounded-xl bg-indigo-500/20 border border-indigo-400/30 flex items-center justify-center text-indigo-300 mb-3 group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-white group-hover:text-indigo-300 transition-colors">Staff Console</h3>
                        <p class="text-xs text-slate-300 mt-1.5 leading-relaxed">CRM intake, verification, attendance, analytics, and platform hub.</p>
                    </div>
                    <div class="pt-4 flex items-center gap-1.5 text-xs font-bold text-indigo-400 group-hover:translate-x-1 transition-transform">
                        <span>Staff Login</span>
                        <span>&rarr;</span>
                    </div>
                </a>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="py-6 border-t border-white/10 bg-slate-950/60 backdrop-blur-xl text-xs text-slate-400">
        <div class="w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 flex flex-col sm:flex-row items-center justify-between gap-3 text-center sm:text-left">
            <p>&copy; {{ date('Y') }} EMK Center. All rights reserved.•T4D </p>
            <p class="text-slate-400">Phone: <a href="tel:+8809606513103" class="hover:underline font-medium text-amber-300">+8809606513103</a> | <a href="tel:+8801926666995" class="hover:underline font-medium text-amber-300">+8801926666995</a> • <a href="mailto:info@emkcenter.org" class="hover:underline font-medium text-amber-300">info@emkcenter.org</a></p>
        </div>
    </footer>
</body>
</html>
