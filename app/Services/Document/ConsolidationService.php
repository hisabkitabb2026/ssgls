<?php

namespace App\Services\Document;

use App\Models\ConsolidationGroup;
use App\Models\WarehouseItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consolidation Service
 *
 * Manages the Part-Load consolidation workflow:
 * - Creating consolidation groups by destination
 * - Adding/removing warehouse items to/from groups
 * - Recalculating group aggregates (weight, packages, item count)
 * - Marking groups ready for dispatch
 *
 * Future scaling:
 * - AI-based consolidation recommendations (optimal grouping by weight/route)
 * - Auto-fill threshold configuration per route
 * - Multi-trip splitting for over-capacity groups
 */
class ConsolidationService
{
    /**
     * Generate the next sequential group number for a company.
     * Format: CONS-{YEAR}-{0001}
     */
    public function generateGroupNumber(int $companyId): string
    {
        $year = now()->year;
        $prefix = "CONS-{$year}-";

        $lastGroup = ConsolidationGroup::forCompany($companyId)
            ->where('group_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->first();

        $next = 1;
        if ($lastGroup) {
            $parts = explode('-', $lastGroup->group_number);
            $next = (int) end($parts) + 1;
        }

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create a new consolidation group for a destination.
     */
    public function createGroup(int $companyId, array $data): ConsolidationGroup
    {
        return ConsolidationGroup::create([
            'company_id' => $companyId,
            'group_number' => $this->generateGroupNumber($companyId),
            'destination_city' => $data['destination_city'],
            'truck_capacity_kg' => $data['truck_capacity_kg'] ?? 9000,
            'status' => ConsolidationGroup::STATUS_OPEN,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Update a consolidation group.
     */
    public function updateGroup(ConsolidationGroup $group, array $data): ConsolidationGroup
    {
        $group->update([
            'truck_capacity_kg' => $data['truck_capacity_kg'] ?? $group->truck_capacity_kg,
            'notes' => $data['notes'] ?? $group->notes,
            'status' => $data['status'] ?? $group->status,
        ]);

        return $group->fresh();
    }

    /**
     * Get a consolidation group by company and ID, with items loaded.
     */
    public function getByCompanyAndId(int $companyId, int $groupId): ?ConsolidationGroup
    {
        return ConsolidationGroup::forCompany($companyId)
            ->with(['items.lr.customer', 'loadTrips'])
            ->find($groupId);
    }

    /**
     * Get all consolidation groups for a company, optionally filtered.
     */
    public function getCompanyGroups(int $companyId, array $filters = []): Collection
    {
        $query = ConsolidationGroup::forCompany($companyId)
            ->with(['items.lr.customer']);

        if (! empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        if (! empty($filters['destination'])) {
            $query->byDestination($filters['destination']);
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * Add a warehouse item to a consolidation group.
     * The item must be stored, unassigned, and destined for the same route.
     */
    public function addItemToGroup(int $companyId, int $groupId, int $itemId): ConsolidationGroup
    {
        $group = ConsolidationGroup::forCompany($companyId)->findOrFail($groupId);
        $item = WarehouseItem::forCompany($companyId)->findOrFail($itemId);

        // Validate item is available for consolidation
        if ($item->consolidation_id !== null) {
            throw new \DomainException('Item is already assigned to a consolidation group.');
        }

        if ($item->status !== WarehouseItem::STATUS_STORED) {
            throw new \DomainException('Only stored items can be added to a consolidation group.');
        }

        if ($item->destination_city !== $group->destination_city) {
            throw new \DomainException('Item destination does not match the consolidation group destination.');
        }

        DB::transaction(function () use ($item, $group) {
            $item->update([
                'consolidation_id' => $group->id,
                'status' => WarehouseItem::STATUS_PICKED,
            ]);

            $this->recalculateAggregates($group);
        });

        return $group->fresh('items.lr.customer');
    }

    /**
     * Remove a warehouse item from a consolidation group.
     */
    public function removeItemFromGroup(int $companyId, int $groupId, int $itemId): ConsolidationGroup
    {
        $group = ConsolidationGroup::forCompany($companyId)->findOrFail($groupId);
        $item = WarehouseItem::forCompany($companyId)->findOrFail($itemId);

        if ($item->consolidation_id !== $group->id) {
            throw new \DomainException('Item does not belong to this consolidation group.');
        }

        DB::transaction(function () use ($item, $group) {
            $item->update([
                'consolidation_id' => null,
                'status' => WarehouseItem::STATUS_STORED,
            ]);

            $this->recalculateAggregates($group);
        });

        return $group->fresh('items.lr.customer');
    }

    /**
     * Mark a consolidation group as ready for dispatch.
     */
    public function markReady(ConsolidationGroup $group): ConsolidationGroup
    {
        if ($group->total_items === 0) {
            throw new \DomainException('Cannot mark an empty group as ready.');
        }

        $group->update(['status' => ConsolidationGroup::STATUS_READY]);

        return $group->fresh();
    }

    /**
     * Get consolidation candidates: stored, unassigned items grouped by destination.
     * Used by the consolidation board to show what's available to consolidate.
     */
    public function getConsolidationCandidates(int $companyId, ?string $destination = null): array
    {
        $query = WarehouseItem::forCompany($companyId)
            ->readyForConsolidation()
            ->with(['lr.customer']);

        if ($destination) {
            $query->byDestination($destination);
        }

        $items = $query->orderBy('date_received')->get();

        // Group by destination
        $grouped = [];
        foreach ($items as $item) {
            $dest = $item->destination_city ?: 'Unknown';
            if (! isset($grouped[$dest])) {
                $grouped[$dest] = [
                    'destination' => $dest,
                    'items' => [],
                    'total_weight_kg' => 0,
                    'total_packages' => 0,
                    'item_count' => 0,
                    'oldest_days' => 0,
                    'overdue_count' => 0,
                ];
            }

            $grouped[$dest]['items'][] = $item;
            $grouped[$dest]['total_weight_kg'] += (float) $item->weight_kg;
            $grouped[$dest]['total_packages'] += (int) $item->no_of_packages;
            $grouped[$dest]['item_count']++;

            $days = $item->days_in_warehouse ?? 0;
            if ($days > $grouped[$dest]['oldest_days']) {
                $grouped[$dest]['oldest_days'] = $days;
            }

            if ($item->is_overdue) {
                $grouped[$dest]['overdue_count']++;
            }
        }

        return array_values($grouped);
    }

    /**
     * Recalculate the aggregate fields on a consolidation group.
     */
    public function recalculateAggregates(ConsolidationGroup $group): void
    {
        $items = $group->items()->get();

        $group->update([
            'total_weight_kg' => $items->sum(fn ($i) => (float) $i->weight_kg),
            'total_packages' => $items->sum(fn ($i) => (int) $i->no_of_packages),
            'total_items' => $items->count(),
        ]);
    }

    /**
     * Delete a consolidation group (only if open and has no dispatched items).
     */
    public function deleteGroup(ConsolidationGroup $group): bool
    {
        if (in_array($group->status, [ConsolidationGroup::STATUS_DISPATCHED, ConsolidationGroup::STATUS_COMPLETED])) {
            throw new \DomainException('Cannot delete a dispatched or completed consolidation group.');
        }

        // Unassign all items
        DB::transaction(function () use ($group) {
            $group->items()->update([
                'consolidation_id' => null,
                'status' => WarehouseItem::STATUS_STORED,
            ]);

            $group->delete();
        });

        return true;
    }
}
