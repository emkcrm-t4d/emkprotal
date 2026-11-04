<div class="space-y-6">
    <!-- Header & Breadcrumbs -->
    <div class="sm:flex sm:items-center sm:justify-between pb-4 border-b border-slate-200">
        <div>
            <nav class="flex items-center gap-2 text-xs text-slate-500 mb-2">
                <a href="/crm/{{ $workspace }}/members" class="hover:text-indigo-600 transition-colors">CRM</a>
                <span>/</span>
                <span class="text-slate-500">Settings</span>
                <span>/</span>
                <span class="text-slate-900 font-semibold">Membership Terms</span>
            </nav>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Membership Terms & Fees</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-mono font-medium bg-slate-100 text-slate-800 border border-slate-200">
                    {{ $workspace }}
                </span>
            </div>
            <p class="mt-1 text-sm text-slate-500">
                Configure workspace validity durations, membership fee structures, and renewal pricing per DEC-08.
            </p>
        </div>

        <div class="mt-4 sm:mt-0 flex items-center gap-3">
            <button
                type="button"
                wire:click="openCreateModal"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-xs transition-colors"
                id="btn-add-term"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add New Term
            </button>
        </div>
    </div>

    <!-- Flash Status Messages -->
    @if (session()->has('status'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between" id="alert-status">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        </div>
    @endif

    <!-- Toolbar: Search filter -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
        <div class="w-full max-w-sm">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search terms by name or code..."
                    class="block w-full pl-9 pr-3 py-2 border border-slate-300 rounded-lg text-sm placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    id="search-terms"
                >
            </div>
        </div>
        <div class="text-xs text-slate-500">
            Showing <span class="font-semibold text-slate-700">{{ $terms->count() }}</span> term(s)
        </div>
    </div>

    <!-- Terms Data Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-6 py-3.5 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                            Term Name
                        </th>
                        <th scope="col" class="px-6 py-3.5 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                            Code
                        </th>
                        <th scope="col" class="px-6 py-3.5 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                            Duration
                        </th>
                        <th scope="col" class="px-6 py-3.5 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                            Fee (BDT)
                        </th>
                        <th scope="col" class="px-6 py-3.5 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3.5 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                            Usage Count
                        </th>
                        <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white" id="terms-table-body">
                    @forelse ($terms as $term)
                        <tr class="hover:bg-slate-50/75 transition-colors" id="term-row-{{ $term->id }}">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-slate-900">{{ $term->name }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <code class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-xs font-mono border border-slate-200">
                                    {{ $term->code }}
                                </code>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-slate-700">
                                {{ $term->duration_months }} {{ $term->duration_months === 1 ? 'Month' : 'Months' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-slate-900">
                                ৳{{ number_format((float) $term->fee_bdt, 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if ($term->is_active)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-slate-600">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">
                                    {{ $term->members_count }} members
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                <button
                                    type="button"
                                    wire:click="editTerm({{ $term->id }})"
                                    class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-md transition-colors"
                                    id="btn-edit-term-{{ $term->id }}"
                                >
                                    Edit
                                </button>
                                <button
                                    type="button"
                                    wire:click="toggleActive({{ $term->id }})"
                                    class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-md transition-colors {{ $term->is_active ? 'text-amber-700 bg-amber-50 hover:bg-amber-100' : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100' }}"
                                    id="btn-toggle-term-{{ $term->id }}"
                                >
                                    {{ $term->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-500">
                                <svg class="mx-auto h-10 w-10 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                <p class="text-base font-medium text-slate-800">No membership terms configured</p>
                                <p class="text-xs text-slate-500 mt-1">Add a term to define validity duration and fee for applicants and renewals.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Dialog: Create / Edit Term -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" id="term-modal">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs transition-opacity" wire:click="closeModal" aria-hidden="true"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-200">
                    <form wire:submit="saveTerm">
                        <div class="bg-white px-6 pt-6 pb-4">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                <h3 class="text-lg font-bold text-slate-900" id="modal-title">
                                    {{ $editingTermId ? 'Edit Membership Term' : 'Add New Membership Term' }}
                                </h3>
                                <button type="button" wire:click="closeModal" class="text-slate-400 hover:text-slate-600 transition-colors">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            @if ($isLocked)
                                <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg flex items-start gap-2 text-xs text-amber-800">
                                    <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <div>
                                        <span class="font-semibold">Term Fields Locked:</span> This term is currently assigned to existing members. The unique code and validity duration cannot be modified to protect historical member expiry calculations.
                                    </div>
                                </div>
                            @endif

                            <div class="mt-4 space-y-4">
                                <!-- Term Name -->
                                <div>
                                    <label for="term-name" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                        Term Name <span class="text-rose-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="term-name"
                                        wire:model="name"
                                        placeholder="e.g. Standard 1-Year Membership"
                                        class="block w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    >
                                    @error('name')
                                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Term Code -->
                                <div>
                                    <label for="term-code" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                        Code (Unique Identifier) <span class="text-rose-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="term-code"
                                        wire:model="code"
                                        placeholder="e.g. term_standard_1yr"
                                        @disabled($isLocked)
                                        class="block w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @if($isLocked) bg-slate-100 text-slate-500 cursor-not-allowed @endif"
                                    >
                                    <p class="mt-0.5 text-xs text-slate-400">Lowercase letters, numbers, and underscores only.</p>
                                    @error('code')
                                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <!-- Duration Months -->
                                    <div>
                                        <label for="term-duration" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                            Duration (Months) <span class="text-rose-500">*</span>
                                        </label>
                                        <input
                                            type="number"
                                            id="term-duration"
                                            wire:model="durationMonths"
                                            placeholder="12"
                                            min="1"
                                            max="60"
                                            @disabled($isLocked)
                                            class="block w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @if($isLocked) bg-slate-100 text-slate-500 cursor-not-allowed @endif"
                                        >
                                        @error('durationMonths')
                                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Fee BDT -->
                                    <div>
                                        <label for="term-fee" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                            Fee (BDT) <span class="text-rose-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 text-sm">৳</span>
                                            <input
                                                type="number"
                                                id="term-fee"
                                                wire:model="feeBdt"
                                                placeholder="1000.00"
                                                step="0.01"
                                                min="0"
                                                class="block w-full pl-7 pr-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                            >
                                        </div>
                                        @error('feeBdt')
                                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Is Active Toggle -->
                                <div class="pt-2">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            wire:model="isActive"
                                            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                            id="checkbox-is-active"
                                        >
                                        <span class="text-sm font-medium text-slate-700">Active (available for new applications and renewals)</span>
                                    </label>
                                    @error('isActive')
                                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Modal Actions Footer -->
                        <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-3 border-t border-slate-100">
                            <button
                                type="button"
                                wire:click="closeModal"
                                class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-100 transition-colors"
                                id="btn-cancel-modal"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-xs transition-colors"
                                id="btn-save-term"
                            >
                                {{ $editingTermId ? 'Update Term' : 'Save Term' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
