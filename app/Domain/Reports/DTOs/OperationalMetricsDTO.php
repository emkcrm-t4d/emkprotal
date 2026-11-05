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
}

