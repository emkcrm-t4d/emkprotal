<div x-data="{ showPass: false }" class="w-full max-w-5xl mx-auto my-4">
    <!-- Main 3D Frosted Glass Login Showcase Container -->
    <div class="relative rounded-3xl bg-slate-950/85 backdrop-blur-3xl border border-white/20 shadow-2xl shadow-black/80 overflow-hidden">
        
        <!-- Subtle Ambient Background Glow inside the Card -->
        <div class="absolute -top-24 -left-24 w-72 h-72 bg-amber-500/15 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -right-24 w-80 h-80 bg-indigo-500/15 rounded-full blur-3xl pointer-events-none"></div>

        <div class="grid grid-cols-1 lg:grid-cols-12 relative z-10">
            
            <!-- Left Column: Interactive Sign In Form -->
            <div class="lg:col-span-7 p-6 sm:p-10 md:p-12 flex flex-col justify-between">
                <div>
                    <!-- Badge & Category -->
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-[10px] sm:text-xs font-black uppercase tracking-widest text-amber-400 bg-amber-400/10 px-3 py-1 rounded-full border border-amber-400/25">
                            Staff &amp; Admin Console
                        </span>
                        <span class="text-[10px] font-semibold text-emerald-400 flex items-center gap-1 bg-emerald-500/10 px-2.5 py-0.5 rounded-full border border-emerald-500/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            Active Session
                        </span>
                    </div>

                    <!-- Main Welcome Back Header (matching sample design) -->
                    <h2 class="text-3xl sm:text-4xl md:text-5xl font-black text-amber-400 tracking-tight leading-none mb-1">
                        WELCOME BACK!
                    </h2>
                    
                    <div class="flex items-center gap-2 text-xs sm:text-sm text-slate-300 mt-2 mb-8">
                        <span>Staff &amp; Admin Sign In</span>
                        <span class="text-slate-600">•</span>
                        <a href="{{ route('public.forms.show', 'membership-application') }}" class="text-amber-300 hover:text-amber-200 hover:underline font-semibold transition-colors">
                            Don't have an account? Sign up
                        </a>
                    </div>

                    <!-- Sign In Form -->
                    <form wire:submit="login" class="space-y-5">
                        <!-- Email / Username Field -->
                        <div>
                            <label for="email" class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-2">
                                Username / Email Address
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206" />
                                    </svg>
                                </div>
                                <input
                                    type="email"
                                    id="email"
                                    wire:model="email"
                                    autocomplete="username"
                                    placeholder="admin@emk.test"
                                    class="w-full pl-12 pr-4 py-3.5 rounded-full bg-slate-900/80 border @error('email') border-rose-400 focus:border-rose-400 focus:ring-rose-400/20 @else border-white/20 focus:border-amber-400 focus:ring-2 focus:ring-amber-400/25 @enderror text-white text-sm shadow-inner transition-all placeholder:text-slate-500 outline-none"
                                    required
                                    autofocus
                                >
                            </div>
                            @error('email')
                                <p class="mt-1.5 text-xs text-rose-400 font-medium flex items-center gap-1.5 pl-4">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                    <span>{{ $message }}</span>
                                </p>
                            @enderror
                        </div>

                        <!-- Password Field with Show/Hide Eye Toggle -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-300">
                                    Password
                                </label>
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </div>
                                <input
                                    :type="showPass ? 'text' : 'password'"
                                    id="password"
                                    wire:model="password"
                                    autocomplete="current-password"
                                    placeholder="••••••••••••"
                                    class="w-full pl-12 pr-12 py-3.5 rounded-full bg-slate-900/80 border @error('password') border-rose-400 focus:border-rose-400 focus:ring-rose-400/20 @else border-white/20 focus:border-amber-400 focus:ring-2 focus:ring-amber-400/25 @enderror text-white text-sm shadow-inner transition-all placeholder:text-slate-500 outline-none"
                                    required
                                >
                                <!-- Show / Hide Password Toggle Eye Button -->
                                <button 
                                    type="button" 
                                    @click="showPass = !showPass"
                                    class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-amber-400 transition-colors cursor-pointer"
                                    :title="showPass ? 'Hide password' : 'Show password'"
                                    aria-label="Toggle password visibility"
                                >
                                    <template x-if="!showPass">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </template>
                                    <template x-if="showPass">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                        </svg>
                                    </template>
                                </button>
                            </div>
                            @error('password')
                                <p class="mt-1.5 text-xs text-rose-400 font-medium flex items-center gap-1.5 pl-4">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                    <span>{{ $message }}</span>
                                </p>
                            @enderror
                        </div>

                        <!-- Remember Me & Reset Password Helper Row -->
                        <div class="flex items-center justify-between pt-1 px-1 text-xs">
                            <label for="remember" class="flex items-center gap-2 cursor-pointer select-none text-slate-300 hover:text-white">
                                <input
                                    type="checkbox"
                                    id="remember"
                                    wire:model="remember"
                                    class="w-4 h-4 rounded border-white/20 bg-slate-900 text-amber-500 focus:ring-amber-400 cursor-pointer"
                                >
                                <span>Remember me</span>
                            </label>

                            <a href="{{ route('portal.login') }}" class="text-amber-400 hover:text-amber-300 font-medium hover:underline transition-colors">
                                Member Portal Login &rarr;
                            </a>
                        </div>

                        <!-- Golden Pill Sign In Action Button (Matching Sample Image) -->
                        <div class="pt-2">
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                class="w-full min-h-[48px] flex items-center justify-center gap-2.5 px-6 py-3.5 rounded-full bg-gradient-to-r from-amber-400 via-amber-500 to-amber-600 hover:from-amber-300 hover:to-amber-500 text-slate-950 font-black text-sm uppercase tracking-wider shadow-lg shadow-amber-500/30 transition-all duration-200 transform hover:-translate-y-0.5 active:translate-y-0 cursor-pointer disabled:opacity-50"
                            >
                                <span wire:loading.remove class="flex items-center gap-2">
                                    <span>Sign In</span>
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                    </svg>
                                </span>
                                <span wire:loading class="inline-flex items-center gap-2">
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

                <!-- Footer Guideline Link -->
                <div class="mt-8 pt-4 border-t border-white/10 text-center sm:text-left">
                    <p class="text-xs text-slate-400">
                        Looking for public Membership Application?
                        <a href="{{ route('public.forms.show', 'membership-application') }}" class="font-bold text-amber-300 hover:text-amber-200 hover:underline transition-colors ml-1">
                            Apply Online &rarr;
                        </a>
                    </p>
                </div>
            </div>

            <!-- Right Column: Luminous Circular EMK Logo Medallion & Showcase (Matching Sample Panda Image) -->
            <div class="lg:col-span-5 p-8 sm:p-10 bg-gradient-to-b from-white/[0.08] via-white/[0.03] to-slate-950/80 border-t lg:border-t-0 lg:border-l border-white/10 flex flex-col items-center justify-between relative overflow-hidden">
                
                <!-- Center: Luminous Circular Medallion for EMK Logo -->
                <div class="flex flex-col items-center justify-center my-auto w-full">
                    <!-- Concentric Glowing Ring Container -->
                    <div class="relative group">
                        <!-- Outer Ambient Glow -->
                        <div class="absolute inset-0 rounded-full bg-amber-400/30 filter blur-xl group-hover:blur-2xl transition-all duration-500 scale-110"></div>
                        
                        <!-- Middle Golden Medallion Ring -->
                        <div class="relative w-44 h-44 sm:w-52 sm:h-52 rounded-full p-2 bg-gradient-to-tr from-amber-500 via-amber-400 to-amber-600 shadow-2xl shadow-amber-500/40 flex items-center justify-center">
                            
                            <!-- Inner Pure White Circular Disc for Crisp Contrast -->
                            <div class="w-full h-full rounded-full bg-white p-3.5 shadow-inner flex items-center justify-center overflow-hidden border-2 border-amber-300">
                                <img 
                                    src="{{ asset('images/emk-logo.png') }}" 
                                    alt="EMK Center Logo" 
                                    class="w-full h-full object-contain filter drop-shadow-sm select-none"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Institution Brand Name -->
                    <div class="mt-6 text-center">
                        <h3 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                            EMK Center Dhaka
                        </h3>
                        <p class="text-xs font-semibold text-amber-300 uppercase tracking-widest mt-1">
                            American Spaces Bangladesh
                        </p>
                        <p class="text-[11px] text-slate-400 mt-1 italic">
                            For Public Service and the Arts
                        </p>
                    </div>

                    <!-- Fast 1-Click Credentials Pod (Developer / Testing Quick-Fill) -->
                    <div class="mt-6 w-full max-w-xs bg-slate-900/80 backdrop-blur-md rounded-2xl p-3 border border-white/10 text-xs shadow-lg">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-400 flex items-center gap-1">
                                <svg class="w-3 h-3 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                                Default Credentials
                            </span>
                            <span class="text-[9px] font-bold text-emerald-400 bg-emerald-500/20 px-2 py-0.5 rounded-full border border-emerald-500/30">
                                1-Click Auto-Fill
                            </span>
                        </div>
                        
                        <div class="space-y-1.5 font-mono text-[11px]">
                            <!-- Admin Fast Fill Pill -->
                            <button
                                type="button"
                                wire:click="fillCredentials('admin@emk.test', 'secret123')"
                                class="w-full flex items-center justify-between p-2 rounded-xl bg-white/5 hover:bg-amber-400/20 border border-white/10 hover:border-amber-400/40 text-slate-200 hover:text-white transition-all cursor-pointer group text-left"
                                title="Click to auto-fill admin credentials"
                            >
                                <span class="truncate">
                                    <span class="text-amber-300 font-bold">Admin:</span> admin@emk.test
                                </span>
                                <span class="text-[10px] text-slate-400 group-hover:text-amber-300 font-sans font-semibold">fill &rarr;</span>
                            </button>

                            <!-- Manager Fast Fill Pill -->
                            <button
                                type="button"
                                wire:click="fillCredentials('manager.dhaka@emk.test', 'secret123')"
                                class="w-full flex items-center justify-between p-2 rounded-xl bg-white/5 hover:bg-amber-400/20 border border-white/10 hover:border-amber-400/40 text-slate-200 hover:text-white transition-all cursor-pointer group text-left"
                                title="Click to auto-fill manager credentials"
                            >
                                <span class="truncate">
                                    <span class="text-amber-300 font-bold">Manager:</span> manager.dhaka@emk.test
                                </span>
                                <span class="text-[10px] text-slate-400 group-hover:text-amber-300 font-sans font-semibold">fill &rarr;</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Bottom Security Badges -->
                <div class="mt-6 pt-4 border-t border-white/10 w-full flex items-center justify-between text-[11px] text-slate-400">
                    <span class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        TLS 1.3 Encrypted
                    </span>
                    <span class="sr-only">guard: web</span>
                </div>
            </div>

        </div>
    </div>
</div>