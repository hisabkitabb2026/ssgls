import { client } from '../client'
import { API } from '../endpoints'

export interface WhatsAppSendRequest {
  id: number
  phone?: string // Optional - will use record's phone if not provided
  message?: string
  attach_pdf?: boolean
}

export interface WhatsAppSendResponse {
  success: boolean
  message_id?: string
  message?: string
  error?: string
}

export interface WhatsAppReportRequest extends WhatsAppSendRequest {
  report_type: string
  from_date: string
  to_date: string
}

export const whatsappService = {
  /**
   * Send invoice via WhatsApp
   */
  async sendInvoice(data: WhatsAppSendRequest): Promise<WhatsAppSendResponse> {
    const response = await client.post(API.WHATSAPP_INVOICE_SEND, data)
    return response.data
  },

  /**
   * Send estimate via WhatsApp
   */
  async sendEstimate(data: WhatsAppSendRequest): Promise<WhatsAppSendResponse> {
    const response = await client.post(API.WHATSAPP_ESTIMATE_SEND, data)
    return response.data
  },

  /**
   * Send payment receipt via WhatsApp
   */
  async sendPayment(data: WhatsAppSendRequest): Promise<WhatsAppSendResponse> {
    const response = await client.post(API.WHATSAPP_PAYMENT_SEND, data)
    return response.data
  },

  /**
   * Send report via WhatsApp
   */
  async sendReport(data: WhatsAppReportRequest): Promise<WhatsAppSendResponse> {
    const response = await client.post(API.WHATSAPP_REPORT_SEND, data)
    return response.data
  },
}
