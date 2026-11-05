<div class="max-w-md mx-auto my-6 sm:my-10">
    <div class="bg-white shadow-md rounded-2xl p-6 sm:p-8 border border-slate-200">
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
                    aria-label="Save Password and Access Portal"
                    class="w-full py-2.5 px-4 min-h-[44px] rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 disabled:opacity-60 shadow-sm transition-colors flex items-center justify-center gap-2 cursor-pointer focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    <span wire:loading.remove>Save Password & Access Portal</span>
                    <span wire:loading class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Updating Password...</span>
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
