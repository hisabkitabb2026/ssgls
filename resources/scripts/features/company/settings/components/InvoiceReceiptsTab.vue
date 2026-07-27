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
    default-series="INV" 
    setting-key="office_invoice_number_format" 
  /> 
  <CustomTemplateUploader document-type="office_invoice" /> 
</template> 
  
