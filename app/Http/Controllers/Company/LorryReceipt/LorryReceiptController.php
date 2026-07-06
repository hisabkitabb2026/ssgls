<?php

namespace App\Http\Controllers\Company\LorryReceipt;

use App\Http\Controllers\Controller;
use App\Http\Resources\LorryReceiptResource;
use App\Models\Customer;
use App\Models\LorryReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LorryReceiptController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        $query = LorryReceipt::whereCompany()
            ->with(['ownerCustomer', 'driverCustomer', 'brokerCustomer']);

        $lorryReceipts = $query->applyFilters($request->all())
            ->paginate($limit);

        return response()->json([
            'data' => LorryReceiptResource::collection($lorryReceipts),
            'meta' => [
                'total' => $lorryReceipts->total(),
                'current_page' => $lorryReceipts->currentPage(),
                'per_page' => $lorryReceipts->perPage(),
                'last_page' => $lorryReceipts->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $lorryReceipt = LorryReceipt::whereCompany()
            ->with(['ownerCustomer', 'driverCustomer', 'brokerCustomer', 'company'])
            ->findOrFail($id);

        return response()->json([
            'data' => new LorryReceiptResource($lorryReceipt),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->all();
        $data['company_id'] = $request->header('company');
        $data['creator_id'] = $request->user()->id;

        // Auto-fill owner/driver/broker details from customer records
        $this->fillPartyDetails($data);

        $lorryReceipt = LorryReceipt::create($data);

        return response()->json([
            'data' => new LorryReceiptResource($lorryReceipt),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $lorryReceipt = LorryReceipt::whereCompany()->findOrFail($id);
        $data = $request->all();
        $data['updated_by'] = $request->user()->id;

        // Auto-fill owner/driver/broker details from customer records
        $this->fillPartyDetails($data);

        // Track modification dates
        $modifiedDates = $lorryReceipt->modified_dates ?? [];
        $modifiedDates[] = now()->toDateTimeString();
        $data['date_modified'] = now();
        $data['modified_dates'] = $modifiedDates;

        $lorryReceipt->update($data);

        return response()->json([
            'data' => new LorryReceiptResource($lorryReceipt->fresh()),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $ids = $request->get('ids', []);
        LorryReceipt::whereCompany()->whereIn('id', $ids)->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Auto-fill owner/driver/broker details from their customer records.
     * When a customer_id is provided, copy name/address/phone into the
     * denormalized fields for PDF generation.
     */
    private function fillPartyDetails(array &$data): void
    {
        if (! empty($data['owner_customer_id'])) {
            $owner = Customer::find($data['owner_customer_id']);
            if ($owner) {
                $data['owner_name'] = $data['owner_name'] ?? $owner->name;
                $data['owner_phone'] = $data['owner_phone'] ?? $owner->phone;
                $data['owner_address'] = $data['owner_address'] ?? $owner->billingAddress?->address_street_1;
            }
        }

        if (! empty($data['driver_customer_id'])) {
            $driver = Customer::find($data['driver_customer_id']);
            if ($driver) {
                $data['driver_name'] = $data['driver_name'] ?? $driver->name;
                $data['driver_address'] = $data['driver_address'] ?? $driver->billingAddress?->address_street_1;
            }
        }

        if (! empty($data['broker_customer_id'])) {
            $broker = Customer::find($data['broker_customer_id']);
            if ($broker) {
                $data['broker_name'] = $data['broker_name'] ?? $broker->name;
                $data['broker_phone'] = $data['broker_phone'] ?? $broker->phone;
                $data['broker_address'] = $data['broker_address'] ?? $broker->billingAddress?->address_street_1;
            }
        }
    }
}
