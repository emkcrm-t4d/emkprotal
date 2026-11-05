<div class="w-full max-w-md mx-auto">
    <div class="bg-white shadow-xl shadow-slate-200/50 rounded-2xl p-6 sm:p-8 border border-slate-200/80">
        <!-- Card Header -->
        <div class="text-center mb-6">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-indigo-600 to-blue-500 text-white flex items-center justify-center mx-auto mb-3.5 shadow-md shadow-indigo-500/20">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Staff &amp; Admin Sign In</h2>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">
                Enter your administrative credentials to access the CRM console
            </p>
        </div>

        <!-- Credentials Helper Box (Local / Dev) -->
        <div class="mb-6 p-3.5 rounded-xl bg-slate-50 border border-slate-200/90 text-xs">
            <div class="flex items-center justify-between mb-1.5 font-semibold text-slate-700">
                <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                    Default Credentials
                </span>
                <span class="text-[10px] uppercase font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">Active</span>
            </div>
            <div class="space-y-1 text-slate-600 font-mono text-[11px]">
                <div class="flex justify-between items-center py-0.5">
                    <span>Admin: <strong class="text-slate-800">admin@emk.test</strong></span>
                    <span class="text-slate-400">secret123</span>
                </div>
                <div class="flex justify-between items-center py-0.5 border-t border-slate-200/60 pt-1">
                    <span>Manager: <strong class="text-slate-800">manager.dhaka@emk.test</strong></span>
                    <span class="text-slate-400">secret123</span>
                </div>
            </div>
        </div>

        <!-- Login Form -->
        <form wire:submit="login" class="space-y-4">
            <!-- Email Field -->
            <div>
                <label for="email" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                    Email Address
                </label>
                <div class="relative">
                    <input
                        type="email"
                        id="email"
                        wire:model="email"
                        autocomplete="username"
                        placeholder="admin@emk.test"
                        class="w-full px-3.5 py-2.5 rounded-xl border @error('email') border-rose-300 focus:border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:border-indigo-600 focus:ring-indigo-600 @enderror text-sm shadow-xs transition-colors placeholder:text-slate-400"
                        required
                        autofocus
                    >
                </div>
                @error('email')
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
                <div class="flex items-center justify-between mb-1">
                    <label for="password" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">
                        Password
                    </label>
                </div>
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

            <!-- Remember Me & Forgot Password -->
            <div class="flex items-center justify-between pt-1">
                <label for="remember" class="flex items-center gap-2 cursor-pointer">
                    <input
                        type="checkbox"
                        id="remember"
                        wire:model="remember"
                        class="w-4 h-4 text-indigo-600 rounded-md border-slate-300 focus:ring-indigo-500"
                    >
                    <span class="text-xs text-slate-600">Remember session</span>
                </label>
            </div>

            <!-- Submit Button -->
            <div class="pt-2">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm shadow-md shadow-indigo-600/20 transition-all cursor-pointer disabled:opacity-50"
                >
                    <span wire:loading.remove>Sign In to Dashboard &rarr;</span>
                    <span wire:loading class="inline-flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Authenticating...</span>
                    </span>
                </button>
            </div>
        </form>

        <!-- Portal Link Footer -->
        <div class="mt-6 pt-5 border-t border-slate-100 text-center">
            <p class="text-xs text-slate-500">
                Looking for the public Membership Application?
                <a href="{{ route('public.forms.show', 'membership-application') }}" class="font-semibold text-indigo-600 hover:text-indigo-800 transition-colors ml-1">
                    Apply Online &rarr;
                </a>
            </p>
        </div>
    </div>
</div>
