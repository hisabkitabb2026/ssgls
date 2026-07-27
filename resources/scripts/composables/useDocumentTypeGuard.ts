import { useCompanyStore } from '@/scripts/stores/company.store'
import type { NavigationGuardWithThis } from 'vue-router'

/**
 * Maps document type route prefixes to their enable/disable company setting key.
 * If the setting is 'NO', the route is blocked and the user is redirected to the dashboard.
 */
const documentTypeSettingMap: Record<string, string> = {
  'standard-invoices': 'enable_standard_invoices',
  'invoices': 'enable_invoice_receipts',
  'lr-receipts': 'enable_lr_receipts',
  'lorry-receipts': 'enable_lorry_receipts',
}

/**
 * Route guard that blocks access to disabled document types.
 * Usage: add `beforeEnter: useDocumentTypeGuard` to route definitions.
 */
export const useDocumentTypeGuard: NavigationGuardWithThis<undefined> = (to) => {
  const companyStore = useCompanyStore()
  const routePrefix = to.path.split('/').filter(Boolean)[0] ?? ''

  const settingKey = documentTypeSettingMap[routePrefix]

  if (!settingKey) return true

  const settingValue = companyStore.selectedCompanySettings?.[settingKey]

  if (settingValue === 'NO') {
    return { name: 'admin.dashboard' }
  }

  return true
}
