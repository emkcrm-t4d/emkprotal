@extends('layouts.portal')

@section('content')
<div class="bg-white shadow-sm rounded-2xl p-6 sm:p-10 border border-slate-200">
    <div class="flex items-center gap-4 border-b border-slate-100 pb-6 mb-6">
        <div class="w-14 h-14 rounded-full bg-indigo-50 border border-indigo-200 flex items-center justify-center text-indigo-700 font-bold text-xl">
            {{ strtoupper(substr(Auth::guard('member')->user()?->name ?? 'M', 0, 1)) }}
        </div>
        <div>
            <h2 class="text-xl sm:text-2xl font-bold text-slate-900">
                Welcome, {{ Auth::guard('member')->user()?->name }}
            </h2>
            <p class="text-sm text-slate-500 mt-0.5">
                EMK Center Membership Dashboard & Profile
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-slate-50 rounded-xl p-4 border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Account Email</span>
            <p class="text-sm font-medium text-slate-800 mt-1">{{ Auth::guard('member')->user()?->email }}</p>
        </div>
        <div class="bg-slate-50 rounded-xl p-4 border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Member Identifier</span>
            <p class="text-sm font-mono font-medium text-indigo-700 mt-1">
                {{ Auth::guard('member')->user()?->member?->member_id ?? 'Provisioning in progress' }}
            </p>
        </div>
    </div>
</div>
@endsection
