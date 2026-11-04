<div class="space-y-6">
    <!-- Staff Context Header -->
    @if ($staff)
        <div class="bg-white p-4 rounded-lg shadow-sm border border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-slate-900">{{ $staff->name }}</h2>
                <div class="flex items-center gap-2 text-xs text-slate-500 mt-0.5">
                    <span>{{ $staff->email }}</span>
                    <span>•</span>
                    <span class="font-mono bg-slate-100 px-1.5 py-0.5 rounded text-slate-700">Workspace: {{ $staff->workspace_id }}</span>
                    <span>•</span>
                    <span>Role: <strong class="text-slate-800">{{ $staff->roles->pluck('name')->implode(', ') ?: 'delegated_staff' }}</strong></span>
                </div>
            </div>
            <div>
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                    {{ count($assignedPermissions) }} Delegated Action{{ count($assignedPermissions) === 1 ? '' : 's' }}
                </span>
            </div>
        </div>
    @endif

    <!-- Feedback Alerts -->
    @if ($statusMessage)
        <div class="rounded-md bg-emerald-50 p-4 border border-emerald-200" role="alert">
            <div class="flex">
                <div class="shrink-0">
                    <svg class="h-5 w-5 text-emerald-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-emerald-800">{{ $statusMessage }}</p>
                </div>
            </div>
        </div>
    @endif

    @if ($errorMessage)
        <div class="rounded-md bg-rose-50 p-4 border border-rose-200" role="alert">
            <div class="flex">
                <div class="shrink-0">
                    <svg class="h-5 w-5 text-rose-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-rose-800">{{ $errorMessage }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Card 1: 8 Delegable Operational Actions -->
    <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 bg-slate-50/70 border-b border-slate-200 sm:flex sm:items-center sm:justify-between">
            <div>
                <h3 class="text-base font-semibold leading-6 text-slate-900">
                    Operational Actions (Delegable by Workspace Manager)
                </h3>
                <p class="mt-1 text-xs text-slate-500">
                    Per DISC-04 and DELEGATION-MATRIX.md, Workspace Managers may safely delegate these 8 specific operational actions to staff.
                </p>
            </div>
            <span class="mt-2 sm:mt-0 inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                8 Delegable Invariants
            </span>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($delegableActions as $action)
                    @php
                        $isGranted = in_array($action, $assignedPermissions, true);
                    @endphp
                    <div class="flex items-center justify-between p-3.5 rounded-lg border {{ $isGranted ? 'border-emerald-200 bg-emerald-50/30' : 'border-slate-200 bg-white' }} hover:border-slate-300 transition-colors">
                        <div class="pr-3">
                            <div class="text-sm font-medium text-slate-900 font-mono">{{ $action }}</div>
                            <div class="text-xs text-slate-500 mt-0.5">
                                @switch($action)
                                    @case('forms.create') Create and design new application forms @break
                                    @case('forms.edit') Modify form fields and instructional copy @break
                                    @case('members.edit') Update member address and contact records @break
                                    @case('members.export_csv') Export workspace members to CSV format @break
                                    @case('members.export_excel') Export workspace members to Excel format @break
                                    @case('invoices.export') Export invoice records and receipts @break
                                    @case('dashboard_reports.generate_reports') Compile custom lead & demographic reports @break
                                    @case('dashboard_reports.export_metrics') Export operational chart metrics @break
                                    @default Permitted operational action @break
                                @endswitch
                            </div>
                        </div>
                        <div class="shrink-0">
                            <button
                                type="button"
                                wire:click="togglePermission('{{ $action }}')"
                                wire:loading.attr="disabled"
                                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 {{ $isGranted ? 'bg-emerald-600' : 'bg-slate-200' }}"
                                role="switch"
                                aria-checked="{{ $isGranted ? 'true' : 'false' }}"
                                title="{{ $isGranted ? 'Click to revoke' : 'Click to delegate' }}"
                            >
                                <span class="sr-only">Toggle {{ $action }}</span>
                                <span
                                    aria-hidden="true"
                                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $isGranted ? 'translate-x-5' : 'translate-x-0' }}"
                                ></span>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Card 2: 16 Strictly Non-Delegable Managerial Actions -->
    <div class="bg-slate-50 rounded-lg shadow-sm border-2 border-amber-200 overflow-hidden">
        <div class="px-6 py-4 bg-amber-50/70 border-b border-amber-200 sm:flex sm:items-center sm:justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-700 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <div>
                    <h3 class="text-base font-semibold leading-6 text-slate-900">
                        Locked Managerial Actions (Strictly Non-Delegable)
                    </h3>
                    <p class="mt-0.5 text-xs text-amber-900">
                        Hardcoded security invariant (T-01-07, DEC-01, DEC-04). These 16 actions cannot be delegated to staff under any circumstances.
                    </p>
                </div>
            </div>
            <span class="mt-2 sm:mt-0 inline-flex items-center rounded-md bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-600/20">
                16 Immutable Locks
            </span>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach ($nonDelegableActions as $action)
                    <div class="flex items-center justify-between p-3 rounded-md border border-slate-200 bg-white/70 opacity-75 cursor-not-allowed" title="Security Invariant: Non-delegable to staff. Reserved for Workspace Manager and above.">
                        <div class="flex items-center gap-2 min-w-0 pr-2">
                            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <span class="text-xs font-mono font-medium text-slate-700 truncate">{{ $action }}</span>
                        </div>
                        <span class="shrink-0 inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 uppercase tracking-wide">
                            Locked
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
