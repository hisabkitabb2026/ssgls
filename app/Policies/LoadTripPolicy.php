<?php

namespace App\Policies;

use App\Models\LoadTrip;
use App\Models\User;
use Silber\Bouncer\Bouncer;

class LoadTripPolicy
{
    public function viewAny(User $user, ?Bouncer $bouncer = null): bool
    {
        return true;
    }

    public function view(User $user, LoadTrip $trip, ?Bouncer $bouncer = null): bool
    {
        return true;
    }

    public function create(User $user, ?Bouncer $bouncer = null): bool
    {
        return $bouncer?->can('create-load-trip') ?? true;
    }

    public function update(User $user, LoadTrip $trip, ?Bouncer $bouncer = null): bool
    {
        return $bouncer?->can('edit-load-trip') ?? true;
    }

    public function delete(User $user, LoadTrip $trip, ?Bouncer $bouncer = null): bool
    {
        return $bouncer?->can('delete-load-trip') ?? true;
    }
}
