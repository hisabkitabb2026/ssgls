import { defineStore } from 'pinia'
import { toRef } from 'vue'
import { useNotificationStore } from '../../../stores/notification.store'
import { useCompanyStore } from '../../../stores/company.store'
import { useUserStore } from '../../../stores/user.store'
import { estimateService } from '../../../api/services/estimate.service'
import type {
  EstimateListParams,
  EstimateListResponse,
  SendEstimatePayload,
  EstimateStatusPayload,
  EstimateTemplate,
} from '../../../api/services/estimate.service'
import type { Estimate } from '../../../types/domain/estimate'
import type { DiscountType } from '../../../types/domain/estimate'
import type { Invoice } from '../../../types/domain/invoice'
import type { Currency } from '../../../types/domain/currency'
import type { Customer } from '../../../types/domain/customer'
import type { Note } from '../../../types/domain/note'
import type { CustomFieldValue } from '../../../types/domain/custom-field'
import type { DocumentTax, DocumentItem } from '../../shared/document-form/use-document-calculations'
import { createEstimateItemStub } from '../../shared/document-form/document-stubs'
import { useDocumentActions } from '../../shared/document-form/use-document-actions'
import { useDocumentCalculations } from '../../shared/document-form/use-document-calculations'
import { formatDate as formatDateUtil } from '../../../utils'


// ----------------------------------------------------------------
// Types
// ----------------------------------------------------------------

export interface EstimateFormData {
  id: number | null
  customer: Customer | null
  template_name: string | null
  tax_per_item: string | null
  tax_included: boolean
  sales_tax_type: string | null
  sales_tax_address_type: string | null
  discount_per_item: string | null
  estimate_date: string
  expiry_date: string
  estimate_number: string
  customer_id: number | null
  sub_total: number
  total: number
  tax: number
  notes: string | null
  discount_type: DiscountType
  discount_val: number
  reference_number: string | null
  discount: number
  items: DocumentItem[]
  taxes: DocumentTax[]
  customFields: CustomFieldValue[]
  fields: CustomFieldValue[]
  selectedNote: Note | null
  selectedCurrency: Currency | Record<string, unknown> | string
  unique_hash?: string
  exchange_rate?: number | null
  currency_id?: number
}

function createEstimateStub(): EstimateFormData {
  return {
    id: null,
    customer: null,
    template_name: '',
    tax_per_item: null,
    tax_included: false,
    sales_tax_type: null,
    sales_tax_address_type: null,
    discount_per_item: null,
    estimate_date: '',
    expiry_date: '',
    estimate_number: '',
    customer_id: null,
    sub_total: 0,
    total: 0,
    tax: 0,
    notes: '',
    discount_type: 'fixed',
    discount_val: 0,
    reference_number: null,
    discount: 0,
    items: [createEstimateItemStub()],
    taxes: [],
    customFields: [],
    fields: [],
    selectedNote: null,
    selectedCurrency: '',
  }
}

// ----------------------------------------------------------------
// Store
// ----------------------------------------------------------------

export interface EstimateState {
  templates: EstimateTemplate[]
  estimates: Estimate[]
  selectAllField: boolean
  selectedEstimates: number[]
  totalEstimateCount: number
  isFetchingInitialSettings: boolean
  showExchangeRate: boolean
  newEstimate: EstimateFormData
}

export const useEstimateStore = defineStore('estimate', {
  state: (): EstimateState => ({
    templates: [],
    estimates: [],
    selectAllField: false,
    selectedEstimates: [],
    totalEstimateCount: 0,
    isFetchingInitialSettings: false,
    showExchangeRate: false,
    newEstimate: createEstimateStub(),
  }),

  getters: {
    // Tax calculations delegated to the shared useDocumentCalculations composable
    // — eliminates ~55 lines of duplicated getter logic that was identical to
    // the invoice store.
    getSubTotal(state): number {
      const { subTotal } = useDocumentCalculations({
        items: toRef(state.newEstimate, 'items') as unknown as import('vue').Ref<DocumentItem[]>,
        taxes: toRef(state.newEstimate, 'taxes'),
        discountVal: toRef(state.newEstimate, 'discount_val'),
        taxPerItem: toRef(state.newEstimate, 'tax_per_item'),
        taxIncluded: toRef(state.newEstimate, 'tax_included'),
      })
      return subTotal.value
    },

    getNetTotal(): number {
      return this.getSubtotalWithDiscount - this.getTotalTax
    },

    getTotalSimpleTax(state): number {
      const { totalSimpleTax } = useDocumentCalculations({
        items: toRef(state.newEstimate, 'items') as unknown as import('vue').Ref<DocumentItem[]>,
        taxes: toRef(state.newEstimate, 'taxes'),
        discountVal: toRef(state.newEstimate, 'discount_val'),
        taxPerItem: toRef(state.newEstimate, 'tax_per_item'),
        taxIncluded: toRef(state.newEstimate, 'tax_included'),
      })
      return totalSimpleTax.value
    },

    getTotalCompoundTax(state): number {
      const { totalCompoundTax } = useDocumentCalculations({
        items: toRef(state.newEstimate, 'items') as unknown as import('vue').Ref<DocumentItem[]>,
        taxes: toRef(state.newEstimate, 'taxes'),
        discountVal: toRef(state.newEstimate, 'discount_val'),
        taxPerItem: toRef(state.newEstimate, 'tax_per_item'),
        taxIncluded: toRef(state.newEstimate, 'tax_included'),
      })
      return totalCompoundTax.value
    },

    getTotalTax(): number {
      return this.getTotalSimpleTax + this.getTotalCompoundTax
    },

    getSubtotalWithDiscount(): number {
      return this.getSubTotal - this.newEstimate.discount_val
    },

    getTotal(): number {
      if (this.newEstimate.tax_included) {
        return this.getSubtotalWithDiscount
      }
      return this.getSubtotalWithDiscount + this.getTotalTax
    },

    isEdit(state): boolean {
      return !!state.newEstimate.id
    },
  },

  actions: {
    // Shared document actions — eliminates ~200 lines of duplicated action
    // implementations that were identical to the invoice store.
    _getDocumentActions() {
      return useDocumentActions({
        formRef: toRef(this, 'newEstimate') as unknown as import('vue').Ref<EstimateFormData & import('../../shared/document-form/use-document-calculations').DocumentFormData>,
        createItemStub: createEstimateItemStub,
      })
    },

    resetCurrentEstimate(): void {
      this.newEstimate = createEstimateStub()
    },

    async previewEstimate(params: { id: number }): Promise<unknown> {
      return estimateService.sendPreview(params.id, params)
    },

    async fetchEstimates(
      params: EstimateListParams & { estimate_number?: string },
    ): Promise<{ data: EstimateListResponse }> {
      const response = await estimateService.list(params)
      this.estimates = response.data
      this.totalEstimateCount = response.meta.estimate_total_count
      return { data: response }
    },

    async getNextNumber(
      params?: Record<string, unknown>,
      setState = false,
    ): Promise<{ data: { nextNumber: string } }> {
      const response = await estimateService.getNextNumber(params as never)
      if (setState) {
        this.newEstimate.estimate_number = response.nextNumber
      }
      return { data: response }
    },

    async fetchEstimate(id: number): Promise<{ data: { data: Estimate } }> {
      const response = await estimateService.get(id)
      this._getDocumentActions().setDocumentData(response.data)
      this._getDocumentActions().setCustomerAddresses(this.newEstimate.customer)
      return { data: response }
    },

    // Delegate to shared actions
    setEstimateData(estimate: Estimate): void {
      this._getDocumentActions().setDocumentData(estimate)
    },

    setCustomerAddresses(customer: Customer | null): void {
      this._getDocumentActions().setCustomerAddresses(customer)
    },

    addSalesTaxUs(taxTypes: import('../../../types/domain/tax').TaxType[]): void {
      this._getDocumentActions().addSalesTaxUs(taxTypes)
    },

    async sendEstimate(data: SendEstimatePayload): Promise<unknown> {
      return estimateService.send(data)
    },

    async addEstimate(data: Record<string, unknown>): Promise<{ data: { data: Estimate } }> {
      const response = await estimateService.create(data as never)
      this.estimates = [...this.estimates, response.data]

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'estimates.created_message',
      })

      return { data: response }
    },

    async deleteEstimate(payload: { ids: number[] }): Promise<{ data: { success: boolean } }> {
      const response = await estimateService.delete(payload)
      const id = payload.ids[0]
      const index = this.estimates.findIndex((est) => est.id === id)
      if (index !== -1) {
        this.estimates.splice(index, 1)
      }
      return { data: response }
    },

    async deleteMultipleEstimates(): Promise<{ data: { success: boolean } }> {
      const response = await estimateService.delete({
        ids: this.selectedEstimates,
      })
      this.selectedEstimates.forEach((estId) => {
        const index = this.estimates.findIndex((est) => est.id === estId)
        if (index !== -1) {
          this.estimates.splice(index, 1)
        }
      })
      this.selectedEstimates = []
      return { data: response }
    },

    async updateEstimate(data: Record<string, unknown>): Promise<{ data: { data: Estimate } }> {
      const response = await estimateService.update(data.id as number, data as never)
      const pos = this.estimates.findIndex((est) => est.id === response.data.id)
      if (pos !== -1) {
        this.estimates[pos] = response.data
      }

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'estimates.updated_message',
      })

      return { data: response }
    },

    async cloneEstimate(data: { id: number }): Promise<{ data: { data: Estimate } }> {
      const response = await estimateService.clone(data.id)
      return { data: response }
    },

    async markAsAccepted(data: EstimateStatusPayload): Promise<unknown> {
      const response = await estimateService.changeStatus({
        ...data,
        status: 'ACCEPTED',
      })
      const pos = this.estimates.findIndex((est) => est.id === data.id)
      if (pos !== -1 && this.estimates[pos]) {
        this.estimates[pos].status = 'ACCEPTED' as Estimate['status']
      }
      return response
    },

    async markAsRejected(data: EstimateStatusPayload): Promise<unknown> {
      const response = await estimateService.changeStatus({
        ...data,
        status: 'REJECTED',
      })
      return response
    },

    async markAsSent(data: EstimateStatusPayload): Promise<unknown> {
      const response = await estimateService.changeStatus(data)
      const pos = this.estimates.findIndex((est) => est.id === data.id)
      if (pos !== -1 && this.estimates[pos]) {
        this.estimates[pos].status = 'SENT' as Estimate['status']
      }
      return response
    },

    async convertToInvoice(id: number): Promise<{ data: { data: Invoice } }> {
      const response = await estimateService.convertToInvoice(id)
      return { data: response }
    },

    async searchEstimate(queryString: string): Promise<unknown> {
      return estimateService.list(
        Object.fromEntries(new URLSearchParams(queryString)) as never,
      )
    },

    selectEstimate(data: number[]): void {
      this.selectedEstimates = data
      this.selectAllField =
        this.selectedEstimates.length === this.estimates.length
    },

    selectAllEstimates(): void {
      if (this.selectedEstimates.length === this.estimates.length) {
        this.selectedEstimates = []
        this.selectAllField = false
      } else {
        this.selectedEstimates = this.estimates.map((est) => est.id)
        this.selectAllField = true
      }
    },

    // Delegate item/note/customer/template actions to shared composable
    async selectCustomer(id: number): Promise<unknown> {
      return this._getDocumentActions().selectCustomer(id)
    },

    selectNote(data: Note): void {
      this._getDocumentActions().selectNote(data)
    },

    setTemplate(name: string): void {
      this._getDocumentActions().setTemplate(name)
    },

    resetSelectedCustomer(): void {
      this._getDocumentActions().resetSelectedCustomer()
    },

    addItem(): void {
      this._getDocumentActions().addItem()
    },

    updateItem(data: DocumentItem & { index: number }): void {
      this._getDocumentActions().updateItem(data)
    },

    removeItem(index: number): void {
      this._getDocumentActions().removeItem(index)
    },

    deselectItem(index: number): void {
      this._getDocumentActions().deselectItem(index)
    },

    resetSelectedNote(): void {
      this._getDocumentActions().resetSelectedNote()
    },

    async fetchEstimateTemplates(): Promise<{
      data: { estimateTemplates: EstimateTemplate[] }
    }> {
      const response = await estimateService.getTemplates()
      this.templates = response.estimateTemplates
      return { data: response }
    },

    async fetchEstimateInitialSettings(
      isEdit: boolean,
      routeParams?: { id?: string; query?: Record<string, string> },
      companySettingsParam?: Record<string, string>,
      companyCurrency?: Currency,
      userSettings?: Record<string, string>,
    ): Promise<void> {
      this.isFetchingInitialSettings = true

      const companyStore = useCompanyStore()
      const companySettings = companySettingsParam ?? companyStore.selectedCompanySettings

      if (companyCurrency || companyStore.selectedCompanyCurrency) {
        this.newEstimate.selectedCurrency = companyCurrency ?? companyStore.selectedCompanyCurrency!
      }

      // If customer is specified in route query
      if (routeParams?.query?.customer) {
        try {
          await this.selectCustomer(Number(routeParams.query.customer))
        } catch {
          // Silently fail
        }
      }

      const editActions: Promise<unknown>[] = []

      if (!isEdit && companySettings) {
        this.newEstimate.tax_per_item = companySettings.tax_per_item ?? null
        this.newEstimate.sales_tax_type = companySettings.sales_tax_type ?? null
        this.newEstimate.sales_tax_address_type =
          companySettings.sales_tax_address_type ?? null
        this.newEstimate.discount_per_item =
          companySettings.discount_per_item ?? null

        const now = new Date()
        this.newEstimate.estimate_date = formatDateUtil(now, 'yyyy-MM-dd')

        if (companySettings.estimate_set_expiry_date_automatically === 'YES') {
          const expiryDate = new Date(now)
          expiryDate.setDate(
            expiryDate.getDate() +
              Number(companySettings.estimate_expiry_date_days ?? 7),
          )
          this.newEstimate.expiry_date = formatDateUtil(expiryDate, 'yyyy-MM-dd')
        }
      } else if (isEdit && routeParams?.id) {
        editActions.push(this.fetchEstimate(Number(routeParams.id)))
      }

      try {
        const [, , templatesRes, nextNumRes] = await Promise.all([
          Promise.resolve(), // placeholder for items fetch
          this.resetSelectedNote(),
          this.fetchEstimateTemplates(),
          this.getNextNumber(),
          Promise.resolve(), // placeholder for tax types fetch
          ...editActions,
        ])

        if (!isEdit) {
          if (nextNumRes?.data?.nextNumber) {
            this.newEstimate.estimate_number = nextNumRes.data.nextNumber
          }

          if (this.templates.length) {
            this.setTemplate(this.templates[0].name)
            const companyStore = useCompanyStore()
            if (companyStore.selectedCompanySettings.default_estimate_template) {
              this.newEstimate.template_name =
                companyStore.selectedCompanySettings.default_estimate_template
            } else {
              const { currentUserSettings } = useUserStore()
              if (currentUserSettings.default_estimate_template) {
                this.newEstimate.template_name =
                  currentUserSettings.default_estimate_template
              }
            }
          }
        }
      } catch {
        // Error handling
      } finally {
        this.isFetchingInitialSettings = false
      }
    },
  },
})

export type EstimateStore = ReturnType<typeof useEstimateStore>
