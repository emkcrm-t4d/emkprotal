<div class="space-y-6">
    <div class="pb-4 border-b border-slate-200">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">ID Card Operations</h1>
        <p class="mt-1 text-sm text-slate-500">Batch PVC printing, pickup readiness and front desk delivery for {{ $workspace }}.</p>
    </div>

    @if (session('success'))
        <div class="rounded-xl bg-emerald-500/20 border border-emerald-400/40 px-4 py-3 text-sm text-emerald-100 shadow-md backdrop-blur-md" id="flash-success" role="alert">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="rounded-xl bg-rose-500/20 border border-rose-400/40 px-4 py-3 text-sm text-rose-100 shadow-md backdrop-blur-md" id="flash-error" role="alert">{{ session('error') }}</div>
    @endif

    <div class="flex flex-wrap gap-2" role="tablist">
        @foreach ([
            'printing_queue' => ['Queued for Printing', $counts['printing_queue']],
            'ready_for_pickup' => ['Ready for Collection', $counts['ready_for_pickup']],
            'delivered' => ['Delivered Cards', null],
            'all' => ['All Cards', null],
        ] as $key => [$label, $badge])
            <button type="button" wire:click="setTab('{{ $key }}')" id="tab-{{ $key }}"
                class="px-3 py-2 rounded-lg text-xs font-semibold border {{ $activeTab === $key ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">
                {{ $label }}
                @if ($badge !== null)
                    <span class="ml-1 inline-flex px-1.5 rounded-full bg-slate-100 text-slate-800">{{ $badge }}</span>
                @endif
            </button>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search card number, member ID or name"
            class="w-72 rounded-lg border border-slate-300 px-3 py-2 text-sm" id="input-search">

        @if ($activeTab === 'printing_queue')
            <input type="text" wire:model="batchReference" placeholder="Batch reference"
                class="w-48 rounded-lg border border-slate-300 px-3 py-2 text-sm" id="input-batch-reference">
            <button type="button" wire:click="exportBatchForPrinting" id="btn-export-batch"
                class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold">Export PVC Print Batch</button>
            <button type="button" wire:click="markSelectedAsPrinted" id="btn-mark-printed"
                class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold">Mark Batch as Printed</button>
        @endif
    </div>

    <div class="overflow-x-auto bg-white border border-slate-200 rounded-xl">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2"></th>
                    <th class="px-3 py-2">Card Number</th>
                    <th class="px-3 py-2">Member ID</th>
                    <th class="px-3 py-2">Full Name</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Photo</th>
                    <th class="px-3 py-2">Printed At</th>
                    <th class="px-3 py-2">Delivered At</th>
                    <th class="px-3 py-2">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($cards as $card)
                    <tr wire:key="card-{{ $card->id }}">
                        <td class="px-3 py-2">
                            @if ($card->status === 'provided_for_printing')
                                <input type="checkbox" wire:model="selectedCardIds" value="{{ $card->id }}">
                            @endif
                        </td>
                        <td class="px-3 py-2 font-mono">{{ $card->card_number }}</td>
                        <td class="px-3 py-2">{{ $card->member?->member_id }}</td>
                        <td class="px-3 py-2">{{ $card->member?->full_name }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-800">{{ str_replace('_', ' ', $card->status) }}</span>
                        </td>
                        <td class="px-3 py-2">
                            @if ($card->member)
                                <a href="/crm/{{ $workspace }}/members/{{ $card->member->uuid }}" class="text-indigo-600 hover:underline">View</a>
                            @endif
                        </td>
                        <td class="px-3 py-2">{{ $card->printed_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-2">{{ $card->delivered_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-2">
                            @if ($card->status === 'card_printed_ready_for_pickup')
                                <button type="button" wire:click="confirmDelivery({{ $card->id }})"
                                    class="px-2.5 py-1 bg-indigo-600 text-white rounded text-xs font-semibold">Mark Delivered</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-3 py-6 text-center text-slate-500">No cards found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $cards->links() }}

    @if ($confirmDeliveryCardId)
        <div class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50" id="delivery-modal">
            <div class="bg-white rounded-xl p-6 w-full max-w-md space-y-4">
                <h2 class="text-lg font-semibold text-slate-900">Confirm card collection</h2>
                <p class="text-sm text-slate-600">Confirm that the member's identity has been verified and the physical card has been handed over.</p>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="cancelDelivery" class="px-3 py-2 border border-slate-300 rounded-lg text-xs">Cancel</button>
                    <button type="button" wire:click="deliverCard({{ $confirmDeliveryCardId }})" id="btn-confirm-delivery"
                        class="px-3 py-2 bg-indigo-600 text-white rounded-lg text-xs font-semibold">Confirm Delivery</button>
                </div>
            </div>
        </div>
    @endif
</div>
