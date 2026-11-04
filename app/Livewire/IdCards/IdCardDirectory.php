<?php

declare(strict_types=1);

namespace App\Livewire\IdCards;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\IdCards\Exceptions\InvalidCardStateTransitionException;
use App\Domain\IdCards\Models\IdCard;
use App\Domain\IdCards\Services\IdCardLifecycleService;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.admin')]
class IdCardDirectory extends Component
{
    use ValidatesWorkspaceOnHydration;
    use WithPagination;

    private const TABS = ['printing_queue', 'ready_for_pickup', 'delivered', 'all'];

    public string $workspace = '';

    #[Url(as: 'tab', except: 'printing_queue')]
    public string $activeTab = 'printing_queue';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** @var array<int, int|string> */
    public array $selectedCardIds = [];

    public string $batchReference = '';

    public ?int $confirmDeliveryCardId = null;

    public function mount(string $workspace): void
    {
        $this->workspace = $workspace;
        $this->assertAccess();
    }

    public function hydrate(): void
    {
        $user = Auth::user();
        if ($user && method_exists($user, 'hasRole') && !$user->hasRole(['superadmin', 'global_admin'])) {
            if (!empty($this->workspace) && (string) $user->workspace_id !== (string) $this->workspace) {
                App::abort(403, 'Unauthorized workspace entity.');
            }
        }
    }

    private function assertAccess(): \App\Models\User
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        $elevated = method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);

        if (!$elevated && (string) $user->workspace_id !== (string) $this->workspace) {
            App::abort(403, 'Unauthorized workspace access.');
        }
        if (!$elevated && !$user->can('members.manage_cards')) {
            App::abort(403, 'Forbidden: members.manage_cards permission required.');
        }

        return $user;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        if (!in_array($tab, self::TABS, true)) {
            return;
        }
        $this->activeTab = $tab;
        $this->selectedCardIds = [];
        $this->resetPage();
    }

    private function baseQuery()
    {
        return IdCard::query()->where('workspace_id', $this->workspace);
    }

    private function filteredQuery()
    {
        $query = $this->baseQuery()->with('member');

        $status = match ($this->activeTab) {
            'printing_queue' => 'provided_for_printing',
            'ready_for_pickup' => 'card_printed_ready_for_pickup',
            'delivered' => 'delivered',
            default => null,
        };
        if ($status) {
            $query->where('status', $status);
        }

        $term = trim($this->search);
        if ($term !== '') {
            $like = '%' . $term . '%';
            $query->where(function ($q) use ($like) {
                $q->where('card_number', 'like', $like)
                    ->orWhereHas('member', function ($mq) use ($like) {
                        $mq->where('member_id', 'like', $like)
                            ->orWhere('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like);
                    });
            });
        }

        return $query;
    }

    /**
     * Selected cards of this workspace restricted to a given status.
     */
    private function selectedCards(string $status)
    {
        $ids = array_values(array_filter(array_map('intval', $this->selectedCardIds)));

        return $this->baseQuery()->with('member')
            ->whereIn('id', $ids)
            ->where('status', $status)
            ->get();
    }

    public function exportBatchForPrinting(): ?StreamedResponse
    {
        $user = $this->assertAccess();

        $cards = $this->selectedCards('provided_for_printing');
        if ($cards->isEmpty()) {
            session()->flash('error', 'Select at least one card queued for printing.');
            return null;
        }

        $batchRef = sprintf('BATCH-%s-%03d', now()->format('Ym'), $cards->count());
        $this->batchReference = $batchRef;
        $filename = strtolower($batchRef) . '-' . now()->format('His') . '.csv';

        AuditLog::create([
            'workspace_id' => $this->workspace,
            'actor_id' => $user->id,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_ID_CARDS_BATCH_EXPORTED,
            'auditable_type' => IdCard::class,
            'auditable_id' => null,
            'old_values' => null,
            'new_values' => [
                'batch_reference' => $batchRef,
                'card_ids' => $cards->pluck('id')->all(),
                'card_numbers' => $cards->pluck('card_number')->all(),
                'exported_rows' => $cards->count(),
                'filename' => $filename,
            ],
            'reason' => "Exported {$cards->count()} ID cards for PVC printing as {$batchRef}",
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'System/Console',
        ]);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->streamDownload(function () use ($cards, $batchRef) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Batch Reference', 'Card Number', 'Member ID', 'Full Name', 'Expiry Date', 'Photo Reference']);
            foreach ($cards as $card) {
                $member = $card->member;
                fputcsv($handle, [
                    $this->sanitizeCsvCell($batchRef),
                    $this->sanitizeCsvCell($card->card_number),
                    $this->sanitizeCsvCell($member?->member_id ?? ''),
                    $this->sanitizeCsvCell($member?->full_name ?? ''),
                    $this->sanitizeCsvCell($member?->expiry_date ? $member->expiry_date->toDateString() : ''),
                    $this->sanitizeCsvCell($member?->member_id ?? ''),
                ]);
            }
            fclose($handle);
        }, $filename, $headers);
    }

    public function markSelectedAsPrinted(?string $batchRef = null): void
    {
        $user = $this->assertAccess();

        $batchRef = trim($batchRef ?? $this->batchReference);
        $service = app(IdCardLifecycleService::class);

        $count = 0;
        foreach ($this->selectedCards('provided_for_printing') as $card) {
            try {
                $service->markPrinted($card, $batchRef !== '' ? $batchRef : null, $user);
                $count++;
            } catch (InvalidCardStateTransitionException $e) {
                session()->flash('error', $e->getMessage());
            }
        }

        $this->selectedCardIds = [];
        $this->batchReference = '';
        if ($count > 0) {
            session()->flash('success', "{$count} card(s) marked as printed; members notified.");
        }
    }

    public function confirmDelivery(int $cardId): void
    {
        $this->assertAccess();
        $this->confirmDeliveryCardId = $cardId;
    }

    public function cancelDelivery(): void
    {
        $this->confirmDeliveryCardId = null;
    }

    public function deliverCard(int $cardId): void
    {
        $user = $this->assertAccess();

        $card = $this->baseQuery()->where('id', $cardId)->first();
        if (!$card) {
            App::abort(404, 'Card not found in this workspace.');
        }

        try {
            app(IdCardLifecycleService::class)->markDelivered($card, $user);
            session()->flash('success', "Card {$card->card_number} marked as delivered.");
        } catch (InvalidCardStateTransitionException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->confirmDeliveryCardId = null;
    }

    /**
     * Neutralize CSV formula injection per T-05-10.
     */
    public function sanitizeCsvCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        $str = trim(is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value);
        if ($str !== '' && in_array($str[0], ['=', '+', '-', '@'], true)) {
            return "'" . $str;
        }

        return $str;
    }

    public function render(): View
    {
        $counts = [
            'printing_queue' => $this->baseQuery()->where('status', 'provided_for_printing')->count(),
            'ready_for_pickup' => $this->baseQuery()->where('status', 'card_printed_ready_for_pickup')->count(),
        ];

        return view('livewire.id-cards.id-card-directory', [
            'cards' => $this->filteredQuery()->orderByDesc('created_at')->paginate(15),
            'counts' => $counts,
        ])->layout('layouts.admin', [
            'activeModule' => 'members',
            'title' => 'ID Card Operations - ' . $this->workspace,
        ]);
    }
}
