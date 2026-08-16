<template>
  <div class="p-6">
    <!-- Header -->
    <div class="flex justify-between items-start mb-6 gap-4">
      <div>
        <h1 class="text-2xl font-bold text-heading">Load Trips</h1>
        <p class="text-sm text-muted mt-1">
          Dispatch consolidated groups as truck trips and track delivery status.
        </p>
      </div>
      <div class="flex gap-2">
        <button
          class="px-4 py-2 bg-surface-secondary text-body rounded border border-line-default hover:bg-hover whitespace-nowrap"
          @click="$router.push({ name: 'consolidation.index' })"
        >
          🚚 Consolidation Board
        </button>
        <button
          class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 whitespace-nowrap"
          @click="showCreateModal = true"
        >
          + New Load Trip
        </button>
      </div>
    </div>

    <!-- KPI Strip -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-surface rounded shadow p-4">
        <p class="text-sm text-muted">Planned</p>
        <p class="text-2xl font-bold text-status-blue">{{ stats.planned }}</p>
      </div>
      <div class="bg-surface rounded shadow p-4">
        <p class="text-sm text-muted">Dispatched</p>
        <p class="text-2xl font-bold text-status-purple">{{ stats.dispatched }}</p>
      </div>
      <div class="bg-surface rounded shadow p-4">
        <p class="text-sm text-muted">Delivered</p>
        <p class="text-2xl font-bold text-status-green">{{ stats.delivered }}</p>
      </div>
      <div class="bg-surface rounded shadow p-4">
        <p class="text-sm text-muted">Total Items Moved</p>
        <p class="text-2xl font-bold text-heading">{{ stats.totalItems }}</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex gap-4 mb-6">
      <select
        v-model="selectedStatus"
        class="px-3 py-2 border border-line-default rounded bg-surface text-body"
        @change="loadTrips"
      >
        <option value="">All Statuses</option>
        <option value="planned">Planned</option>
        <option value="dispatched">Dispatched</option>
        <option value="delivered">Delivered</option>
        <option value="cancelled">Cancelled</option>
      </select>
    </div>

    <!-- Loading / Empty -->
    <div v-if="store.loading" class="text-center text-muted py-8">Loading...</div>
    <div v-else-if="store.trips.length === 0" class="text-center text-muted py-12 bg-surface-secondary rounded">
      <p class="text-lg">No load trips yet</p>
      <p class="text-sm mt-2">
        Create a load trip from a ready consolidation group to dispatch a truck.
      </p>
    </div>

    <!-- Trips Table -->
    <div v-else class="overflow-x-auto bg-surface rounded shadow">
      <table class="w-full text-sm">
        <thead class="bg-surface-secondary text-muted text-xs uppercase">
          <tr>
            <th class="text-left px-4 py-3">Trip Number</th>
            <th class="text-left px-4 py-3">Route</th>
            <th class="text-left px-4 py-3">Truck / Driver</th>
            <th class="text-left px-4 py-3">Consolidation</th>
            <th class="text-center px-4 py-3">Items</th>
            <th class="text-center px-4 py-3">Dispatch Date</th>
            <th class="text-center px-4 py-3">Status</th>
            <th class="text-center px-4 py-3">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-line-light">
          <tr
            v-for="trip in store.trips"
            :key="trip.id"
            class="hover:bg-hover cursor-pointer"
            @click="$router.push({ name: 'load-trips.show', params: { id: trip.id } })"
          >
            <td class="px-4 py-3 font-semibold text-heading">{{ trip.trip_number }}</td>
            <td class="px-4 py-3 text-body">
              {{ trip.origin_city || '—' }} → {{ trip.destination_city }}
            </td>
            <td class="px-4 py-3 text-body">
              <p class="font-medium">{{ trip.truck_number || '—' }}</p>
              <p class="text-xs text-muted">{{ trip.driver_name || '—' }}</p>
            </td>
            <td class="px-4 py-3 text-body">
              <span v-if="trip.consolidation_group" class="text-primary-500 cursor-pointer">
                {{ trip.consolidation_group.group_number }}
              </span>
              <span v-else class="text-muted">—</span>
            </td>
            <td class="px-4 py-3 text-center text-body">
              {{ trip.warehouse_items?.length || 0 }}
            </td>
            <td class="px-4 py-3 text-center text-body">
              {{ trip.dispatch_date ? formatDate(trip.dispatch_date) : '—' }}
            </td>
            <td class="px-4 py-3 text-center">
              <span class="text-xs px-2 py-1 rounded font-bold" :class="getStatusClass(trip.status)">
                {{ trip.status }}
              </span>
            </td>
            <td class="px-4 py-3 text-center" @click.stop>
              <div class="flex justify-center gap-1">
                <button
                  v-if="trip.status === 'planned'"
                  class="px-2 py-1 bg-status-purple text-white text-xs rounded hover:bg-status-purple/90"
                  @click="dispatchTrip(trip.id)"
                >
                  Dispatch
                </button>
                <button
                  v-if="trip.status === 'dispatched'"
                  class="px-2 py-1 bg-status-green text-white text-xs rounded hover:bg-status-green/90"
                  @click="markDelivered(trip.id)"
                >
                  Deliver
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create Modal -->
    <div
      v-if="showCreateModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
      @click.self="closeCreateModal"
    >
      <div class="bg-surface rounded-lg shadow-xl max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto">
        <h2 class="text-xl font-bold text-heading mb-4">New Load Trip</h2>
        <div class="space-y-4">
          <!-- Select Consolidation Group -->
          <div>
            <label class="text-sm font-semibold text-body block mb-1">Consolidation Group *</label>
            <select
              v-model="createForm.consolidation_group_id"
              class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
            >
              <option value="">Select a ready group...</option>
              <option
                v-for="group in readyGroups"
                :key="group.id"
                :value="group.id"
              >
                {{ group.group_number }} → {{ group.destination_city }} ({{ formatWeight(group.total_weight_kg) }} kg)
              </option>
            </select>
            <p v-if="readyGroups.length === 0" class="text-xs text-status-red mt-1">
              No ready consolidation groups available. Mark a group as ready first.
            </p>
          </div>

          <!-- Truck Details -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-sm font-semibold text-body block mb-1">Truck Number *</label>
              <input
                v-model="createForm.truck_number"
                type="text"
                placeholder="e.g. KA01 AB 1234"
                class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
              />
            </div>
            <div>
              <label class="text-sm font-semibold text-body block mb-1">Origin City</label>
              <input
                v-model="createForm.origin_city"
                type="text"
                placeholder="e.g. Bangalore"
                class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-sm font-semibold text-body block mb-1">Driver Name *</label>
              <input
                v-model="createForm.driver_name"
                type="text"
                placeholder="Driver name"
                class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
              />
            </div>
            <div>
              <label class="text-sm font-semibold text-body block mb-1">Driver Phone</label>
              <input
                v-model="createForm.driver_phone"
                type="text"
                placeholder="Phone number"
                class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-sm font-semibold text-body block mb-1">Dispatch Date</label>
              <input
                v-model="createForm.dispatch_date"
                type="date"
                class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
              />
            </div>
            <div>
              <label class="text-sm font-semibold text-body block mb-1">Expected Delivery</label>
              <input
                v-model="createForm.expected_delivery_date"
                type="date"
                class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
              />
            </div>
          </div>

          <div>
            <label class="text-sm font-semibold text-body block mb-1">Notes</label>
            <textarea
              v-model="createForm.notes"
              rows="2"
              class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
            ></textarea>
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-6">
          <button
            class="px-4 py-2 bg-surface-secondary text-body rounded border border-line-default hover:bg-hover"
            @click="closeCreateModal"
          >
            Cancel
          </button>
          <button
            class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700"
            :disabled="!canSubmit"
            @click="submitCreate"
          >
            Create Trip
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useLoadTripStore } from '../store'
import { useConsolidationStore } from '../../consolidation/store'

const store = useLoadTripStore()
const consolidationStore = useConsolidationStore()
const selectedStatus = ref('')
const showCreateModal = ref(false)

const createForm = ref({
  consolidation_group_id: '',
  truck_number: '',
  driver_name: '',
  driver_phone: '',
  origin_city: '',
  destination_city: '',
  dispatch_date: '',
  expected_delivery_date: '',
  notes: '',
})

const readyGroups = computed(() => {
  return consolidationStore.groups.filter((g: any) => g.status === 'ready')
})

const canSubmit = computed(() => {
  return (
    createForm.value.consolidation_group_id &&
    createForm.value.truck_number &&
    createForm.value.driver_name
  )
})

const stats = computed(() => {
  const s = { planned: 0, dispatched: 0, delivered: 0, totalItems: 0 }
  store.trips.forEach((t: any) => {
    if (t.status === 'planned') s.planned++
    else if (t.status === 'dispatched') s.dispatched++
    else if (t.status === 'delivered') s.delivered++
    s.totalItems += t.warehouse_items?.length || 0
  })
  return s
})

const loadTrips = () => {
  store.fetchTrips({ status: selectedStatus.value || undefined })
}

const closeCreateModal = () => {
  showCreateModal.value = false
  createForm.value = {
    consolidation_group_id: '',
    truck_number: '',
    driver_name: '',
    driver_phone: '',
    origin_city: '',
    destination_city: '',
    dispatch_date: '',
    expected_delivery_date: '',
    notes: '',
  }
}

const submitCreate = async () => {
  try {
    // Auto-fill destination from selected group
    const group = readyGroups.value.find(
      (g: any) => g.id === parseInt(createForm.value.consolidation_group_id)
    )
    if (group) {
      createForm.value.destination_city = group.destination_city
    }
    await store.createTrip(createForm.value)
    closeCreateModal()
  } catch {
    alert('Failed to create load trip')
  }
}

const dispatchTrip = async (id: number) => {
  if (confirm('Dispatch this trip? Items will be marked as in transit.')) {
    try {
      await store.dispatchTrip(id)
    } catch {
      alert('Failed to dispatch trip')
    }
  }
}

const markDelivered = async (id: number) => {
  if (confirm('Mark this trip as delivered?')) {
    try {
      await store.markDelivered(id)
    } catch {
      alert('Failed to mark trip as delivered')
    }
  }
}

const getStatusClass = (status: string) => {
  const classes: Record<string, string> = {
    planned: 'bg-status-blue/10 text-status-blue',
    dispatched: 'bg-status-purple/10 text-status-purple',
    delivered: 'bg-status-green/10 text-status-green',
    cancelled: 'bg-status-red/10 text-status-red',
  }
  return classes[status] || ''
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
  loadTrips()
  consolidationStore.fetchGroups({ status: 'ready' })
})
</script>
