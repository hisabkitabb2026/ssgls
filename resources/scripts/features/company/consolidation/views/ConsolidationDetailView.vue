<template>
  <div class="p-6">
    <!-- Loading -->
    <div v-if="loading" class="text-center text-muted py-8">Loading...</div>

    <div v-else-if="!group">
      <div class="text-center text-muted py-12">
        <p class="text-lg">Consolidation group not found</p>
        <button
          class="mt-4 px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700"
          @click="$router.push({ name: 'consolidation.index' })"
        >
          Back to Board
        </button>
      </div>
    </div>

    <div v-else>
      <!-- Header -->
      <div class="flex justify-between items-start mb-6 gap-4">
        <div>
          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-heading">{{ group.group_number }}</h1>
            <span class="text-xs px-2 py-1 rounded font-bold" :class="getStatusClass(group.status)">
              {{ group.status }}
            </span>
          </div>
          <p class="text-sm text-muted mt-1">
            Destination: <span class="font-semibold text-body">{{ group.destination_city }}</span>
          </p>
        </div>
        <div class="flex gap-2">
          <button
            class="px-4 py-2 bg-surface-secondary text-body rounded border border-line-default hover:bg-hover"
            @click="$router.push({ name: 'consolidation.index' })"
          >
            ← Back to Board
          </button>
          <button
            v-if="group.status === 'open'"
            class="px-4 py-2 bg-status-green text-white rounded hover:bg-status-green/90"
            :disabled="!group.is_ready_to_dispatch"
            :class="{ 'opacity-50 cursor-not-allowed': !group.is_ready_to_dispatch }"
            @click="markReady"
          >
            ✓ Mark Ready
          </button>
          <button
            v-if="group.status === 'ready'"
            class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700"
            @click="$router.push({ name: 'load-trips.index' })"
          >
            Create Load Trip →
          </button>
        </div>
      </div>

      <!-- Fill Progress -->
      <div class="bg-surface rounded shadow p-4 mb-6">
        <div class="flex justify-between items-center mb-2">
          <h2 class="font-bold text-heading">Truck Fill Status</h2>
          <span
            v-if="group.is_ready_to_dispatch"
            class="text-sm bg-status-green/10 text-status-green px-3 py-1 rounded-full font-semibold"
          >
            ✓ Ready to Dispatch ({{ (group.fill_percentage || 0).toFixed(1) }}%)
          </span>
          <span v-else class="text-sm text-muted">
            {{ (group.fill_percentage || 0).toFixed(1) }}% filled — need 80% to dispatch
          </span>
        </div>
        <div class="w-full bg-surface-tertiary rounded-full h-4 overflow-hidden">
          <div
            class="h-full rounded-full transition-all"
            :class="getFillBarClass(group.fill_percentage)"
            :style="{ width: Math.min(group.fill_percentage || 0, 100) + '%' }"
          ></div>
        </div>
        <div class="flex justify-between text-sm text-muted mt-2">
          <span>{{ formatWeight(group.total_weight_kg) }} kg loaded</span>
          <span>{{ formatWeight(group.truck_capacity_kg) }} kg capacity</span>
        </div>
      </div>

      <!-- Stats Grid -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-surface rounded shadow p-4">
          <p class="text-sm text-muted">Items in Group</p>
          <p class="text-2xl font-bold text-heading">{{ group.total_items || 0 }}</p>
        </div>
        <div class="bg-surface rounded shadow p-4">
          <p class="text-sm text-muted">Total Packages</p>
          <p class="text-2xl font-bold text-heading">{{ group.total_packages || 0 }}</p>
        </div>
        <div class="bg-surface rounded shadow p-4">
          <p class="text-sm text-muted">Total Weight</p>
          <p class="text-2xl font-bold text-heading">{{ formatWeight(group.total_weight_kg) }} kg</p>
        </div>
        <div class="bg-surface rounded shadow p-4">
          <p class="text-sm text-muted">Overdue Items</p>
          <p class="text-2xl font-bold" :class="overdueCount > 0 ? 'text-status-red' : 'text-heading'">
            {{ overdueCount }}
          </p>
        </div>
      </div>

      <!-- Items in Group -->
      <div class="mb-6">
        <h2 class="text-lg font-bold text-heading mb-3">Material in This Group</h2>
        <div v-if="!group.items || group.items.length === 0" class="text-center text-muted py-8 bg-surface-secondary rounded">
          No items assigned to this group yet.
        </div>
        <div v-else class="overflow-x-auto bg-surface rounded shadow">
          <table class="w-full text-sm">
            <thead class="bg-surface-secondary text-muted text-xs uppercase">
              <tr>
                <th class="text-left px-4 py-3">LR Number</th>
                <th class="text-left px-4 py-3">Consignor</th>
                <th class="text-left px-4 py-3">Consignee</th>
                <th class="text-right px-4 py-3">Weight</th>
                <th class="text-right px-4 py-3">Packages</th>
                <th class="text-center px-4 py-3">Days</th>
                <th class="text-center px-4 py-3">Deadline</th>
                <th v-if="group.status === 'open'" class="text-center px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line-light">
              <tr v-for="item in group.items" :key="item.id" class="hover:bg-hover">
                <td class="px-4 py-3 font-semibold text-heading">{{ item.lr?.invoice_number || '-' }}</td>
                <td class="px-4 py-3 text-body">{{ item.consignor_name || item.lr?.customer?.name || '-' }}</td>
                <td class="px-4 py-3 text-body">{{ item.consignee_name || '-' }}</td>
                <td class="px-4 py-3 text-right text-body">{{ formatWeight(item.weight_kg) }} kg</td>
                <td class="px-4 py-3 text-right text-body">{{ item.no_of_packages || 0 }}</td>
                <td class="px-4 py-3 text-center">
                  <span class="font-semibold" :class="getDaysClass(item.days_in_warehouse)">
                    {{ item.days_in_warehouse || 0 }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span v-if="item.promised_dispatch_date" :class="item.is_overdue ? 'text-status-red font-bold' : 'text-body'">
                    {{ formatDate(item.promised_dispatch_date) }}
                    <span v-if="item.is_overdue" class="block text-xs">⚠ OVERDUE</span>
                  </span>
                  <span v-else class="text-muted">—</span>
                </td>
                <td v-if="group.status === 'open'" class="px-4 py-3 text-center">
                  <button
                    class="text-status-red hover:bg-status-red/10 px-2 py-1 rounded text-xs"
                    @click="removeItem(item.id)"
                  >
                    Remove
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add Items Section (only when open) -->
      <div v-if="group.status === 'open'" class="mb-6">
        <h2 class="text-lg font-bold text-heading mb-3">Add Material to This Group</h2>
        <p class="text-sm text-muted mb-3">
          Only unassigned stored items for <span class="font-semibold">{{ group.destination_city }}</span> can be added.
        </p>
        <div v-if="availableItems.length === 0" class="text-center text-muted py-6 bg-surface-secondary rounded">
          No unassigned items available for this destination.
        </div>
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="item in availableItems"
            :key="item.id"
            class="bg-surface rounded shadow p-4 border border-line-light"
          >
            <div class="flex justify-between items-start mb-2">
              <div>
                <p class="font-bold text-heading">{{ item.lr?.invoice_number || '-' }}</p>
                <p class="text-xs text-muted">{{ item.consignor_name || item.lr?.customer?.name || '-' }}</p>
              </div>
              <button
                class="px-3 py-1 bg-primary-600 text-white text-xs rounded hover:bg-primary-700"
                @click="addItem(item.id)"
              >
                + Add
              </button>
            </div>
            <div class="grid grid-cols-3 gap-2 text-xs">
              <div>
                <p class="text-muted">Weight</p>
                <p class="font-semibold text-body">{{ formatWeight(item.weight_kg) }} kg</p>
              </div>
              <div>
                <p class="text-muted">Packages</p>
                <p class="font-semibold text-body">{{ item.no_of_packages || 0 }}</p>
              </div>
              <div>
                <p class="text-muted">Days</p>
                <p class="font-semibold" :class="getDaysClass(item.days_in_warehouse)">
                  {{ item.days_in_warehouse || 0 }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Load Trips -->
      <div v-if="group.load_trips && group.load_trips.length > 0" class="mb-6">
        <h2 class="text-lg font-bold text-heading mb-3">Load Trips from This Group</h2>
        <div class="space-y-3">
          <div
            v-for="trip in group.load_trips"
            :key="trip.id"
            class="bg-surface rounded shadow p-4 flex justify-between items-center cursor-pointer hover:shadow-md transition"
            @click="$router.push({ name: 'load-trips.show', params: { id: trip.id } })"
          >
            <div>
              <p class="font-bold text-heading">{{ trip.trip_number }}</p>
              <p class="text-sm text-muted">
                {{ trip.truck_number }} · {{ trip.driver_name }}
              </p>
            </div>
            <div class="flex items-center gap-4">
              <span class="text-xs px-2 py-1 rounded font-bold" :class="getTripStatusClass(trip.status)">
                {{ trip.status }}
              </span>
              <span class="text-muted">→</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useConsolidationStore } from '../store'
import { useWarehouseItemStore } from '../../warehouse-items/store'

const route = useRoute()
const store = useConsolidationStore()
const warehouseStore = useWarehouseItemStore()
const loading = ref(true)

const group = computed(() => store.currentGroup)

const overdueCount = computed(() => {
  if (!group.value?.items) return 0
  return group.value.items.filter((i: any) => i.is_overdue).length
})

const availableItems = computed(() => {
  if (!group.value) return []
  return warehouseStore.items.filter((item: any) => {
    return (
      item.status === 'stored' &&
      !item.consolidation_id &&
      item.destination_city === group.value.destination_city
    )
  })
})

const loadGroup = async () => {
  loading.value = true
  const id = parseInt(route.params.id as string)
  await store.getGroup(id)
  await warehouseStore.fetchItems()
  loading.value = false
}

const markReady = async () => {
  try {
    await store.markReady(parseInt(route.params.id as string))
    await store.getGroup(parseInt(route.params.id as string))
  } catch {
    alert('Failed to mark group as ready')
  }
}

const addItem = async (itemId: number) => {
  try {
    await store.addItemToGroup(parseInt(route.params.id as string), itemId)
    await store.getGroup(parseInt(route.params.id as string))
    await warehouseStore.fetchItems()
  } catch {
    alert('Failed to add item to group')
  }
}

const removeItem = async (itemId: number) => {
  try {
    await store.removeItemFromGroup(parseInt(route.params.id as string), itemId)
    await store.getGroup(parseInt(route.params.id as string))
    await warehouseStore.fetchItems()
  } catch {
    alert('Failed to remove item from group')
  }
}

const getStatusClass = (status: string) => {
  const classes: Record<string, string> = {
    open: 'bg-status-blue/10 text-status-blue',
    ready: 'bg-status-green/10 text-status-green',
    dispatched: 'bg-status-purple/10 text-status-purple',
    completed: 'bg-surface-tertiary text-muted',
    cancelled: 'bg-status-red/10 text-status-red',
  }
  return classes[status] || ''
}

const getTripStatusClass = (status: string) => {
  const classes: Record<string, string> = {
    planned: 'bg-status-blue/10 text-status-blue',
    dispatched: 'bg-status-purple/10 text-status-purple',
    delivered: 'bg-status-green/10 text-status-green',
    cancelled: 'bg-status-red/10 text-status-red',
  }
  return classes[status] || ''
}

const getFillBarClass = (percentage: number) => {
  const p = percentage || 0
  if (p >= 80) return 'bg-status-green'
  if (p >= 50) return 'bg-status-yellow'
  if (p > 0) return 'bg-status-blue'
  return 'bg-surface-tertiary'
}

const getDaysClass = (days: number) => {
  if (days <= 3) return 'text-status-green'
  if (days <= 7) return 'text-status-yellow'
  if (days <= 15) return 'text-status-blue'
  return 'text-status-red'
}

const formatWeight = (weight: any) => {
  const w = parseFloat(weight) || 0
  return w.toLocaleString('en-IN', { maximumFractionDigits: 2 })
}

const formatDate = (date: string) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: '2-digit' })
}

onMounted(() => {
  loadGroup()
})
</script>
