<?php

declare(strict_types=1);

namespace App\Domain\Reports\Services;

use App\Domain\AccessControl\Exceptions\TenancyBoundaryException;
use App\Domain\IdCards\Models\IdCard;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Domain\Reports\DTOs\OperationalMetricsDTO;
use App\Domain\Tenancy\Scopes\TenantScope;
use App\Domain\Workspaces\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MetricsAggregationService
{
    public const CACHE_TTL_SECONDS = 300; // 5 minutes

    /**
     * Compute operational metrics scoped to a specific workspace and optional date range.
     */
    public function getMetrics(
        string $workspaceId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        bool $useCache = true
    ): OperationalMetricsDTO {
        $normalizedDateFrom = !empty($dateFrom) ? Carbon::parse($dateFrom)->toDateString() : null;
        $normalizedDateTo = !empty($dateTo) ? Carbon::parse($dateTo)->toDateString() : null;

        $cacheKey = "metrics_{$workspaceId}_{$normalizedDateFrom}_{$normalizedDateTo}";

        if ($useCache) {
            $this->trackCacheKey($workspaceId, $cacheKey);

            return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($workspaceId, $normalizedDateFrom, $normalizedDateTo) {
                return $this->computeMetrics($workspaceId, $normalizedDateFrom, $normalizedDateTo);
            });
        }

        $metrics = $this->computeMetrics($workspaceId, $normalizedDateFrom, $normalizedDateTo);
        $this->trackCacheKey($workspaceId, $cacheKey);
        Cache::put($cacheKey, $metrics, self::CACHE_TTL_SECONDS);

        return $metrics;
    }

    /**
     * Invalidate cached metrics for a workspace.
     */
    public function clearMetricsCache(string $workspaceId): void
    {
        $keysTrackingKey = "metrics_tracked_keys_{$workspaceId}";
        $trackedKeys = Cache::get($keysTrackingKey, []);

        if (is_array($trackedKeys)) {
            foreach ($trackedKeys as $key) {
                Cache::forget($key);
            }
        }

        // Also forget standard presets
        Cache::forget("metrics_{$workspaceId}__");
        Cache::forget($keysTrackingKey);
    }

    /**
     * Compute global multi-workspace metrics aggregated across all American Spaces.
     * Accessible exclusively to superadmin or global_admin per DASH-02 and DEC-01.
     *
     * @return array<string, mixed>
     * @throws TenancyBoundaryException
     */
    public function getGlobalMetrics(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $user = Auth::user();
        $isElevatedAdmin = $user !== null
            && method_exists($user, 'hasRole')
            && $user->hasRole(['superadmin', 'global_admin']);

        if (!$isElevatedAdmin) {
            throw new TenancyBoundaryException('Unauthorized: Multi-workspace aggregation requires Superadmin privileges.');
        }

        $normalizedDateFrom = !empty($dateFrom) ? Carbon::parse($dateFrom)->toDateString() : null;
        $normalizedDateTo = !empty($dateTo) ? Carbon::parse($dateTo)->toDateString() : null;

        // Fetch all workspaces with their associated space codes
        $workspaces = DB::table('workspaces')
            ->leftJoin('spaces', 'workspaces.space_id', '=', 'spaces.id')
            ->select([
                'workspaces.id as workspace_id',
                'workspaces.name as workspace_name',
                'spaces.code as space_code',
                'spaces.name as space_name',
            ])
            ->get();

        $spacesBreakdown = [];
        $globalTotalMembers = 0;
        $globalTotalLeads = 0;
        $globalConvertedLeads = 0;
        $globalTotalIdCards = 0;
        $globalTotalRevenue = 0.0;
        $globalPaidInvoicesCount = 0;

        foreach ($workspaces as $ws) {
            $metrics = $this->computeMetrics($ws->workspace_id, $normalizedDateFrom, $normalizedDateTo);

            $spaceCode = $ws->space_code ?? $ws->workspace_id;
            $spacesBreakdown[$spaceCode] = [
                'workspace_id' => $ws->workspace_id,
                'space_code' => $spaceCode,
                'space_name' => $ws->space_name ?? $ws->workspace_name,
                'total_members' => $metrics->totalMembers,
                'active_members' => $metrics->memberStatusBreakdown['active'] ?? 0,
                'total_leads' => $metrics->totalLeads,
                'converted_leads' => $metrics->leadPipelineBreakdown['converted'] ?? 0,
                'conversion_rate' => $metrics->conversionRate,
                'total_id_cards' => $metrics->totalIdCards,
                'total_revenue_bdt' => $metrics->totalRevenueBdt,
                'paid_invoices_count' => $metrics->paidInvoicesCount,
            ];

            $globalTotalMembers += $metrics->totalMembers;
            $globalTotalLeads += $metrics->totalLeads;
            $globalConvertedLeads += ($metrics->leadPipelineBreakdown['converted'] ?? 0);
            $globalTotalIdCards += $metrics->totalIdCards;
            $globalTotalRevenue += $metrics->totalRevenueBdt;
            $globalPaidInvoicesCount += $metrics->paidInvoicesCount;
        }

        $globalConversionRate = $globalTotalLeads > 0
            ? (float) round(($globalConvertedLeads / $globalTotalLeads) * 100, 2)
            : 0.0;

        return [
            'total_members' => $globalTotalMembers,
            'total_leads' => $globalTotalLeads,
            'conversion_rate' => $globalConversionRate,
            'total_id_cards' => $globalTotalIdCards,
            'total_revenue_bdt' => round($globalTotalRevenue, 2),
            'paid_invoices_count' => $globalPaidInvoicesCount,
            'spaces_breakdown' => $spacesBreakdown,
            'date_from' => $normalizedDateFrom,
            'date_to' => $normalizedDateTo,
        ];
    }

    /**
     * Core computation logic executing database-level aggregates scoped to workspace.
     */
    protected function computeMetrics(
        string $workspaceId,
        ?string $dateFrom,
        ?string $dateTo
    ): OperationalMetricsDTO {
        // 1. Members Aggregation
        $memberQuery = Member::withoutGlobalScope(TenantScope::class)->where('workspace_id', $workspaceId);
        $this->applyDateFilter($memberQuery, 'created_at', $dateFrom, $dateTo);

        $memberCounts = (clone $memberQuery)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $memberStatusBreakdown = [
            'active' => (int) ($memberCounts['active'] ?? 0),
            'inactive' => (int) ($memberCounts['inactive'] ?? 0),
            'restricted' => (int) ($memberCounts['restricted'] ?? 0),
            'revoked' => (int) ($memberCounts['revoked'] ?? 0),
            'archived_reversed' => (int) ($memberCounts['archived_reversed'] ?? 0),
        ];
        $totalMembers = (int) $memberQuery->count();

        // 2. Leads Pipeline Aggregation
        $leadQuery = Lead::withoutGlobalScope(TenantScope::class)->where('workspace_id', $workspaceId);
        $this->applyDateFilter($leadQuery, 'created_at', $dateFrom, $dateTo);

        $leadCounts = (clone $leadQuery)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $leadPipelineBreakdown = [
            'draft' => (int) ($leadCounts['draft'] ?? 0),
            'submitted' => (int) ($leadCounts['submitted'] ?? 0),
            'under_review' => (int) ($leadCounts['under_review'] ?? 0),
            'missing_items' => (int) ($leadCounts['missing_items'] ?? 0),
            'approved' => (int) ($leadCounts['approved'] ?? 0),
            'converted' => (int) ($leadCounts['converted'] ?? 0),
            'rejected' => (int) ($leadCounts['rejected'] ?? 0),
            'blocked' => (int) ($leadCounts['blocked'] ?? 0),
        ];
        $totalLeads = (int) $leadQuery->count();
        $convertedCount = (int) ($leadPipelineBreakdown['converted'] ?? 0);

        // Mathematical zero-division safety per DASH-01
        $conversionRate = $totalLeads > 0
            ? (float) round(($convertedCount / $totalLeads) * 100, 2)
            : 0.0;

        // 3. ID Cards Aggregation
        $idCardQuery = IdCard::withoutGlobalScope(TenantScope::class)->where('workspace_id', $workspaceId);
        $this->applyDateFilter($idCardQuery, 'created_at', $dateFrom, $dateTo);

        $idCardCounts = (clone $idCardQuery)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $idCardStatusBreakdown = [
            'provided_for_printing' => (int) ($idCardCounts['provided_for_printing'] ?? 0),
            'card_printed_ready_for_pickup' => (int) ($idCardCounts['card_printed_ready_for_pickup'] ?? 0),
            'delivered' => (int) ($idCardCounts['delivered'] ?? 0),
            'superseded_replaced' => (int) ($idCardCounts['superseded_replaced'] ?? 0),
            'voided_reversal' => (int) ($idCardCounts['voided_reversal'] ?? 0),
        ];
        $totalIdCards = (int) $idCardQuery->count();

        // 4. Invoices and Revenue Aggregation
        $paidInvoiceQuery = Invoice::withoutGlobalScope(TenantScope::class)
            ->where('workspace_id', $workspaceId)
            ->where('status', 'paid');
        $this->applyInvoiceDateFilter($paidInvoiceQuery, $dateFrom, $dateTo);

        $paidInvoicesCount = (int) $paidInvoiceQuery->count();
        $totalRevenueBdt = (float) round((float) ($paidInvoiceQuery->sum('amount_bdt') ?? 0.0), 2);

        return new OperationalMetricsDTO(
            totalMembers: $totalMembers,
            memberStatusBreakdown: $memberStatusBreakdown,
            totalLeads: $totalLeads,
            leadPipelineBreakdown: $leadPipelineBreakdown,
            conversionRate: $conversionRate,
            totalIdCards: $totalIdCards,
            idCardStatusBreakdown: $idCardStatusBreakdown,
            totalRevenueBdt: $totalRevenueBdt,
            paidInvoicesCount: $paidInvoicesCount,
            workspaceId: $workspaceId,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
        );
    }

    /**
     * Apply date range filter to a query on a given timestamp column.
     */
    protected function applyDateFilter($query, string $column, ?string $dateFrom, ?string $dateTo): void
    {
        if (!empty($dateFrom)) {
            $query->where($column, '>=', Carbon::parse($dateFrom)->startOfDay());
        }

        if (!empty($dateTo)) {
            $query->where($column, '<=', Carbon::parse($dateTo)->endOfDay());
        }
    }

    /**
     * Apply date range filter to invoice queries, checking paid_at or created_at.
     */
    protected function applyInvoiceDateFilter($query, ?string $dateFrom, ?string $dateTo): void
    {
        if (empty($dateFrom) && empty($dateTo)) {
            return;
        }

        $from = !empty($dateFrom) ? Carbon::parse($dateFrom)->startOfDay() : null;
        $to = !empty($dateTo) ? Carbon::parse($dateTo)->endOfDay() : null;

        $query->where(function ($q) use ($from, $to) {
            $q->where(function ($paidQuery) use ($from, $to) {
                $paidQuery->whereNotNull('paid_at');
                if ($from) {
                    $paidQuery->where('paid_at', '>=', $from);
                }
                if ($to) {
                    $paidQuery->where('paid_at', '<=', $to);
                }
            })->orWhere(function ($createdQuery) use ($from, $to) {
                $createdQuery->whereNull('paid_at');
                if ($from) {
                    $createdQuery->where('created_at', '>=', $from);
                }
                if ($to) {
                    $createdQuery->where('created_at', '<=', $to);
                }
            });
        });
    }

    /**
     * Track a cache key under the workspace tracking set.
     */
    protected function trackCacheKey(string $workspaceId, string $cacheKey): void
    {
        $keysTrackingKey = "metrics_tracked_keys_{$workspaceId}";
        $trackedKeys = Cache::get($keysTrackingKey, []);

        if (!is_array($trackedKeys)) {
            $trackedKeys = [];
        }

        if (!in_array($cacheKey, $trackedKeys, true)) {
            $trackedKeys[] = $cacheKey;
            Cache::put($keysTrackingKey, $trackedKeys, self::CACHE_TTL_SECONDS * 2);
        }
    }
}

