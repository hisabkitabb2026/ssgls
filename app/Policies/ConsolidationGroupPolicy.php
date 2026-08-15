<?php

namespace App\Policies;

use App\Models\ConsolidationGroup;
use App\Models\User;
use Silber\Bouncer\Bouncer;

class ConsolidationGroupPolicy
{
    public function viewAny(User $user, ?Bouncer $bouncer = null): bool
    {
        return true;
    }

    public function view(User $user, ConsolidationGroup $group, ?Bouncer $bouncer = null): bool
    {
        return true;
    }

    public function create(User $user, ?Bouncer $bouncer = null): bool
    {
        return $bouncer?->can('create-consolidation-group') ?? true;
    }

    public function update(User $user, ConsolidationGroup $group, ?Bouncer $bouncer = null): bool
    {
        return $bouncer?->can('edit-consolidation-group') ?? true;
    }

    public function delete(User $user, ConsolidationGroup $group, ?Bouncer $bouncer = null): bool
    {
        return $bouncer?->can('delete-consolidation-group') ?? true;
    }
}
