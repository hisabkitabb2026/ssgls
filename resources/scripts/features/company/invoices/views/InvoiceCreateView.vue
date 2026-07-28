<template>
  <!-- Hidden File Input for Auto Fill -->
  <input
    ref="fileInput"
    type="file"
    accept="image/*,application/pdf"
    class="hidden"
    @change="onAutoFillFileSelected"
  />

  <!-- Pause/Freezing Screen Loader Overlay -->
  <div
    v-if="isAutoFilling"
    class="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-gray-900/60 backdrop-blur-xs"
  >
    <div class="p-8 bg-surface ronded-xl shadow-2xl flex flex-col items-center max-w-sm w-full mx-4 border border-line-light">
      <div class="animate-spin rounded-full h-14 w-14 border-t-4 border-b-4 border-primary-500 mb-6"></div>
      <h3 class="text-xl font-bold text-heading mb-2">LR Receipt is being Auto Filled</h3>
      <p class="text-sm text-muted text-center leading-relaxed">
        Please wait a moment while we process your document. To ensure all fields are populated correctly, please do not close or navigate away from this screen.
      </p>
    </div>
  </div>

  <BasePage class="relative invoice-create-page">
    <form @submit.prevent="submitForm">
      <BasePageHeader :title="pageTitle">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
          <BaseBreadcrumbItem :title="indexTitle" :to="indexPath" />
          <BaseBreadcrumbItem
            v-if="isEdit"
            :title="editTitle"
            to="#"
            active
          />
          <BaseBreadcrumbItem v-else :title="createTitle" to="#" active />
        </BaseBreadcrumb>

        <template #actions>
          <!-- Make Recurring Toggle (regular invoices only) -->
          <div v-if="!isEdit && !isTransportReceipt" class="flex items-center mr-4">
            <BaseSwitch v-model="isRecurring" class="mr-2" />
            <span class="text-sm font-medium text-heading whitespace-nowrap">{{ $t('recurring_invoices.make_recurring') }}</span>
          </div>

          <router-link
            v-if="isEdit"
            :to="`/invoices/pdf/${invoiceStore.newInvoice.unique_hash}`"
            target="_blank"
          >
            <BaseButton class="mr-3" variant="primary-outline" type="button">
              <span class="flex">
                {{ $t('general.view_pdf') }}
              </span>
            </BaseButton>
          </router-link>

          <BaseButton
            v-if="isLrReceipt"
            class="mr-3"
            variant="primary-outline"
            type="button"
            :loading="isAutoFilling"
            :disabled="isAutoFilling"
            @click="triggerAutoFill"
          >
            <template #left="slotProps">
              <BaseIcon
                v-if="!isAutoFilling"
                name="SparklesIcon"
                :class="slotProps.class"
              />
            </template>
            Auto Fill LR
          </BaseButton>

          <BaseButton
            :loading="isSaving"
            :disabled="isSaving"
            variant="primary"
            type="submit"
          >
            <template #left="slotProps">
              <BaseIcon
                v-if="!isSaving"
                name="ArrowDownOnSquareIcon"
                :class="slotProps.class"
              />
            </template>
            {{ isTransportReceipt ? saveButtonLabel : (isRecurring ? $t('recurring_invoices.save_invoice') : $t('invoices.save_invoice')) }}
          </BaseButton>
        </template>
      </BasePageHeader>


      <!-- Booking Information box (LR Receipt only) -->
      <BaseCard v-if="isLrReceipt" class="mb-6">
        <template #header>
          <div class="flex items-center gap-2">
            <BaseIcon name="ClipboardIcon" class="h-5 w-5 text-primary-500" />
            <h3 class="text-base font-semibold text-heading">Booking Information</h3>
          </div>
        </template>
        <InvoiceBasicFields
          :v="v$"
          :is-loading="isLoadingContent"
          :is-edit="isEdit"
          :is-recurring="isRecurring"
          :show-consignee="isLrReceipt"
          :is-transport-receipt="isTransportReceipt"
        />
      </BaseCard>

      <!-- Invoice Information box (office_invoice only) -->
      <BaseCard v-else-if="isOfficeInvoice" class="mb-6">
        <template #header>
          <div class="flex items-center gap-2">
            <BaseIcon name="DocumentTextIcon" class="h-5 w-5 text-primary-500" />
            <h3 class="text-base font-semibold text-heading">Invoice Information</h3>
          </div>
        </template>
        <InvoiceBasicFields
          :v="v$"
          :is-loading="isLoadingContent"
          :is-edit="isEdit"
          :is-recurring="isRecurring"
          :show-consignee="false"
          :is-transport-receipt="isTransportReceipt"
        />
      </BaseCard>

      <!-- Regular invoices: basic fields without a card wrapper -->
      <InvoiceBasicFields
        v-else
        :v="v$"
        :is-loading="isLoadingContent"
        :is-edit="isEdit"
        :is-recurring="isRecurring"
        :show-consignee="isLrReceipt"
        :is-transport-receipt="isTransportReceipt"
      />





      <!-- Transport-specific: Custom fields & Lorry Receipt party fields -->
      <div v-if="isTransportReceipt" class="mb-8">
        <!-- Lorry Receipt: Owner/Driver/Broker party selector -->
        <LorryReceiptPartyFields v-if="isLorryReceipt" />

        <!-- Office Invoice: Consignment items table with Add New Item -->
        <OfficeInvoiceItemsTable
          v-if="isOfficeInvoice"
          :is-edit="isEdit"
          :is-loading="isLoadingContent"
          :store="invoiceStore"
          store-prop="newInvoice"
          :item-validation-scope="invoiceValidationScope"
          @consignment-valid="onConsignmentValidChange"
        />


        <!-- Transport fields — sectioned card layout (lr_receipt, lorry_receipt only) -->
        <TransportCustomFields
          v-if="!isOfficeInvoice"
          :is-loading="isLoadingContent"
          :store="invoiceStore"
          store-prop="newInvoice"
          :template-name="transportTemplateName"
          :class="isLorryReceipt ? 'mt-6' : ''"
        />

        <!-- Lorry Receipt: Document uploads (Aadhar, PAN, RC copies) -->
        <BaseCard v-if="isLorryReceipt" class="mt-6">
          <template #header>
            <div class="flex items-center gap-2">
              <BaseIcon name="PaperClipIcon" class="h-5 w-5 text-primary-500" />
              <h3 class="text-base font-semibold text-heading">
                Attached Document
              </h3>
            </div>
          </template>

          <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <BaseInputGroup
              v-for="document in lorryDocumentFields"
              :key="document.key"
              :label="document.label"
              variant="vertical"
            >
              <BaseFileUploader
                accept="image/*,application/pdf"
                base64
                :input-field-name="document.key"
                :model-value="getLorryDocumentValue(document.key)"
                :recommended-text="getLorryDocumentHint(document.key)"
                @change="onLorryDocumentChange"
                @remove="onLorryDocumentRemove(document.key)"
              />
            </BaseInputGroup>
          </div>
        </BaseCard>
      </div>

      <BaseScrollPane>
        <!-- Invoice Items (hidden for transport receipt templates which use custom fields) -->
        <DocumentItemsTable
          v-if="!isTransportEntryTemplate"

          :currency="invoiceStore.newInvoice.selectedCurrency"

          :is-loading="isLoadingContent"
          :item-validation-scope="invoiceValidationScope"
          :store="invoiceStore"
          store-prop="newInvoice"
        />

        <!-- Invoice Footer Section -->
        <div
          class="block mt-10 invoice-foot lg:flex lg:justify-between lg:items-start"
        >
          <div class="relative w-full lg:w-1/2 lg:mr-4">
            <!-- Invoice Custom Notes (hidden for transport entry templates) -->
            <DocumentNotes
              v-if="!isTransportEntryTemplate"
              :store="invoiceStore"
              store-prop="newInvoice"
              :fields="invoiceNoteFieldList"
              type="Invoice"
            />

            <!-- Invoice Custom Fields (for regular invoices only) -->
            <InvoiceCustomFields
              v-if="!isTransportReceipt"
              type="Invoice"
              :is-edit="isEdit"
              :is-loading="isLoadingContent"
              :store="invoiceStore"
              store-prop="newInvoice"
              :custom-field-scope="invoiceValidationScope"
              class="mb-6"
            />

            <!-- Invoice Template Button -->
            <TemplateSelectButton
              :store="invoiceStore"
              store-prop="newInvoice"
              :is-mark-as-default="isMarkAsDefault"
            />
            <SelectTemplateModal />
          </div>

          <DocumentTotals
            v-if="!isTransportEntryTemplate"
            :currency="invoiceStore.newInvoice.selectedCurrency"
            :is-loading="isLoadingContent"
            :store="invoiceStore"
            store-prop="newInvoice"
            tax-popup-type="invoice"
          />
        </div>
      </BaseScrollPane>
    </form>
  </BasePage>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import cloneDeep from 'lodash/cloneDeep'
import { parseISO, isValid, addDays, format, differenceInCalendarDays } from 'date-fns'
import {
  required,
  maxLength,
  helpers,
  requiredIf,
  decimal,
} from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useInvoiceStore } from '../store'
import { useRecurringInvoiceStore } from '@/scripts/features/company/recurring-invoices/store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { client as httpClient } from '@/scripts/api/client'
import InvoiceBasicFields from '../components/InvoiceBasicFields.vue'
import InvoiceCustomFields from '@/scripts/features/company/customers/components/CreateCustomFields.vue'
import TransportCustomFields from '../components/TransportCustomFields.vue'
import LorryReceiptPartyFields from '../components/LorryReceiptPartyFields.vue'
import OfficeInvoiceItemsTable from '../components/OfficeInvoiceItemsTable.vue'
import {
  DocumentItemsTable,
  DocumentTotals,
  DocumentNotes,
  TemplateSelectButton,
  SelectTemplateModal,
} from '../../../shared/document-form'

const invoiceStore = useInvoiceStore()
const recurringInvoiceStore = useRecurringInvoiceStore()
const companyStore = useCompanyStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const invoiceValidationScope = 'newInvoice'
const isSaving = ref<boolean>(false)
const isMarkAsDefault = ref<boolean>(false)
const isRecurring = ref<boolean>(false)

// Tracks whether all consignment numbers in the Office Invoice are valid
// (i.e., each matches an existing LR Receipt). Updated via emit from
// OfficeInvoiceItemsTable.
const allConsignmentsValid = ref<boolean>(false)

function onConsignmentValidChange(allValid: boolean): void {
  allConsignmentsValid.value = allValid
}

// --- Auto Fill LR ---
const isAutoFilling = ref<boolean>(false)
const fileInput = ref<HTMLInputElement | null>(null)

function triggerAutoFill(): void {
  fileInput.value?.click()
}

async function onAutoFillFileSelected(event: Event): Promise<void> {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return

  isAutoFilling.value = true

  const formData = new FormData()
  formData.append('file', file)

  try {
    const response = await httpClient.post('/api/v1/invoices/auto-fill', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    const data = response.data as Record<string, unknown>

    // 1. Populate Consignor and Consignee (customer)
    if (data.consignor) {
      invoiceStore.newInvoice.customer = data.consignor as typeof invoiceStore.newInvoice.customer
      invoiceStore.newInvoice.customer_id = (data.consignor as Record<string, unknown>).id as string
    }
    if (data.consignee) {
      invoiceStore.newInvoice.consignee_customer = data.consignee as typeof invoiceStore.newInvoice.consignee_customer
      invoiceStore.newInvoice.consignee_customer_id = (data.consignee as Record<string, unknown>).id as string
    }

    // 2. Populate basic fields
    if (data.date) {
      invoiceStore.newInvoice.invoice_date = data.date as string
    }
    if (data.due_date) {
      invoiceStore.newInvoice.due_date = data.due_date as string
    }
    if (data.docket_no) {
      invoiceStore.newInvoice.invoice_number = data.docket_no as string
    }

    // 3. Populate custom fields (From, To, Truck No, Mode of Payment, GST Tax Payable By, etc.)
    if (data.fields) {
      Object.entries(data.fields as Record<string, string>).forEach(([label, value]) => {
        setInvoiceCustomField(label, value)
      })
    }

    // 4. Populate item custom fields on the first item row
    if (data.item_fields && invoiceStore.newInvoice.items?.[0]) {
      Object.entries(data.item_fields as Record<string, string>).forEach(([label, value]) => {
        setItemCustomField(0, label, value)
      })
    }
  } catch (error) {
    console.error('Failed to auto-fill LR receipt:', error)
    notificationStore.showNotification({
      type: 'error',
      message: (error as { response?: { data?: { error?: string } } })?.response?.data?.error || 'Failed to auto-fill LR receipt. Please try again.',
    })
  } finally {
    isAutoFilling.value = false
    if (fileInput.value) {
      fileInput.value.value = ''
    }
  }
}

function setInvoiceCustomField(label: string, value: string | null): void {
  const normalizedLabel = label.toLowerCase().replace(/[^a-z0-9]/g, '')
  const field = invoiceStore.newInvoice.customFields?.find(
    (f) => (f.custom_field?.label ?? '').toLowerCase().replace(/[^a-z0-9]/g, '') === normalizedLabel,
  )
  if (field) {
    if (field.custom_field?.type === 'Number') {
      field.number_answer = value ? parseFloat(value) || 0 : 0
    } else {
      field.string_answer = value || ''
    }
  }
}

function setItemCustomField(itemIndex: number, label: string, value: string | null): void {
  const item = invoiceStore.newInvoice.items?.[itemIndex]
  if (!item) return

  const normalizedLabel = label.toLowerCase().replace(/[^a-z0-9]/g, '')
  const field = item.customFields?.find(
    (f) => (f.custom_field?.label ?? '').toLowerCase().replace(/[^a-z0-9]/g, '') === normalizedLabel,
  )
  if (field) {
    if (field.custom_field?.type === 'Number') {
      field.number_answer = value ? parseFloat(value) || 0 : 0
    } else {
      field.string_answer = value || ''
    }
  }
}


// --- Lorry Receipt: Document uploads ---
interface LorryDocumentField {
  key: string
  label: string
}

const lorryDocumentFields: LorryDocumentField[] = [
  { key: 'aadhar_front_copy', label: 'Aadhar Front Copy' },
  { key: 'aadhar_back_copy', label: 'Aadhar Back Copy' },
  { key: 'pan_card_front_copy', label: 'Pan Card Copy Front' },
  { key: 'pan_card_back_copy', label: 'Pan Card Copy Back' },
  { key: 'rc_copy_front', label: 'RC Copy Front' },
  { key: 'rc_copy_back', label: 'RC Copy Back' },
]

interface LorryDocument {
  name?: string
  data?: string
  file_name?: string
  url?: string
  image?: string
  mime_type?: string
}

function onLorryDocumentChange(
  fieldName: string,
  data: FileList | File | string,
  _fileCount: number,
  file?: File,
): void {
  const docs = (invoiceStore.newInvoice as Record<string, unknown>).lorry_documents as
    | Record<string, LorryDocument>
    | undefined
  ;(invoiceStore.newInvoice as Record<string, unknown>).lorry_documents = {
    ...(docs || {}),
    [fieldName]: {
      name: file?.name,
      data: data as string,
    },
  }
}

function onLorryDocumentRemove(fieldName: string): void {
  const docs = (invoiceStore.newInvoice as Record<string, unknown>).lorry_documents as
    | Record<string, LorryDocument>
    | undefined
  if (!docs) return
  delete docs[fieldName]
}

function getLorryDocumentHint(fieldName: string): string {
  const docs = (invoiceStore.newInvoice as Record<string, unknown>).lorry_documents as
    | Record<string, LorryDocument>
    | undefined
  const document = docs?.[fieldName]
  if (document?.file_name) {
    return `Uploaded: ${document.file_name}`
  }
  return 'PNG, JPEG, or PDF'
}

function getLorryDocumentValue(fieldName: string): Array<{ name: string; image: string | null }> {
  const docs = (invoiceStore.newInvoice as Record<string, unknown>).lorry_documents as
    | Record<string, LorryDocument>
    | undefined
  const doc = docs?.[fieldName]
  if (!doc) return []
  const name = doc.file_name || doc.name || ''
  const isPdf = name.toLowerCase().endsWith('.pdf') || doc.mime_type === 'application/pdf'
  const isImage = !isPdf
  return [
    {
      name,
      image: isImage ? (doc.url || doc.image || null) : null,
    },
  ]
}

// Auto-due-date bookkeeping: tracks whether the user has manually edited the
// due date, so the invoice_date watcher can avoid clobbering a manual value.
const dueDateManuallyChanged = ref<boolean>(false)
let isAutoUpdatingDueDate = false
const expectedAutoDueDate = ref<string | null>(null)

const invoiceNoteFieldList = ref<string[]>(['customer', 'company', 'invoice'])

const isLoadingContent = computed<boolean>(
  () => invoiceStore.isFetchingInvoice || invoiceStore.isFetchingInitialSettings,
)

// --- Transport receipt detection (mirrors ssgls InvoiceCreate.vue) ---
const isLrReceipt = computed<boolean>(() => route.path.includes('/admin/lr-receipts'))
const isLorryReceipt = computed<boolean>(() => route.path.includes('/admin/lorry-receipts'))
const isStandardInvoice = computed<boolean>(() => route.path.includes('/admin/standard-invoices'))
// Standard invoices (via /admin/standard-invoices) use the upstream
// InvoiceShelf flow (items table, taxes, discounts, template selector).
// Invoice Receipts (/admin/invoices), LR Receipts, and Lorry Receipts
// are transport receipts with their own custom field layouts.
const isTransportReceipt = computed<boolean>(() => !isStandardInvoice.value)
const transportTemplateName = computed<string>(() => {
  if (isLorryReceipt.value) return 'lorry_receipt'
  if (isLrReceipt.value) return 'lr_receipt'
  return 'office_invoice'
})

const isOfficeInvoice = computed<boolean>(() =>
  transportTemplateName.value === 'office_invoice',
)



// Templates that use custom fields instead of the standard items table
const isTransportEntryTemplate = computed<boolean>(() => {
  return ['office_invoice', 'lr_receipt', 'lorry_receipt'].includes(
    invoiceStore.newInvoice.template_name ?? '',
  )
})

// Templates that have NO items at all (lr_receipt, lorry_receipt use only
// Invoice-level custom fields). office_invoice DOES use Item-level fields.
const isItemlessTransportTemplate = computed<boolean>(() => {
  return ['lr_receipt', 'lorry_receipt'].includes(
    invoiceStore.newInvoice.template_name ?? '',
  )
})

const indexTitle = computed<string>(() => {
  if (isLorryReceipt.value) return 'Lorry Receipts'
  if (isLrReceipt.value) return 'LR Receipts'
  if (isStandardInvoice.value) return t('invoices.invoice', 2)
  return t('invoices.invoice', 2)
})

const indexPath = computed<string>(() => {
  if (isLorryReceipt.value) return '/admin/lorry-receipts'
  if (isLrReceipt.value) return '/admin/lr-receipts'
  if (isStandardInvoice.value) return '/admin/standard-invoices'
  return '/admin/invoices'
})

const createTitle = computed<string>(() => {
  if (isLorryReceipt.value) return 'New Lorry Receipt'
  if (isLrReceipt.value) return 'New LR Receipt'
  return t('invoices.new_invoice')
})

const editTitle = computed<string>(() => {
  if (isLorryReceipt.value) return 'Edit Lorry Receipt'
  if (isLrReceipt.value) return 'Edit LR Receipt'
  return t('invoices.edit_invoice')
})

const saveButtonLabel = computed<string>(() => {
  if (isLorryReceipt.value) return 'Save Lorry Receipt'
  if (isLrReceipt.value) return 'Save LR'
  return t('invoices.save_invoice')
})


const pageTitle = computed<string>(() =>
  isEdit.value ? editTitle.value : createTitle.value,
)

const isEdit = computed<boolean>(() =>
  route.name === 'invoices.edit' ||
  route.name === 'standard-invoices.edit' ||
  route.name === 'recurring-invoices.edit' ||
  route.name === 'lr-receipts.edit' ||
  route.name === 'lorry-receipts.edit',
)

const isRecurringEdit = computed<boolean>(() =>
  route.name === 'recurring-invoices.edit',
)

const rules = computed(() => {
  if (isRecurring.value) {
    return {
      customer_id: {
        required: helpers.withMessage(t('validation.required'), required),
      },
    }
  }

  const baseRules: Record<string, unknown> = {
    invoice_date: {
      required: helpers.withMessage(t('validation.required'), required),
    },
    reference_number: {
      maxLength: helpers.withMessage(t('validation.price_maxlength'), maxLength(255)),
    },
    customer_id: {
      required: helpers.withMessage(t('validation.required'),
        requiredIf(() => !isLrReceipt.value && !isLorryReceipt.value && !isOfficeInvoice.value)),
    },
    invoice_number: {
      required: helpers.withMessage(t('validation.required'), required),
    },
    exchange_rate: {
      required: requiredIf(() => invoiceStore.showExchangeRate && !isTransportReceipt.value),
      decimal: helpers.withMessage(t('validation.valid_exchange_rate'), decimal),
    },
  }

  // Office Invoice: each item must have a consignment number
  if (isOfficeInvoice.value) {
    baseRules.items = {
      required: helpers.withMessage('At least one consignment item is required', required),
      $each: helpers.forEach({
        consignment_number: {
          required: helpers.withMessage('Consignment No is required', required),
        },
      }),
    }
  }

  return baseRules
})

const v$ = useVuelidate(
  rules,
  computed(() => invoiceStore.newInvoice),
  { $scope: invoiceValidationScope },
)

// Initialization
invoiceStore.resetCurrentInvoice()
v$.value.$reset

// Set the transport template name before fetching initial settings
if (isTransportReceipt.value) {
  invoiceStore.newInvoice.template_name = transportTemplateName.value
}

// Check for recurring mode
if (route.query.recurring === '1' || isRecurringEdit.value) {
  isRecurring.value = true
}

// Initialize recurring store
recurringInvoiceStore.initFrequencies(t)

if (isRecurringEdit.value) {
  // Editing a recurring invoice — load its data into both stores
  recurringInvoiceStore.resetCurrentRecurringInvoice()
  recurringInvoiceStore.fetchRecurringInvoiceInitialSettings(
    true,
    { id: route.params.id as string, query: route.query as Record<string, string> },
  ).then(() => {
    // Sync recurring data to invoice store for the shared form fields
    const ri = recurringInvoiceStore.newRecurringInvoice
    invoiceStore.newInvoice.customer = ri.customer
    invoiceStore.newInvoice.customer_id = ri.customer_id
    invoiceStore.newInvoice.items = ri.items as typeof invoiceStore.newInvoice.items
    invoiceStore.newInvoice.taxes = ri.taxes as typeof invoiceStore.newInvoice.taxes
    invoiceStore.newInvoice.notes = ri.notes
    invoiceStore.newInvoice.discount = ri.discount
    invoiceStore.newInvoice.discount_type = ri.discount_type as typeof invoiceStore.newInvoice.discount_type
    invoiceStore.newInvoice.discount_val = ri.discount_val
    invoiceStore.newInvoice.discount_per_item = ri.discount_per_item
    invoiceStore.newInvoice.tax_per_item = ri.tax_per_item
    invoiceStore.newInvoice.tax_included = ri.tax_included
    invoiceStore.newInvoice.template_name = ri.template_name
    invoiceStore.newInvoice.currency_id = ri.currency_id as typeof invoiceStore.newInvoice.currency_id
    invoiceStore.newInvoice.exchange_rate = ri.exchange_rate as typeof invoiceStore.newInvoice.exchange_rate
    invoiceStore.newInvoice.customFields = ri.customFields as typeof invoiceStore.newInvoice.customFields
  })
} else if (!isRecurring.value) {
  // Normal invoice / LR / Lorry Receipt create/edit
  invoiceStore.fetchInvoiceInitialSettings(
    isEdit.value,
    { id: route.params.id as string, query: route.query as Record<string, string> },
  )
} else {
  // New recurring invoice — just initialize
  recurringInvoiceStore.resetCurrentRecurringInvoice()
  invoiceStore.fetchInvoiceInitialSettings(
    false,
    { query: route.query as Record<string, string> },
  )
}

// Ensure transport template is preserved after initial settings load
watch(
  () => invoiceStore.isFetchingInitialSettings,
  (loading) => {
    if (!loading && isTransportReceipt.value && !isEdit.value) {
      invoiceStore.newInvoice.template_name = transportTemplateName.value
    }
  },
)

// Initialize recurring store when toggled on manually
watch(isRecurring, (newVal) => {
  if (newVal && !isRecurringEdit.value) {
    recurringInvoiceStore.resetCurrentRecurringInvoice()
  }
})

watch(
  () => invoiceStore.newInvoice.customer,
  (newVal) => {
    if (newVal && (newVal as Record<string, unknown>).currency) {
      invoiceStore.newInvoice.selectedCurrency = (
        newVal as Record<string, unknown>
      ).currency as Record<string, unknown>
    }
  },
)

// Detect manual edits to the due date so we know not to overwrite them
// when the invoice date changes afterwards.
watch(
  () => invoiceStore.newInvoice.due_date,
  (newDueDate, oldDueDate) => {
    if (
      !isAutoUpdatingDueDate
      && newDueDate !== oldDueDate
      && oldDueDate !== undefined
      && newDueDate !== expectedAutoDueDate.value
    ) {
      dueDateManuallyChanged.value = true
    }
  },
)

// Auto-compute due_date = invoice_date + invoice_due_date_days whenever the
// invoice date changes, if the company setting opts into automatic dates
// and the user hasn't locked a manual due date in the future.
watch(
  () => invoiceStore.newInvoice.invoice_date,
  (newInvoiceDate, oldInvoiceDate) => {
    if (
      companyStore.selectedCompanySettings?.invoice_set_due_date_automatically !== 'YES'
      || !newInvoiceDate
      || newInvoiceDate === oldInvoiceDate
      || oldInvoiceDate === undefined
    ) {
      return
    }

    const dueDateDays = parseInt(
      companyStore.selectedCompanySettings?.invoice_due_date_days ?? '0',
      10,
    )
    const invoiceDate = parseISO(newInvoiceDate as string)
    if (!isValid(invoiceDate)) {
      return
    }

    const calculatedDueDate = format(addDays(invoiceDate, dueDateDays), 'yyyy-MM-dd')
    expectedAutoDueDate.value = calculatedDueDate

    if (dueDateManuallyChanged.value) {
      const currentDueDate = invoiceStore.newInvoice.due_date
      if (currentDueDate) {
        const currentDue = parseISO(currentDueDate as string)
        if (isValid(currentDue) && differenceInCalendarDays(currentDue, invoiceDate) >= 0) {
          // Manual due date still valid — leave it alone
          return
        }
      }
      // Manual due date is in the past / invalid — take back control
      dueDateManuallyChanged.value = false
    }

    isAutoUpdatingDueDate = true
    invoiceStore.newInvoice.due_date = calculatedDueDate
    isAutoUpdatingDueDate = false
  },
)

async function submitForm(): Promise<void> {
  v$.value.$touch()

  if (v$.value.$invalid) {
    console.log('Invoice form invalid. Errors:', JSON.stringify(
      v$.value.$errors.map((e: { $property: string; $message: string }) => `${e.$property}: ${e.$message}`)
    ))
    return
  }

  // Office Invoice: block submission if any consignment number doesn't
  // match an existing LR Receipt
  if (isOfficeInvoice.value && !allConsignmentsValid.value) {
    notificationStore.showNotification({
      type: 'error',
      message: 'One or more consignment numbers do not match existing LR Receipts. Please fix them before saving.',
    })
    return
  }

  isSaving.value = true

  try {
    if (isRecurring.value) {
      const recurringData = recurringInvoiceStore.newRecurringInvoice
      const invoiceData = invoiceStore.newInvoice

      // Build clean payload with only backend-expected fields
      const data: Record<string, unknown> = {
        // Recurring-specific fields
        starts_at: recurringData.starts_at,
        send_automatically: recurringData.send_automatically ? 1 : 0,
        frequency: recurringData.frequency,
        status: recurringData.status || 'ACTIVE',
        limit_by: recurringData.limit_by || 'NONE',
        limit_count: recurringData.limit_count,
        limit_date: recurringData.limit_date,

        // Shared fields from invoice form
        customer_id: invoiceData.customer_id,
        discount: invoiceData.discount,
        discount_type: invoiceData.discount_type,
        discount_val: invoiceData.discount_val,
        discount_per_item: invoiceData.discount_per_item,
        tax_per_item: invoiceData.tax_per_item,
        tax_included: invoiceData.tax_included,
        sales_tax_type: invoiceData.sales_tax_type,
        sales_tax_address_type: invoiceData.sales_tax_address_type,
        notes: invoiceData.notes,
        template_name: invoiceData.template_name,
        items: cloneDeep(invoiceData.items),
        taxes: cloneDeep(invoiceData.taxes),
        currency_id: invoiceData.currency_id,
        exchange_rate: invoiceData.exchange_rate,
        customFields: invoiceData.customFields,

        // Calculated totals
        sub_total: Math.round(invoiceStore.getSubTotal),
        total: Math.round(invoiceStore.getTotal),
        tax: Math.round(invoiceStore.getTotalTax),
      }

      let response
      if (isRecurringEdit.value) {
        data.id = recurringData.id
        response = await recurringInvoiceStore.updateRecurringInvoice(data)
      } else {
        response = await recurringInvoiceStore.addRecurringInvoice(data)
      }
      router.push(`/admin/recurring-invoices/${response.data.data.id}/view`)
    } else {
      const data: Record<string, unknown> = {
        ...cloneDeep(invoiceStore.newInvoice),
        sub_total: Math.round(invoiceStore.getSubTotal),
        total: Math.round(invoiceStore.getTotal),
        tax: Math.round(invoiceStore.getTotalTax),
      }

      // lr_receipt and lorry_receipt use only Invoice-level custom fields —
      // strip the default empty item stubs so the backend doesn't try to
      // insert rows with null names. office_invoice DOES use Item-level
      // custom fields, so items are kept.
      if (isItemlessTransportTemplate.value) {
        delete data.items
        delete data.taxes
      } else if (!isOfficeInvoice.value) {
        const items = data.items as Array<Record<string, unknown>>
        if (data.discount_per_item === 'YES') {
          items.forEach((item, index) => {
            if (item.discount_type === 'fixed') {
              items[index].discount = (item.discount as number) * 100
            }
          })
        } else {
          if (data.discount_type === 'fixed') {
            data.discount = (data.discount as number) * 100
          }
        }

        const taxes = data.taxes as Array<Record<string, unknown>>
        if (data.tax_per_item !== 'YES' && taxes.length) {
          data.tax_type_ids = taxes.map((tax) => tax.tax_type_id)
        }
      }

      const action = isEdit.value
        ? invoiceStore.updateInvoice
        : invoiceStore.addInvoice

      const response = await action(data)

      // Redirect based on the route
      const redirectPath = isLorryReceipt.value
        ? `/admin/lorry-receipts/${response.data.data.id}/view`
        : isLrReceipt.value
          ? `/admin/lr-receipts/${response.data.data.id}/view`
          : isStandardInvoice.value
            ? `/admin/standard-invoices/${response.data.data.id}/view`
            : `/admin/invoices/${response.data.data.id}/view`
      router.push(redirectPath)
    }
  } catch (err) {
    console.error(err)
  }

  isSaving.value = false
}
</script>
