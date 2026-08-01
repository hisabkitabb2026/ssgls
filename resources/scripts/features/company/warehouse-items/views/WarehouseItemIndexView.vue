<template>
  <div class="p-6">
    <div class="mb-6">
      <div class="flex justify-between items-start mb-4 gap-4">
        <h1 class="text-2xl font-bold">Warehouse Items</h1>
        <button
          @click="$router.push({ name: 'warehouse-items.create' })"
          class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 whitespace-nowrap"
        >
          + Add New
        </button>
      </div>

      <!-- Filters - Responsive: stack on mobile, row on desktop -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold">Filter by Status:</label>
          <select
            v-model="selectedStatus"
            class="px-3 py-2 border rounded bg-white"
          >
            <option value="">All Statuses</option>
            <option value="stored">Stored</option>
            <option value="picked_for_consolidation">Picked for Consolidation</option>
            <option value="loaded_on_vehicle">Loaded on Vehicle</option>
            <option value="in_transit">In Transit</option>
            <option value="delivered">Delivered</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>

        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold">Filter by Destination:</label>
          <select
            v-model="selectedDestination"
            class="px-3 py-2 border rounded bg-white"
          >
            <option value="">All Destinations</option>
            <option v-for="dest in allDestinations" :key="dest" :value="dest">
              {{ dest }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <div v-if="store.loading" class="text-center text-gray-500 py-8">
      Loading...
    </div>

    <div v-else-if="groupedItems.length === 0" class="text-center text-gray-500 py-12 bg-gray-50 rounded">
      <p class="text-lg">No warehouse items yet</p>
      <p class="text-sm mt-2">Click "Add New" to add items to your warehouse</p>
    </div>

    <!-- Card Grid grouped by destination -->
    <div v-else class="space-y-8">
      <div v-for="group in groupedItems" :key="group.destination" class="space-y-3">
        <div class="flex items-center gap-3 sticky top-6 bg-white z-10 py-2">
          <h2 class="text-xl font-bold text-heading">{{ group.destination }}</h2>
          <span class="text-sm bg-blue-100 text-blue-800 px-3 py-1 rounded-full">
            {{ group.items.length }} item{{ group.items.length !== 1 ? 's' : '' }}
          </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="item in group.items" :key="item.id" class="bg-white rounded shadow">
            <div class="p-4">
              <!-- Header with status and delete -->
              <div class="flex justify-between items-start mb-4 pb-4 border-b gap-2">
                <div class="flex-1 min-w-0">
                  <p class="text-xs text-muted uppercase tracking-wide">LR Receipt Number</p>
                  <p class="font-bold text-lg break-words">{{ item.lr?.invoice_number || '-' }}</p>
                </div>
                <div class="flex items-center gap-2">
                  <select
                    :value="item.status"
                    @change="(e) => updateStatus(item.id, e.target.value)"
                    class="text-xs px-2 py-1 rounded border cursor-pointer font-bold"
                    :class="getStatusClass(item.status)"
                  >
                    <option value="stored">Stored</option>
                    <option value="picked_for_consolidation">Picked</option>
                    <option value="loaded_on_vehicle">Loaded</option>
                    <option value="in_transit">In Transit</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                  </select>
                  <button
                    @click="deleteItem(item.id)"
                    class="text-red-500 hover:text-red-700 hover:bg-red-50 p-2 rounded transition"
                    title="Delete"
                  >
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Consignor & Consignee -->
              <div class="grid grid-cols-2 gap-2 mb-3">
                <div>
                  <p class="text-xs text-muted">Consignor</p>
                  <p class="font-semibold text-sm break-words">{{ item.lr?.customer?.name || '-' }}</p>
                </div>
                <div>
                  <p class="text-xs text-muted">Consignee</p>
                  <p class="font-semibold text-sm break-words">{{ item.consignee_name || '-' }}</p>
                </div>
              </div>

              <!-- Warehouse Location & Destination -->
              <div class="grid grid-cols-2 gap-2 mb-3 pb-3 border-b">
                <div>
                  <p class="text-xs text-muted">Warehouse Location</p>
                  <p class="font-semibold">{{ item.warehouse_location }}</p>
                </div>
                <div>
                  <p class="text-xs text-muted">Destination</p>
                  <p class="font-semibold">{{ item.destination_city }}</p>
                </div>
              </div>

              <!-- Received Date & Days in Warehouse -->
              <div class="grid grid-cols-2 gap-2 text-xs">
                <div>
                  <p class="text-muted">Received Date</p>
                  <p class="font-semibold">{{ formatDate(item.date_received) }}</p>
                </div>
                <div>
                  <p class="text-muted">Days in Warehouse</p>
                  <p class="font-semibold">{{ item.days_in_warehouse || 0 }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useWarehouseItemStore } from '../store'

const store = useWarehouseItemStore()
const selectedStatus = ref('')
const selectedDestination = ref('')

const allDestinations = computed(() => {
  const destinations = new Set<string>()
  store.items.forEach((item: any) => {
    if (item.destination_city) {
      destinations.add(item.destination_city)
    }
  })
  return Array.from(destinations).sort()
})

const groupedItems = computed(() => {
  const groups: Record<string, any> = {}

  store.items.forEach((item: any) => {
    // Filter by status if selected
    if (selectedStatus.value && item.status !== selectedStatus.value) {
      return
    }

    // Filter by destination if selected
    if (selectedDestination.value && item.destination_city !== selectedDestination.value) {
      return
    }

    const destination = item.destination_city || 'Unknown Destination'
    if (!groups[destination]) {
      groups[destination] = { destination, items: [] }
    }
    groups[destination].items.push(item)
  })

  // Sort by destination name and sort items by date
  return Object.values(groups)
    .sort((a: any, b: any) => a.destination.localeCompare(b.destination))
    .map((group: any) => ({
      ...group,
      items: group.items.sort((a: any, b: any) =>
        new Date(b.date_received).getTime() - new Date(a.date_received).getTime()
      ),
    }))
})

const getStatusClass = (status: string) => {
  const classes: Record<string, string> = {
    'stored': 'bg-green-100 text-green-800',
    'picked_for_consolidation': 'bg-yellow-100 text-yellow-800',
    'loaded_on_vehicle': 'bg-purple-100 text-purple-800',
    'in_transit': 'bg-blue-100 text-blue-800',
    'delivered': 'bg-green-100 text-green-800',
    'cancelled': 'bg-red-100 text-red-800',
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

const formatStatus = (status: string) => {
  return status.replace(/_/g, ' ').toUpperCase()
}

const formatDate = (date: string) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: '2-digit' })
}

const deleteItem = async (id: number) => {
  if (confirm('Delete this warehouse item?')) {
    await store.deleteItem(id)
  }
}

const updateStatus = async (id: number, newStatus: string) => {
  try {
    await store.updateItem(id, { status: newStatus })
  } catch (error) {
    console.error('Error updating status:', error)
    alert('Failed to update status')
    // Refresh to revert the change
    await store.fetchItems()
  }
}

onMounted(() => {
  store.fetchItems()
})
</script>