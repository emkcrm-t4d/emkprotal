<div class="space-y-6">
    <!-- Header Section -->
    <div class="sm:flex sm:items-center sm:justify-between pb-4 border-b border-slate-200">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Audit Logs</h1>
            <p class="mt-1 text-sm text-slate-500">
                Immutable, append-only forensic activity trail scoped to workspace:
                <span class="font-semibold text-slate-700">{{ $workspace ?: 'All Workspaces' }}</span>
            </p>
        </div>
        <div class="mt-4 sm:mt-0 flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                Append-Only Verified
            </span>
        </div>
    </div>

    <!-- Filter Bar Card -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <!-- Search Input -->
            <div class="lg:col-span-2">
                <label for="search" class="block text-xs font-medium text-slate-600 mb-1">Search Audit Trail</label>
                <div class="relative">
                    <input
                        type="text"
                        id="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search entity, actor, email, reason..."
                        class="w-full text-sm rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 pl-9"
                    />
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Event Type Selector -->
            <div>
                <label for="eventType" class="block text-xs font-medium text-slate-600 mb-1">Event Type</label>
                <select
                    id="eventType"
                    wire:model.live="eventType"
                    class="w-full text-sm rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">All Event Types</option>
                    @foreach($eventTypes as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Date Range From -->
            <div>
                <label for="dateFrom" class="block text-xs font-medium text-slate-600 mb-1">From Date</label>
                <input
                    type="date"
                    id="dateFrom"
                    wire:model.live="dateFrom"
                    class="w-full text-sm rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            <!-- Date Range To -->
            <div>
                <label for="dateTo" class="block text-xs font-medium text-slate-600 mb-1">To Date</label>
                <input
                    type="date"
                    id="dateTo"
                    wire:model.live="dateTo"
                    class="w-full text-sm rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
        </div>

        @if($isSuperadmin)
            <!-- Superadmin Workspace Scope Filter -->
            <div class="pt-3 border-t border-slate-100 flex items-center gap-3">
                <span class="text-xs font-medium text-purple-700 bg-purple-50 px-2 py-1 rounded border border-purple-200">
                    Superadmin Authority
                </span>
                <label for="selectedWorkspace" class="text-xs font-medium text-slate-600">Workspace Scope Filter:</label>
                <select
                    id="selectedWorkspace"
                    wire:model.live="selectedWorkspace"
                    class="text-sm rounded-lg border-slate-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 py-1"
                >
                    <option value="">All Workspaces (Cross-Tenant)</option>
                    @foreach($workspaces as $ws)
                        <option value="{{ $ws->id }}">{{ $ws->name }} ({{ $ws->id }})</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    <!-- Audit Logs Results Table -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs font-semibold uppercase tracking-wider">
                    <tr>
                        <th scope="col" class="px-4 py-3">Timestamp</th>
                        <th scope="col" class="px-4 py-3">Workspace</th>
                        <th scope="col" class="px-4 py-3">Actor</th>
                        <th scope="col" class="px-4 py-3">Guard</th>
                        <th scope="col" class="px-4 py-3">Event Type</th>
                        <th scope="col" class="px-4 py-3">Target Entity</th>
                        <th scope="col" class="px-4 py-3">Reason</th>
                        <th scope="col" class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($auditLogs as $log)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-3 whitespace-nowrap text-xs text-slate-500">
                                <div>{{ $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : 'N/A' }}</div>
                                <div class="text-[10px] text-slate-400">{{ $log->created_at ? $log->created_at->diffForHumans() : '' }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-xs font-mono">
                                <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-800 border border-slate-200">
                                    {{ $log->workspace_id ?? 'Global' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($log->actor)
                                    <div class="font-medium text-slate-900">{{ $log->actor->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $log->actor->email }}</div>
                                @else
                                    <span class="text-xs italic text-slate-400">System / Unauthenticated</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-xs font-mono text-slate-600">
                                {{ $log->actor_guard ?? 'N/A' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @php
                                    $badgeColor = match(true) {
                                        str_contains($log->event_type, 'created') => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        str_contains($log->event_type, 'updated') => 'bg-blue-50 text-blue-700 border-blue-200',
                                        str_contains($log->event_type, 'deleted') => 'bg-rose-50 text-rose-700 border-rose-200',
                                        str_contains($log->event_type, 'login_success') => 'bg-teal-50 text-teal-700 border-teal-200',
                                        str_contains($log->event_type, 'login_failed') => 'bg-amber-50 text-amber-700 border-amber-200',
                                        str_contains($log->event_type, 'permission') => 'bg-purple-50 text-purple-700 border-purple-200',
                                        default => 'bg-slate-50 text-slate-700 border-slate-200',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border {{ $badgeColor }}">
                                    {{ $log->event_type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-xs">
                                @if($log->auditable_type)
                                    <div class="font-mono text-slate-800">{{ class_basename($log->auditable_type) }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">ID: {{ $log->auditable_id }}</div>
                                @else
                                    <span class="text-slate-400 italic">None</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600 max-w-xs truncate" title="{{ $log->reason }}">
                                {{ $log->reason ?: '—' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                <button
                                    type="button"
                                    wire:click="viewDetails({{ $log->id }})"
                                    class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded text-indigo-700 bg-indigo-50 hover:bg-indigo-100 transition-colors"
                                >
                                    View Details
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-slate-400 text-sm">
                                No audit log records found matching the active criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($auditLogs->hasPages())
            <div class="p-4 border-t border-slate-200 bg-slate-50">
                {{ $auditLogs->links() }}
            </div>
        @endif
    </div>

    <!-- Detail Modal -->
    @if($showDetailModal && $selectedLog)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-slate-900 bg-opacity-60 transition-opacity" wire:click="closeDetails" aria-hidden="true"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                    <!-- Modal Header -->
                    <div class="bg-slate-900 text-white px-6 py-4 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold" id="modal-title">
                                Audit Log Entry #{{ $selectedLog->id }}
                            </h3>
                            <p class="text-xs text-slate-400 font-mono mt-0.5">
                                {{ $selectedLog->event_type }} &bull; {{ $selectedLog->created_at ? $selectedLog->created_at->format('Y-m-d H:i:s T') : 'N/A' }}
                            </p>
                        </div>
                        <button
                            type="button"
                            wire:click="closeDetails"
                            class="text-slate-400 hover:text-white transition-colors"
                        >
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-6 space-y-6 max-h-[75vh] overflow-y-auto">
                        <!-- Metadata Grid -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs">
                            <div>
                                <span class="font-medium text-slate-500 block">Workspace:</span>
                                <span class="font-mono text-slate-800 font-semibold">{{ $selectedLog->workspace_id ?? 'Global' }}</span>
                            </div>
                            <div>
                                <span class="font-medium text-slate-500 block">Actor:</span>
                                <span class="font-semibold text-slate-800">{{ $selectedLog->actor?->name ?? 'System' }}</span>
                                <span class="text-slate-500 block text-[11px]">{{ $selectedLog->actor?->email ?? '' }}</span>
                            </div>
                            <div>
                                <span class="font-medium text-slate-500 block">Guard:</span>
                                <span class="font-mono text-slate-800">{{ $selectedLog->actor_guard ?? 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="font-medium text-slate-500 block">IP / Client:</span>
                                <span class="font-mono text-slate-800">{{ $selectedLog->ip_address ?? 'N/A' }}</span>
                            </div>
                            <div class="col-span-2">
                                <span class="font-medium text-slate-500 block">Auditable Target:</span>
                                <span class="font-mono text-slate-800">{{ $selectedLog->auditable_type ?? 'None' }} (ID: {{ $selectedLog->auditable_id ?? 'N/A' }})</span>
                            </div>
                            <div class="col-span-2">
                                <span class="font-medium text-slate-500 block">Audit Reason:</span>
                                <span class="text-slate-800">{{ $selectedLog->reason ?: 'No explicit reason specified.' }}</span>
                            </div>
                        </div>

                        <!-- Visual Diff Representation of Modified Fields -->
                        <div>
                            <h4 class="text-sm font-semibold text-slate-900 mb-3 flex items-center gap-2">
                                <span>Modified Attributes Diff</span>
                                <span class="text-xs font-normal text-slate-500">(Sensitive credentials scrubbed to [REDACTED])</span>
                            </h4>

                            @php
                                $oldVals = is_array($selectedLog->old_values) ? $selectedLog->old_values : [];
                                $newVals = is_array($selectedLog->new_values) ? $selectedLog->new_values : [];
                                $allKeys = array_unique(array_merge(array_keys($oldVals), array_keys($newVals)));
                                sort($allKeys);
                            @endphp

                            @if(count($allKeys) > 0)
                                <div class="border border-slate-200 rounded-lg overflow-hidden">
                                    <table class="min-w-full divide-y divide-slate-200 text-xs">
                                        <thead class="bg-slate-100 text-slate-600 font-semibold uppercase">
                                            <tr>
                                                <th class="px-4 py-2 text-left w-1/4">Field</th>
                                                <th class="px-4 py-2 text-left w-3/8 bg-rose-50 text-rose-800">Before (Old Value)</th>
                                                <th class="px-4 py-2 text-left w-3/8 bg-emerald-50 text-emerald-800">After (New Value)</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-200 font-mono">
                                            @foreach($allKeys as $key)
                                                @php
                                                    $oldVal = $oldVals[$key] ?? null;
                                                    $newVal = $newVals[$key] ?? null;
                                                    $hasChanged = $oldVal !== $newVal;
                                                    $oldString = is_array($oldVal) ? json_encode($oldVal, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : (is_null($oldVal) ? 'null' : (string) $oldVal);
                                                    $newString = is_array($newVal) ? json_encode($newVal, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : (is_null($newVal) ? 'null' : (string) $newVal);
                                                @endphp
                                                <tr class="{{ $hasChanged ? 'bg-amber-50/40' : '' }}">
                                                    <td class="px-4 py-2.5 font-semibold text-slate-900 whitespace-nowrap align-top">
                                                        {{ $key }}
                                                        @if($hasChanged)
                                                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-500 ml-1" title="Changed"></span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-2.5 bg-rose-50/50 text-slate-700 align-top break-all">
                                                        @if($oldString === '[REDACTED]')
                                                            <span class="font-bold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">[REDACTED]</span>
                                                        @else
                                                            <span class="{{ is_null($oldVal) ? 'text-slate-400 italic' : '' }}">{{ $oldString }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-2.5 bg-emerald-50/50 text-slate-700 align-top break-all">
                                                        @if($newString === '[REDACTED]')
                                                            <span class="font-bold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">[REDACTED]</span>
                                                        @else
                                                            <span class="{{ is_null($newVal) ? 'text-slate-400 italic' : '' }}">{{ $newString }}</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="bg-slate-50 p-4 rounded-lg text-xs text-slate-500 text-center border border-slate-200">
                                    No attribute modifications recorded for this event.
                                </div>
                            @endif
                        </div>

                        <!-- Raw JSON Payloads -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <span class="text-xs font-semibold text-slate-700 block mb-1">Old Values (JSON)</span>
                                <pre class="bg-slate-900 text-slate-100 text-[11px] p-3 rounded-lg overflow-x-auto max-h-48"><code>{{ json_encode($selectedLog->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-700 block mb-1">New Values (JSON)</span>
                                <pre class="bg-slate-900 text-slate-100 text-[11px] p-3 rounded-lg overflow-x-auto max-h-48"><code>{{ json_encode($selectedLog->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="bg-slate-50 px-6 py-3 border-t border-slate-200 flex justify-end">
                        <button
                            type="button"
                            wire:click="closeDetails"
                            class="px-4 py-2 bg-white border border-slate-300 rounded-lg text-xs font-medium text-slate-700 hover:bg-slate-100 transition-colors"
                        >
                            Close Inspector
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
