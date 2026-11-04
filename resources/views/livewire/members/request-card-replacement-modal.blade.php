<div>
    @if ($isOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" id="card-replacement-modal">
            <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs"></div>
            <div class="flex min-h-full items-center justify-center p-4 sm:p-0">
                <div class="relative overflow-hidden rounded-2xl bg-white text-left shadow-xl sm:my-8 sm:w-full sm:max-w-lg border border-slate-200">
                    <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
                        <h3 class="text-base font-bold text-slate-900">Replace ID Card</h3>
                        <p class="text-xs text-slate-500 mt-0.5 font-mono">{{ $member->full_name }} • {{ $member->member_id }}</p>
                    </div>

                    <form wire:submit="submit" class="px-6 py-4 space-y-4">
                        <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800">
                            Replacing this card will immediately supersede the existing card and enqueue a new badge for PVC printing.
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-700" for="replacementReason">Reason</label>
                            <select id="replacementReason" wire:model="replacementReason" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                                <option value="">Select a reason…</option>
                                <option value="LOST_CARD">Lost Card</option>
                                <option value="DAMAGED_CARD">Damaged Card</option>
                                <option value="NAME_CHANGE">Information Update</option>
                                <option value="OTHER">Other</option>
                            </select>
                            @error('replacementReason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <label class="flex items-center gap-2 text-xs text-slate-700">
                            <input type="checkbox" wire:model.live="requiresFee" class="rounded border-slate-300" />
                            Charge replacement fee
                        </label>

                        @if ($requiresFee)
                            <div>
                                <label class="block text-xs font-medium text-slate-700" for="bkashTxId">bKash Transaction ID</label>
                                <input id="bkashTxId" type="text" wire:model="bkashTxId" maxlength="10" class="mt-1 block w-full rounded-lg border-slate-300 text-sm font-mono uppercase" />
                                @error('bkashTxId') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" wire:click="closeModal" class="px-3.5 py-2 text-xs font-medium rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="px-3.5 py-2 text-xs font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Replace Card</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
