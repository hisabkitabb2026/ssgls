import type { RouteRecordRaw } from 'vue-router'

const routes: RouteRecordRaw[] = [
  {
    path: 'lorry-receipts',
    name: 'lorry-receipts.index',
    component: () => import('./views/LorryReceiptIndexView.vue'),
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
    meta: {
      requiresAuth: true,
      ability: 'view-invoice',
      title: 'Lorry Receipts',
    },
  },
]

export default routes
