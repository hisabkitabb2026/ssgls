import type { RouteRecordRaw } from 'vue-router'

const LoadTripIndexView = () => import('./views/LoadTripIndexView.vue')
const LoadTripDetailView = () => import('./views/LoadTripDetailView.vue')

export const loadTripRoutes: RouteRecordRaw[] = [
  {
    path: 'load-trips',
    name: 'load-trips.index',
    component: LoadTripIndexView,
    meta: {
      requiresAuth: true,
      title: 'Load Trips',
    },
  },
  {
    path: 'load-trips/:id',
    name: 'load-trips.show',
    component: LoadTripDetailView,
    meta: {
      requiresAuth: true,
      title: 'Load Trip',
    },
  },
]
