<div>
    @if ($isOpen)
        <div
            class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="modal-title"
            role="dialog"
            aria-modal="true"
        >
            {{-- Backdrop --}}
            <div
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
                wire:click="closeModal"
            ></div>

            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div
                    class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-2xl border border-slate-200"
                    @click.stop
                >
                    {{-- Header --}}
                    <div class="px-6 py-5 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-indigo-600/30 border border-indigo-400/30 flex items-center justify-center text-indigo-300">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-white tracking-tight" id="modal-title">
                                    Request Profile Update
                                </h3>
                                <p class="text-xs text-slate-300">
                                    Member ID: <span class="font-mono font-semibold text-indigo-300">{{ $member->member_id }}</span>
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            wire:click="closeModal"
                            class="rounded-lg p-1.5 text-slate-400 hover:text-white hover:bg-white/10 transition"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {{-- Form Body --}}
                    <div class="p-6 space-y-5 max-h-[calc(85vh-160px)] overflow-y-auto">
                        {{-- Policy Banner --}}
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600 space-y-1">
                            <p class="font-semibold text-slate-800 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Administrative Verification Required
                            </p>
                            <p>
                                Direct self-editing of database records is restricted. Proposed modifications are reviewed by workspace managers prior to being applied to your permanent profile.
                            </p>
                        </div>

                        {{-- Pending Duplication Guard Warning --}}
                        @if ($hasPendingRequest)
                            <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs flex items-start gap-3">
                                <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <div>
                                    <p class="font-bold text-amber-950">Change Request Already Pending</p>
                                    <p class="mt-0.5">
                                        You already have a profile change request pending review by workspace staff. Additional submissions are restricted until the existing request is processed.
                                    </p>
                                </div>
                            </div>
                        @endif

                        {{-- Submission Error Banner --}}
                        @if ($submissionError)
                            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs flex items-start gap-3">
                                <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div>
                                    <p class="font-bold text-rose-950">Submission Error</p>
                                    <p class="mt-0.5">{{ $submissionError }}</p>
                                </div>
                            </div>
                        @endif

                        <form wire:submit.prevent="submit" class="space-y-4">
                            {{-- Contact Information --}}
                            <div class="border-b border-slate-100 pb-3">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                                    Contact & Location
                                </h4>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                                            Contact Phone Number <span class="text-rose-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            wire:model="contact_number"
                                            @disabled($hasPendingRequest)
                                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-slate-100"
                                            placeholder="+8801XXXXXXXXX"
                                        >
                                        @error('contact_number') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                                            House / Flat No. <span class="text-rose-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            wire:model="address_house_no"
                                            @disabled($hasPendingRequest)
                                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-slate-100"
                                        >
                                        @error('address_house_no') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                                            Road / Street No.
                                        </label>
                                        <input
                                            type="text"
                                            wire:model="address_road_no"
                                            @disabled($hasPendingRequest)
                                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-slate-100"
                                        >
                                        @error('address_road_no') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                                            Area / Neighborhood <span class="text-rose-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            wire:model="address_area"
                                            @disabled($hasPendingRequest)
                                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-slate-100"
                                        >
                                        @error('address_area') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                                            Postal / Zip Code <span class="text-rose-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            wire:model="address_zip_code"
                                            @disabled($hasPendingRequest)
                                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-slate-100"
                                        >
                                        @error('address_zip_code') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                                            Division <span class="text-rose-500">*</span>
                                        </label>
                                        <select
                                            wire:model="address_division"
                                            @disabled($hasPendingRequest)
                                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-slate-100"
                                        >
                                            <option value="">Select Division</option>
                                            <option value="barishal">Barishal</option>
                                            <option value="chattogram">Chattogram</option>
                                            <option value="dhaka">Dhaka</option>
                                            <option value="khulna">Khulna</option>
                                            <option value="mymensingh">Mymensingh</option>
                                            <option value="rajshahi">Rajshahi</option>
                                            <option value="rangpur">Rangpur</option>
                                            <option value="sylhet">Sylhet</option>
                                        </select>
                                        @error('address_division') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                                            District <span class="text-rose-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            wire:model="address_district"
                                            @disabled($hasPendingRequest)
                                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-slate-100"
                                        >
                                        @error('address_district') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- Academic & Professional Background --}}
                            <div class="border-b border-slate-100 pb-3">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                                    Academic & Professional Information
                                </h4>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                                            Occupation <span class="text-rose-500">*</span>
                                        </label>
                                        <select
                                            wire:model="occupation"
                                            @disabled($hasPendingRequest)
                                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-slate-100"
                                        >
                                            <option value="">Select Occupation</option>
                                            <option value="artist">Artist</option>
                                            <option value="community_worker">Community Worker</option>
                                            <option value="educator">Educator</option>
                                            <option value="entrepreneur">Entrepreneur</option>
                                            <option value="media_professional">Media Professional</option>
                                            <option value="performer">Performer</option>
                                            <option value="student">Student</option>
                                            <option value="other">Other</option>
                                        </select>
                                        @error('occupation') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                                            Institution / Organization <span class="text-rose-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            wire:model="institution_study"
                                            @disabled($hasPendingRequest)
                                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-slate-100"
                                        >
                                        @error('institution_study') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                                            Highest Certification <span class="text-rose-500">*</span>
                                        </label>
                                        <select
                                            wire:model="highest_certification"
                                            @disabled($hasPendingRequest)
                                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-slate-100"
                                        >
                                            <option value="">Select Certification</option>
                                            <option value="ssc">SSC</option>
                                            <option value="o_level">O-Level</option>
                                            <option value="hsc">HSC</option>
                                            <option value="a_level">A-Level</option>
                                            <option value="undergraduate_degree">Undergraduate Degree</option>
                                            <option value="undergraduate_honors">Undergraduate Honors</option>
                                            <option value="graduate">Graduate</option>
                                            <option value="postgraduate">Postgraduate</option>
                                            <option value="other">Other</option>
                                        </select>
                                        @error('highest_certification') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                                            Area / Major of Subject <span class="text-rose-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            wire:model="area_of_subject"
                                            @disabled($hasPendingRequest)
                                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-slate-100"
                                        >
                                        @error('area_of_subject') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- Justification --}}
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Justification / Reason for Modification <span class="text-rose-500">*</span>
                                </label>
                                <textarea
                                    wire:model="justification"
                                    rows="3"
                                    @disabled($hasPendingRequest)
                                    class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-slate-100"
                                    placeholder="Please describe why this change is requested (minimum 10 characters)..."
                                ></textarea>
                                <p class="text-[11px] text-slate-400 mt-0.5">Minimum 10 characters required for staff audit record.</p>
                                @error('justification') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                            </div>
                        </form>
                    </div>

                    {{-- Actions --}}
                    <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="px-4 py-2 text-xs font-semibold text-slate-700 hover:text-slate-900 hover:bg-slate-100 rounded-xl transition"
                        >
                            Cancel
                        </button>

                        <button
                            type="button"
                            wire:click="submit"
                            wire:loading.attr="disabled"
                            @disabled($hasPendingRequest)
                            class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-500 rounded-xl shadow-xs transition disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span wire:loading.remove wire:target="submit">Submit Change Request</span>
                            <span wire:loading wire:target="submit">Submitting...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
