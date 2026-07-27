import type { RouteRecordRaw } from 'vue-router'
import { useDocumentTypeGuard } from '@/scripts/composables/useDocumentTypeGuard'

const routes: RouteRecordRaw[] = [
  {
    path: 'lorry-receipts',
    name: 'lorry-receipts.index',
    component: () => import('./views/LorryReceiptIndexView.vue'),
    beforeEnter: useDocumentTypeGuard,
    meta: {
      requiresAuth: true,
      ability: 'view-invoice',
      title: 'Lorry Receipts',
    },
  },
  {
    path: 'lorry-receipts/create',
    name: 'lorry-receipts.create',
    component: () => import('../invoices/views/InvoiceCreateView.vue'),
    beforeEnter: useDocumentTypeGuard,
    meta: {
      requiresAuth: true,
      ability: 'create-invoice',
      title: 'New Lorry Receipt',
    },
  },
  {
    path: 'lorry-receipts/:id/edit',
    name: 'lorry-receipts.edit',
    component: () => import('../invoices/views/InvoiceCreateView.vue'),
    beforeEnter: useDocumentTypeGuard,
    meta: {
      requiresAuth: true,
      ability: 'edit-invoice',
      title: 'Edit Lorry Receipt',
    },
  },
  {
    path: 'lorry-receipts/:id/view',
    name: 'lorry-receipts.view',
    component: () => import('../invoices/views/InvoiceDetailView.vue'),
    beforeEnter: useDocumentTypeGuard,
    meta: {
      requiresAuth: true,
      ability: 'view-invoice',
      title: 'Lorry Receipts',
    },
  },
]

export default routes
