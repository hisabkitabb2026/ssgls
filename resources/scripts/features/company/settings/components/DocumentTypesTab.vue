<script setup lang="ts">
import { computed, inject, reactive } from 'vue'
import { useCompanyStore } from '@/scripts/stores/company.store'

interface Utils {
  mergeSettings: (target: Record<string, unknown>, source: Record<string, unknown>) => void
}

const utils = inject<Utils>('utils')!
const companyStore = useCompanyStore()

const settings = reactive<{
  enable_standard_invoices: string | null
  enable_invoice_receipts: string | null
  enable_lr_receipts: string | null
  enable_lorry_receipts: string | null
  enable_warehouse_items: string | null
}>({
  enable_standard_invoices: null,
  enable_invoice_receipts: null,
  enable_lr_receipts: null,
  enable_lorry_receipts: null,
  enable_warehouse_items: null,
})

utils.mergeSettings(
  settings as unknown as Record<string, unknown>,
  { ...companyStore.selectedCompanySettings },
)

interface DocumentTypeToggle {
  key: string
  title: string
  description: string
}

const documentTypes: DocumentTypeToggle[] = [
  {
    key: 'enable_standard_invoices',
    title: 'settings.customization.document_types.standard_invoices',
    description: 'settings.customization.document_types.standard_invoices_desc',
  },
  {
    key: 'enable_invoice_receipts',
    title: 'settings.customization.document_types.invoice_receipts',
    description: 'settings.customization.document_types.invoice_receipts_desc',
  },
  {
    key: 'enable_lr_receipts',
    title: 'settings.customization.document_types.lr_receipts',
    description: 'settings.customization.document_types.lr_receipts_desc',
  },
  {
    key: 'enable_lorry_receipts',
    title: 'settings.customization.document_types.lorry_receipts',
    description: 'settings.customization.document_types.lorry_receipts_desc',
  },
  {
    key: 'enable_warehouse_items',
    title: 'settings.customization.document_types.warehouse_items',
    description: 'settings.customization.document_types.warehouse_items_desc',
  },
]

function getToggle(key: string) {
  return computed<boolean>({
    get: () => settings[key as keyof typeof settings] === 'YES',
    set: async (newValue: boolean) => {
      const value = newValue ? 'YES' : 'NO'
      settings[key as keyof typeof settings] = value

      await companyStore.updateCompanySettings({
        data: { settings: { [key]: value } },
        message: 'general.setting_updated',
      })
    },
  })
}

const enableStandardInvoices = getToggle('enable_standard_invoices')
const enableInvoiceReceipts = getToggle('enable_invoice_receipts')
const enableLrReceipts = getToggle('enable_lr_receipts')
const enableLorryReceipts = getToggle('enable_lorry_receipts')
const enableWarehouseItems = getToggle('enable_warehouse_items')

const toggleMap: Record<string, ReturnType<typeof getToggle>> = {
  enable_standard_invoices: enableStandardInvoices,
  enable_invoice_receipts: enableInvoiceReceipts,
  enable_lr_receipts: enableLrReceipts,
  enable_lorry_receipts: enableLorryReceipts,
  enable_warehouse_items: enableWarehouseItems,
}
</script>

<template>
  <BaseSettingCard
    :title="$t('settings.customization.document_types.title')"
    :description="$t('settings.customization.document_types.description')"
  >
    <ul class="divide-y divide-line-default">
      <BaseSwitchSection
        v-for="docType in documentTypes"
        :key="docType.key"
        v-model="toggleMap[docType.key].value"
        :title="$t(docType.title)"
        :description="$t(docType.description)"
      />
    </ul>

    <p class="mt-4 text-sm text-muted">
      {{ $t('settings.customization.document_types.reload_notice') }}
    </p>
  </BaseSettingCard>
</template>
