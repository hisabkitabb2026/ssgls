<template>
  <BaseCard class="mb-6">
    <template #header>
      <div class="flex items-center gap-2">
        <BaseIcon name="TruckIcon" class="h-5 w-5 text-primary-500" />
        <h3 class="text-base font-semibold text-heading">Consignment Details</h3>
      </div>
    </template>

    <div v-if="isLoading" class="space-y-3">
      <BaseContentPlaceholders v-for="i in 2" :key="i">
        <BaseContentPlaceholdersBox :rounded="true" class="w-full" style="min-height: 60px" />
      </BaseContentPlaceholders>
    </div>

    <div v-else>
      <!-- Item rows -->
      <div
        v-for="(item, index) in items"
        :key="item.id ?? index"
        class="border border-line-light rounded-lg p-4 mb-4 relative"
      >
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm font-semibold text-muted">Item {{ index + 1 }}</span>
          <button
            v-if="items.length > 1"
            type="button"
            class="text-danger hover:text-danger-hover"
            @click="removeItem(index)"
          >
            <BaseIcon name="TrashIcon" class="h-5 w-5" />
          </button>
        </div>

        <div class="grid gap-x-4 gap-y-4 grid-cols-1 md:grid-cols-2 xl:grid-cols-3">
          <div
            v-for="field in getItemFields(index)"
            :key="field.id"
            :class="[
              isAmountField(field) ? 'xl:col-span-1' : '',
            ]"
          >
            <!-- Amount is auto-calculated, render as read-only -->
            <BaseInputGroup
              v-if="isAmountField(field)"
              :label="field.custom_field?.label ?? field.label"
            >
              <BaseInput
                :model-value="field.value"
                disabled
                class="bg-surface-muted font-semibold"
              />
            </BaseInputGroup>
            <SingleField
              v-else
              :custom-field-scope="`${itemValidationScope}.items.${index}.customFields`"
              :store="store"
              :store-prop="storeProp"
              :index="getItemFieldIndex(index, field)"
              :field="field"
            />
          </div>
        </div>
      </div>

      <!-- Add New Item button -->
      <button
        type="button"
        class="flex items-center justify-center w-full px-6 py-3 text-base border border-dashed border-line-default rounded-lg cursor-pointer text-primary-500 hover:bg-hover"
        @click="addItem"
      >
        <BaseIcon name="PlusIcon" class="h-5 w-5 mr-2" />
        Add New Item
      </button>
    </div>
  </BaseCard>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue'
import { customFieldService } from '@/scripts/api/services/custom-field.service'
import SingleField from '@/scripts/features/company/customers/components/CreateCustomFieldsSingle.vue'
import { generateClientId } from '@/scripts/utils'

interface CustomFieldItem {
  id: number
  value: string | boolean | number | null
  default_answer: string | boolean | number | null
  label: string
  options: string[] | null
  is_required: boolean
  placeholder: string | null
  order: number | null
  type: string
  number_answer?: number | null
  string_answer?: string | null
  custom_field_id?: number
  custom_field?: {
    label: string
    options: string[] | null
    is_required: boolean
    placeholder: string | null
    order: number | null
    type: string
  }
}

interface StoreWithProp {
  [key: string]: {
    items: Array<Record<string, unknown> & {
      id?: string | number
      customFields?: CustomFieldItem[]
    }>
    [key: string]: unknown
  }
}

const props = withDefaults(
  defineProps<{
    store: StoreWithProp
    storeProp: string
    isEdit?: boolean
    isLoading?: boolean | null
    itemValidationScope: string
  }>(),
  {
    isEdit: false,
    isLoading: null,
  }
)

const formData = computed(() => props.store[props.storeProp])
const items = computed(() => formData.value?.items ?? [])

// The charge fields that sum into Amount
const chargeFieldLabels = ['Rate', 'Other Charge', 'LR Charge', 'DD Charge']

function isAmountField(field: CustomFieldItem): boolean {
  return (field.custom_field?.label ?? field.label) === 'Amount'
}

function getItemFields(index: number): CustomFieldItem[] {
  return items.value[index]?.customFields ?? []
}

function getItemFieldIndex(itemIndex: number, field: CustomFieldItem): number {
  const fields = items.value[itemIndex]?.customFields ?? []
  return fields.indexOf(field)
}

function getFieldValue(itemIndex: number, label: string): number {
  const fields = items.value[itemIndex]?.customFields ?? []
  const field = fields.find(
    (f) => (f.custom_field?.label ?? f.label) === label
  )
  if (!field) return 0
  const num = Number(field.value ?? field.number_answer ?? 0)
  return isNaN(num) ? 0 : num
}

function setFieldValue(itemIndex: number, label: string, value: number): void {
  const fields = items.value[itemIndex]?.customFields ?? []
  const field = fields.find(
    (f) => (f.custom_field?.label ?? f.label) === label
  )
  if (field) {
    field.value = value
    field.number_answer = value
  }
}

function recalcAmount(itemIndex: number): void {
  const fields = items.value[itemIndex]?.customFields ?? []
  const amountField = fields.find(
    (f) => (f.custom_field?.label ?? f.label) === 'Amount'
  )
  if (!amountField) return

  const sum = chargeFieldLabels.reduce(
    (acc, label) => acc + getFieldValue(itemIndex, label),
    0
  )
  setFieldValue(itemIndex, 'Amount', sum)
}

// Watch all items' custom fields for changes to auto-calc Amount
watch(
  () => items.value.map((item) =>
    (item.customFields ?? []).map((f) => ({
      label: f.custom_field?.label ?? f.label,
      value: f.value,
    }))
  ),
  () => {
    items.value.forEach((_, index) => recalcAmount(index))
  },
  { deep: true }
)

function addItem(): void {
  if (!formData.value) return

  // Clone the custom fields from the first item (or fetch fresh if none)
  const firstItem = items.value[0]
  const newFields: CustomFieldItem[] = firstItem?.customFields
    ? firstItem.customFields.map((f) => ({
        ...f,
        id: 0,
        value: f.default_answer,
        number_answer: typeof f.default_answer === 'number' ? f.default_answer : null,
        string_answer: typeof f.default_answer === 'string' ? f.default_answer : null,
        custom_field_id: f.custom_field_id ?? f.id,
      }))
    : []

  formData.value.items.push({
    id: generateClientId(),
    name: '',
    quantity: 1,
    price: 0,
    total: 0,
    discount: 0,
    discount_type: 'fixed',
    taxes: [],
    customFields: newFields,
  } as Record<string, unknown>)
}

function removeItem(index: number): void {
  if (items.value.length <= 1) return
  formData.value?.items.splice(index, 1)
}

// Fetch Item-level custom fields for office_invoice and populate first item
async function fetchItemCustomFields(): Promise<void> {
  if (!formData.value) return

  // Ensure at least one item exists
  if (!items.value.length) {
    formData.value.items = [{
      id: generateClientId(),
      name: '',
      quantity: 1,
      price: 0,
      total: 0,
      discount: 0,
      discount_type: 'fixed',
      taxes: [],
      customFields: [],
    } as Record<string, unknown>]
  }

  // If first item already has custom fields (edit mode), don't re-fetch
  if (items.value[0]?.customFields?.length) return

  const res = await customFieldService.list({
    type: 'Item',
    limit: 'all',
    template_name: 'office_invoice',
  })

    const data = (res as Record<string, unknown>).data as CustomFieldItem[]
    data.forEach((d) => {
      d.value = d.default_answer
    })

    // Sort by order so fields appear in the defined sequence
    // (Consignment No, Consignment Date, ..., Rate, Other Charge, LR Charge,
    //  DD Charge, Amount)
    data.sort((a, b) => (a.order ?? 0) - (b.order ?? 0))

    if (items.value[0]) {
      items.value[0].customFields = data
    }
}

fetchItemCustomFields()
</script>
