<template>
  <div class="p-6 max-w-3xl">
    <h1 class="text-2xl font-bold text-heading mb-6">Receive Material into Warehouse</h1>

    <form class="bg-surface rounded shadow p-6 space-y-6" @submit.prevent="handleSubmit">
      <!-- Section 1: Customer & LR Selection -->
      <div>
        <h3 class="text-lg font-semibold text-heading mb-4">Select Customer & LR Receipt</h3>

        <!-- Step 1: Customer (uses BaseCustomerSelectInput — same as Payment page) -->
        <div class="mb-4">
          <label class="block text-sm font-semibold text-body mb-2">Customer *</label>
          <BaseCustomerSelectInput
            v-model="selectedCustomerId"
            show-action
            @update:model-value="onCustomerChange"
          />
        </div>

        <!-- Step 2: LR Receipt (filtered by customer) -->
        <div v-if="selectedCustomerId" class="mb-4">
          <label class="block text-sm font-semibold text-body mb-2">LR Receipt *</label>
          <BaseMultiselect
            v-model="selectedLrId"
            value-prop="id"
            track-by="invoice_number"
            label="invoice_number"
            :options="lrList"
            :loading="isLoadingLrs"
            placeholder="Select an LR receipt..."
            searchable
            @select="onLrSelect"
          />
          <p v-if="!isLoadingLrs && lrList.length === 0" class="text-xs text-status-red mt-1">
            No LR receipts found for this customer.
          </p>
        </div>

        <!-- LR Details (Auto-populated) -->
        <div v-if="selectedLrDetails" class="mt-4 p-4 bg-surface-secondary border border-line-light rounded">
          <h3 class="font-semibold text-heading mb-3">LR Details (Auto-populated)</h3>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-sm text-muted">LR Number</p>
              <p class="font-semibold text-body">{{ selectedLrDetails.invoice_number }}</p>
            </div>
            <div>
              <p class="text-sm text-muted">Company/Customer</p>
              <p class="font-semibold text-body">{{ selectedLrDetails.customer?.name }}</p>
            </div>
            <div>
              <p class="text-sm text-muted">Goods Description</p>
              <p class="font-semibold text-body">{{ selectedLrDetails.description_of_goods || '-' }}</p>
            </div>
            <div>
              <p class="text-sm text-muted">E-Way Bill No</p>
              <p class="font-semibold text-body">{{ selectedLrDetails.eway_bill_no || '-' }}</p>
            </div>
            <div>
              <p class="text-sm text-muted">Actual Weight</p>
              <p class="font-semibold text-body">{{ selectedLrDetails.actual_weight || '-' }} kg</p>
            </div>
            <div>
              <p class="text-sm text-muted">No. of Articles</p>
              <p class="font-semibold text-body">{{ selectedLrDetails.no_of_articles || '-' }}</p>
            </div>
            <div>
              <p class="text-sm text-muted">Packing Type</p>
              <p class="font-semibold text-body">{{ selectedLrDetails.packing || '-' }}</p>
            </div>
            <div>
              <p class="text-sm text-muted">Destination</p>
              <p class="font-semibold text-body">{{ selectedLrDetails.to_name || '-' }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Section 2: Service Type -->
      <div class="border-t border-line-light pt-6">
        <label class="block text-sm font-semibold text-body mb-3">Service Type *</label>
        <div class="grid grid-cols-2 gap-4">
          <label
            class="flex items-center gap-3 p-4 border rounded cursor-pointer transition"
            :class="formData.load_type === 'part_load' ? 'border-primary-500 bg-primary-500/5' : 'border-line-default bg-surface'"
          >
            <input
              v-model="formData.load_type"
              type="radio"
              value="part_load"
              class="w-4 h-4"
            />
            <div>
              <p class="font-semibold text-body">Part Load</p>
              <p class="text-xs text-muted">Material stored in warehouse, consolidated with other shipments</p>
            </div>
          </label>
          <label
            class="flex items-center gap-3 p-4 border rounded cursor-pointer transition"
            :class="formData.load_type === 'full_load' ? 'border-primary-500 bg-primary-500/5' : 'border-line-default bg-surface'"
          >
            <input
              v-model="formData.load_type"
              type="radio"
              value="full_load"
              class="w-4 h-4"
            />
            <div>
              <p class="font-semibold text-body">Full Load</p>
              <p class="text-xs text-muted">Direct dispatch, no warehouse storage needed</p>
            </div>
          </label>
        </div>
      </div>

      <!-- Section 3: Warehouse Storage (only for Part Load) -->
      <div v-if="formData.load_type === 'part_load'" class="border-t border-line-light pt-6">
        <h3 class="text-lg font-semibold text-heading mb-4">Warehouse Storage</h3>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-body mb-2">Warehouse Location *</label>
            <select
              v-model="formData.warehouse_location"
              required
              class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
            >
              <option value="">Select Warehouse Location</option>
              <option value="VAPI">VAPI</option>
              <option value="UMB">UMB</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-body mb-2">Date Received *</label>
            <input
              v-model="formData.date_received"
              type="date"
              required
              class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
            />
          </div>
        </div>
      </div>

      <!-- Section 4: Delivery Commitment -->
      <div class="border-t border-line-light pt-6">
        <h3 class="text-lg font-semibold text-heading mb-4">Delivery Commitment</h3>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-body mb-2">Promised Dispatch Date</label>
            <input
              v-model="formData.promised_dispatch_date"
              type="date"
              class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
            />
            <p class="text-xs text-muted mt-1">Deadline given to customer for dispatch</p>
          </div>
          <div>
            <label class="block text-sm font-semibold text-body mb-2">Priority</label>
            <select
              v-model="formData.priority"
              class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
            >
              <option value="normal">Normal</option>
              <option value="urgent">Urgent</option>
              <option value="critical">Critical</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Section 5: Material Details -->
      <div class="border-t border-line-light pt-6">
        <h3 class="text-lg font-semibold text-heading mb-4">Material Details</h3>
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-semibold text-body mb-2">Weight (kg)</label>
            <input
              v-model="formData.weight_kg"
              type="number"
              step="0.01"
              min="0"
              class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
            />
          </div>
          <div>
            <label class="block text-sm font-semibold text-body mb-2">No. of Packages</label>
            <input
              v-model="formData.no_of_packages"
              type="number"
              min="0"
              class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
            />
          </div>
          <div>
            <label class="block text-sm font-semibold text-body mb-2">Destination City</label>
            <input
              v-model="formData.destination_city"
              type="text"
              readonly
              class="w-full px-3 py-2 border border-line-default rounded bg-surface-secondary text-body"
            />
          </div>
        </div>
      </div>

      <!-- Section 6: Notes -->
      <div class="border-t border-line-light pt-6">
        <label class="block text-sm font-semibold text-body mb-2">Additional Notes</label>
        <textarea
          v-model="formData.notes"
          placeholder="Any special handling instructions..."
          class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
          rows="3"
        ></textarea>
      </div>

      <!-- Actions -->
      <div class="flex gap-3 pt-4 border-t border-line-light">
        <button
          type="submit"
          :disabled="loading || !selectedLrDetails"
          class="px-4 py-2 bg-primary-600 text-white rounded border border-primary-600 hover:bg-primary-700 disabled:opacity-60 disabled:cursor-not-allowed font-semibold"
        >
          {{ loading ? 'Saving...' : 'Save Warehouse Item' }}
        </button>
        <button
          type="button"
          class="px-4 py-2 bg-surface-secondary text-body rounded border border-line-default hover:bg-hover"
          @click="$router.back()"
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
import { invoiceService } from '@/scripts/api/services/invoice.service'

const router = useRouter()
const store = useWarehouseItemStore()

const loading = ref(false)
const selectedCustomerId = ref<number | string>('')
const selectedLrId = ref<number | string>('')
const selectedLrDetails = ref<any>(null)
const lrList = ref<any[]>([])
const isLoadingLrs = ref(false)

const formData = reactive({
  lr_id: '',
  load_type: 'part_load',
  warehouse_location: '',
  date_received: new Date().toISOString().split('T')[0],
  destination_city: '',
  promised_dispatch_date: '',
  priority: 'normal',
  weight_kg: 0,
  no_of_packages: 0,
  notes: '',
})

/**
 * When customer changes, fetch LR receipts for that customer.
 * Uses the same invoiceService.list() as the Payment page, but
 * filters by template_name=lr_receipt to get only LR Receipts.
 */
const onCustomerChange = async () => {
  // Reset LR selection
  selectedLrId.value = ''
  selectedLrDetails.value = null
  formData.lr_id = ''
  formData.destination_city = ''
  formData.weight_kg = 0
  formData.no_of_packages = 0
  lrList.value = []

  if (!selectedCustomerId.value) return

  isLoadingLrs.value = true
  try {
    const response = await invoiceService.list({
      customer_id: Number(selectedCustomerId.value),
      template_name: 'lr_receipt',
      limit: 'all',
    } as never)
    lrList.value = (response.data as unknown as any[]) ?? []
  } catch (error) {
    console.error('Error fetching LR receipts:', error)
    lrList.value = []
  } finally {
    isLoadingLrs.value = false
  }
}

/**
 * When an LR is selected from the dropdown, fetch full details via
 * the lookup endpoint and auto-fill the form fields.
 */
const onLrSelect = async (lrId: number) => {
  if (!lrId) {
    selectedLrDetails.value = null
    formData.lr_id = ''
    formData.destination_city = ''
    return
  }

  // Find the LR in the list to get the invoice number
  const lrOption = lrList.value.find((lr: any) => lr.id === lrId)
  if (!lrOption) return

  // Fetch full LR details via lookup endpoint
  const lrData = await store.lookupLr(lrOption.invoice_number)

  if (lrData) {
    selectedLrDetails.value = lrData
    formData.lr_id = lrData.id
    formData.destination_city = lrData.to_name || ''
    // Auto-fill weight from LR if available
    if (lrData.actual_weight) {
      formData.weight_kg = parseFloat(lrData.actual_weight) || 0
    }
    if (lrData.no_of_articles) {
      formData.no_of_packages = parseInt(lrData.no_of_articles) || 0
    }
  } else {
    selectedLrDetails.value = null
    formData.lr_id = ''
    formData.destination_city = ''
    alert('Could not load LR details. Please try again.')
  }
}

const handleSubmit = async () => {
  if (!selectedLrDetails.value) {
    alert('Please select a valid LR Receipt')
    return
  }

  loading.value = true
  try {
    const data: any = {
      lr_id: parseInt(formData.lr_id),
      load_type: formData.load_type,
      warehouse_location: formData.load_type === 'part_load' ? formData.warehouse_location : null,
      date_received: formData.date_received,
      destination_city: formData.destination_city,
      promised_dispatch_date: formData.promised_dispatch_date || null,
      priority: formData.priority,
      weight_kg: parseFloat(formData.weight_kg) || 0,
      no_of_packages: parseInt(formData.no_of_packages) || 0,
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
  formData.date_received = new Date().toISOString().split('T')[0]
})
</script>
