<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\LorryPartyProfile;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

class LorryPartyProfilePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if (BouncerFacade::can('view-invoice', Invoice::class)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LorryPartyProfile $lorryPartyProfile): bool
    {
        if (BouncerFacade::can('view-invoice', Invoice::class) && $user->hasCompany($lorryPartyProfile->company_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if (BouncerFacade::can('create-invoice', Invoice::class)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LorryPartyProfile $lorryPartyProfile): bool
    {
        if (BouncerFacade::can('edit-invoice', Invoice::class) && $user->hasCompany($lorryPartyProfile->company_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LorryPartyProfile $lorryPartyProfile): bool
    {
        if (BouncerFacade::can('delete-invoice', Invoice::class) && $user->hasCompany($lorryPartyProfile->company_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, LorryPartyProfile $lorryPartyProfile): bool
    {
        if (BouncerFacade::can('delete-invoice', Invoice::class) && $user->hasCompany($lorryPartyProfile->company_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, LorryPartyProfile $lorryPartyProfile): bool
    {
        if (BouncerFacade::can('delete-invoice', Invoice::class) && $user->hasCompany($lorryPartyProfile->company_id)) {
            return true;
        }

        return false;
    }
}
