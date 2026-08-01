<template>
  <div class="p-6">
    <div v-if="loading" class="text-center text-gray-500 py-8">
      Loading...
    </div>

    <div v-else-if="item" class="max-w-4xl">
      <!-- Header -->
      <div class="flex justify-between items-start mb-6">
        <div>
          <p class="text-sm text-muted mb-1">LR Receipt Number</p>
          <h1 class="text-3xl font-bold">{{ item.lr?.invoice_number || '-' }}</h1>
        </div>
        <div class="flex gap-2">
          <button
            @click="$router.back()"
            class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400"
          >
            ← Back
          </button>
        </div>
      </div>

      <!-- Main content in cards -->
      <div class="space-y-4">
        <!-- LR Receipt Details Card -->
        <div class="bg-white rounded shadow p-6">
          <h3 class="text-lg font-semibold mb-4">LR Receipt Information</h3>
          <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div>
              <p class="text-sm text-gray-600">Consignor (Company)</p>
              <p class="font-semibold">{{ item.lr?.customer?.name || '-' }}</p>
            </div>
            <div>
              <p class="text-sm text-gray-600">Description of Goods</p>
              <p class="font-semibold">{{ item.lr?.description_of_goods || '-' }}</p>
            </div>
            <div>
              <p class="text-sm text-gray-600">E-Way Bill No</p>
              <p class="font-semibold">{{ item.lr?.eway_bill_no || '-' }}</p>
            </div>
            <div>
              <p class="text-sm text-gray-600">Actual Weight</p>
              <p class="font-semibold">{{ item.lr?.actual_weight || '-' }}</p>
            </div>
            <div>
              <p class="text-sm text-gray-600">No. of Articles</p>
              <p class="font-semibold">{{ item.lr?.no_of_articles || '-' }}</p>
            </div>
            <div>
              <p class="text-sm text-gray-600">Packing Type</p>
              <p class="font-semibold">{{ item.lr?.packing || '-' }}</p>
            </div>
          </div>
        </div>

        <!-- Warehouse Storage Card -->
        <div class="bg-white rounded shadow p-6">
          <h3 class="text-lg font-semibold mb-4">Warehouse Storage</h3>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
              <p class="text-sm text-gray-600">Location</p>
              <p class="font-semibold">{{ item.warehouse_location }}</p>
            </div>
            <div>
              <p class="text-sm text-gray-600">Section</p>
              <p class="font-semibold">{{ item.section_name || '-' }}</p>
            </div>
            <div>
              <p class="text-sm text-gray-600">Date Received</p>
              <p class="font-semibold">{{ formatDate(item.date_received) }}</p>
            </div>
            <div>
              <p class="text-sm text-gray-600">Days in Warehouse</p>
              <p class="font-semibold text-blue-600">{{ item.days_in_warehouse || 0 }}</p>
            </div>
          </div>
        </div>

        <!-- Status & Destination Card -->
        <div class="bg-white rounded shadow p-6">
          <h3 class="text-lg font-semibold mb-4">Status & Destination</h3>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-sm text-gray-600 mb-2">Current Status</p>
              <select
                v-model="item.status"
                @change="updateStatus"
                class="w-full px-3 py-2 border rounded"
              >
                <option value="stored">Stored</option>
                <option value="picked_for_consolidation">Picked for Consolidation</option>
                <option value="loaded_on_vehicle">Loaded on Vehicle</option>
                <option value="in_transit">In Transit</option>
                <option value="delivered">Delivered</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
            <div>
              <p class="text-sm text-gray-600">Destination City</p>
              <p class="font-semibold text-lg">{{ item.destination_city }}</p>
            </div>
          </div>
        </div>

        <!-- Notes Card -->
        <div v-if="item.notes" class="bg-white rounded shadow p-6">
          <h3 class="text-lg font-semibold mb-4">Notes</h3>
          <p class="whitespace-pre-wrap">{{ item.notes }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useWarehouseItemStore } from '../store'

const route = useRoute()
const router = useRouter()
const store = useWarehouseItemStore()

const item = ref<any>(null)
const loading = ref(true)

const formatDate = (date: string) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: '2-digit' })
}

const fetchItem = async () => {
  loading.value = true
  try {
    item.value = await store.getItem(parseInt(route.params.id as string))
  } finally {
    loading.value = false
  }
}

const updateStatus = async () => {
  if (item.value) {
    try {
      await store.updateItem(item.value.id, { status: item.value.status })
      await fetchItem()
    } catch (error) {
      console.error('Error updating status:', error)
      alert('Failed to update status')
      await fetchItem()
    }
  }
}

onMounted(() => {
  fetchItem()
})
</script>