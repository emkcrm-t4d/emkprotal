<div class="space-y-6">
    <!-- Header Section -->
    <div class="sm:flex sm:items-center sm:justify-between pb-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white">Form Management & Builder</h1>
            <span class="sr-only">{{ $workspace }}</span>
        </div>
        <div class="mt-4 sm:mt-0 flex gap-3">
            @if (auth()->user()?->can('forms.create') || auth()->user()?->hasRole(['workspace_manager', 'superadmin', 'global_admin']))
                <button
                    type="button"
                    wire:click="openCreateModal"
                    class="inline-flex items-center gap-2 rounded-full bg-white text-slate-950 font-bold px-4 py-2 text-xs shadow-lg shadow-white/20 hover:bg-amber-400 transition-all cursor-pointer"
                >
                    <svg class="w-4 h-4 text-slate-950" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    Create New Form
                </button>
            @endif
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

    <!-- Search Toolbar -->
    <div class="flex items-center justify-between gap-4">
        <div class="relative flex-1 max-w-md">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input 
                type="text" 
                wire:model.live.debounce.300ms="search" 
                placeholder="Search forms by title or slug..."
                class="block w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            >
        </div>
    </div>

    <!-- Forms Table -->
    <div class="bg-slate-900/70 backdrop-blur-2xl border border-white/10 rounded-3xl overflow-hidden shadow-2xl">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-white/10 text-left text-sm">
                <thead class="bg-slate-950/60 text-slate-300">
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 sm:pl-6 font-bold text-white text-xs uppercase tracking-wider">Form Title</th>
                        <th scope="col" class="px-3 py-3.5 font-bold text-white text-xs uppercase tracking-wider">Version</th>
                        <th scope="col" class="px-3 py-3.5 font-bold text-white text-xs uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-3 py-3.5 font-bold text-white text-xs uppercase tracking-wider">Submissions</th>
                        <th scope="col" class="px-3 py-3.5 font-bold text-white text-xs uppercase tracking-wider">Default</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6 text-right font-bold text-white text-xs uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 bg-slate-900/40">
                    @forelse ($forms as $form)
                        <tr class="hover:bg-white/5 transition-colors">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 sm:pl-6">
                                <div class="font-bold text-white text-sm tracking-tight">{{ $form->title }}</div>
                                <span class="sr-only">{{ $form->slug }}</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-xs font-mono text-slate-300">
                                v{{ $form->version }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4">
                                @if ($form->is_published)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/20 px-2.5 py-1 text-xs font-semibold text-emerald-300 border border-emerald-500/30">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                        Published
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-800/80 px-2.5 py-1 text-xs font-semibold text-slate-300 border border-white/10">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        Draft / Archived
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-xs text-slate-300">
                                <span class="font-bold text-white">{{ $form->submissions_count }}</span> entries
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-xs">
                                @if ($form->is_default)
                                    <span class="inline-flex items-center rounded-full bg-amber-400/15 px-2.5 py-1 text-xs font-semibold text-amber-300 border border-amber-400/30">
                                        Default Form
                                    </span>
                                @else
                                    <span class="text-slate-500">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap py-4 pl-3 pr-4 sm:pr-6 text-right text-xs font-medium space-x-2">
                                <a 
                                    href="{{ route('crm.forms.builder', ['workspace' => $workspace, 'form' => $form->id]) }}" 
                                    class="inline-flex items-center rounded-full bg-white text-slate-950 px-3.5 py-1.5 text-xs font-bold shadow-md hover:bg-amber-400 transition-colors"
                                >
                                    Builder
                                </a>

                                <button
                                    type="button"
                                    wire:click="openEmbedModal({{ $form->id }})"
                                    class="inline-flex items-center rounded-full bg-white/10 text-slate-200 border border-white/10 px-3 py-1.5 text-xs font-semibold hover:bg-white/20 transition-colors"
                                >
                                    Embed
                                </button>

                                @if (auth()->user()?->can('forms.publish') || auth()->user()?->hasRole(['superadmin', 'global_admin']))
                                    @if ($form->is_published)
                                        <button
                                            type="button"
                                            wire:click="archive({{ $form->id }})"
                                            wire:confirm="Are you sure you want to archive this form? Public submissions will be blocked."
                                            class="inline-flex items-center rounded-full bg-amber-500/20 text-amber-300 border border-amber-400/30 px-3 py-1.5 text-xs font-semibold hover:bg-amber-500/30 transition-colors"
                                        >
                                            Archive
                                        </button>
                                    @else
                                        <button
                                            type="button"
                                            wire:click="publish({{ $form->id }})"
                                            class="inline-flex items-center rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 px-3 py-1.5 text-xs font-semibold hover:bg-emerald-500/30 transition-colors"
                                        >
                                            Publish
                                        </button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-slate-400">
                                <p class="font-medium text-white">No forms found for this workspace.</p>
                                <p class="mt-1 text-xs text-slate-400">Click "Create New Form" above to initialize a new form definition.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($forms->hasPages())
            <div class="border-t border-white/10 px-4 py-3 sm:px-6">
                {{ $forms->links() }}
            </div>
        @endif
    </div>

    <!-- Create Form Modal -->
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-2xl border border-slate-200 max-w-lg w-full p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h2 class="text-lg font-bold text-slate-900">Create New Form</h2>
                    <button type="button" wire:click="closeCreateModal" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Form Title *</label>
                        <input 
                            type="text" 
                            wire:model.live="newTitle" 
                            placeholder="e.g. EMK Summer Internship Application"
                            class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        >
                        @error('newTitle') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">URL Slug *</label>
                        <div class="flex rounded-md shadow-xs">
                            <span class="inline-flex items-center rounded-l-md border border-r-0 border-slate-300 bg-slate-50 px-3 text-xs text-slate-500">/apply/</span>
                            <input 
                                type="text" 
                                wire:model="newSlug" 
                                placeholder="summer-internship-2026"
                                class="block w-full min-w-0 flex-1 rounded-none rounded-r-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 font-mono"
                            >
                        </div>
                        @error('newSlug') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Description (Optional)</label>
                        <textarea 
                            wire:model="newDescription" 
                            rows="3" 
                            placeholder="Brief description for applicants..."
                            class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        ></textarea>
                        @error('newDescription') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                    <button 
                        type="button" 
                        wire:click="closeCreateModal" 
                        class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors"
                    >
                        Cancel
                    </button>
                    <button 
                        type="button" 
                        wire:click="createForm" 
                        class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-500 rounded-lg shadow-xs transition-colors"
                    >
                        Create Form
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Embed Options Modal -->
    @if ($showEmbedModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-2xl border border-slate-200 max-w-2xl w-full p-6 space-y-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Form Embed & Integration Codes</h2>
                        <p class="text-xs text-slate-500">{{ $selectedFormTitle }}</p>
                    </div>
                    <button type="button" wire:click="closeEmbedModal" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Option A: Direct Permalink -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">A. Public Form Permalink</label>
                    <p class="text-xs text-slate-500">Direct standalone URL for applicants to access this form:</p>
                    <div class="flex rounded-md shadow-xs">
                        <input 
                            type="text" 
                            readonly 
                            value="{{ $embedPermalink }}" 
                            class="block w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-xs font-mono text-slate-700 select-all"
                        >
                    </div>
                </div>

                <!-- Option B: Responsive iFrame Snippet -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">B. Responsive iFrame Snippet</label>
                    <p class="text-xs text-slate-500">Embed this form seamlessly into any CMS (WordPress, Drupal, etc.):</p>
                    <textarea 
                        readonly 
                        rows="3" 
                        class="block w-full rounded-md border border-slate-300 bg-slate-50 p-2.5 text-xs font-mono text-slate-700 select-all"
                    >{{ $embedIframe }}</textarea>
                </div>

                <!-- Option C: HTML/CSS Embed Snippet -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700">C. HTML / CSS External Snippet</label>
                    <p class="text-xs text-slate-500">Container snippet with responsive width wrappers:</p>
                    <textarea 
                        readonly 
                        rows="4" 
                        class="block w-full rounded-md border border-slate-300 bg-slate-50 p-2.5 text-xs font-mono text-slate-700 select-all"
                    >{{ $embedHtml }}</textarea>
                </div>

                <div class="flex items-center justify-end pt-3 border-t border-slate-100">
                    <button 
                        type="button" 
                        wire:click="closeEmbedModal" 
                        class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
