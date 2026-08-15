<?php

namespace App\Http\Controllers\Company\LoadTrip;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLoadTripRequest;
use App\Http\Requests\UpdateLoadTripRequest;
use App\Http\Resources\LoadTripResource;
use App\Models\LoadTrip;
use App\Services\Document\LoadTripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LoadTripController extends Controller
{
    public function __construct(
        private LoadTripService $service
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', LoadTrip::class);

        $filters = [
            'status' => $request->query('status'),
            'destination' => $request->query('destination'),
        ];

        $trips = $this->service->getCompanyTrips($request->header('company'), $filters);

        return LoadTripResource::collection($trips);
    }

    public function show(Request $request, int $id): LoadTripResource
    {
        $trip = $this->service->getByCompanyAndId($request->header('company'), $id);

        abort_if(! $trip, 404);

        $this->authorize('view', $trip);

        return new LoadTripResource($trip);
    }

    public function store(StoreLoadTripRequest $request): LoadTripResource
    {
        $this->authorize('create', LoadTrip::class);

        $trip = $this->service->createTrip(
            $request->header('company'),
            $request->validated()
        );

        return new LoadTripResource($trip->load('consolidationGroup'));
    }

    public function update(UpdateLoadTripRequest $request, int $id): LoadTripResource
    {
        $trip = $this->service->getByCompanyAndId($request->header('company'), $id);

        abort_if(! $trip, 404);

        $this->authorize('update', $trip);

        $updated = $this->service->updateTrip($trip, $request->validated());

        return new LoadTripResource($updated);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $trip = $this->service->getByCompanyAndId($request->header('company'), $id);

        abort_if(! $trip, 404);

        $this->authorize('delete', $trip);

        $this->service->deleteTrip($trip);

        return response()->json(['message' => 'Load trip deleted']);
    }

    /**
     * Dispatch a load trip: set status to dispatched and update all linked items.
     * Note: Method is named dispatchTrip (not dispatch) to avoid conflict with
     * the base Controller::dispatch($job) method used for queueing jobs.
     */
    public function dispatchTrip(Request $request, int $id): LoadTripResource
    {
        $trip = $this->service->getByCompanyAndId($request->header('company'), $id);

        abort_if(! $trip, 404);

        $this->authorize('update', $trip);

        $updated = $this->service->dispatchTrip($trip);

        return new LoadTripResource($updated);
    }

    /**
     * Mark a load trip as delivered.
     */
    public function markDelivered(Request $request, int $id): LoadTripResource
    {
        $trip = $this->service->getByCompanyAndId($request->header('company'), $id);

        abort_if(! $trip, 404);

        $this->authorize('update', $trip);

        $updated = $this->service->markDelivered($trip);

        return new LoadTripResource($updated);
    }
}
