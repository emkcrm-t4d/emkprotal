<div>
    @if ($isOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" id="member-status-modal">
            <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs"></div>
            <div class="flex min-h-full items-center justify-center p-4 sm:p-0">
                <div class="relative overflow-hidden rounded-2xl bg-white text-left shadow-xl sm:my-8 sm:w-full sm:max-w-lg border border-slate-200">
                    <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
                        <h3 class="text-base font-bold text-slate-900">Change Member Status</h3>
                        <p class="text-xs text-slate-500 mt-0.5 font-mono">{{ $member->full_name }} • {{ $member->member_id }} • current: {{ $member->status }}</p>
                    </div>

                    <form wire:submit="submit" class="px-6 py-4 space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-700" for="targetStatus">Target status</label>
                            <select id="targetStatus" wire:model.live="targetStatus" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                                <option value="restricted">Restricted</option>
                                <option value="revoked">Revoked</option>
                                <option value="active">Active (reinstate)</option>
                            </select>
                            @error('targetStatus') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        @if ($targetStatus === 'revoked')
                            <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800">
                                Revocation is permanent and disables all portal and facility access.
                            </div>
                        @endif

                        <div>
                            <label class="block text-xs font-medium text-slate-700" for="directiveRef">Management directive reference</label>
                            <input id="directiveRef" type="text" wire:model="directiveRef" placeholder="e.g., DIR-2026-EMK-042" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                            @error('directiveRef') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-700" for="rationale">Rationale</label>
                            <textarea id="rationale" rows="4" wire:model.live="rationale" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></textarea>
                            <p class="mt-1 text-xs {{ strlen($rationale) >= 20 ? 'text-emerald-600' : 'text-slate-500' }}">{{ strlen($rationale) }} / 20 minimum characters</p>
                            @error('rationale') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" wire:click="closeModal" class="px-3.5 py-2 text-xs font-medium rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="px-3.5 py-2 text-xs font-medium rounded-lg bg-rose-600 text-white hover:bg-rose-700">Submit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
