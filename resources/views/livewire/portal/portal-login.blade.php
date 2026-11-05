<div class="w-full py-4 sm:py-6">
    <!-- Glassmorphic Member Portal Login Panel -->
    <div class="relative w-full max-w-4xl mx-auto rounded-3xl overflow-hidden shadow-2xl shadow-emerald-950/70 border border-emerald-400/25 backdrop-blur-2xl bg-gradient-to-br from-[#0c392c]/95 via-[#0e4536]/90 to-[#07261d]/95">
        
        <div class="grid grid-cols-1 lg:grid-cols-12 min-h-[580px]">
            
            <!-- Left Column: Form & Identification (5 Cols) -->
            <div class="lg:col-span-5 xl:col-span-5 p-7 sm:p-10 flex flex-col justify-between relative z-20 bg-black/25 backdrop-blur-xl border-b lg:border-b-0 lg:border-r border-white/10">
                
                <!-- Top Section: 3D Avatar & Welcome Title -->
                <div>
                    <!-- 3D Circular Avatar Badge -->
                    <div class="w-20 h-20 rounded-full mx-auto p-1 bg-gradient-to-tr from-teal-400/40 via-emerald-400/20 to-teal-200/40 border border-teal-300/40 shadow-xl shadow-teal-950/50 flex items-center justify-center backdrop-blur-md relative group">
                        <div class="absolute inset-0 rounded-full bg-teal-400/20 blur-md group-hover:blur-lg transition-all"></div>
                        <div class="w-full h-full rounded-full overflow-hidden bg-gradient-to-b from-[#256353] to-[#123e33] flex items-center justify-center relative">
                            <!-- Stylized 3D Avatar Graphic -->
                            <svg class="w-13 h-13 text-teal-100 drop-shadow-md" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <!-- Cap / Beret in Warm Ochre -->
                                <ellipse cx="32" cy="22" rx="14" ry="5.5" fill="#e59837" />
                                <path d="M20 22C20 15 44 15 44 22Z" fill="#f6b158" />
                                <circle cx="32" cy="15" r="2" fill="#c4781d" />
                                <!-- Face / Head -->
                                <circle cx="32" cy="28" r="9.5" fill="#fbd3b0" />
                                <!-- Hair -->
                                <path d="M22.5 24C24 23 27 23 29 25C31 23 35 23 37 25C39 23 41 23 41.5 24" stroke="#4a2e18" stroke-width="1.8" stroke-linecap="round"/>
                                <!-- Eyes -->
                                <circle cx="28.5" cy="28" r="1.2" fill="#2d1c10"/>
                                <circle cx="35.5" cy="28" r="1.2" fill="#2d1c10"/>
                                <!-- Cheerful Smile -->
                                <path d="M29.5 32C30.5 33.5 33.5 33.5 34.5 32" stroke="#b45309" stroke-width="1.4" stroke-linecap="round"/>
                                <!-- Neck & Collar -->
                                <rect x="29" y="37" width="6" height="5" fill="#f4c49c" />
                                <path d="M18 52C18 43 24 41 32 41C40 41 46 43 46 52" fill="#75a99c" />
                                <path d="M28 41L32 46L36 41" fill="#f4c49c" />
                            </svg>
                        </div>
                    </div>

                    <!-- Welcome Title & Heading -->
                    <div class="text-center mt-4 mb-6">
                        <h1 class="font-serif text-3xl sm:text-4xl font-black text-white tracking-[0.25em] drop-shadow-md">
                            WELCOME
                        </h1>
                        <p class="text-xs font-bold tracking-wider uppercase text-emerald-300/90 mt-1">
                            Member Portal Login
                        </p>
                        <p class="sr-only">
                            Enter your registered Email address or Member ID (e.g. EMK-2026-00001)
                        </p>
                    </div>

                    <!-- Member Authentication Form -->
                    <form wire:submit="login" class="space-y-4">
                        <!-- Email or Member ID Field -->
                        <div>
                            <label for="identifier" class="block text-xs font-semibold uppercase tracking-wider text-emerald-100/90 mb-1.5 flex items-center justify-between">
                                <span>Email or Member ID</span>
                            </label>
                            <div class="relative">
                                <input
                                    type="text"
                                    id="identifier"
                                    wire:model="identifier"
                                    autocomplete="username"
                                    placeholder="e.g. EMK-2026-00001 or member@emk.test"
                                    class="w-full px-4 py-3.5 min-h-[48px] rounded-xl bg-white/[0.08] hover:bg-white/[0.12] focus:bg-white/[0.15] border @error('identifier') border-rose-400 focus:border-rose-400 focus:ring-rose-400/25 @else border-emerald-400/25 focus:border-amber-400 focus:ring-amber-400/25 @enderror text-white placeholder-emerald-100/40 text-base sm:text-sm shadow-inner backdrop-blur-sm transition-all focus:outline-none focus:ring-2"
                                    required
                                    autofocus
                                >
                            </div>
                            @error('identifier')
                                <p class="mt-1.5 text-xs text-rose-300 font-medium flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                    <span>{{ $message }}</span>
                                </p>
                            @enderror
                        </div>

                        <!-- Password Field -->
                        <div>
                            <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-emerald-100/90 mb-1.5">
                                Password
                            </label>
                            <div class="relative">
                                <input
                                    type="password"
                                    id="password"
                                    wire:model="password"
                                    autocomplete="current-password"
                                    placeholder="••••••••••••"
                                    class="w-full px-4 py-3.5 min-h-[48px] rounded-xl bg-white/[0.08] hover:bg-white/[0.12] focus:bg-white/[0.15] border @error('password') border-rose-400 focus:border-rose-400 focus:ring-rose-400/25 @else border-emerald-400/25 focus:border-amber-400 focus:ring-amber-400/25 @enderror text-white placeholder-emerald-100/40 text-base sm:text-sm shadow-inner backdrop-blur-sm transition-all focus:outline-none focus:ring-2"
                                    required
                                >
                            </div>
                            @error('password')
                                <p class="mt-1.5 text-xs text-rose-300 font-medium flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                    <span>{{ $message }}</span>
                                </p>
                            @enderror
                        </div>

                        <!-- Options Row: Remember Me & Forgot Password -->
                        <div class="flex items-center justify-between pt-1 min-h-[44px]">
                            <label class="flex items-center gap-2.5 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    wire:model="remember"
                                    class="w-5 h-5 rounded text-amber-500 bg-white/10 border-white/20 focus:ring-amber-400 cursor-pointer"
                                >
                                <span class="text-xs text-emerald-100/80">Remember this device</span>
                            </label>

                            <a href="mailto:info@emkcenter.org?subject=Password%20Assistance%20Request" class="text-xs font-semibold text-amber-300 hover:text-amber-200 transition-colors p-1">
                                Forgot Password?
                            </a>
                        </div>

                        <!-- Golden LOGIN Action Button (Reference Accent, Finger Sized) -->
                        <div class="pt-3">
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                class="w-full py-4 px-6 min-h-[50px] rounded-xl text-sm sm:text-base font-extrabold uppercase tracking-widest text-slate-950 bg-gradient-to-r from-amber-400 via-yellow-400 to-amber-500 hover:from-amber-300 hover:to-yellow-300 active:scale-[0.99] disabled:opacity-60 shadow-lg shadow-amber-500/25 hover:shadow-amber-500/40 transition-all flex items-center justify-center gap-2 cursor-pointer border border-amber-300/60"
                            >
                                <span wire:loading.remove>LOGIN</span>
                                <span wire:loading class="flex items-center gap-2">
                                    <svg class="animate-spin w-4 h-4 text-slate-950" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>Authenticating...</span>
                                </span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Bottom Front Desk Assistance Notice -->
                <div class="mt-6 pt-4 border-t border-white/10 text-center">
                    <p class="text-xs text-emerald-200/70 leading-relaxed">
                        Need help or first time logging in? Contact:
                        <a href="tel:+8809606513103" class="text-amber-300 font-semibold hover:underline">+8809606513103</a>
                        <span class="text-white/30">•</span>
                        <a href="mailto:info@emkcenter.org" class="text-amber-300 font-semibold hover:underline">info@emkcenter.org</a>
                    </p>
                </div>
            </div>

            <!-- Right Column: 3D Illustration & Organic Wave (7 Cols) -->
            <div class="lg:col-span-7 xl:col-span-7 relative min-h-[440px] lg:min-h-full flex items-center justify-center overflow-hidden bg-gradient-to-br from-[#0c3a2d] via-[#083025] to-[#052018] select-none p-6">
                
                <!-- Hanging Minimalist Pendant Cone Lamp (Top Center) -->
                <div class="absolute top-0 right-1/3 z-20 flex flex-col items-center pointer-events-none">
                    <!-- Cord hanging from ceiling -->
                    <div class="w-0.5 h-16 bg-gradient-to-b from-white/30 via-amber-200/60 to-amber-300"></div>
                    <!-- Metallic Socket Fixture -->
                    <div class="w-3.5 h-3 bg-gradient-to-r from-amber-400 via-yellow-400 to-amber-600 rounded-t-sm shadow-sm"></div>
                    <!-- Cone Shade -->
                    <div class="relative">
                        <div class="w-22 h-11 bg-gradient-to-b from-[#1f5847] to-[#0e372a] rounded-t-full border-b-2 border-amber-400 shadow-xl flex items-center justify-center overflow-hidden">
                            <div class="absolute top-1 left-4 w-5 h-1 bg-white/20 rounded-full blur-xs"></div>
                        </div>
                        <!-- Glowing Warm Bulb -->
                        <div class="w-8 h-4 bg-gradient-to-b from-amber-200 via-amber-400 to-amber-500 rounded-b-full mx-auto shadow-md"></div>
                    </div>
                    <!-- Ambient Light Cone Glow -->
                    <div class="w-80 h-80 bg-gradient-to-b from-amber-300/35 via-amber-400/10 to-transparent rounded-full blur-2xl -mt-6"></div>
                </div>

                <!-- Organic Warm Yellow Wave Curve (Smooth SVG) -->
                <svg class="absolute inset-0 w-full h-full pointer-events-none z-10" viewBox="0 0 600 600" preserveAspectRatio="none" fill="none">
                    <defs>
                        <linearGradient id="emkGoldWave" x1="0%" y1="60%" x2="100%" y2="40%">
                            <stop offset="0%" stop-color="#f59e0b" />
                            <stop offset="60%" stop-color="#fbbf24" />
                            <stop offset="100%" stop-color="#f59e0b" />
                        </linearGradient>
                        <filter id="waveShadow" x="-10%" y="-10%" width="120%" height="120%">
                            <feDropShadow dx="-6" dy="4" stdDeviation="12" flood-color="#000000" flood-opacity="0.35"/>
                        </filter>
                    </defs>
                    <path d="M 120 600 C 140 450, 180 340, 280 310 C 360 280, 380 220, 370 180 C 350 130, 390 60, 520 20 C 560 5, 600 0, 600 0 L 600 600 Z" fill="url(#emkGoldWave)" filter="url(#waveShadow)" />
                </svg>

                <!-- Floating Glass Accent Badge with Exact Circle-Bordered EMK Logo (Right-side Corner) -->
                <div class="absolute top-6 right-6 z-20 flex items-center gap-2.5 px-3.5 py-1.5 rounded-full bg-white/10 border border-white/20 backdrop-blur-md text-white text-xs font-semibold shadow-xl">
                    <div class="w-6 h-6 rounded-full border border-amber-400/90 bg-white p-0.5 overflow-hidden shadow-xs flex items-center justify-center shrink-0" aria-hidden="true">
                        <img src="{{ asset('images/emk-logo.png') }}" alt="EMK Center Logo" class="w-full h-full object-contain rounded-full">
                    </div>
                    <span class="tracking-wide">EMK Center Network</span>
                </div>

                <!-- High-Resolution Clean 3D Member Character Illustration -->
                <div class="relative z-20 flex flex-col items-center justify-center pt-14 pb-4">
                    <div class="relative w-64 sm:w-72 md:w-80 lg:w-84 max-w-full transition-transform hover:scale-[1.02] duration-300">
                        <img
                            src="{{ asset('images/portal-character-transparent.png') }}"
                            alt="EMK Member Portal 3D Character Illustration"
                            class="w-full h-auto object-contain filter drop-shadow-[0_25px_35px_rgba(0,0,0,0.5)]"
                            loading="eager"
                        >
                    </div>
                </div>

            </div>

        </div>

    </div>
</div>
