import type { RouteRecordRaw } from 'vue-router'

export default <RouteRecordRaw[]>[
  {
    path: 'lr-receipts',
    name: 'lr-receipts.index',
    component: () => import('./views/LrReceiptIndexView.vue'),
    meta: {
      requiresAuth: true,
      ability: 'view-invoice',
      title: 'LR Receipts',
    },
  },
  {
    path: 'lr-receipts/create',
    name: 'lr-receipts.create',
    component: () => import('../invoices/views/InvoiceCreateView.vue'),
    meta: {
      requiresAuth: true,
      ability: 'create-invoice',
      title: 'New LR Receipt',
    },
  },
  {
    path: 'lr-receipts/:id/edit',
    name: 'lr-receipts.edit',
    component: () => import('../invoices/views/InvoiceCreateView.vue'),
    meta: {
      requiresAuth: true,
      ability: 'edit-invoice',
      title: 'Edit LR Receipt',
    },
  },
  {
    path: 'lr-receipts/:id/view',
    name: 'lr-receipts.view',
    component: () => import('../invoices/views/InvoiceDetailView.vue'),
    meta: {
      requiresAuth: true,
      ability: 'view-invoice',
      title: 'LR Receipts',
    },
  },
]
