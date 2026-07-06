<script setup lang="ts">
import { computed, watch } from 'vue'
import lodash from 'lodash'
import { parse, format } from 'date-fns'
import { customFieldService } from '@/scripts/api/services/custom-field.service'
import SingleField from '@/scripts/features/company/customers/components/CreateCustomFieldsSingle.vue'

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
    customFields: CustomFieldItem[]
    fields: CustomFieldItem[]
  }
}

interface FieldSection {
  title: string
  icon?: string
  fieldLabels: string[]
  gridLayout?: string
}

const props = withDefaults(
  defineProps<{
    store: StoreWithProp
    storeProp: string
    isEdit?: boolean
    type?: string | null
    isLoading?: boolean | null
    customFieldScope: string
    templateName?: string | null
  }>(),
  {
    isEdit: false,
    type: null,
    isLoading: null,
    templateName: null,
  }
)

const storeData = computed(() => {
  const data = props.store[props.storeProp]

  if (!data) {
    return null
  }

  if (!Array.isArray(data.customFields)) {
    data.customFields = []
  }

  if (!Array.isArray(data.fields)) {
    data.fields = []
  }

  return data
})

// --- Section definitions per template ---
const lrReceiptSections: FieldSection[] = [
  {
    title: 'Trip Details',
    icon: 'TruckIcon',
    fieldLabels: [
      'From', 'To', 'Truck No',
      'Mode of Payment', 'GST Tax Payable By',
    ],
  },
  {
    title: 'Consignment Details',
    icon: 'ClipboardDocumentListIcon',
    fieldLabels: [
      'Description of Goods', 'HSN Code', 'E-way Bill No',
      'Actual Weight', 'Charged Weight', 'No of Articles',
      'Packing', 'Invoice No',
    ],
  },
  {
    title: 'Freight Details',
    icon: 'CurrencyRupeeIcon',
    fieldLabels: [
      'Basic Freight', 'Hamali', 'FOV',
      'Local Collection', 'Door Delivery', 'Other Charge',
      'Net Amount',
    ],
  },
]


const lorryReceiptSections: FieldSection[] = [
  {
    title: 'Trip Details',
    icon: 'MapPinIcon',
    fieldLabels: [
      'From', 'To', 'No Of Pages', 'No Of Packages',
      'Actual Weight', 'Charge Weight',
    ],
  },
  {
    title: 'Vehicle Details',
    icon: 'TruckIcon',
    fieldLabels: [
      'Lorry No', 'Regd at', 'Body Type', 'Make', 'Model',
      'Colour', 'Chasis No', 'Engine No',
    ],
  },
  {
    title: 'Hire Particulars',
    icon: 'CurrencyRupeeIcon',
    fieldLabels: [
      'Paid To', 'Lorry Hire', 'Add Other Charges',
      'Advance Paid by Cash/Cheque No', 'Advance On', 'Bank',
      'Advance Paid Rs', 'Balance Payable at', 'Loaded By',
    ],
  },
  {
    title: 'Final Payment Details',
    icon: 'BanknotesIcon',
    fieldLabels: [
      'Final Paid To', 'Add Detention Rs.', 'Extra Hire Rs', 'Other Rs',
      'Less Adv. at other branch', 'Less Deduction for Claims',
      'Final Balance Amount Paid at', 'Final Balance Date',
      'Cash/Cheque No.', 'Final Bank',
    ],
  },
  {
    title: 'Owner Details',
    icon: 'UserIcon',
    fieldLabels: [
      'Owner Name', 'Owner Address', 'Owner Phone No',
      'Owner Bank Account No', 'Owner PAN No', 'Financer Address',
    ],
  },
  {
    title: 'Driver Details',
    icon: 'IdentificationIcon',
    fieldLabels: [
      'Driver Name', 'Driver Address', 'Driver Place',
      'Driver Licence No', 'Driver Licence Date', 'Driver Licence Issued By',
      'Driver RTO', 'Driver Valid Up To', 'Driver Bank Account No',
    ],
  },
  {
    title: 'Broker Details',
    icon: 'BriefcaseIcon',
    fieldLabels: [
      'Broker Name', 'Broker Address', 'Broker Pan No',
      'Advice Date', 'Destination Broker Name', 'Destination Broker Address',
      'Broker Phone No', 'Broker Bank Account No',
    ],
  },
]

const sections = computed<FieldSection[]>(() => {
  if (props.templateName === 'lr_receipt') return lrReceiptSections
  if (props.templateName === 'lorry_receipt') return lorryReceiptSections
  // office_invoice fields are at Item level — rendered by OfficeInvoiceItemsTable
  return []
})

// --- Field fetching & merging (same logic as CreateCustomFields.vue) ---
getInitialCustomFields()

function mergeExistingValues(): void {
  if (props.isEdit && storeData.value) {
    storeData.value.fields.forEach((field) => {
      const existingIndex = storeData.value?.customFields.findIndex(
        (f) => f.id === field.custom_field_id
      ) ?? -1

      if (existingIndex > -1) {
        let value: string | boolean | number | null = field.default_answer

        if (value && field.custom_field?.type === 'DateTime') {
          value = format(
            parse(String(field.default_answer), 'yyyy-MM-dd HH:mm:ss', new Date()),
            'yyyy-MM-dd HH:mm'
          )
        }

        storeData.value.customFields[existingIndex] = {
          ...field,
          id: field.custom_field_id ?? field.id,
          value,
          label: field.custom_field?.label ?? '',
          options: field.custom_field?.options ?? null,
          is_required: field.custom_field?.is_required ?? false,
          placeholder: field.custom_field?.placeholder ?? null,
          order: field.custom_field?.order ?? null,
          type: field.custom_field?.type ?? field.type,
        }
      }
    })
  }
}

async function getInitialCustomFields(): Promise<void> {
  if (!storeData.value) {
    return
  }

  const res = await customFieldService.list({
    type: props.type ?? undefined,
    limit: 'all',
    template_name: props.templateName ?? undefined,
  })

  const data = (res as Record<string, unknown>).data as CustomFieldItem[]
  data.forEach((d) => {
    d.value = d.default_answer
  })

  storeData.value.customFields = lodash.sortBy(
    data,
    (_cf: CustomFieldItem) => _cf.order
  )

  mergeExistingValues()
}

watch(
  () => storeData.value?.fields,
  () => {
    mergeExistingValues()
  }
)

watch(
  () => props.templateName,
  () => {
    getInitialCustomFields()
  }
)

// --- Section field lookup ---
function getFieldsForSection(section: FieldSection): CustomFieldItem[] {
  if (!storeData.value) return []
  return section.fieldLabels
    .map((label) =>
      storeData.value!.customFields.find(
        (f) => (f.custom_field?.label ?? f.label) === label
      )
    )
    .filter((f): f is CustomFieldItem => !!f)
}

function getFieldIndex(field: CustomFieldItem): number {
  return storeData.value?.customFields.indexOf(field) ?? -1
}

// TextArea fields should span full width; everything else is compact
function isTextAreaField(field: CustomFieldItem): boolean {
  return (field.custom_field?.type ?? field.type) === 'TextArea'
}

// Dropdown and Date fields get a bit more room
function isWideField(field: CustomFieldItem): boolean {
  const type = field.custom_field?.type ?? field.type
  return type === 'Dropdown' || type === 'Date'
}

// Net Amount is auto-calculated — render as read-only
function isNetAmountField(field: CustomFieldItem): boolean {
  return (field.custom_field?.label ?? field.label) === 'Net Amount'
}

// --- LR Receipt: Net Amount auto-calculation ---
// Net Amount = Basic Freight + Local Collection + Door Delivery + Hamali
//              + Docket Charge + Other Charge + FOV
const lrReceiptChargeFields = [
  'Basic Freight',
  'Local Collection',
  'Door Delivery',
  'Hamali',
  'Docket Charge',
  'Other Charge',
  'FOV',
]

function getCustomFieldValue(label: string): number {
  if (!storeData.value) return 0
  const lowerLabel = label.toLowerCase()
  const field = storeData.value.customFields.find(
    (f) => (f.custom_field?.label ?? f.label).toLowerCase() === lowerLabel
  )
  if (!field) return 0
  const num = Number(field.value)
  return isNaN(num) ? 0 : num
}

function setCustomFieldValue(label: string, value: number): void {
  if (!storeData.value) return
  const field = storeData.value.customFields.find(
    (f) => (f.custom_field?.label ?? f.label) === label
  )
  if (field) {
    field.value = value
    field.number_answer = value
  }
}

function recalcNetAmount(): void {
  if (props.templateName !== 'lr_receipt') return
  if (!storeData.value) return

  // Don't auto-calc if the Net Amount field doesn't exist yet
  const netAmountField = storeData.value.customFields.find(
    (f) => (f.custom_field?.label ?? f.label) === 'Net Amount'
  )
  if (!netAmountField) return

  const sum = lrReceiptChargeFields.reduce(
    (acc, label) => acc + getCustomFieldValue(label),
    0
  )
  setCustomFieldValue('Net Amount', sum)
}

// Watch all charge fields for changes
watch(
  () => storeData.value?.customFields.map((f) => ({
    label: f.custom_field?.label ?? f.label,
    value: f.value,
  })),
  () => {
    recalcNetAmount()
  },
  { deep: true }
)
</script>

<template>
  <div
    v-if="storeData && storeData.customFields.length > 0 && !isLoading"
    class="space-y-6"
  >
    <BaseCard
      v-for="section in sections"
      :key="section.title"
    >
      <template #header>
        <div class="flex items-center gap-2">
          <BaseIcon
            v-if="section.icon"
            :name="section.icon"
            class="h-5 w-5 text-primary-500"
          />
          <h3 class="text-base font-semibold text-heading">
            {{ section.title }}
          </h3>
        </div>
      </template>

      <div class="grid gap-x-4 gap-y-5 grid-cols-1 md:grid-cols-2 xl:grid-cols-3">
        <div
          v-for="field in getFieldsForSection(section)"
          :key="field.id"
          :class="[
            isTextAreaField(field) ? 'md:col-span-2 xl:col-span-3' : '',
            isWideField(field) ? 'xl:col-span-1' : '',
          ]"
        >
          <!-- Net Amount is auto-calculated, render as read-only -->
          <BaseInputGroup
            v-if="isNetAmountField(field)"
            :label="field.label"
          >
            <BaseInput
              :model-value="field.value"
              disabled
              class="bg-surface-muted font-semibold"
            />
          </BaseInputGroup>
          <SingleField
            v-else
            :custom-field-scope="customFieldScope"
            :store="store"
            :store-prop="storeProp"
            :index="getFieldIndex(field)"
            :field="field"
          />
        </div>
      </div>
    </BaseCard>
  </div>
</template>
