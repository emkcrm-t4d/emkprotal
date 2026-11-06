<div x-data="{ showPass: false, showConfirmPass: false }" class="w-full max-w-xl mx-auto my-8">
    <div class="relative rounded-3xl bg-slate-950/85 backdrop-blur-3xl border border-white/20 shadow-2xl shadow-black/80 p-8 sm:p-10 text-white">
        
        <!-- Top Laser Shimmer during update -->
        <div wire:loading wire:target="resetPassword" class="absolute top-0 inset-x-0 h-1 overflow-hidden z-30 pointer-events-none rounded-t-3xl">
            <div class="w-full h-full bg-gradient-to-r from-amber-500 via-amber-200 to-amber-500 animate-shimmer shadow-[0_0_12px_rgba(245,158,11,0.9)]"></div>
        </div>

        <div class="flex items-center gap-2 mb-3">
            <span class="text-[10px] sm:text-xs font-black uppercase tracking-widest text-amber-400 bg-amber-400/10 px-3 py-1 rounded-full border border-amber-400/25">
                Security &amp; Account Recovery
            </span>
        </div>

        <h2 class="text-2xl sm:text-3xl font-black text-amber-400 tracking-tight leading-tight mb-2">
            Reset Account Password
        </h2>
        <p class="text-xs sm:text-sm text-slate-300 mb-6">
            Enter your registered email and choose a secure new password for your EMK Center account.
        </p>

        @if ($isSuccess)
            <div class="p-5 rounded-2xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-200 text-sm space-y-4">
                <div class="flex items-center gap-2.5 font-bold text-emerald-400">
                    <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Password Reset Successful!</span>
                </div>
                <p class="text-xs text-emerald-100/90 leading-relaxed">
                    {{ $statusMessage }}
                </p>
                <div class="pt-2 flex items-center gap-3">
                    <a href="{{ route('login') }}" class="px-5 py-2.5 rounded-full bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 text-slate-950 font-black text-xs uppercase tracking-wider shadow-md">
                        Sign In Now &rarr;
                    </a>
                    <a href="{{ route('portal.login') }}" class="px-4 py-2.5 rounded-full bg-white/10 hover:bg-white/15 text-slate-200 font-semibold text-xs">
                        Member Portal Login
                    </a>
                </div>
            </div>
        @else
            <form wire:submit="resetPassword" class="space-y-5">
                <!-- Registered Email Field -->
                <div>
                    <label for="email" class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-2">
                        Registered Email Address
                    </label>
                    <input
                        type="email"
                        id="email"
                        wire:model="email"
                        class="w-full px-4 py-3.5 rounded-full bg-slate-900/80 border @error('email') border-rose-400 focus:border-rose-400 @else border-white/20 focus:border-amber-400 @enderror text-white text-sm outline-none transition"
                        placeholder="your.email@example.com"
                        required
                    >
                    @error('email')
                        <p class="mt-1.5 text-xs text-rose-400 font-medium pl-4">{{ $message }}</p>
                    @enderror
                </div>

                <!-- New Password Field -->
                <div>
                    <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-2">
                        New Password (Min 8 Characters)
                    </label>
                    <div class="relative">
                        <input
                            :type="showPass ? 'text' : 'password'"
                            id="password"
                            wire:model="password"
                            class="w-full pl-4 pr-12 py-3.5 rounded-full bg-slate-900/80 border @error('password') border-rose-400 focus:border-rose-400 @else border-white/20 focus:border-amber-400 @enderror text-white text-sm outline-none transition"
                            placeholder="••••••••••••"
                            required
                        >
                        <button
                            type="button"
                            @click="showPass = !showPass"
                            class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-amber-400 transition"
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
                        <p class="mt-1.5 text-xs text-rose-400 font-medium pl-4">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Confirm Password Field -->
                <div>
                    <label for="password_confirmation" class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-2">
                        Confirm New Password
                    </label>
                    <div class="relative">
                        <input
                            :type="showConfirmPass ? 'text' : 'password'"
                            id="password_confirmation"
                            wire:model="password_confirmation"
                            class="w-full pl-4 pr-12 py-3.5 rounded-full bg-slate-900/80 border border-white/20 focus:border-amber-400 text-white text-sm outline-none transition"
                            placeholder="••••••••••••"
                            required
                        >
                        <button
                            type="button"
                            @click="showConfirmPass = !showConfirmPass"
                            class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-amber-400 transition"
                        >
                            <template x-if="!showConfirmPass">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </template>
                            <template x-if="showConfirmPass">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                </svg>
                            </template>
                        </button>
                    </div>
                </div>

                <div class="pt-3">
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="resetPassword"
                        class="group relative w-full min-h-[50px] overflow-hidden rounded-full bg-gradient-to-r from-amber-400 via-amber-300 to-amber-500 hover:from-amber-300 hover:to-amber-400 active:scale-[0.99] text-slate-950 font-black text-sm uppercase tracking-wider shadow-lg shadow-amber-500/30 transition duration-300 cursor-pointer"
                    >
                        <span wire:loading.remove wire:target="resetPassword">
                            Update &amp; Set New Password
                        </span>
                        <span wire:loading wire:target="resetPassword" class="inline-flex items-center justify-center gap-2">
                            <svg class="animate-spin w-5 h-5 text-slate-950 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Updating Password...</span>
                        </span>
                    </button>
                </div>

                <div class="text-center pt-2">
                    <a href="{{ route('login') }}" class="text-xs text-amber-300 hover:text-amber-200 transition">
                        &larr; Back to Sign In
                    </a>
                </div>
            </form>
        @endif
    </div>
</div>

