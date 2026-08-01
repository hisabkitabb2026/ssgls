<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Models\WarehouseItem;
use Illuminate\Http\Request;
use Silber\Bouncer\Bouncer;

class WarehouseItemPolicy
{
public function __construct(private Request $request) {}

private function getCompanyId(): ?int
{
  // Get company from request header (set by CompanyMiddleware)
  return $this->request->header('company');
}

public function viewAny(User $user, ?Bouncer $bouncer = null): bool
{
  // Allow viewing warehouse items for all authenticated users in the company
  return true;
}

public function view(User $user, WarehouseItem $item, ?Bouncer $bouncer = null): bool
{
  // Allow viewing if item belongs to the active company
  return $item->company_id === $this->getCompanyId();
}

public function create(User $user, ?Bouncer $bouncer = null): bool
{
  // Allow creation for all authenticated users in the company
  return true;
}

public function update(User $user, WarehouseItem $item, ?Bouncer $bouncer = null): bool
{
  // Allow updating if item belongs to the active company
  return $item->company_id === $this->getCompanyId();
}

public function delete(User $user, WarehouseItem $item, ?Bouncer $bouncer = null): bool
{
  // Allow deletion if item belongs to the active company
  return $item->company_id === $this->getCompanyId();
}
}