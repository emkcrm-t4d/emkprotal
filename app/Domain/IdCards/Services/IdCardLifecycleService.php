<?php

declare(strict_types=1);

namespace App\Domain\IdCards\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\IdCards\Exceptions\InvalidCardStateTransitionException;
use App\Domain\IdCards\Models\IdCard;
use App\Domain\Members\Models\Member;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class IdCardLifecycleService
{
    /**
     * Create an initial ID card record in provided_for_printing status.
     */
    public function createInitialCard(Member $member): IdCard
    {
        return DB::transaction(function () use ($member) {
            $existingCount = IdCard::where('member_id', $member->id)->count();
            $count = $existingCount + 1;

            do {
                $suffix = str_pad((string) $count, 2, '0', STR_PAD_LEFT);
                $cardNumber = "CRD-{$member->member_id}-{$suffix}";
                $count++;
            } while (IdCard::where('card_number', $cardNumber)->exists());

            return IdCard::create([
                'workspace_id' => $member->workspace_id,
                'member_id' => $member->id,
                'card_number' => $cardNumber,
                'status' => 'provided_for_printing',
            ]);
        });
    }

    /**
     * Advance a card from provided_for_printing to card_printed_ready_for_pickup.
     * Queues member pickup notification email and records audit log.
     *
     * @throws InvalidCardStateTransitionException
     */
    public function markPrinted(IdCard $card, ?string $batchReference = null, ?User $staff = null): IdCard
    {
        if ($card->status !== 'provided_for_printing') {
            throw new InvalidCardStateTransitionException(
                "Cannot mark card as printed: card is currently in '{$card->status}' state (must be 'provided_for_printing')."
            );
        }

        $now = Carbon::now();

        $card->status = 'card_printed_ready_for_pickup';
        $card->printed_at = $now;
        if ($batchReference !== null && $batchReference !== '') {
            $card->batch_reference = $batchReference;
        }
        $card->save();

        // Queue member pickup notification email
        $card->loadMissing('member');
        if ($card->member && !empty($card->member->email)) {
            if (class_exists(\App\Mail\CardReadyNotificationMail::class)) {
                Mail::to($card->member->email)->queue(new \App\Mail\CardReadyNotificationMail($card->member, $card));
            }

            if (!empty($card->workspace_id) && \Illuminate\Support\Facades\Schema::hasTable('workflow_rules')) {
                try {
                    app(\App\Domain\Mail\Services\WorkflowRuleEngine::class)->trigger(
                        (string) $card->workspace_id,
                        \App\Domain\Mail\Models\WorkflowRule::TRIGGER_ID_CARD_READY,
                        (string) $card->member->email,
                        [
                            'member_name' => $card->member->full_name,
                            'member_id' => (string) $card->member->member_id,
                            'workspace_name' => (string) $card->workspace_id,
                            'expiry_date' => $card->member->expiry_date ? $card->member->expiry_date->format('Y-m-d') : '',
                        ],
                        $card->member->lead_id !== null ? (int) $card->member->lead_id : null,
                        (int) $card->member->id,
                        "id_card_ready:{$card->id}"
                    );
                } catch (\Throwable) {
                    // Fault-isolated per T-08-14
                }
            }
        }

        // DEC-11: Record immutable audit log
        $actorId = $staff?->id ?? auth()->id();
        AuditLog::create([
            'workspace_id' => $card->workspace_id,
            'actor_id' => $actorId,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_ID_CARD_PRINTED,
            'auditable_type' => IdCard::class,
            'auditable_id' => $card->id,
            'new_values' => [
                'card_number' => $card->card_number,
                'member_id' => $card->member_id,
                'status' => $card->status,
                'batch_reference' => $card->batch_reference,
                'printed_at' => $now->toIso8601String(),
                'staff_id' => $actorId,
            ],
            'reason' => 'Physical card printed and checked in ready for member pickup.',
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'System/Console',
        ]);

        return $card;
    }

    /**
     * Advance a card from card_printed_ready_for_pickup to delivered.
     * Records delivering staff ID and timestamp in immutable audit log.
     *
     * @throws InvalidCardStateTransitionException
     */
    public function markDelivered(IdCard $card, User $staff): IdCard
    {
        if ($card->status !== 'card_printed_ready_for_pickup') {
            throw new InvalidCardStateTransitionException(
                "Cannot mark card as delivered: card is currently in '{$card->status}' state (must be 'card_printed_ready_for_pickup')."
            );
        }

        $now = Carbon::now();

        $card->status = 'delivered';
        $card->delivered_at = $now;
        $card->save();

        // DEC-11: Record immutable audit log with delivering staff ID
        AuditLog::create([
            'workspace_id' => $card->workspace_id,
            'actor_id' => $staff->id,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_ID_CARD_DELIVERED,
            'auditable_type' => IdCard::class,
            'auditable_id' => $card->id,
            'new_values' => [
                'card_number' => $card->card_number,
                'member_id' => $card->member_id,
                'status' => $card->status,
                'delivering_staff_id' => $staff->id,
                'delivered_at' => $now->toIso8601String(),
            ],
            'reason' => 'Physical membership card handed over to verified member at front desk.',
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'System/Console',
        ]);

        return $card;
    }

    public const REPLACEMENT_REASONS = ['LOST_CARD', 'DAMAGED_CARD', 'NAME_CHANGE', 'OTHER'];

    /**
     * Replace a member's active card(s): supersede prior cards, issue a new card in
     * provided_for_printing, optionally bill a replacement fee invoice (MEMB-06).
     *
     * @throws \InvalidArgumentException
     */
    public function replaceCard(
        Member $member,
        string $replacementReason,
        ?string $bkashTxId = null,
        ?User $actor = null
    ): IdCard {
        if (!in_array($replacementReason, self::REPLACEMENT_REASONS, true)) {
            throw new \InvalidArgumentException(
                'Replacement reason must be one of: ' . implode(', ', self::REPLACEMENT_REASONS) . '.'
            );
        }

        $bkashTxId = ($bkashTxId !== null && trim($bkashTxId) !== '') ? trim($bkashTxId) : null;

        return DB::transaction(function () use ($member, $replacementReason, $bkashTxId, $actor): IdCard {
            $activeCards = IdCard::where('member_id', $member->id)
                ->whereIn('status', ['provided_for_printing', 'card_printed_ready_for_pickup', 'delivered'])
                ->lockForUpdate()
                ->get();

            $priorNumbers = [];
            foreach ($activeCards as $old) {
                $priorNumbers[] = $old->card_number;
                $old->status = 'superseded_replaced';
                $old->replacement_reason = $replacementReason;
                $old->save();
            }

            $invoiceNumber = null;
            if ($bkashTxId !== null) {
                $invoice = \Illuminate\Container\Container::getInstance()->make(\App\Domain\Invoices\Services\InvoiceService::class)
                    ->generateCardReplacementInvoice($member, $bkashTxId);
                $invoiceNumber = $invoice->invoice_number;
            }

            $count = IdCard::where('member_id', $member->id)->count() + 1;
            do {
                $suffix = str_pad((string) $count, 2, '0', STR_PAD_LEFT);
                $cardNumber = "CRD-{$member->member_id}-{$suffix}";
                $count++;
            } while (IdCard::where('card_number', $cardNumber)->exists());

            $newCard = IdCard::create([
                'workspace_id' => $member->workspace_id,
                'member_id' => $member->id,
                'card_number' => $cardNumber,
                'status' => 'provided_for_printing',
                'replacement_reason' => $replacementReason,
            ]);

            $actorId = $actor?->id ?? auth()->id();
            AuditLog::create([
                'workspace_id' => $member->workspace_id,
                'actor_id' => $actorId,
                'actor_guard' => 'web',
                'event_type' => AuditLog::EVENT_ID_CARD_REPLACED,
                'auditable_type' => IdCard::class,
                'auditable_id' => $newCard->id,
                'new_values' => [
                    'member_id' => $member->id,
                    'prior_card_numbers' => $priorNumbers,
                    'new_card_number' => $newCard->card_number,
                    'replacement_reason' => $replacementReason,
                    'fee_invoice_number' => $invoiceNumber,
                    'actor_user_id' => $actorId,
                ],
                'reason' => "ID card replaced: {$replacementReason}.",
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System/Console',
            ]);

            return $newCard;
        });
    }
}
