

<CustomerSelector
:valid="v.customer_id"
:content-loading="isLoading"
:customer-type="customerSelectorType"
:show-lorry-profiles="isLorryReceipt"
:class="customerSelectorClass"
/>


<ConsigneeSelector
v-if="showConsignee"
:is-loading="isLoading"
:selected="selectedConsignee"
:search-results="consigneeResults"
:search-query="consigneeSearch"
class="order-2 col-span-12 lg:col-span-4"
@update:search="debounceSearchConsignees"
@select="selectConsignee"
@edit="editConsignee"
@deselect="resetConsignee"
@create-new="openCustomerModal"
/>


<RecurringFields
v-if="isRecurring"
:is-loading="isLoading"
:is-edit="isEdit"
/>


<InvoiceDatesAndNumbers
v-if="!showConsignee"
:is-loading="isLoading"
:is-edit="isEdit"
:v="v"
:is-transport-receipt="isTransportReceipt"
:is-generating-number="isGeneratingNumber"
:company-settings="companySettings"
:class="invoiceDatesNumbersClass"
@generate-number="generateInvoiceNumber"
/>


<TransportFieldsSection
v-if="isTransportTemplate"
:template-type="templateType"
:model-value="transportFields"
@update:model-value="updateTransportFields"
/>


<ExchangeRateConverter
v-if="!isTransportReceipt"
:store="invoiceStore"
store-prop="newInvoice"
:v="v"
:is-loading="isLoading"
:is-edit="isEdit"
:customer-currency="invoiceStore.newInvoice.currency_id"
/>

 + + + +