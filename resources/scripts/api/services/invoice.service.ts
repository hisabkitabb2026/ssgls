import { client } from '../client'
import { API } from '../endpoints'
import type { Invoice, CreateInvoicePayload } from '@/scripts/types/domain/invoice'
import type {
  ApiResponse,
  PaginatedResponse,
  ListParams,
  DateRangeParams,
  NextNumberResponse,
  DeletePayload,
} from '@/scripts/types/api'

export interface InvoiceListParams extends ListParams, DateRangeParams {
  status?: string
  customer_id?: number
}

export interface InvoiceListMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  invoice_total_count: number
}

export interface InvoiceListResponse {
  data: Invoice[]
  meta: InvoiceListMeta
}

export interface SendInvoicePayload {
  id: number
  subject?: string | null
  body?: string | null
  from?: string | null
  to?: string | null
  cc?: string | null
  bcc?: string | null
}

export interface InvoiceStatusPayload {
  id: number
  status: string
}

export interface SendPreviewParams {
  id: number
  from?: string | null
  to?: string | null
  cc?: string | null
  bcc?: string | null
  subject?: string | null
  body?: string | null
}

export interface InvoiceTemplate {
  name: string
  path: string
}

export interface InvoiceTemplatesResponse {
  invoiceTemplates: InvoiceTemplate[]
}

export const invoiceService = {
  async list(params?: InvoiceListParams): Promise<InvoiceListResponse> {
    const { data } = await client.get(API.INVOICES, { params })
    return data
  },

  async get(id: number): Promise<ApiResponse<Invoice>> {
    const { data } = await client.get(`${API.INVOICES}/${id}`)
    return data
  },

  async create(payload: CreateInvoicePayload): Promise<ApiResponse<Invoice>> {
    const { data } = await client.post(API.INVOICES, payload)
    return data
  },

  async update(id: number, payload: Partial<CreateInvoicePayload>): Promise<ApiResponse<Invoice>> {
    const { data } = await client.put(`${API.INVOICES}/${id}`, payload)
    return data
  },

  async delete(payload: DeletePayload): Promise<{ success: boolean }> {
    const { data } = await client.post(API.INVOICES_DELETE, payload)
    return data
  },

  async send(payload: SendInvoicePayload): Promise<ApiResponse<Invoice>> {
    const { data } = await client.post(`${API.INVOICES}/${payload.id}/send`, payload)
    return data
  },

  async sendPreview(params: SendPreviewParams): Promise<ApiResponse<string>> {
    const { data } = await client.post(`${API.INVOICES}/${params.id}/send/preview`, params)
    return data
  },

  async clone(id: number): Promise<ApiResponse<Invoice>> {
    const { data } = await client.post(`${API.INVOICES}/${id}/clone`)
    return data
  },

  async convertToEstimate(id: number): Promise<ApiResponse<Record<string, unknown>>> {
    const { data } = await client.post(`${API.INVOICES}/${id}/convert-to-estimate`)
    return data
  },

  async changeStatus(payload: InvoiceStatusPayload): Promise<ApiResponse<Invoice>> {
    const { data } = await client.post(`${API.INVOICES}/${payload.id}/status`, payload)
    return data
  },

  async getNextNumber(params?: { key?: string; template_name?: string }): Promise<NextNumberResponse> {
    // When template_name is provided, use the invoice-specific next-number
    // endpoint which calls setTemplateName() on the backend — this ensures
    // the correct per-template format (e.g. lr_receipt_number_format) is used.
    if (params?.template_name) {
      const { data } = await client.get(`${API.INVOICES}/next-number`, { params })
      // InvoicesController returns { success, data: { next_number } }
      // Normalize to the NextNumberResponse format { nextNumber }
      return { nextNumber: data.data.next_number }
    }
    const { data } = await client.get(API.NEXT_NUMBER, { params: { key: 'invoice', ...params } })
    return data
  },

  /**
   * Find an invoice by its invoice_number and template_name.
   * Used by the Office Invoice form to auto-fill Consignment Details
   * from a matching LR Receipt (Docket No = Consignment No).
   */
  async findByInvoiceNumber(
    invoiceNumber: string,
    templateName: string = 'lr_receipt',
  ): Promise<ApiResponse<Invoice> | null> {
    try {
      const { data } = await client.get(`${API.INVOICES}/find-by-number`, {
        params: { invoice_number: invoiceNumber, template_name: templateName },
      })
      return data
    } catch {
      return null
    }
  },

  async getTemplates(documentType?: string): Promise<InvoiceTemplatesResponse> {
    const params: Record<string, string> = {}
    if (documentType) {
      params.document_type = documentType
    }
    const { data } = await client.get(API.INVOICE_TEMPLATES, { params })
    return data
  },
}
