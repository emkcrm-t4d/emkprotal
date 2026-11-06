<div class="max-w-md mx-auto my-6 sm:my-10">
    <div class="relative bg-white shadow-md rounded-2xl p-6 sm:p-8 border border-slate-200 overflow-hidden">
        <!-- Top Laser Indicator during Authentication -->
        <div wire:loading wire:target="updatePassword" class="absolute top-0 inset-x-0 h-1 overflow-hidden z-30 pointer-events-none">
            <div class="w-full h-full bg-gradient-to-r from-amber-500 via-amber-200 to-amber-500 animate-shimmer shadow-[0_0_12px_rgba(245,158,11,0.9)]"></div>
        </div>

        <!-- Security Header Banner -->
        <div class="text-center mb-6">
            <div class="w-12 h-12 rounded-2xl bg-amber-50 border border-amber-200 text-amber-700 flex items-center justify-center mx-auto mb-3 shadow-xs">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full">
                Security Requirement
            </span>
            <h2 class="text-2xl font-bold text-slate-900 tracking-tight mt-2">First Login: Create Your Permanent Password</h2>
            <p class="text-xs sm:text-sm text-slate-500 mt-1 leading-relaxed">
                To protect your membership account, please choose a strong permanent password before accessing your portal.
            </p>
        </div>

        <!-- Password Reset Form -->
        <form wire:submit="updatePassword" class="space-y-4">
            @if ($requiresCurrentPassword)
                <!-- Current Password Field -->
                <div>
                    <label for="current_password" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                        Current / Temporary Password <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <input
                            type="password"
                            id="current_password"
                            wire:model="current_password"
                            autocomplete="current-password"
                            placeholder="Enter current password"
                            aria-required="true"
                            @error('current_password') aria-invalid="true" aria-describedby="current_password-error" @enderror
                            class="w-full px-3.5 py-2.5 rounded-xl border @error('current_password') border-rose-300 focus:border-rose-500 focus:ring-2 focus:ring-rose-500 @else border-slate-300 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600 @enderror text-sm shadow-xs transition-colors placeholder:text-slate-400"
                            required
                        >
                    </div>
                    @error('current_password')
                        <p id="current_password-error" class="mt-1.5 text-xs text-rose-600 font-medium flex items-center gap-1" role="alert">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>
            @endif

            <!-- New Permanent Password Field -->
            <div>
                <label for="password" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                    New Permanent Password <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <input
                        type="password"
                        id="password"
                        wire:model="password"
                        autocomplete="new-password"
                        placeholder="At least 8 characters"
                        aria-required="true"
                        aria-describedby="password-rules @error('password') password-error @enderror"
                        @error('password') aria-invalid="true" @enderror
                        class="w-full px-3.5 py-2.5 rounded-xl border @error('password') border-rose-300 focus:border-rose-500 focus:ring-2 focus:ring-rose-500 @else border-slate-300 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600 @enderror text-sm shadow-xs transition-colors placeholder:text-slate-400"
                        required
                    >
                </div>
                @error('password')
                    <p id="password-error" class="mt-1.5 text-xs text-rose-600 font-medium flex items-center gap-1" role="alert">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <span>{{ $message }}</span>
                    </p>
                @enderror
            </div>

            <!-- Confirm Permanent Password Field -->
            <div>
                <label for="password_confirmation" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                    Confirm Permanent Password <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <input
                        type="password"
                        id="password_confirmation"
                        wire:model="password_confirmation"
                        autocomplete="new-password"
                        placeholder="Re-type new password"
                        aria-required="true"
                        aria-describedby="password-rules"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600 text-sm shadow-xs transition-colors placeholder:text-slate-400"
                        required
                    >
                </div>
            </div>

            <!-- Password Requirements Guidance -->
            <div id="password-rules" role="region" aria-label="Password Requirements" class="bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs text-slate-600 space-y-1">
                <span class="font-semibold text-slate-700">Password Checklist:</span>
                <ul class="list-disc list-inside space-y-0.5 text-slate-600 text-[11px]">
                    <li>Minimum 8 characters</li>
                    <li>Different from your temporary password</li>
                    <li>Mix of letters, numbers, and symbols recommended</li>
                </ul>
            </div>

            <!-- Submit Action Button -->
            <div class="pt-2">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="updatePassword"
                    aria-label="Save Password and Access Portal"
                    class="group relative w-full min-h-[48px] overflow-hidden rounded-full bg-gradient-to-r from-amber-400 via-amber-300 to-amber-500 hover:from-amber-300 hover:to-amber-400 active:scale-[0.99] text-slate-950 font-black text-sm uppercase tracking-wider shadow-lg shadow-amber-500/30 hover:shadow-amber-500/50 transition-all duration-300 transform hover:-translate-y-0.5 active:translate-y-0 cursor-pointer disabled:cursor-wait disabled:hover:translate-y-0 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2"
                >
                    <!-- Liquid Light Shimmer Sweep across the button -->
                    <div wire:loading wire:target="updatePassword" class="absolute inset-0 bg-gradient-to-r from-transparent via-white/45 to-transparent -translate-x-full animate-shimmer pointer-events-none"></div>

                    <!-- Default Ready State -->
                    <span wire:loading.remove wire:target="updatePassword" class="relative z-10 flex items-center justify-center gap-2.5 font-black tracking-wider">
                        <span>Save Password &amp; Access Portal</span>
                        <svg class="w-4 h-4 transition-transform duration-200 group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </span>

                    <!-- Authenticating Loading State -->
                    <span wire:loading wire:target="updatePassword" class="relative z-10 inline-flex items-center justify-center gap-3">
                        <svg class="animate-spin w-5 h-5 text-slate-950 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3.5"></circle>
                            <path class="opacity-95" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="tracking-widest uppercase font-black text-xs sm:text-sm text-slate-950 select-none">Authenticating</span>
                        <span class="inline-flex items-center gap-1 ml-0.5" aria-hidden="true">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-950 animate-bounce" style="animation-duration: 0.8s; animation-delay: 0ms;"></span>
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-950 animate-bounce" style="animation-duration: 0.8s; animation-delay: 150ms;"></span>
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-950 animate-bounce" style="animation-duration: 0.8s; animation-delay: 300ms;"></span>
                        </span>
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
