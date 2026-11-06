<div class="space-y-6">
    <!-- Header Section -->
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white">Workspace Staff Directory</h1>
            <span class="sr-only">{{ $workspace }}</span>
        </div>
        <div class="mt-4 sm:mt-0 flex gap-3">
            <span class="inline-flex items-center rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-700/10">
                Staff Count: {{ $staffUsers->count() }}
            </span>
        </div>
    </div>

    <!-- Feedback Alerts -->
    @if ($statusMessage)
        <div class="rounded-xl bg-emerald-500/20 p-4 border border-emerald-400/40 shadow-lg backdrop-blur-md" role="alert">
            <div class="flex items-center">
                <div class="shrink-0">
                    <svg class="h-5 w-5 text-emerald-300" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-semibold text-emerald-100">{{ $statusMessage }}</p>
                </div>
            </div>
        </div>
    @endif

    @if ($errorMessage)
        <div class="rounded-xl bg-rose-500/20 p-4 border border-rose-400/40 shadow-lg backdrop-blur-md" role="alert">
            <div class="flex items-center">
                <div class="shrink-0">
                    <svg class="h-5 w-5 text-rose-300" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-semibold text-rose-100">{{ $errorMessage }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Staff Members Table -->
    <div class="bg-white shadow-sm ring-1 ring-slate-900/5 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-slate-700">
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 sm:pl-6 font-semibold">Staff Member</th>
                        <th scope="col" class="px-3 py-3.5 font-semibold">Workspace</th>
                        <th scope="col" class="px-3 py-3.5 font-semibold">Assigned Roles</th>
                        <th scope="col" class="px-3 py-3.5 font-semibold">Delegated Permissions</th>
                        <th scope="col" class="px-3 py-3.5 font-semibold">Status</th>
                        <th scope="col" class="px-3 py-3.5 font-semibold">Security Status</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($staffUsers as $user)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 sm:pl-6">
                                <div class="font-medium text-slate-900">{{ $user->name }}</div>
                                <div class="text-xs text-slate-500">{{ $user->email }}</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-xs font-mono text-slate-600">
                                {{ $user->workspace_id ?? 'Global' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($user->roles as $role)
                                        <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium {{ $role->name === 'workspace_manager' ? 'bg-amber-100 text-amber-800' : 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-700/10' }}">
                                            {{ $role->name }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-slate-400 italic">No roles</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-xs text-slate-600">
                                @php
                                    $directCount = $user->permissions->count();
                                @endphp
                                @if ($directCount > 0)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                        {{ $directCount }} Delegated
                                    </span>
                                @else
                                    <span class="text-slate-400">Default only</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-4">
                                @if ($user->is_active)
                                    <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-md bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/20">
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-xs">
                                @if ($user->must_reset_password)
                                    <span class="inline-flex items-center gap-1 rounded bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-600/20" title="User must reset password on next login per DEC-06">
                                        <svg class="w-3.5 h-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        Reset Required
                                    </span>
                                @else
                                    <span class="text-slate-500">Verified</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap py-4 pl-3 pr-4 sm:pr-6 text-right text-sm space-x-2">
                                <button
                                    type="button"
                                    wire:click="openDelegation({{ $user->id }})"
                                    class="inline-flex items-center gap-1 rounded bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50"
                                >
                                    <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                    </svg>
                                    Delegation
                                </button>
                                <button
                                    type="button"
                                    wire:click="toggleActivation({{ $user->id }})"
                                    class="inline-flex items-center rounded px-2.5 py-1.5 text-xs font-semibold {{ $user->is_active ? 'text-rose-700 bg-rose-50 hover:bg-rose-100 ring-1 ring-inset ring-rose-600/10' : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100 ring-1 ring-inset ring-emerald-600/10' }}"
                                >
                                    {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-sm text-slate-500">
                                No staff members found for workspace <span class="font-mono">{{ $workspace }}</span>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Role Delegation Modal -->
    @if ($showDelegationModal && $selectedStaffId)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="fixed inset-0 bg-slate-500/75 transition-opacity" wire:click="closeDelegation"></div>

                <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl p-6">
                    <div class="flex justify-between items-center pb-4 border-b border-slate-200">
                        <h3 class="text-lg font-semibold leading-6 text-slate-900" id="modal-title">
                            Staff Role Delegation Interface
                        </h3>
                        <button type="button" wire:click="closeDelegation" class="text-slate-400 hover:text-slate-500">
                            <span class="sr-only">Close</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="mt-4">
                        <livewire:staff.role-delegation :staffId="$selectedStaffId" :key="'delegation-modal-'.$selectedStaffId" />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button
                            type="button"
                            wire:click="closeDelegation"
                            class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50"
                        >
                            Done
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
