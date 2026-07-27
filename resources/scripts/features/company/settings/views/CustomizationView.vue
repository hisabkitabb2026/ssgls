<script setup lang="ts">
import { provide } from 'vue'
import DocumentTypesTab from '@/scripts/features/company/settings/components/DocumentTypesTab.vue'
import InvoicesTab from '@/scripts/features/company/settings/components/InvoicesTab.vue'
import InvoiceReceiptsTab from '@/scripts/features/company/settings/components/InvoiceReceiptsTab.vue'
import LrReceiptsTab from '@/scripts/features/company/settings/components/LrReceiptsTab.vue'
import LorryReceiptsTab from '@/scripts/features/company/settings/components/LorryReceiptsTab.vue'
import EstimatesTab from '@/scripts/features/company/settings/components/EstimatesTab.vue'
import PaymentsTab from '@/scripts/features/company/settings/components/PaymentsTab.vue'
import ItemsTab from '@/scripts/features/company/settings/components/ItemsTab.vue'

provide('utils', {
  mergeSettings(target: Record<string, unknown>, source: Record<string, unknown>): void {
    Object.keys(source).forEach((key) => {
      if (key in target) {
        target[key] = source[key]
      }
    })
  },
})
</script>

<template>
  <div class="relative">
    <BaseCard container-class="px-4 py-5 sm:px-8 sm:py-2">
      <BaseTabGroup>
        <BaseTab
          tab-panel-container="py-4 mt-px"
          :title="$t('settings.customization.document_types.title')"
        >
          <DocumentTypesTab />
        </BaseTab>

        <BaseTab
          tab-panel-container="py-4 mt-px"
          :title="$t('settings.customization.invoices.title')"
        >
          <InvoicesTab />
        </BaseTab>

        <BaseTab
          tab-panel-container="py-4 mt-px"
          :title="$t('settings.customization.invoice_receipts.title')"
        >
          <InvoiceReceiptsTab />
        </BaseTab>

        <BaseTab
          tab-panel-container="py-4 mt-px"
          :title="$t('settings.customization.lr_receipts.title')"
        >
          <LrReceiptsTab />
        </BaseTab>

        <BaseTab
          tab-panel-container="py-4 mt-px"
          :title="$t('settings.customization.lorry_receipts.title')"
        >
          <LorryReceiptsTab />
        </BaseTab>

        <BaseTab
          tab-panel-container="py-4 mt-px"
          :title="$t('settings.customization.estimates.title')"
        >
          <EstimatesTab />
        </BaseTab>

        <BaseTab
          tab-panel-container="py-4 mt-px"
          :title="$t('settings.customization.payments.title')"
        >
          <PaymentsTab />
        </BaseTab>

        <BaseTab
          tab-panel-container="py-4 mt-px"
          :title="$t('settings.customization.items.title')"
        >
          <ItemsTab />
        </BaseTab>
      </BaseTabGroup>
    </BaseCard>
  </div>
</template>
