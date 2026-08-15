<?php

namespace App\Services\Document;

use App\Models\ConsolidationGroup;
use App\Models\LoadTrip;
use App\Models\WarehouseItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Load Trip Service (Truck Dispatch)
 *
 * Manages the truck dispatch workflow:
 * - Creating load trips from consolidation groups (or direct full-load dispatches)
 * - Dispatching trips (sets all linked items to in_transit)
 * - Marking trips as delivered (sets all linked items to delivered)
 *
 * Future scaling:
 * // TODO: Integrate real GPS tracking API for live truck location
 * // TODO: Integrate Fastag API for toll tracking
 * // TODO: Add E-Way Bill API integration for compliance
 * // TODO: Support multi-leg trips (trip_segments table)
 */
class LoadTripService
{
    /**
     * Generate the next sequential trip number for a company.
     * Format: TRIP-{YEAR}-{0001}
     */
    public function generateTripNumber(int $companyId): string
    {
        $year = now()->year;
        $prefix = "TRIP-{$year}-";

        $lastTrip = LoadTrip::forCompany($companyId)
            ->where('trip_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->first();

        $next = 1;
        if ($lastTrip) {
            $parts = explode('-', $lastTrip->trip_number);
            $next = (int) end($parts) + 1;
        }

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create a new load trip, optionally linked to a consolidation group.
     */
    public function createTrip(int $companyId, array $data): LoadTrip
    {
        $trip = LoadTrip::create([
            'company_id' => $companyId,
            'consolidation_group_id' => $data['consolidation_group_id'] ?? null,
            'trip_number' => $this->generateTripNumber($companyId),
            'truck_number' => $data['truck_number'] ?? null,
            'driver_name' => $data['driver_name'] ?? null,
            'driver_phone' => $data['driver_phone'] ?? null,
            'origin_city' => $data['origin_city'] ?? null,
            'destination_city' => $data['destination_city'],
            'dispatch_date' => $data['dispatch_date'] ?? null,
            'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
            'status' => LoadTrip::STATUS_PLANNED,
            'notes' => $data['notes'] ?? null,
        ]);

        // If linked to a consolidation group, link all its items to this trip
        if (! empty($data['consolidation_group_id'])) {
            $group = ConsolidationGroup::find($data['consolidation_group_id']);
            if ($group) {
                $group->items()->update(['delivery_id' => $trip->id]);
            }
        }

        return $trip;
    }

    /**
     * Update a load trip.
     */
    public function updateTrip(LoadTrip $trip, array $data): LoadTrip
    {
        $trip->update([
            'truck_number' => $data['truck_number'] ?? $trip->truck_number,
            'driver_name' => $data['driver_name'] ?? $trip->driver_name,
            'driver_phone' => $data['driver_phone'] ?? $trip->driver_phone,
            'dispatch_date' => $data['dispatch_date'] ?? $trip->dispatch_date,
            'expected_delivery_date' => $data['expected_delivery_date'] ?? $trip->expected_delivery_date,
            'actual_delivery_date' => $data['actual_delivery_date'] ?? $trip->actual_delivery_date,
            'status' => $data['status'] ?? $trip->status,
            'notes' => $data['notes'] ?? $trip->notes,
        ]);

        return $trip->fresh();
    }

    /**
     * Get a load trip by company and ID.
     */
    public function getByCompanyAndId(int $companyId, int $tripId): ?LoadTrip
    {
        return LoadTrip::forCompany($companyId)
            ->with(['consolidationGroup.items.lr.customer', 'warehouseItems.lr.customer'])
            ->find($tripId);
    }

    /**
     * Get all load trips for a company, optionally filtered.
     */
    public function getCompanyTrips(int $companyId, array $filters = []): Collection
    {
        $query = LoadTrip::forCompany($companyId)
            ->with(['consolidationGroup']);

        if (! empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        if (! empty($filters['destination'])) {
            $query->byDestination($filters['destination']);
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * Dispatch a load trip: set status to dispatched and update all linked items.
     */
    public function dispatchTrip(LoadTrip $trip): LoadTrip
    {
        DB::transaction(function () use ($trip) {
            $trip->update([
                'status' => LoadTrip::STATUS_DISPATCHED,
                'dispatch_date' => $trip->dispatch_date ?? now(),
            ]);

            // Update all linked warehouse items to in_transit
            $trip->warehouseItems()->update([
                'status' => WarehouseItem::STATUS_IN_TRANSIT,
            ]);

            // Update consolidation group status if linked
            if ($trip->consolidationGroup) {
                $trip->consolidationGroup->update([
                    'status' => ConsolidationGroup::STATUS_DISPATCHED,
                ]);
            }
        });

        return $trip->fresh();
    }

    /**
     * Mark a load trip as delivered: set status and update all linked items.
     */
    public function markDelivered(LoadTrip $trip): LoadTrip
    {
        DB::transaction(function () use ($trip) {
            $trip->update([
                'status' => LoadTrip::STATUS_DELIVERED,
                'actual_delivery_date' => $trip->actual_delivery_date ?? now()->toDateString(),
            ]);

            // Update all linked warehouse items to delivered
            $trip->warehouseItems()->update([
                'status' => WarehouseItem::STATUS_DELIVERED,
            ]);

            // Update consolidation group status if linked
            if ($trip->consolidationGroup) {
                $trip->consolidationGroup->update([
                    'status' => ConsolidationGroup::STATUS_COMPLETED,
                ]);
            }
        });

        return $trip->fresh();
    }

    /**
     * Delete a load trip (only if planned, not yet dispatched).
     */
    public function deleteTrip(LoadTrip $trip): bool
    {
        if ($trip->status !== LoadTrip::STATUS_PLANNED) {
            throw new \DomainException('Cannot delete a dispatched or delivered trip.');
        }

        DB::transaction(function () use ($trip) {
            // Unlink warehouse items
            $trip->warehouseItems()->update(['delivery_id' => null]);
            $trip->delete();
        });

        return true;
    }
}
