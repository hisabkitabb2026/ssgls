<script setup lang="ts">
import { useInvoiceStore } from '@/scripts/features/company/invoices/store'
import NumberCustomizer from './NumberCustomizer.vue'
import CustomTemplateUploader from './CustomTemplateUploader.vue'

const invoiceStore = useInvoiceStore()
</script>

<template>
  <NumberCustomizer
    type="invoice"
    :type-store="invoiceStore"
    default-series="LR"
    setting-key="lr_receipt_number_format"
  />
  <CustomTemplateUploader document-type="lr_receipt" />
</template>
