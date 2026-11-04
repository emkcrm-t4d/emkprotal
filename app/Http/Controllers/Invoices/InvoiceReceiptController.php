<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invoices;

use App\Domain\Invoices\Models\Invoice;
use App\Domain\Workspaces\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class InvoiceReceiptController extends Controller
{
    /**
     * Display printable official receipt for a workspace invoice.
     */
    public function show(Request $request, string|Workspace $workspace, string|int $invoice): View
    {
        $workspaceId = $workspace instanceof Workspace ? (string) $workspace->id : (string) $workspace;

        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated.');
        }

        $isElevatedAdmin = method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);

        // Staff workspace boundary assertion
        if (!$isElevatedAdmin && (string) $user->workspace_id !== $workspaceId) {
            App::abort(403, 'Unauthorized workspace entity.');
        }

        // Resolve invoice model bypassing tenant scope for explicit boundary verification
        $invoiceModel = Invoice::withoutGlobalScopes()
            ->with(['member.term', 'lead'])
            ->findOrFail($invoice);

        // Strict cross-workspace isolation per DEC-01 / T-07-09
        if ((string) $invoiceModel->workspace_id !== $workspaceId) {
            App::abort(403, 'Unauthorized cross-workspace invoice receipt access.');
        }

        $workspaceModel = Workspace::find($workspaceId);
        $spaceName = $workspaceModel?->name ?? 'American Center / EMK Center Dhaka';
        $spaceAddress = 'Level 8, Grand Delvistaa, Plot 35, Road 11, Banani, Dhaka 1213, Bangladesh';
        $spaceContact = 'info@emk.com.bd | +880 1926-666999';

        $amountInWords = $this->amountToWords((float) $invoiceModel->amount_bdt);
        $verificationUrl = url("/crm/{$workspaceId}/invoices/{$invoiceModel->id}/receipt");

        return view('invoices.receipt', [
            'invoice' => $invoiceModel,
            'member' => $invoiceModel->member,
            'workspace' => $workspaceId,
            'spaceName' => $spaceName,
            'spaceAddress' => $spaceAddress,
            'spaceContact' => $spaceContact,
            'amountInWords' => $amountInWords,
            'verificationUrl' => $verificationUrl,
        ]);
    }

    /**
     * Convert currency amount to capitalized English words format.
     */
    public function amountToWords(float|int $amount): string
    {
        $number = (int) round((float) $amount);
        if ($number === 0) {
            return 'Zero Taka Only';
        }

        $units = [
            1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
            6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
            11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
            16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
        ];
        $tens = [
            2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
            6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety',
        ];

        $convertNumber = function (int $num) use (&$convertNumber, $units, $tens): string {
            if ($num < 20) {
                return $units[$num] ?? '';
            }
            if ($num < 100) {
                $rem = $num % 10;
                return $tens[(int) ($num / 10)] . ($rem > 0 ? ' ' . $units[$rem] : '');
            }
            if ($num < 1000) {
                $rem = $num % 100;
                return $units[(int) ($num / 100)] . ' Hundred' . ($rem > 0 ? ' ' . $convertNumber($rem) : '');
            }
            if ($num < 100000) {
                $thousands = (int) ($num / 1000);
                $rem = $num % 1000;
                return $convertNumber($thousands) . ' Thousand' . ($rem > 0 ? ' ' . $convertNumber($rem) : '');
            }
            if ($num < 10000000) {
                $lakhs = (int) ($num / 100000);
                $rem = $num % 100000;
                return $convertNumber($lakhs) . ' Lakh' . ($rem > 0 ? ' ' . $convertNumber($rem) : '');
            }
            $crores = (int) ($num / 10000000);
            $rem = $num % 10000000;
            return $convertNumber($crores) . ' Crore' . ($rem > 0 ? ' ' . $convertNumber($rem) : '');
        };

        return trim($convertNumber($number)) . ' Taka Only';
    }
}
