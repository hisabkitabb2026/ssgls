import type { RouteRecordRaw } from 'vue-router'

const WarehouseItemIndexView = () => import('./views/WarehouseItemIndexView.vue')
const WarehouseItemCreateView = () => import('./views/WarehouseItemCreateView.vue')

export const warehouseItemRoutes: RouteRecordRaw[] = [
{
path: 'warehouse-items',
name: 'warehouse-items.index',
component: WarehouseItemIndexView,
meta: {
requiresAuth: true,
title: 'Warehouse Items',
},
},
{
path: 'warehouse-items/create',
name: 'warehouse-items.create',
component: WarehouseItemCreateView,
meta: {
requiresAuth: true,
title: 'Add Warehouse Item',
},
},
{
path: 'warehouse-items/:id/edit',
name: 'warehouse-items.edit',
component: WarehouseItemCreateView,
meta: {
requiresAuth: true,
title: 'Edit Warehouse Item',
},
},
]