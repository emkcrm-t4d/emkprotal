<?php

declare(strict_types=1);

namespace App\Domain\Reports\DTOs;

final class OperationalMetricsDTO
{
    /**
     * @param array<string, int> $memberStatusBreakdown
     * @param array<string, int> $leadPipelineBreakdown
     * @param array<string, int> $idCardStatusBreakdown
     */
    public function __construct(
        public readonly int $totalMembers,
        public readonly array $memberStatusBreakdown,
        public readonly int $totalLeads,
        public readonly array $leadPipelineBreakdown,
        public readonly float $conversionRate,
        public readonly int $totalIdCards,
        public readonly array $idCardStatusBreakdown,
        public readonly float $totalRevenueBdt,
        public readonly int $paidInvoicesCount,
        public readonly ?string $workspaceId = null,
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
    ) {
    }

    /**
     * Convert DTO to associative array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'totalMembers' => $this->totalMembers,
            'memberStatusBreakdown' => $this->memberStatusBreakdown,
            'totalLeads' => $this->totalLeads,
            'leadPipelineBreakdown' => $this->leadPipelineBreakdown,
            'conversionRate' => $this->conversionRate,
            'totalIdCards' => $this->totalIdCards,
            'idCardStatusBreakdown' => $this->idCardStatusBreakdown,
            'totalRevenueBdt' => $this->totalRevenueBdt,
            'paidInvoicesCount' => $this->paidInvoicesCount,
            'workspaceId' => $this->workspaceId,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
        ];
    }

    /**
     * Reconstruct DTO from associative array representation.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            totalMembers: (int) ($data['totalMembers'] ?? 0),
            memberStatusBreakdown: (array) ($data['memberStatusBreakdown'] ?? []),
            totalLeads: (int) ($data['totalLeads'] ?? 0),
            leadPipelineBreakdown: (array) ($data['leadPipelineBreakdown'] ?? []),
            conversionRate: (float) ($data['conversionRate'] ?? 0.0),
            totalIdCards: (int) ($data['totalIdCards'] ?? 0),
            idCardStatusBreakdown: (array) ($data['idCardStatusBreakdown'] ?? []),
            totalRevenueBdt: (float) ($data['totalRevenueBdt'] ?? 0.0),
            paidInvoicesCount: (int) ($data['paidInvoicesCount'] ?? 0),
            workspaceId: isset($data['workspaceId']) ? (string) $data['workspaceId'] : null,
            dateFrom: isset($data['dateFrom']) ? (string) $data['dateFrom'] : null,
            dateTo: isset($data['dateTo']) ? (string) $data['dateTo'] : null,
        );
    }
}

