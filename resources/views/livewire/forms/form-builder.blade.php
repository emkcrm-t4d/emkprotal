<div class="space-y-6">
    <!-- Top Action Bar -->
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200 pb-5">
        <div class="flex items-center gap-4">
            <a 
                href="{{ route('crm.forms.index', ['workspace' => $workspace]) }}" 
                class="p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
                title="Back to Forms Index"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $form->title }}</h1>
                    @if ($isPublished)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            Live
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                            Draft / Archived
                        </span>
                    @endif
                </div>
                <p class="mt-1 text-xs font-mono text-slate-500">
                    slug: <span class="text-indigo-600 font-semibold">{{ $form->slug }}</span> | 
                    version: <span class="text-slate-700 font-semibold">v{{ $version }}</span> | 
                    workspace: <span class="text-slate-700 font-semibold">{{ $workspace }}</span>
                </p>
            </div>
        </div>

        <div class="mt-4 sm:mt-0 flex flex-wrap items-center gap-2">
            <button
                type="button"
                wire:click="openEmbedModal"
                class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-xs ring-1 ring-inset ring-slate-300 hover:bg-slate-50 transition-colors"
            >
                <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                </svg>
                Embed Options
            </button>

            @if (auth()->user()?->can('forms.publish') || auth()->user()?->hasRole(['superadmin', 'global_admin']))
                @if ($isPublished)
                    <button
                        type="button"
                        wire:click="archive"
                        wire:confirm="Archiving this form will immediately deactivate public submissions. Proceed?"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20 hover:bg-amber-100 transition-colors"
                    >
                        Archive Form
                    </button>
                @else
                    <button
                        type="button"
                        wire:click="publish"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-emerald-500 transition-colors"
                    >
                        Publish Form
                    </button>
                @endif
            @endif

            <button
                type="button"
                wire:click="saveSchema"
                class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                </svg>
                Save Form Schema
            </button>
        </div>
    </div>

    <!-- Feedback Alerts -->
    @if ($statusMessage)
        <div class="rounded-md bg-emerald-50 p-4 border border-emerald-200" role="alert">
            <div class="flex">
                <div class="shrink-0">
                    <svg class="h-5 w-5 text-emerald-400" viewBox="0 0 20 20" fill="currentColor">
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
                    <svg class="h-5 w-5 text-rose-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-rose-800">{{ $errorMessage }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Form Metadata Details -->
    <div class="bg-white rounded-lg shadow-xs ring-1 ring-slate-900/5 p-5 space-y-4">
        <h2 class="text-base font-semibold text-slate-900">Form Details</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Title</label>
                <input 
                    type="text" 
                    wire:model="formTitle" 
                    class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                >
                @error('formTitle') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Description</label>
                <input 
                    type="text" 
                    wire:model="formDescription" 
                    class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                >
                @error('formDescription') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>

    <!-- Dynamic Schema Canvas -->
    <div class="bg-white rounded-lg shadow-xs ring-1 ring-slate-900/5 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Configured Schema Fields</h2>
                <p class="text-xs text-slate-500">Ordered sequence of fields evaluated dynamically during rendering and validation.</p>
            </div>
            <button
                type="button"
                wire:click="openAddFieldModal"
                class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-indigo-500 transition-colors"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Field
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-100/75 text-slate-700 text-xs uppercase tracking-wider font-semibold">
                    <tr>
                        <th scope="col" class="py-3 pl-4 pr-2 w-12 text-center">#</th>
                        <th scope="col" class="px-3 py-3">Field Label & Key</th>
                        <th scope="col" class="px-3 py-3">Type</th>
                        <th scope="col" class="px-3 py-3">Requirement</th>
                        <th scope="col" class="px-3 py-3">Conditionals</th>
                        <th scope="col" class="px-3 py-3">Validation Rules</th>
                        <th scope="col" class="relative py-3 pl-3 pr-4 sm:pr-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($fields as $index => $field)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="whitespace-nowrap py-3.5 pl-4 pr-2 text-center font-mono text-xs text-slate-400">
                                {{ $index + 1 }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5">
                                <div class="font-medium text-slate-900">{{ $field['label'] ?? 'Untitled' }}</div>
                                <div class="font-mono text-xs text-slate-500">{{ $field['name'] ?? '—' }}</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5">
                                <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-mono font-medium bg-slate-100 text-slate-800 border border-slate-200">
                                    {{ $field['type'] ?? 'text' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5">
                                @if (!empty($field['required']))
                                    <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/20">
                                        Required
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400">Optional</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5 text-xs">
                                @if (!empty($field['conditional_rules']))
                                    @php
                                        $c = $field['conditional_rules'];
                                        $condText = isset($c['field']) 
                                            ? "when {$c['field']} {$c['operator']} " . (is_array($c['value'] ?? null) ? implode(',', $c['value']) : ($c['value'] ?? ''))
                                            : "conditional logic applied";
                                    @endphp
                                    <span class="inline-flex items-center rounded bg-amber-50 px-2 py-0.5 text-xs font-mono text-amber-800 border border-amber-200" title="{{ json_encode($c) }}">
                                        ⚡ {{ Str::limit($condText, 28) }}
                                    </span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5 text-xs text-slate-500 font-mono">
                                @php
                                    $rules = [];
                                    if (!empty($field['regex'])) $rules[] = 'regex';
                                    if (!empty($field['casing']) && $field['casing'] !== 'none') $rules[] = $field['casing'];
                                    if (!empty($field['dataset'])) $rules[] = 'dataset:' . $field['dataset'];
                                    if (!empty($field['mimes'])) $rules[] = $field['mimes'];
                                    if (!empty($field['max_kb'])) $rules[] = $field['max_kb'] . 'KB';
                                @endphp
                                {{ !empty($rules) ? implode(', ', $rules) : '—' }}
                            </td>
                            <td class="whitespace-nowrap py-3.5 pl-3 pr-4 sm:pr-6 text-right text-xs font-medium space-x-1">
                                <!-- Reorder Buttons -->
                                <button
                                    type="button"
                                    wire:click="moveFieldUp({{ $index }})"
                                    @if ($index === 0) disabled class="text-slate-200 cursor-not-allowed p-1" @else class="text-slate-500 hover:text-slate-800 p-1 rounded hover:bg-slate-100" @endif
                                    title="Move Up"
                                >
                                    ▲
                                </button>
                                <button
                                    type="button"
                                    wire:click="moveFieldDown({{ $index }})"
                                    @if ($index === count($fields) - 1) disabled class="text-slate-200 cursor-not-allowed p-1" @else class="text-slate-500 hover:text-slate-800 p-1 rounded hover:bg-slate-100" @endif
                                    title="Move Down"
                                >
                                    ▼
                                </button>

                                <!-- Edit Button -->
                                <button
                                    type="button"
                                    wire:click="openEditFieldModal({{ $index }})"
                                    class="inline-flex items-center rounded bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-700/10 hover:bg-indigo-100 transition-colors"
                                >
                                    Edit
                                </button>

                                <!-- Delete Button -->
                                <button
                                    type="button"
                                    wire:click="deleteField({{ $index }})"
                                    wire:confirm="Remove field '{{ $field['label'] ?? 'this field' }}' from schema?"
                                    class="inline-flex items-center rounded bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/10 hover:bg-rose-100 transition-colors"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-sm text-slate-500">
                                <p class="font-medium text-slate-900">No fields configured yet.</p>
                                <p class="mt-1 text-xs text-slate-400">Click "Add Field" above to configure your first form input.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Field Editor Modal -->
    @if ($showFieldModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-2xl border border-slate-200 max-w-2xl w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h2 class="text-lg font-bold text-slate-900">
                        {{ $editingIndex !== null ? 'Edit Field' : 'Add New Field' }}
                    </h2>
                    <button type="button" wire:click="closeFieldModal" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Field Type -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Input Type *</label>
                        <select 
                            wire:model.live="fieldType" 
                            class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        >
                            @foreach ($catalog as $typeKey => $meta)
                                <option value="{{ $typeKey }}">{{ $meta['name'] }} ({{ $typeKey }})</option>
                            @endforeach
                        </select>
                        @error('fieldType') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Field Label -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Field Label *</label>
                        <input 
                            type="text" 
                            wire:model.live="fieldLabel" 
                            placeholder="e.g. First Name"
                            class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        >
                        @error('fieldLabel') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Field Key (Name) -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Field Key (Column/Payload) *</label>
                        <input 
                            type="text" 
                            wire:model="fieldName" 
                            placeholder="e.g. first_name"
                            class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-mono focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        >
                        @error('fieldName') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Required / Visibility Toggles -->
                    <div class="flex items-center gap-6 pt-5">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="fieldRequired" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-xs font-semibold text-slate-700">Required Field</span>
                        </label>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="fieldVisible" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-xs font-semibold text-slate-700">Initially Visible</span>
                        </label>
                    </div>

                    <!-- Placeholder -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Placeholder</label>
                        <input 
                            type="text" 
                            wire:model="fieldPlaceholder" 
                            placeholder="Optional input prompt..."
                            class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>

                    <!-- Help Text -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Help Text</label>
                        <input 
                            type="text" 
                            wire:model="fieldHelpText" 
                            placeholder="Guidance shown below input..."
                            class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>

                    <!-- Casing -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Casing Transformation</label>
                        <select 
                            wire:model="fieldCasing" 
                            class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        >
                            <option value="none">None (Preserve)</option>
                            <option value="uppercase">Uppercase (A-Z)</option>
                            <option value="lowercase">Lowercase (a-z)</option>
                            <option value="capitalize">Capitalize Words</option>
                        </select>
                    </div>

                    <!-- Regex Pattern -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Regex Pattern</label>
                        <input 
                            type="text" 
                            wire:model="fieldRegex" 
                            placeholder="e.g. ^[A-Z0-9]{10}$"
                            class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-mono focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>

                    <!-- Length Bounds -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Min Length</label>
                        <input 
                            type="number" 
                            wire:model="fieldMinLength" 
                            class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Max Length</label>
                        <input 
                            type="number" 
                            wire:model="fieldMaxLength" 
                            class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>

                    <!-- Dataset & Parent Field (for select / cascading_select) -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Dataset Key</label>
                        <input 
                            type="text" 
                            wire:model="fieldDataset" 
                            placeholder="e.g. bangladesh_divisions or districts"
                            class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-mono focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Parent Field (Cascading)</label>
                        <input 
                            type="text" 
                            wire:model="fieldParentField" 
                            placeholder="e.g. address_division"
                            class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-mono focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>

                    <!-- File Settings (for file/multi_file) -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Allowed Mimes</label>
                        <input 
                            type="text" 
                            wire:model="fieldMimes" 
                            placeholder="e.g. pdf,jpg,jpeg,png"
                            class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-mono focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Max Size (KB)</label>
                        <input 
                            type="number" 
                            wire:model="fieldMaxKb" 
                            placeholder="e.g. 4096"
                            class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>
                </div>

                <!-- Options (for Select / Radio / Checkbox) -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                        Select / Radio Options (Key:Value per line)
                    </label>
                    <textarea 
                        wire:model="fieldOptionsRaw" 
                        rows="3" 
                        placeholder="male:Male&#10;female:Female&#10;na:N/A"
                        class="block w-full rounded-md border border-slate-300 px-3 py-2 text-xs font-mono focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    ></textarea>
                </div>

                <!-- Conditional Visibility Logic Section -->
                <div class="border-t border-slate-200 pt-3 space-y-3">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model.live="fieldHasConditional" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">Configure Conditional Visibility</span>
                    </label>

                    @if ($fieldHasConditional)
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 bg-slate-50 rounded-lg border border-slate-200">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Target Field</label>
                                <input 
                                    type="text" 
                                    wire:model="condField" 
                                    placeholder="e.g. has_us_experience or age_years"
                                    class="block w-full rounded-md border border-slate-300 px-3 py-1.5 text-xs font-mono focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                >
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Operator</label>
                                <select 
                                    wire:model="condOperator" 
                                    class="block w-full rounded-md border border-slate-300 px-3 py-1.5 text-xs font-mono focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                >
                                    @foreach ($operators as $op)
                                        <option value="{{ $op }}">{{ $op }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Expected Value</label>
                                <input 
                                    type="text" 
                                    wire:model="condValue" 
                                    placeholder="e.g. 1 or 18"
                                    class="block w-full rounded-md border border-slate-300 px-3 py-1.5 text-xs font-mono focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                >
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                    <button 
                        type="button" 
                        wire:click="closeFieldModal" 
                        class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors"
                    >
                        Cancel
                    </button>
                    <button 
                        type="button" 
                        wire:click="saveField" 
                        class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-500 rounded-lg shadow-xs transition-colors"
                    >
                        {{ $editingIndex !== null ? 'Update Field' : 'Add Field' }}
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
                        <p class="text-xs text-slate-500">{{ $form->title }}</p>
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
