<template>
  <div class="p-6 max-w-3xl">
    <h1 class="text-2xl font-bold mb-6">Add Warehouse Item</h1>

    <form @submit.prevent="handleSubmit" class="bg-white rounded shadow p-6 space-y-6">
      <!-- LR Number Search with Card Display -->
      <div>
        <label class="block text-sm font-semibold mb-2">LR Receipt Number *</label>
        <input
          v-model="lrSearchQuery"
          @input="handleLrSearch"
          type="text"
          placeholder="Type LR number (e.g., LR-001234)..."
          required
          class="w-full px-3 py-2 border rounded"
        />

        <!-- Auto-populated LR Card (shows when LR is found) -->
        <div v-if="selectedLrDetails" class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded">
          <h3 class="font-semibold text-blue-900 mb-3">LR Details (Auto-populated)</h3>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-sm text-blue-700">LR Number</p>
              <p class="font-semibold">{{ selectedLrDetails.invoice_number }}</p>
            </div>
            <div>
              <p class="text-sm text-blue-700">Company/Customer</p>
              <p class="font-semibold">{{ selectedLrDetails.customer.name }}</p>
            </div>
            <div>
              <p class="text-sm text-blue-700">Goods Description</p>
              <p class="font-semibold">{{ selectedLrDetails.description_of_goods || '-' }}</p>
            </div>
            <div>
              <p class="text-sm text-blue-700">E-Way Bill No</p>
              <p class="font-semibold">{{ selectedLrDetails.eway_bill_no || '-' }}</p>
            </div>
            <div>
              <p class="text-sm text-blue-700">Actual Weight (Packing)</p>
              <p class="font-semibold">{{ selectedLrDetails.actual_weight || '-' }} kg</p>
            </div>
            <div>
              <p class="text-sm text-blue-700">No. of Articles</p>
              <p class="font-semibold">{{ selectedLrDetails.no_of_articles || '-' }}</p>
            </div>
            <div>
              <p class="text-sm text-blue-700">Packing Type</p>
              <p class="font-semibold">{{ selectedLrDetails.packing || '-' }}</p>
            </div>
            <div>
              <p class="text-sm text-blue-700">Destination</p>
              <p class="font-semibold">{{ selectedLrDetails.to_name || '-' }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Warehouse Location -->
      <div>
        <label class="block text-sm font-semibold mb-2">Warehouse Location *</label>
        <select
          v-model="formData.warehouse_location"
          required
          class="w-full px-3 py-2 border rounded"
        >
          <option value="">Select Warehouse Location</option>
          <option value="VAPI">VAPI</option>
          <option value="UMB">UMB</option>
        </select>
      </div>

      <!-- Date Received -->
      <div>
        <label class="block text-sm font-semibold mb-2">Date Received *</label>
        <input
          v-model="formData.date_received"
          type="date"
          required
          class="w-full px-3 py-2 border rounded"
        />
      </div>

      <!-- Destination City (auto-populated from LR) -->
      <div>
        <label class="block text-sm font-semibold mb-2">Destination City *</label>
        <input
          v-model="formData.destination_city"
          type="text"
          placeholder="e.g., DELHI"
          required
          class="w-full px-3 py-2 border rounded bg-blue-50"
          readonly
        />
        <p class="text-xs text-gray-500 mt-1">Auto-filled from LR Receipt</p>
      </div>

      <!-- Notes -->
      <div>
        <label class="block text-sm font-semibold mb-2">Additional Notes</label>
        <textarea
          v-model="formData.notes"
          placeholder="Any special handling instructions..."
          class="w-full px-3 py-2 border rounded"
          rows="3"
        ></textarea>
      </div>

      <!-- Actions -->
      <div class="flex gap-3 pt-4 border-t">
        <button
          type="submit"
          :disabled="loading || !selectedLrDetails"
          class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ loading ? 'Saving...' : 'Save Warehouse Item' }}
        </button>
        <button
          type="button"
          @click="$router.back()"
          class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400"
        >
          Cancel
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useWarehouseItemStore } from '../store'

const router = useRouter()
const store = useWarehouseItemStore()

const loading = ref(false)
const lrSearchQuery = ref('')
const selectedLrDetails = ref<any>(null)

const formData = reactive({
  lr_id: '',
  warehouse_location: '',
  date_received: new Date().toISOString().split('T')[0],
  destination_city: '',
  notes: '',
})

const handleLrSearch = async () => {
  if (!lrSearchQuery.value.trim()) {
    selectedLrDetails.value = null
    formData.lr_id = ''
    formData.destination_city = ''
    return
  }

  const lrData = await store.lookupLr(lrSearchQuery.value)

  if (lrData) {
    selectedLrDetails.value = lrData
    formData.lr_id = lrData.id
    formData.destination_city = lrData.to_name || ''
  } else {
    selectedLrDetails.value = null
    formData.lr_id = ''
    formData.destination_city = ''
  }
}

const handleSubmit = async () => {
  if (!selectedLrDetails.value) {
    alert('Please select a valid LR Receipt')
    return
  }

  loading.value = true
  try {
    const data = {
      lr_id: parseInt(formData.lr_id),
      warehouse_location: formData.warehouse_location,
      date_received: formData.date_received,
      destination_city: formData.destination_city,
      notes: formData.notes,
    }

    await store.createItem(data)
    router.push({ name: 'warehouse-items.index' })
  } catch (error) {
    console.error('Error creating item:', error)
    alert('Failed to save warehouse item')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  // Pre-fill today's date
  formData.date_received = new Date().toISOString().split('T')[0]
})
</script>