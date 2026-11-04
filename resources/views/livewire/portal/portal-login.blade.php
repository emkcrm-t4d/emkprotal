<div class="max-w-md mx-auto my-6 sm:my-10">
    <div class="bg-white shadow-md rounded-2xl p-6 sm:p-8 border border-slate-200">
        <!-- Card Header -->
        <div class="text-center mb-6">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 border border-indigo-100 text-indigo-700 flex items-center justify-center mx-auto mb-3 shadow-xs">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Member Portal Login</h2>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">
                Enter your registered Email address or Member ID (e.g. <span class="font-mono font-medium text-slate-700">EMK-2026-00001</span>)
            </p>
        </div>

        <!-- Login Form -->
        <form wire:submit="login" class="space-y-4">
            <!-- Identifier Field -->
            <div>
                <label for="identifier" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                    Email or Member ID
                </label>
                <div class="relative">
                    <input
                        type="text"
                        id="identifier"
                        wire:model="identifier"
                        autocomplete="username"
                        placeholder="e.g. EMK-2026-00001 or name@example.com"
                        class="w-full px-3.5 py-2.5 rounded-xl border @error('identifier') border-rose-300 focus:border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:border-indigo-600 focus:ring-indigo-600 @enderror text-sm shadow-xs transition-colors placeholder:text-slate-400"
                        required
                        autofocus
                    >
                </div>
                @error('identifier')
                    <p class="mt-1.5 text-xs text-rose-600 font-medium flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <span>{{ $message }}</span>
                    </p>
                @enderror
            </div>

            <!-- Password Field -->
            <div>
                <label for="password" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                    Password
                </label>
                <div class="relative">
                    <input
                        type="password"
                        id="password"
                        wire:model="password"
                        autocomplete="current-password"
                        placeholder="••••••••••••"
                        class="w-full px-3.5 py-2.5 rounded-xl border @error('password') border-rose-300 focus:border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:border-indigo-600 focus:ring-indigo-600 @enderror text-sm shadow-xs transition-colors placeholder:text-slate-400"
                        required
                    >
                </div>
                @error('password')
                    <p class="mt-1.5 text-xs text-rose-600 font-medium flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <span>{{ $message }}</span>
                    </p>
                @enderror
            </div>

            <!-- Remember Me Checkbox -->
            <div class="flex items-center justify-between pt-1">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input
                        type="checkbox"
                        wire:model="remember"
                        class="w-4 h-4 rounded text-indigo-600 border-slate-300 focus:ring-indigo-500"
                    >
                    <span class="text-xs text-slate-600">Remember this device</span>
                </label>
            </div>

            <!-- Submit Action Button -->
            <div class="pt-2">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="w-full py-2.5 px-4 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 disabled:opacity-60 shadow-sm transition-colors flex items-center justify-center gap-2 cursor-pointer"
                >
                    <span wire:loading.remove>Sign In to Member Portal</span>
                    <span wire:loading class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Authenticating...</span>
                    </span>
                </button>
            </div>
        </form>

        <!-- Help Notice -->
        <div class="mt-6 pt-5 border-t border-slate-100 text-center">
            <p class="text-xs text-slate-500 leading-relaxed">
                Need help accessing your account or first time logging in? Contact the front desk at
                <a href="mailto:membership@emk.com.bd" class="text-indigo-600 font-medium hover:underline">membership@emk.com.bd</a>
            </p>
        </div>
    </div>
</div>
