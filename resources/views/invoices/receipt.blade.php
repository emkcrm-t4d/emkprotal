<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Receipt - {{ $invoice->invoice_number }}</title>
    <!-- Browser Top Tab Icon (Favicon) - EMK Circular Logo -->
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/emk-favicon-32.png') }}?v=3">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/emk-circle-logo.png') }}?v=3">
    <!-- Tailwind CSS CDN for standalone print layout -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body {
                background-color: #ffffff !important;
                color: #000000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
            .print-container {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
            }
            @page {
                size: A4 portrait;
                margin: 15mm;
            }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen py-8 px-4 font-sans">
    <!-- Action Toolbar (hidden when printing) -->
    <div class="max-w-3xl mx-auto mb-6 flex items-center justify-between no-print">
        <a
            href="/crm/{{ $workspace }}/invoices"
            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50 shadow-xs transition-colors"
        >
            <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Invoices
        </a>

        <div class="flex items-center gap-2">
            <button
                type="button"
                onclick="window.print()"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold shadow-xs transition-colors"
                id="btn-print-receipt"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Print Receipt
            </button>
        </div>
    </div>

    <!-- Printable Official Receipt Card -->
    <div class="print-container max-w-3xl mx-auto bg-white rounded-2xl shadow-md border border-slate-200 p-8 sm:p-12 relative overflow-hidden">
        <!-- Header & Branding -->
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between pb-8 border-b-2 border-slate-200 gap-6">
            <div>
                <div class="flex items-center gap-3">
                    <!-- American Spaces Seal Representation -->
                    <div class="w-12 h-12 rounded-xl bg-indigo-900 text-white flex items-center justify-center font-bold tracking-wider text-sm shadow-xs">
                        EMK
                    </div>
                    <div>
                        <h1 class="text-xl font-bold tracking-tight text-slate-900 uppercase">
                            {{ $spaceName }}
                        </h1>
                        <p class="text-xs text-slate-500 font-medium">An American Spaces Partner Facility</p>
                    </div>
                </div>
                <div class="mt-3 text-xs text-slate-500 space-y-0.5">
                    <p>{{ $spaceAddress }}</p>
                    <p>{{ $spaceContact }}</p>
                </div>
            </div>

            <!-- Receipt Metadata & Watermark -->
            <div class="text-left sm:text-right">
                <div class="inline-block px-3 py-1 rounded-md bg-indigo-50 border border-indigo-200 text-indigo-900 text-xs font-bold uppercase tracking-widest mb-2">
                    Official Money Receipt
                </div>
                <div class="font-mono text-lg font-bold text-slate-900" id="receipt-invoice-number">
                    {{ $invoice->invoice_number }}
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    Date Issued: <span class="font-medium text-slate-700">{{ $invoice->paid_at ? $invoice->paid_at->format('F d, Y') : ($invoice->created_at ? $invoice->created_at->format('F d, Y') : '—') }}</span>
                </p>
                <div class="mt-2">
                    @if($invoice->status === 'paid')
                        <span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300 uppercase tracking-wider">
                            PAID
                        </span>
                    @elseif($invoice->status === 'voided')
                        <span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-300 uppercase tracking-wider">
                            VOIDED
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800 border border-slate-300 uppercase tracking-wider">
                            {{ strtoupper($invoice->status) }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Particulars: Billed To & Payment Reconciliation -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 py-6 border-b border-slate-200 text-sm">
            <div>
                <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Member Particulars</h2>
                @if($member)
                    <p class="font-bold text-slate-900 text-base" id="receipt-member-name">{{ $member->full_name }}</p>
                    <p class="text-xs font-mono text-indigo-700 font-semibold mt-0.5" id="receipt-member-id">Member ID: {{ $member->member_id }}</p>
                    <p class="text-xs text-slate-600 mt-1">{{ $member->email }}</p>
                    <p class="text-xs text-slate-600">{{ $member->contact_number }}</p>
                @else
                    <p class="text-sm italic text-slate-400">Direct Applicant (Conversion In Progress)</p>
                @endif
            </div>

            <div class="sm:text-right">
                <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Payment Details</h2>
                <p class="text-slate-800 font-medium">Payment Method: <span class="font-bold">bKash Manual Merchant Transfer</span></p>
                <p class="text-xs font-mono text-slate-700 mt-1" id="receipt-bkash-txid">
                    bKash TxID: <span class="font-bold text-slate-900 px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200">{{ $invoice->payment_reference }}</span>
                </p>
                <p class="text-xs text-slate-500 mt-1">Reconciled At: {{ $invoice->paid_at ? $invoice->paid_at->format('Y-m-d H:i:s') : 'Pending' }}</p>
                <p class="text-xs text-slate-500">Workspace Code: <span class="font-mono">{{ $workspace }}</span></p>
            </div>
        </div>

        <!-- Itemized Table -->
        <div class="py-6">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wider text-left">
                        <th scope="col" class="pb-3">Description</th>
                        <th scope="col" class="pb-3 text-center">Duration</th>
                        <th scope="col" class="pb-3 text-right">Unit Price (BDT)</th>
                        <th scope="col" class="pb-3 text-center">Qty</th>
                        <th scope="col" class="pb-3 text-right">Total (BDT)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    <tr>
                        <td class="py-4 font-medium text-slate-900">
                            @if($member && $member->term)
                                {{ $member->term->name }} Induction / Renewal Fee
                            @else
                                EMK Center Membership Subscription Fee
                            @endif
                            <div class="text-xs text-slate-400 font-normal">Official space facility access & membership credential issuance</div>
                        </td>
                        <td class="py-4 text-center font-mono text-xs">
                            @if($member && $member->term)
                                {{ $member->term->duration_months }} Months
                            @else
                                12 Months
                            @endif
                        </td>
                        <td class="py-4 text-right font-mono">
                            {{ number_format((float) $invoice->amount_bdt, 2) }}
                        </td>
                        <td class="py-4 text-center font-mono">1</td>
                        <td class="py-4 text-right font-mono font-bold text-slate-900">
                            {{ number_format((float) $invoice->amount_bdt, 2) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Financial Summary & In-Words -->
        <div class="border-t-2 border-slate-200 pt-4 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-6">
            <div class="max-w-md">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Amount in Words:</p>
                <p class="text-sm font-semibold text-indigo-900 italic mt-0.5" id="receipt-amount-words">
                    {{ $amountInWords }}
                </p>
                <p class="text-xs text-slate-400 mt-2">
                    * This is a computer-generated official money receipt. Verified electronically via EMK CRM.
                </p>
            </div>

            <div class="w-full sm:w-64 space-y-2">
                <div class="flex justify-between text-xs text-slate-600">
                    <span>Subtotal:</span>
                    <span class="font-mono">৳ {{ number_format((float) $invoice->amount_bdt, 2) }}</span>
                </div>
                <div class="flex justify-between text-xs text-slate-600">
                    <span>Tax / VAT (0%):</span>
                    <span class="font-mono">৳ 0.00</span>
                </div>
                <div class="flex justify-between text-base font-bold text-slate-900 pt-2 border-t border-slate-200" id="receipt-total-amount">
                    <span>Total Paid:</span>
                    <span class="font-mono text-indigo-700">৳ {{ number_format((float) $invoice->amount_bdt, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Official Signatures & Verification QR Code Box -->
        <div class="mt-12 pt-8 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-6 text-xs text-slate-500">
            <!-- QR Verification Placeholder / Link Pattern -->
            <div class="flex items-center gap-4">
                <div class="w-20 h-20 bg-slate-50 p-1.5 border border-slate-300 rounded-lg flex flex-col items-center justify-center">
                    <svg class="w-16 h-16 text-slate-800" viewBox="0 0 100 100" fill="currentColor">
                        <!-- Stylized QR pattern SVG for zero-dependency printing -->
                        <rect x="5" y="5" width="25" height="25" fill="#1e293b"/>
                        <rect x="9" y="9" width="17" height="17" fill="#ffffff"/>
                        <rect x="13" y="13" width="9" height="9" fill="#1e293b"/>

                        <rect x="70" y="5" width="25" height="25" fill="#1e293b"/>
                        <rect x="74" y="9" width="17" height="17" fill="#ffffff"/>
                        <rect x="78" y="13" width="9" height="9" fill="#1e293b"/>

                        <rect x="5" y="70" width="25" height="25" fill="#1e293b"/>
                        <rect x="9" y="74" width="17" height="17" fill="#ffffff"/>
                        <rect x="13" y="78" width="9" height="9" fill="#1e293b"/>

                        <rect x="40" y="15" width="8" height="8" fill="#1e293b"/>
                        <rect x="50" y="25" width="8" height="8" fill="#1e293b"/>
                        <rect x="35" y="45" width="10" height="10" fill="#1e293b"/>
                        <rect x="55" y="45" width="8" height="8" fill="#1e293b"/>
                        <rect x="45" y="65" width="8" height="8" fill="#1e293b"/>
                        <rect x="70" y="65" width="8" height="8" fill="#1e293b"/>
                        <rect x="80" y="75" width="10" height="10" fill="#1e293b"/>
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-slate-800">Scan to Verify</p>
                    <p class="text-[10px] text-slate-400 font-mono mt-0.5 break-all max-w-[220px]">
                        {{ $verificationUrl }}
                    </p>
                </div>
            </div>

            <!-- Signatory Seal Representation -->
            <div class="text-center sm:text-right">
                <div class="h-10 border-b border-dashed border-slate-300 w-48 mx-auto sm:ml-auto"></div>
                <p class="font-semibold text-slate-800 mt-1">Authorized Accounts Officer</p>
                <p class="text-[11px] text-slate-400">{{ $spaceName }}</p>
            </div>
        </div>
    </div>
</body>
</html>
