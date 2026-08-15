<template>
  <div class="grid grid-cols-12 gap-8 mt-6 mb-8">
    <!-- Lorry Receipt: Party selector uses LorryPartyProfile (OWNER/BROKER) -->
    <LorryPartySelectPopup
      v-if="isLorryReceipt"
      :class="showConsignee ? 'order-1 col-span-12 lg:col-span-4 pr-0' : 'col-span-12 lg:col-span-6 pr-0'"
    />

    <!-- All other templates: Customer-based selector -->
    <BaseCustomerSelectPopup
      v-else
      :valid="v.customer_id"
      :content-loading="isLoading"
      type="invoice"
      :label="isTransportReceipt ? customerLabel : ''"
      :customer-type="null"
      :show-lorry-profiles="false"
      :class="showConsignee ? 'order-1 col-span-12 lg:col-span-4 pr-0' : 'col-span-12 lg:col-span-6 pr-0'"
    />


    <!-- Consignee Selector (LR Receipt only) -->
    <div
      v-if="showConsignee"
      class="order-2 col-span-12 lg:col-span-4 pr-0"
    >
      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersBox
          :rounded="true"
          class="w-full"
          style="min-height: 170px"
        />
      </BaseContentPlaceholders>
      <div v-else>
        <!-- Selected consignee card -->
        <div
          v-if="selectedConsignee"
          class="
            flex flex-col
            p-4
            bg-surface
            border border-line-light border-solid
            min-h-[170px]
            rounded-xl
            shadow
          "
          @click.stop
        >
          <div class="flex relative justify-between mb-2">
            <BaseText
              :text="selectedConsignee.name || selectedConsignee.display_name"
              class="flex-1 text-base font-medium text-left text-heading"
            />
            <div class="flex">
              <a
                class="
                  relative
                  my-0
                  ml-6
                  text-sm
                  font-medium
                  cursor-pointer
                  text-primary-500
                  items-center
                  flex
                "
                @click.stop="editConsignee"
              >
                <BaseIcon name="PencilIcon" class="text-muted h-4 w-4 mr-1" />
                {{ $t('general.edit') }}
              </a>
              <a
                class="
                  relative
                  my-0
                  ml-6
                  text-sm
                  flex
                  items-center
                  font-medium
                  cursor-pointer
                  text-primary-500
                "
                @click="resetConsignee"
              >
                <BaseIcon name="XCircleIcon" class="text-muted h-4 w-4 mr-1" />
                {{ $t('general.deselect') }}
              </a>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-8 mt-2">
            <div v-if="selectedConsignee.billing" class="flex flex-col">
              <label
                class="
                  mb-1
                  text-sm
                  font-medium
                  text-left text-subtle
                  uppercase
                  whitespace-nowrap
                "
              >
                {{ $t('general.bill_to') }}
              </label>

              <div
                v-if="selectedConsignee.billing"
                class="flex flex-col flex-1 p-0 text-left"
              >
                <label
                  v-if="selectedConsignee.billing.name"
                  class="relative w-11/12 text-sm truncate"
                >
                  {{ selectedConsignee.billing.name }}
                </label>

                <label class="relative w-11/12 text-sm truncate">
                  <span v-if="selectedConsignee.billing.city">
                    {{ selectedConsignee.billing.city }}
                  </span>
                  <span
                    v-if="
                      selectedConsignee.billing.city &&
                      selectedConsignee.billing.state
                    "
                  >
                    ,
                  </span>
                  <span v-if="selectedConsignee.billing.state">
                    {{ selectedConsignee.billing.state }}
                  </span>
                </label>
                <label
                  v-if="selectedConsignee.billing.zip"
                  class="relative w-11/12 text-sm truncate"
                >
                  {{ selectedConsignee.billing.zip }}
                </label>
              </div>
            </div>

            <div v-if="selectedConsignee.shipping" class="flex flex-col">
              <label
                class="
                  mb-1
                  text-sm
                  font-medium
                  text-left text-subtle
                  uppercase
                  whitespace-nowrap
                "
              >
                {{ $t('general.ship_to') }}
              </label>

              <div
                v-if="selectedConsignee.shipping"
                class="flex flex-col flex-1 p-0 text-left"
              >
                <label
                  v-if="selectedConsignee.shipping.name"
                  class="relative w-11/12 text-sm truncate"
                >
                  {{ selectedConsignee.shipping.name }}
                </label>

                <label class="relative w-11/12 text-sm truncate">
                  <span v-if="selectedConsignee.shipping.city">
                    {{ selectedConsignee.shipping.city }}
                  </span>
                  <span
                    v-if="
                      selectedConsignee.shipping.city &&
                      selectedConsignee.shipping.state
                    "
                  >
                    ,
                  </span>
                  <span v-if="selectedConsignee.shipping.state">
                    {{ selectedConsignee.shipping.state }}
                  </span>
                </label>
                <label
                  v-if="selectedConsignee.shipping.zip"
                  class="relative w-11/12 text-sm truncate"
                >
                  {{ selectedConsignee.shipping.zip }}
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- Unselected: Popover search selector -->
        <Popover v-else v-slot="{ open }" class="relative flex flex-col rounded-xl">
          <PopoverButton
            :class="{
              'focus:ring-2 focus:ring-primary-400': !open,
            }"
            class="w-full outline-hidden rounded-xl"
            @click="ensureConsigneesLoaded"
          >
            <div class="relative flex justify-center px-0 p-0 py-16 bg-surface border border-line-light border-solid rounded-xl shadow min-h-[170px]">
              <BaseIcon name="UserIcon" class="flex justify-center !w-10 !h-10 p-2 mr-5 text-sm text-white bg-surface-muted rounded-full font-base" />
              <div class="mt-1">
                <label class="text-lg font-medium text-heading">Consignee</label>
              </div>
            </div>
          </PopoverButton>

          <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-1 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-1 opacity-0"
          >
            <div v-if="open" class="absolute min-w-full z-10">
              <PopoverPanel
                v-slot="{ close }"
                static
                class="overflow-hidden rounded-xl shadow ring-1 ring-black/5 bg-surface"
              >
                <div class="relative">
                  <BaseInput
                    v-model="consigneeSearch"
                    container-class="m-4"
                    :placeholder="$t('general.search')"
                    type="text"
                    icon="search"
                    @update:model-value="debounceSearchConsignees"
                  />

                  <ul class="max-h-80 flex flex-col overflow-auto list border-t border-line-light">
                    <li
                      v-for="customer in consigneeResults"
                      :key="customer.id"
                      class="flex px-6 py-2 border-b border-line-light border-solid cursor-pointer hover:cursor-pointer hover:bg-hover-strong focus:outline-hidden focus:bg-surface-tertiary last:border-b-0"
                      @click="selectConsignee(customer, close)"
                    >
                      <span class="flex items-center content-center justify-center w-10 h-10 mr-4 text-xl font-semibold leading-9 text-white bg-surface-muted rounded-full avatar">
                        {{ initGenerator(customer.name || customer.display_name) }}
                      </span>
                      <div class="flex-grow flex flex-col justify-center text-left">
                        <BaseText v-if="customer.name" :text="customer.name" class="m-0 text-base font-normal leading-tight cursor-pointer" />
                        <BaseText v-if="customer.contact_name" :text="customer.contact_name" class="m-0 text-sm font-medium text-subtle cursor-pointer" />
                        <BaseText v-if="customer.phone" :text="customer.phone" class="m-0 text-xs text-muted cursor-pointer" />
                      </div>
                    </li>
                  </ul>

                  <button
                    v-if="userStore.hasAbilities(ABILITIES.CREATE_CUSTOMER)"
                    type="button"
                    class="h-10 flex items-center justify-center w-full px-2 py-3 bg-surface-muted border-none outline-hidden focus:bg-surface-muted"
                    @click="openCustomerModal(close)"
                  >
                    <BaseIcon name="UserPlusIcon" class="text-primary-400" />
                    <label class="m-0 ml-3 text-sm leading-none cursor-pointer font-base text-primary-400">
                      {{ $t('customers.add_new_customer') }}
                    </label>
                  </button>
                </div>
              </PopoverPanel>
            </div>
          </transition>
        </Popover>
      </div>
    </div>

    <RecurringFields
      v-if="isRecurring"
      :is-loading="isLoading"
      :is-edit="isEdit"
    />

    <BaseInputGrid
      v-else
      :class="showConsignee ? 'order-3 col-span-12 lg:col-span-4' : 'col-span-12 lg:col-span-6'"
      class="rounded-xl shadow border border-line-light bg-surface p-5"
    >
      <BaseInputGroup
        :label="isTransportReceipt ? dateLabel : $t('invoices.invoice_date')"
        :content-loading="isLoading"
        required
        :error="v.invoice_date.$error && v.invoice_date.$errors[0].$message"
      >
        <BaseDatePicker
          v-model="invoiceStore.newInvoice.invoice_date"
          :content-loading="isLoading"
          :calendar-button="true"
          calendar-button-icon="calendar"
          :enable-time="enableTime"
          :time24hr="time24h"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('invoices.due_date')"
        :content-loading="isLoading"
      >
        <BaseDatePicker
          v-model="invoiceStore.newInvoice.due_date"
          :content-loading="isLoading"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="isTransportReceipt ? numberLabel : $t('invoices.invoice_number')"
        :content-loading="isLoading"
        :error="v.invoice_number.$error && v.invoice_number.$errors[0].$message"
        required
      >
        <div class="flex gap-2">
          <BaseInput
            v-model="invoiceStore.newInvoice.invoice_number"
            class="flex-1"
            :content-loading="isLoading"
            @input="v.invoice_number.$touch()"
          />
          <BaseButton
            v-if="!isEdit && !invoiceStore.newInvoice.invoice_number"
            type="button"
            variant="primary-outline"
            size="sm"
            :loading="isGeneratingNumber"
            @click="generateInvoiceNumber"
          >
            <template #left="slotProps">
              <BaseIcon name="SparklesIcon" :class="slotProps.class" />
            </template>
            {{ $t('general.auto_generate') }}
          </BaseButton>
        </div>
      </BaseInputGroup>

      <!-- Received No Of Bilties (lorry_receipt only — right after Challan No) -->
      <BaseInputGroup
        v-if="isLorryReceipt"
        label="Received No Of Bilties"
        :content-loading="isLoading"
      >
        <BaseInput
          v-model="invoiceStore.newInvoice.received_no_bilties"
          :content-loading="isLoading"
          placeholder="e.g. 1937, 471"
          @input="onBiltiesInput"
        />
      </BaseInputGroup>

      <!-- GST Tax Through dropdown (office_invoice template only) -->
      <BaseInputGroup
        v-if="gstTaxThroughField"
        :label="gstTaxThroughField.custom_field?.label ?? gstTaxThroughField.label ?? 'GST Tax Through'"
        :required="gstTaxThroughField.custom_field?.is_required ? true : false"
      >
        <BaseMultiselect
          v-model="gstTaxThroughField.value"
          :options="gstTaxThroughOptions"
          label="name"
          value-prop="name"
          :can-deselect="false"
          :can-clear="false"
        />
      </BaseInputGroup>

      <ExchangeRateConverter
        v-if="!isTransportReceipt"
        :store="invoiceStore"
        store-prop="newInvoice"
        :v="v"
        :is-loading="isLoading"
        :is-edit="isEdit"
        :customer-currency="invoiceStore.newInvoice.currency_id"
      />
    </BaseInputGrid>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import { useDebounceFn } from '@vueuse/core'
import { ExchangeRateConverter } from '../../../shared/document-form'
import { useInvoiceStore } from '../store'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useCustomerStore } from '@/scripts/features/company/customers/store'
import { useGlobalStore } from '@/scripts/stores/global.store'
import { customerService } from '../../../../api/services/customer.service'
import { invoiceService } from '@/scripts/api/services/invoice.service'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { useUserStore } from '@/scripts/stores/user.store'
import { ABILITIES } from '@/scripts/config/abilities'
import RecurringFields from './RecurringFields.vue'
import LorryPartySelectPopup from './LorryPartySelectPopup.vue'


interface ValidationField {
  $error: boolean
  $errors: Array<{ $message: string }>
  $touch: () => void
}

interface CustomerAddress {
  name?: string
  address_street_1?: string
  address_street_2?: string
  city?: string
  state?: string
  zip?: string
}

interface Customer {
  id: number
  name?: string
  display_name?: string
  phone?: string
  tax_id?: string
  billing?: CustomerAddress
  shipping?: CustomerAddress
}

interface CustomFieldItem {
  id: number
  label?: string
  value?: string | boolean | number | null
  string_answer?: string
  custom_field?: {
    label: string
    options?: string[] | null
    is_required?: boolean
  }
}

interface Props {
  v: Record<string, ValidationField>
  isLoading?: boolean
  isEdit?: boolean
  isRecurring?: boolean
  showConsignee?: boolean
  isTransportReceipt?: boolean
  companySettings?: Record<string, string>
}

const props = withDefaults(defineProps<Props>(), {
  isLoading: false,
  isEdit: false,
  isRecurring: false,
  showConsignee: false,
  isTransportReceipt: false,
  companySettings: () => ({}),
})


const invoiceStore = useInvoiceStore()
const modalStore = useModalStore()
const customerStore = useCustomerStore()
const globalStore = useGlobalStore()
const userStore = useUserStore()
const notificationStore = useNotificationStore()

const consigneeSearch = ref('')
const consigneeResults = ref<Customer[]>([])
const selectedConsignee = ref<Customer | null>(null)
const isInitialized = ref(false)
const isGeneratingNumber = ref(false)

fetchConsignees()

function initGenerator(name?: string): string {
  if (name) {
    const nameSplit = name.split(' ')
    return nameSplit[0].charAt(0).toUpperCase()
  }
  return ''
}

const enableTime = computed<boolean>(() => {
  return props.companySettings?.invoice_use_time === 'YES'
})

const time24h = computed<boolean>(() => {
  const format = props.companySettings?.carbon_time_format ?? ''
  return format.indexOf('H') > -1
})

const isTransportReceipt = computed<boolean>(() => props.isTransportReceipt || props.showConsignee)

// Lorry Receipt detection — used to show "Received No Of Bilties" in the basic box
const isLorryReceipt = computed<boolean>(() =>
  invoiceStore.newInvoice.template_name === 'lorry_receipt',
)

// Received No Of Bilties — custom field shown in the basic fields box for lorry_receipt
const receivedBiltiesField = computed<CustomFieldItem | undefined>(() => {
  return getInvoiceField('Received No Of Bilties')
})

// Only allow numbers and commas in the bilties field
function onBiltiesInput(): void {
  const raw = String(invoiceStore.newInvoice.received_no_bilties ?? '')
  invoiceStore.newInvoice.received_no_bilties = raw.replace(/[^0-9,]/g, '')

  // Trigger debounced auto-fill check — only fires when a single bilty
  // number is entered (no comma). If the entry matches an LR Receipt
  // docket number, the Lorry Receipt fields are auto-filled from it.
  debouncedAutoFillFromBilty()
}

/**
 * When the user enters a single bilty number (no comma), look it up as an
 * LR Receipt docket number. If found, auto-fill the Lorry Receipt's
 * transport fields from the matching LR Receipt.
 *
 * Field mapping (LR Receipt → Lorry Receipt):
 *   from_code       → from_code        (From → From)
 *   to_code         → to_code          (To → To)
 *   no_of_articles  → no_of_packages   (No of Articles → No Of Packages)
 *   actual_weight   → actual_weight    (Actual Weight → Actual Weight)
 *   charged_weight  → charged_weight   (Charged Weight → Charge Weight)
 *   truck_no        → truck_no         (Truck No → Lorry No)
 */
async function tryAutoFillFromBilty(): Promise<void> {
  // Skip in edit mode — user is editing an existing receipt
  if (props.isEdit) return

  const raw = String(invoiceStore.newInvoice.received_no_bilties ?? '').trim()

  // Only auto-fill for a single entry (no comma)
  if (!raw || raw.includes(',')) return

  try {
    const response = await invoiceService.findByInvoiceNumber(raw, 'lr_receipt')
    if (!response?.data) return

    const lr = response.data as Record<string, unknown>

    // Auto-fill Lorry Receipt fields from the matching LR Receipt
    if (lr.from_code) invoiceStore.newInvoice.from_code = lr.from_code as string
    if (lr.to_code) invoiceStore.newInvoice.to_code = lr.to_code as string
    if (lr.no_of_articles) invoiceStore.newInvoice.no_of_packages = lr.no_of_articles as string
    if (lr.actual_weight) invoiceStore.newInvoice.actual_weight = lr.actual_weight as string
    if (lr.charged_weight) invoiceStore.newInvoice.charged_weight = lr.charged_weight as string
    if (lr.truck_no) invoiceStore.newInvoice.truck_no = lr.truck_no as string

    notificationStore.showNotification({
      type: 'success',
      message: `Auto-filled from LR Receipt: ${lr.invoice_number ?? raw}`,
    })
  } catch {
    // No match found — silently do nothing
  }
}

const debouncedAutoFillFromBilty = useDebounceFn(() => {
  tryAutoFillFromBilty()
}, 600)

const customerLabel = computed<string>(() => {
  const templateName = invoiceStore.newInvoice.template_name
  if (templateName === 'lorry_receipt') return 'Party'
  if (templateName === 'lr_receipt') return 'Consignor'
  return 'Customer'
})

const numberLabel = computed<string>(() => {
  const templateName = invoiceStore.newInvoice.template_name
  if (templateName === 'lorry_receipt') return 'Challan No.'
  if (templateName === 'lr_receipt') return 'Docket No.'
  return 'Invoice No'
})

const dateLabel = computed<string>(() => {
  const templateName = invoiceStore.newInvoice.template_name
  if (templateName === 'office_invoice') return 'Invoice Date'
  return 'Date'
})


// GST Tax Through dropdown — only present on the office_invoice template
const gstTaxThroughField = computed<CustomFieldItem | undefined>(() => {
  return getInvoiceField('GST Tax Through')
})

const gstTaxThroughOptions = computed<Array<{ name: string }>>(() => {
  const options = gstTaxThroughField.value?.custom_field?.options
  if (!options || !Array.isArray(options)) return []
  return options.map((opt) => ({ name: opt }))
})

function formatAddressLines(address: CustomerAddress | undefined): string[] {
  if (!address) return []
  const cityState = [address.city, address.state].filter(Boolean).join(', ')
  const cityStateZip = [cityState, address.zip].filter(Boolean).join(' ')
  return [address.name, address.address_street_1, address.address_street_2, cityStateZip].filter(Boolean) as string[]
}

function compact(value: string | null | undefined): string {
  return value ? String(value).trim() : ''
}

function formatPartyDetails(customer: Customer | null): string {
  if (!customer) return ''
  const address = customer.billing || customer.shipping
  const lines = [
    compact(customer.name || customer.display_name),
    ...formatAddressLines(address).filter(
      (line) => line !== compact(customer.name || customer.display_name),
    ),
  ]
  return lines.filter(Boolean).join('\n')
}

function getInvoiceField(label: string): CustomFieldItem | undefined {
  return (invoiceStore.newInvoice.customFields as CustomFieldItem[] | undefined)?.find(
    (field) => (field.custom_field?.label ?? field.label) === label,
  )
}


function setInvoiceField(label: string, value: string | null | undefined): void {
  const field = getInvoiceField(label)
  if (field) {
    field.value = value || ''
    field.string_answer = value || ''
  }
}

function syncConsigneeFields(customer: Customer | null): void {
  setInvoiceField('Consignee', formatPartyDetails(customer))
  setInvoiceField('Consignee Phone No', customer?.phone)
  setInvoiceField('Consignee GST No', customer?.tax_id)
}

function syncConsignorFields(customer: Customer | null): void {
  setInvoiceField('Consignor', formatPartyDetails(customer))
  setInvoiceField('Consignor Phone No', customer?.phone)
  setInvoiceField('Consignor GST No', customer?.tax_id)
}

const debounceSearchConsignees = useDebounceFn(() => {
  fetchConsignees(consigneeSearch.value)
}, 500)

async function fetchConsignees(search = ''): Promise<void> {
  try {
    const response = await customerService.list({
      display_name: search,
      page: 1,
    } as never)
    consigneeResults.value = (response.data as Customer[]) || []
  } catch {
    consigneeResults.value = []
  }
}


function ensureConsigneesLoaded(): void {
  if (!consigneeResults.value.length) {
    fetchConsignees()
  }
}

async function selectConsignee(customer: Customer, close: () => void): Promise<void> {
  try {
    const response = await customerService.get(customer.id)
    const fullCustomer = response.data as Customer
    selectedConsignee.value = fullCustomer
    invoiceStore.newInvoice.consignee_customer_id = fullCustomer.id
    syncConsigneeFields(fullCustomer)
  } catch {
    // ignore
  }
  close()
  consigneeSearch.value = ''
}

function resetConsignee(): void {
  selectedConsignee.value = null
  invoiceStore.newInvoice.consignee_customer_id = null
  syncConsigneeFields(null)
}

function openCustomerModal(close?: () => void): void {
  close?.()
  globalStore.fetchCurrencies()
  globalStore.fetchCountries()
  customerStore.resetCurrentCustomer()
  modalStore.openModal({
    title: 'Add Customer',
    componentName: 'CustomerModal',
    size: 'lg',
  })
}

async function editConsignee(): Promise<void> {
  if (!selectedConsignee.value?.id) return

  await customerStore.fetchCustomer(selectedConsignee.value.id)
  customerStore.isEdit = true
  modalStore.openModal({
    title: 'Edit Customer',
    componentName: 'CustomerModal',
    size: 'lg',
  })
}

// Sync consignor fields when customer changes (LR Receipt)
watch(
  () => invoiceStore.newInvoice.customer,
  (customer) => {
    if (props.isEdit && !isInitialized.value) return

    if (isTransportReceipt.value && customer) {
      syncConsignorFields(customer as Customer)
    }
  },
  { deep: true },
)

// Initialize consignee from invoice data on edit
watch(
  () => invoiceStore.newInvoice.consignee_customer,
  (val) => {
    if (val) {
      selectedConsignee.value = val as Customer
    }
  },
  { immediate: true },
)

// Initialize consignee from custom fields on edit
watch(
  () => props.isLoading,
  async (loading) => {
    if (!loading && isTransportReceipt.value) {
      if (props.isEdit) {
        await initializeConsigneeFromCustomFields()
      } else {
        if (invoiceStore.newInvoice.consignee_customer) {
          selectedConsignee.value = invoiceStore.newInvoice.consignee_customer as Customer
          syncConsigneeFields(selectedConsignee.value)
        }
        isInitialized.value = true
      }
    } else if (!props.isEdit) {
      isInitialized.value = true
    }
  },
  { immediate: true },
)

async function initializeConsigneeFromCustomFields(): Promise<void> {
  if (!isTransportReceipt.value) {
    isInitialized.value = true
    return
  }

  if (invoiceStore.newInvoice.consignee_customer) {
    selectedConsignee.value = invoiceStore.newInvoice.consignee_customer as Customer
    isInitialized.value = true
    return
  }

  const consigneeField = getInvoiceField('Consignee')
  if (!consigneeField || !consigneeField.string_answer) {
    isInitialized.value = true
    return
  }

  const lines = consigneeField.string_answer.split('\n')
  const name = lines[0]?.trim()

  if (!name) {
    isInitialized.value = true
    return
  }

  try {
    const response = await customerService.list({
      display_name: name,
      page: 1,
    } as never)
    const customer = (response.data as Customer[])?.find(
      (c) => (c.name || c.display_name) === name,
    )


    if (customer) {
      const fullResponse = await customerService.get(customer.id)
      selectedConsignee.value = fullResponse.data as Customer
      invoiceStore.newInvoice.consignee_customer_id = selectedConsignee.value.id
    }
  } catch {
    // ignore
  } finally {
    isInitialized.value = true
  }
}

// Generate invoice number using the format from company settings.
// Uses the invoice store's getNextNumber method (which goes through the axios
// client) so the company header is included — this is critical for the backend
// to look up the correct per-template format setting (e.g.
// lr_receipt_number_format, lorry_receipt_number_format,
// office_invoice_number_format) instead of the default invoice_number_format.
async function generateInvoiceNumber(): Promise<void> {
  isGeneratingNumber.value = true
  try {
    const templateName = invoiceStore.newInvoice.template_name
    const params: { key?: string; template_name?: string } = { key: 'invoice' }
    if (templateName) {
      params.template_name = templateName
    }

    const response = await invoiceStore.getNextNumber(params)
    if (response?.data?.nextNumber) {
      invoiceStore.newInvoice.invoice_number = response.data.nextNumber
    }
  } catch (error) {
    console.error('Failed to generate invoice number:', error)
  } finally {
    isGeneratingNumber.value = false
  }
}

// Auto-populate the invoice number when the form loads for a new (non-edit) invoice.
// The store's fetchInvoiceInitialSettings already passes template_name to
// getNextNumber, so the correct per-template number is generated on the first
// call. This watcher is a fallback for cases where the store didn't set a number.
watch(
  () => props.isLoading,
  async (loading) => {
    if (!loading && !props.isEdit && !invoiceStore.newInvoice.invoice_number) {
      await generateInvoiceNumber()
    }
  },
  { immediate: true },
)

</script>
