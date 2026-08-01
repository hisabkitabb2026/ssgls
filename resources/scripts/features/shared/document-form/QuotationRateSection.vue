<script setup lang="ts">
import { ref, computed } from 'vue'

interface QuotationRow {
  station: string
  '9mt': string | number
  '10mt': string | number
  '12mt': string | number
  '15mt': string | number
  '18mt': string | number
  '24mt': string | number
  '30mt': string | number
  [key: string]: string | number
}

interface Props {
  store: {
    newEstimate: {
      quotation_rates?: QuotationRow[]
      [key: string]: unknown
    }
    [key: string]: unknown
  }
}

const props = defineProps<Props>()

const columnHeaders = ['9mt', '10mt', '12mt', '15mt', '18mt', '24mt', '30mt']
const rows = ref<QuotationRow[]>([])
const showAddRowForm = ref(false)
const newRowData = ref<QuotationRow>(createEmptyRow())

function createEmptyRow(): QuotationRow {
  return {
    station: '',
    '9mt': '',
    '10mt': '',
    '12mt': '',
    '15mt': '',
    '18mt': '',
    '24mt': '',
    '30mt': '',
  }
}

function initializeRows() {
  const estimate = props.store.newEstimate
  if (estimate.quotation_rates && Array.isArray(estimate.quotation_rates) && estimate.quotation_rates.length > 0) {
    rows.value = estimate.quotation_rates.map(row => ({ ...row }))
  } else {
    rows.value = []
  }
}

function addNewRow() {
  if (!newRowData.value.station.trim()) {
    alert('Please enter a station name')
    return
  }

  if (!validateRowData(newRowData.value)) {
    alert('Please enter valid numeric values for all rates')
    return
  }

  rows.value.push({ ...newRowData.value })
  newRowData.value = createEmptyRow()
  showAddRowForm.value = false
  updateEstimateRates()
}

function removeRow(index: number) {
  rows.value.splice(index, 1)
  updateEstimateRates()
}

function updateRowData(index: number, column: string, value: string | number) {
  rows.value[index][column] = value
  updateEstimateRates()
}

function validateRowData(row: QuotationRow): boolean {
  for (const col of columnHeaders) {
    const value = row[col]
    if (value !== '' && isNaN(Number(value))) {
      return false
    }
  }
  return true
}

function updateEstimateRates() {
  const cleanedRows = rows.value.filter(row => row.station.trim() !== '')
  ;(props.store.newEstimate as Record<string, unknown>).quotation_rates = cleanedRows
}

function toggleAddRowForm() {
  showAddRowForm.value = !showAddRowForm.value
  if (!showAddRowForm.value) {
    newRowData.value = createEmptyRow()
  }
}

function editRow(index: number) {
  newRowData.value = { ...rows.value[index] }
  removeRow(index)
  showAddRowForm.value = true
}

// Initialize on mount
initializeRows()
</script>

<template>
  <div class="mt-10 bg-surface border border-line-default rounded-lg p-4 sm:p-6">
    <!-- Header -->
    <div class="mb-6">
      <h3 class="text-lg font-semibold text-heading mb-1">
        Quotation Rate Sheet
      </h3>
      <p class="text-sm text-muted">
        Add transportation rates for different truck capacities across stations
      </p>
    </div>

    <!-- Rate Table - Desktop View -->
    <div v-if="rows.length > 0" class="hidden lg:block overflow-x-auto mb-6">
      <table class="w-full border-collapse">
        <thead>
          <tr class="bg-surface-muted border-b border-line-default">
            <th class="px-3 py-3 text-left font-semibold text-xs sm:text-sm text-heading border-r border-line-default min-w-[120px]">
              Station
            </th>
            <th
              v-for="col in columnHeaders"
              :key="`header-${col}`"
              class="px-2 py-3 text-center font-semibold text-xs sm:text-sm text-heading border-r border-line-default min-w-[80px]"
            >
              {{ col.replace('mt', ' MT').toUpperCase() }}
            </th>
            <th class="px-3 py-3 text-center font-semibold text-xs sm:text-sm text-heading min-w-[70px]">
              Action
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, index) in rows"
            :key="`row-${index}`"
            class="border-b border-line-default hover:bg-surface-muted transition"
          >
            <td class="px-3 py-3 border-r border-line-default font-medium text-heading text-sm">
              {{ row.station }}
            </td>
            <td
              v-for="col in columnHeaders"
              :key="`${index}-${col}`"
              class="px-2 py-3 border-r border-line-default text-right text-sm"
            >
              <span class="text-body">{{ row[col] || '—' }}</span>
            </td>
            <td class="px-3 py-3 text-center">
              <div class="flex justify-center gap-2">
                <button
                  type="button"
                  @click="editRow(index)"
                  class="p-1 text-primary-500 hover:text-primary-700 transition"
                  title="Edit row"
                >
                  <BaseIcon name="PencilIcon" class="h-4 w-4" />
                </button>
                <button
                  type="button"
                  @click="removeRow(index)"
                  class="p-1 text-red-500 hover:text-red-700 transition"
                  title="Remove row"
                >
                  <BaseIcon name="TrashIcon" class="h-4 w-4" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Rate Cards - Mobile View -->
    <div v-if="rows.length > 0" class="lg:hidden space-y-3 mb-6">
      <div
        v-for="(row, index) in rows"
        :key="`mobile-card-${index}`"
        class="bg-surface-muted border border-line-default rounded-lg p-4"
      >
        <!-- Station Name -->
        <div class="flex justify-between items-start mb-3">
          <h4 class="text-base font-semibold text-heading">{{ row.station }}</h4>
          <div class="flex gap-1">
            <button
              type="button"
              @click="editRow(index)"
              class="p-2 text-primary-500 hover:text-primary-700 transition"
              title="Edit row"
            >
              <BaseIcon name="PencilIcon" class="h-4 w-4" />
            </button>
            <button
              type="button"
              @click="removeRow(index)"
              class="p-2 text-red-500 hover:text-red-700 transition"
              title="Remove row"
            >
              <BaseIcon name="TrashIcon" class="h-4 w-4" />
            </button>
          </div>
        </div>

        <!-- Rates Grid -->
        <div class="grid grid-cols-2 gap-2">
          <div v-for="col in columnHeaders" :key="`${index}-${col}-mobile`" class="bg-surface rounded p-2">
            <p class="text-xs font-semibold text-muted mb-1">{{ col.replace('mt', ' MT').toUpperCase() }}</p>
            <p class="text-sm font-semibold text-heading">{{ row[col] || '—' }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-if="rows.length === 0" class="text-center py-8 border border-dashed border-line-default rounded-lg mb-6">
      <BaseIcon name="DocumentChartBarIcon" class="h-12 w-12 text-muted mx-auto mb-2" />
      <p class="text-muted text-sm">No quotation rates added yet</p>
    </div>

    <!-- Add Row Form -->
    <div v-if="showAddRowForm" class="bg-surface-muted border border-line-default rounded-lg p-4 sm:p-5 mb-6">
      <!-- Station Input -->
      <div class="mb-4">
        <label class="block text-xs font-semibold text-heading mb-2">Station Name *</label>
        <input
          v-model="newRowData.station"
          type="text"
          placeholder="e.g., Delhi"
          class="w-full px-3 py-2 border border-line-default rounded focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 text-sm"
        />
      </div>

      <!-- MT Rate Inputs - Mobile Optimized Grid -->
      <div class="mb-4">
        <p class="text-xs font-semibold text-heading mb-3">Transportation Rates</p>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
          <div v-for="col in columnHeaders" :key="`form-${col}`">
            <label class="block text-xs font-semibold text-heading mb-1">{{ col.replace('mt', ' MT').toUpperCase() }}</label>
            <input
              v-model="newRowData[col]"
              type="number"
              placeholder="0.00"
              step="0.01"
              class="w-full px-2 py-2 border border-line-default rounded focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 text-sm text-right"
            />
          </div>
        </div>
      </div>

      <!-- Form Actions -->
      <div class="flex justify-end gap-2 pt-2">
        <BaseButton variant="primary-outline" size="small" @click="toggleAddRowForm">
          {{ $t('general.cancel') }}
        </BaseButton>
        <BaseButton variant="primary" size="small" @click="addNewRow">
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>
          Add Rate
        </BaseButton>
      </div>
    </div>

    <!-- Add Button (when form hidden) -->
    <BaseButton
      v-if="!showAddRowForm"
      variant="primary-outline"
      size="small"
      @click="toggleAddRowForm"
      class="w-full sm:w-auto"
    >
      <template #left="slotProps">
        <BaseIcon name="PlusIcon" :class="slotProps.class" />
      </template>
      Add Quotation Rate
    </BaseButton>
  </div>
</template>