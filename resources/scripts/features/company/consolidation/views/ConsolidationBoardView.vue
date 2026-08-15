<template>
  <div class="p-6">
    <!-- Header -->
    <div class="flex justify-between items-start mb-6 gap-4">
      <div>
        <h1 class="text-2xl font-bold text-heading">Consolidation Board</h1>
        <p class="text-sm text-muted mt-1">
          Group part-load material by destination, fill trucks, and dispatch when ready.
        </p>
      </div>
      <div class="flex gap-2">
        <button
          class="px-4 py-2 bg-surface-secondary text-body rounded border border-line-default hover:bg-hover whitespace-nowrap"
          @click="showCreateModal = true"
        >
          + New Consolidation Group
        </button>
      </div>
    </div>

    <!-- KPI Strip -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-surface rounded shadow p-4">
        <p class="text-sm text-muted">Open Groups</p>
        <p class="text-2xl font-bold text-heading">{{ stats.open }}</p>
      </div>
      <div class="bg-surface rounded shadow p-4">
        <p class="text-sm text-muted">Ready to Dispatch</p>
        <p class="text-2xl font-bold text-status-green">{{ stats.ready }}</p>
      </div>
      <div class="bg-surface rounded shadow p-4">
        <p class="text-sm text-muted">Dispatched</p>
        <p class="text-2xl font-bold text-status-blue">{{ stats.dispatched }}</p>
      </div>
      <div class="bg-surface rounded shadow p-4">
        <p class="text-sm text-muted">Total Weight (Open)</p>
        <p class="text-2xl font-bold text-heading">{{ formatWeight(stats.totalWeight) }} kg</p>
      </div>
    </div>

    <!-- Candidates Section (unassigned items by destination) -->
    <div v-if="store.candidates.length > 0" class="mb-8">
      <h2 class="text-lg font-bold text-heading mb-3">Unassigned Material (Available for Consolidation)</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="candidate in store.candidates"
          :key="candidate.destination"
          class="bg-surface rounded shadow p-4 border-l-4 border-status-yellow"
        >
          <div class="flex justify-between items-start mb-2">
            <h3 class="font-bold text-heading">{{ candidate.destination }}</h3>
            <span class="text-xs bg-status-yellow/10 text-status-yellow px-2 py-1 rounded-full">
              {{ candidate.item_count }} item{{ candidate.item_count !== 1 ? 's' : '' }}
            </span>
          </div>
          <div class="grid grid-cols-2 gap-2 text-sm mb-3">
            <div>
              <p class="text-xs text-muted">Total Weight</p>
              <p class="font-semibold text-body">{{ formatWeight(candidate.total_weight_kg) }} kg</p>
            </div>
            <div>
              <p class="text-xs text-muted">Total Packages</p>
              <p class="font-semibold text-body">{{ candidate.total_packages || 0 }}</p>
            </div>
          </div>
          <button
            class="w-full px-3 py-2 bg-primary-600 text-white text-sm rounded hover:bg-primary-700"
            @click="openCreateForDestination(candidate.destination)"
          >
            Create Group for {{ candidate.destination }}
          </button>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex gap-4 mb-6">
      <select
        v-model="selectedStatus"
        class="px-3 py-2 border border-line-default rounded bg-surface text-body"
        @change="loadGroups"
      >
        <option value="">All Statuses</option>
        <option value="open">Open</option>
        <option value="ready">Ready</option>
        <option value="dispatched">Dispatched</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
      </select>
    </div>

    <!-- Loading / Empty -->
    <div v-if="store.loading" class="text-center text-muted py-8">Loading...</div>
    <div v-else-if="store.groups.length === 0" class="text-center text-muted py-12 bg-surface-secondary rounded">
      <p class="text-lg">No consolidation groups yet</p>
      <p class="text-sm mt-2">Create a group to start combining part-load material for a destination.</p>
    </div>

    <!-- Groups Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="group in store.groups"
        :key="group.id"
        class="bg-surface rounded shadow cursor-pointer hover:shadow-lg transition"
        @click="goToDetail(group.id)"
      >
        <div class="p-4">
          <!-- Header -->
          <div class="flex justify-between items-start mb-3">
            <div>
              <p class="font-bold text-heading">{{ group.group_number }}</p>
              <p class="text-sm text-muted">→ {{ group.destination_city }}</p>
            </div>
            <span class="text-xs px-2 py-1 rounded font-bold" :class="getStatusClass(group.status)">
              {{ group.status }}
            </span>
          </div>

          <!-- Fill Progress Bar -->
          <div class="mb-3">
            <div class="flex justify-between text-xs text-muted mb-1">
              <span>Truck Fill</span>
              <span class="font-semibold text-body">{{ formatWeight(group.total_weight_kg) }} / {{ formatWeight(group.truck_capacity_kg) }} kg</span>
            </div>
            <div class="w-full bg-surface-tertiary rounded-full h-2.5 overflow-hidden">
              <div
                class="h-full rounded-full transition-all"
                :class="getFillBarClass(group.fill_percentage)"
                :style="{ width: Math.min(group.fill_percentage || 0, 100) + '%' }"
              ></div>
            </div>
            <p class="text-xs text-right mt-1" :class="group.is_ready_to_dispatch ? 'text-status-green font-semibold' : 'text-muted'">
              {{ (group.fill_percentage || 0).toFixed(1) }}% filled
              <span v-if="group.is_ready_to_dispatch"> ✓ Ready!</span>
            </p>
          </div>

          <!-- Stats -->
          <div class="grid grid-cols-3 gap-2 text-sm pt-3 border-t border-line-light">
            <div>
              <p class="text-xs text-muted">Items</p>
              <p class="font-semibold text-body">{{ group.total_items || 0 }}</p>
            </div>
            <div>
              <p class="text-xs text-muted">Packages</p>
              <p class="font-semibold text-body">{{ group.total_packages || 0 }}</p>
            </div>
            <div>
              <p class="text-xs text-muted">Weight</p>
              <p class="font-semibold text-body">{{ formatWeight(group.total_weight_kg) }} kg</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Modal -->
    <div
      v-if="showCreateModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
      @click.self="closeCreateModal"
    >
      <div class="bg-surface rounded-lg shadow-xl max-w-md w-full p-6">
        <h2 class="text-xl font-bold text-heading mb-4">New Consolidation Group</h2>
        <div class="space-y-4">
          <div>
            <label class="text-sm font-semibold text-body block mb-1">Destination City *</label>
            <input
              v-model="createForm.destination_city"
              type="text"
              placeholder="e.g. Mumbai"
              class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
            />
          </div>
          <div>
            <label class="text-sm font-semibold text-body block mb-1">Truck Capacity (kg)</label>
            <input
              v-model.number="createForm.truck_capacity_kg"
              type="number"
              placeholder="e.g. 9000"
              class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
            />
            <p class="text-xs text-muted mt-1">Default: 9000 kg (standard truck load)</p>
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
            :disabled="!createForm.destination_city"
            @click="submitCreate"
          >
            Create Group
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useConsolidationStore } from '../store'

const store = useConsolidationStore()
const route = useRoute()
const router = useRouter()
const selectedStatus = ref('')
const showCreateModal = ref(false)

const createForm = ref({
  destination_city: '',
  truck_capacity_kg: 9000,
  notes: '',
})

const stats = computed(() => {
  const s = { open: 0, ready: 0, dispatched: 0, totalWeight: 0 }
  store.groups.forEach((g: any) => {
    if (g.status === 'open') {
      s.open++
      s.totalWeight += parseFloat(g.total_weight_kg) || 0
    } else if (g.status === 'ready') {
      s.ready++
    } else if (g.status === 'dispatched') {
      s.dispatched++
    }
  })
  return s
})

const loadGroups = () => {
  store.fetchGroups({
    status: selectedStatus.value || undefined,
    destination: route.query.destination as string || undefined,
  })
}

const openCreateForDestination = (destination: string) => {
  createForm.value.destination_city = destination
  showCreateModal.value = true
}

const closeCreateModal = () => {
  showCreateModal.value = false
  createForm.value = { destination_city: '', truck_capacity_kg: 9000, notes: '' }
}

const submitCreate = async () => {
  try {
    await store.createGroup(createForm.value)
    closeCreateModal()
    await store.fetchCandidates()
  } catch {
    alert('Failed to create consolidation group')
  }
}

const goToDetail = (id: number) => {
  router.push({ name: 'consolidation.show', params: { id } })
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

const getFillBarClass = (percentage: number) => {
  const p = percentage || 0
  if (p >= 80) return 'bg-status-green'
  if (p >= 50) return 'bg-status-yellow'
  if (p > 0) return 'bg-status-blue'
  return 'bg-surface-tertiary'
}

const formatWeight = (weight: any) => {
  const w = parseFloat(weight) || 0
  return w.toLocaleString('en-IN', { maximumFractionDigits: 2 })
}

onMounted(() => {
  loadGroups()
  store.fetchCandidates()
})
</script>
