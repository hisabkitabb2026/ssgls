export type LorryPartyProfileType = 'OWNER' | 'DRIVER' | 'BROKER'

export interface LorryPartyProfile {
  id?: number
  company_id?: number
  customer_id?: number | null
  type: LorryPartyProfileType
  code?: string
  name: string
  address?: string
  phone?: string
  financer_name?: string
  financer_address?: string
  place?: string
  bank_account_no?: string
  licence_no?: string
  licence_date?: string
  licence_issued_by?: string
  rto_address?: string
  valid_up_to?: string
  advice_no?: string
  advice_date?: string
  destination_broker_name?: string
  destination_broker_address?: string
  rc_front_path?: string | null
  rc_back_path?: string | null
  pan_front_path?: string | null
  insurance_path?: string | null
  license_front_path?: string | null
  license_back_path?: string | null
  pan_front_path_broker?: string | null
  formatted_created_at?: string
}

export interface LorryPartyProfilePayload {
  type: LorryPartyProfileType
  name: string
  address?: string
  phone?: string
  financer_name?: string
  financer_address?: string
  place?: string
  bank_account_no?: string
  licence_no?: string
  licence_date?: string
  licence_issued_by?: string
  rto_address?: string
  valid_up_to?: string
  advice_no?: string
  advice_date?: string
  destination_broker_name?: string
  destination_broker_address?: string
  rc_front_path?: string | null
  rc_back_path?: string | null
  pan_front_path?: string | null
  insurance_path?: string | null
  license_front_path?: string | null
  license_back_path?: string | null
  pan_front_path_broker?: string | null
}
