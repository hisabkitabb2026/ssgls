<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseItem extends Model
{
use HasFactory;

protected $guarded = ['id'];

protected function casts(): array
{
  return [
      'date_received' => 'datetime',
      'created_at' => 'datetime',
      'updated_at' => 'datetime',
  ];
}

public const STATUS_STORED = 'stored';

public const STATUS_PICKED = 'picked_for_consolidation';

public const STATUS_LOADED = 'loaded_on_vehicle';

public const STATUS_IN_TRANSIT = 'in_transit';

public const STATUS_DELIVERED = 'delivered';

public const STATUS_CANCELLED = 'cancelled';

public static function getStatuses(): array
{
  return [
      self::STATUS_STORED,
      self::STATUS_PICKED,
      self::STATUS_LOADED,
      self::STATUS_IN_TRANSIT,
      self::STATUS_DELIVERED,
      self::STATUS_CANCELLED,
  ];
}

public function company(): BelongsTo
{
  return $this->belongsTo(Company::class);
}

public function lr(): BelongsTo
{
  return $this->belongsTo(Invoice::class, 'lr_id');
}

public function consolidation(): BelongsTo
{
  return $this->belongsTo(ConsolidationGroup::class, 'consolidation_id');
}

public function delivery(): BelongsTo
{
  return $this->belongsTo(Delivery::class);
}

public function getDaysInWarehouseAttribute(): ?int
{
  if (! $this->date_received) {
      return null;
  }

  return now()->diffInDays($this->date_received);
}

public function scopeForCompany($query, int $companyId)
{
  return $query->where('company_id', $companyId);
}

public function scopeStored($query)
{
  return $query->where('status', self::STATUS_STORED);
}

public function scopeByDestination($query, string $destination)
{
  return $query->where('destination_city', $destination);
}

public function scopeByLocation($query, string $location)
{
  return $query->where('warehouse_location', $location);
}

public function scopeByStatus($query, string $status)
{
  return $query->where('status', $status);
}

public function scopeReadyForConsolidation($query)
{
  return $query->where('status', self::STATUS_STORED);
}
}