<template> 

  <div class="p-6"> 

    <!-- Loading --> 

    <div v-if="loading" class="text-center text-muted py-8">Loading...</div> 

  

    <div v-else-if="!trip"> 

      <div class="text-center text-muted py-12"> 

        <p class="text-lg">Load trip not found</p> 

        <button 

          class="mt-4 px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700" 

          @click="$router.push({ name: 'load-trips.index' })" 

        > 

          Back to Trips 

        </button> 

      </div> 

    </div> 

  

    <div v-else> 

      <!-- Header --> 

      <div class="flex justify-between items-start mb-6 gap-4"> 

        <div> 

          <div class="flex items-center gap-3"> 

            <h1 class="text-2xl font-bold text-heading">{{ trip.trip_number }}</h1> 

            <span class="text-xs px-2 py-1 rounded font-bold" :class="getStatusClass(trip.status)"> 

              {{ trip.status }} 

            </span> 

          </div> 

          <p class="text-sm text-muted mt-1"> 

            {{ trip.origin_city || '—' }} → {{ trip.destination_city }} 

          </p> 

        </div> 

        <div class="flex gap-2"> 

          <button 

            class="px-4 py-2 bg-surface-secondary text-body rounded border border-line-default hover:bg-hover" 

            @click="$router.push({ name: 'load-trips.index' })" 

          > 

            ← Back to Trips 

          </button> 

          <button 

            v-if="trip.status === 'planned'" 

            class="px-4 py-2 bg-status-purple text-white rounded hover:bg-status-purple/90" 

            @click="dispatchTrip" 

          > 

             Dispatch Trip 

          </button> 

          <button 

            v-if="trip.status === 'dispatched'" 

            class="px-4 py-2 bg-status-green text-white rounded hover:bg-status-green/90" 

            @click="markDelivered" 

          > 

            ✓ Mark Delivered 

          </button> 

        </div> 

      </div> 

  

      <!-- Trip Info Grid --> 

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6"> 

        <!-- Truck & Driver --> 

        <div class="bg-surface rounded shadow p-4"> 

          <h2 class="font-bold text-heading mb-3">Truck & Driver</h2> 

          <div class="space-y-2 text-sm"> 

            <div class="flex justify-between"> 

              <span class="text-muted">Truck Number</span> 

              <span class="font-semibold text-body">{{ trip.truck_number || '—' }}</span> 

            </div> 

            <div class="flex justify-between"> 

              <span class="text-muted">Driver Name</span> 

              <span class="font-semibold text-body">{{ trip.driver_name || '—' }}</span> 

            </div> 

            <div class="flex justify-between"> 

              <span class="text-muted">Driver Phone</span> 

              <span class="font-semibold text-body">{{ trip.driver_phone || '—' }}</span> 

            </div> 

          </div> 

        </div> 

  

        <!-- Dates --> 

        <div class="bg-surface rounded shadow p-4"> 

          <h2 class="font-bold text-heading mb-3">Timeline</h2> 

          <div class="space-y-2 text-sm"> 

            <div class="flex justify-between"> 

              <span class="text-muted">Dispatch Date</span> 

              <span class="font-semibold text-body">{{ trip.dispatch_date ? formatDate(trip.dispatch_date) : '—' }}</span> 

            </div> 

            <div class="flex justify-between"> 

              <span class="text-muted">Expected Delivery</span> 

              <span class="font-semibold text-body">{{ trip.expected_delivery_date ? formatDate(trip.expected_delivery_date) : '—' }}</span> 

            </div> 

            <div class="flex justify-between"> 

              <span class="text-muted">Actual Delivery</span> 

              <span class="font-semibold" :class="trip.actual_delivery_date ? 'text-status-green' : 'text-muted'"> 

                {{ trip.actual_delivery_date ? formatDate(trip.actual_delivery_date) : 'Pending' }} 

              </span> 

            </div> 

          </div> 

        </div> 

      </div> 

  

      <!-- Consolidation Group Link --> 

      <div v-if="trip.consolidation_group" class="bg-surface rounded shadow p-4 mb-6"> 

        <h2 class="font-bold text-heading mb-2">Consolidation Group</h2> 

        <div 

          class="flex justify-between items-center cursor-pointer hover:bg-hover p-2 rounded" 

          @click="$router.push({ name: 'consolidation.show', params: { id: trip.consolidation_group.id } })" 

        > 

          <div> 

            <p class="font-semibold text-primary-500">{{ trip.consolidation_group.group_number }}</p> 

            <p class="text-sm text-muted">→ {{ trip.consolidation_group.destination_city }}</p> 

          </div> 

          <span class="text-muted">→</span> 

        </div> 

      </div> 

  

      <!-- Items in Trip --> 

      <div class="mb-6"> 

        <h2 class="text-lg font-bold text-heading mb-3">Material on This Trip</h2> 

        <div v-if="!trip.warehouse_items || trip.warehouse_items.length === 0" class="text-center text-muted py-8 bg-surface-secondary rounded"> 

          No items assigned to this trip. 

        </div> 

        <div v-else class="overflow-x-auto bg-surface rounded shadow"> 

          <table class="w-full text-sm"> 

            <thead class="bg-surface-secondary text-muted text-xs uppercase"> 

              <tr> 

                <th class="text-left px-4 py-3">LR Number</th> 

                <th class="text-left px-4 py-3">Consignor</th> 

                <th class="text-left px-4 py-3">Consignee</th> 

                <th class="text-right px-4 py-3">Weight</th> 

                <th class="text-right px-4 py-3">Packages</th> 

                <th class="text-center px-4 py-3">Status</th> 

              </tr> 

            </thead> 

            <tbody class="divide-y divide-line-light"> 

              <tr v-for="item in trip.warehouse_items" :key="item.id" class="hover:bg-hover"> 

                <td class="px-4 py-3 font-semibold text-heading">{{ item.lr?.invoice_number || '-' }}</td> 

                <td class="px-4 py-3 text-body">{{ item.consignor_name || item.lr?.customer?.name || '-' }}</td> 

                <td class="px-4 py-3 text-body">{{ item.consignee_name || '-' }}</td> 

                <td class="px-4 py-3 text-right text-body">{{ formatWeight(item.weight_kg) }} kg</td> 

                <td class="px-4 py-3 text-right text-body">{{ item.no_of_packages || 0 }}</td> 

                <td class="px-4 py-3 text-center"> 

                  <span class="text-xs px-2 py-1 rounded font-bold" :class="getItemStatusClass(item.status)"> 

                    {{ item.status }} 

                  </span> 

                </td> 

              </tr> 

            </tbody> 

            <tfoot class="bg-surface-secondary font-bold"> 

              <tr> 

                <td class="px-4 py-3 text-heading" colspan="3">Total</td> 

                <td class="px-4 py-3 text-right text-heading">{{ formatWeight(totalWeight) }} kg</td> 

                <td class="px-4 py-3 text-right text-heading">{{ totalPackages }}</td> 

                <td></td> 

              </tr> 

            </tfoot> 

          </table> 

        </div> 

      </div> 

  

      <!-- Notes --> 

      <div v-if="trip.notes" class="bg-surface rounded shadow p-4 mb-6"> 

        <h2 class="font-bold text-heading mb-2">Notes</h2> 

        <p class="text-sm text-body">{{ trip.notes }}</p> 

      </div> 

  

      <!-- Future Integration Placeholder --> 

      <div class="bg-surface-secondary rounded p-4 border border-dashed border-line-default"> 

        <p class="text-xs text-muted"> 

           Future integrations: GPS tracking, Fastag toll data, E-Way Bill verification, and route optimization 

          will appear here when production APIs are connected. 

        </p> 

      </div> 

    </div> 

  </div> 

</template> 

  

<script setup lang="ts"> 

import { computed, onMounted, ref } from 'vue' 

import { useRoute } from 'vue-router' 

import { useLoadTripStore } from '../store' 

  

const route = useRoute() 

const store = useLoadTripStore() 

const loading = ref(true) 

  

const trip = computed(() => store.currentTrip) 

  

const totalWeight = computed(() => { 

  if (!trip.value?.warehouse_items) return 0 

  return trip.value.warehouse_items.reduce( 

    (sum: number, item: any) => sum + (parseFloat(item.weight_kg) || 0), 

    0 

  ) 

}) 

  

const totalPackages = computed(() => { 

  if (!trip.value?.warehouse_items) return 0 

  return trip.value.warehouse_items.reduce( 

    (sum: number, item: any) => sum + (item.no_of_packages || 0), 

    0 

  ) 

}) 

  

const loadTrip = async () => { 

  loading.value = true 

  const id = parseInt(route.params.id as string) 

  await store.getTrip(id) 

  loading.value = false 

} 

  

const dispatchTrip = async () => { 

  if (confirm('Dispatch this trip? All items will be marked as in transit.')) { 

    try { 

      await store.dispatchTrip(parseInt(route.params.id as string)) 

      await store.getTrip(parseInt(route.params.id as string)) 

    } catch { 

      alert('Failed to dispatch trip') 

    } 

  } 

} 

  

const markDelivered = async () => { 

  if (confirm('Mark this trip as delivered? All items will be marked as delivered.')) { 

    try { 

      await store.markDelivered(parseInt(route.params.id as string)) 

      await store.getTrip(parseInt(route.params.id as string)) 

    } catch { 

      alert('Failed to mark trip as delivered') 

    } 

  } 

} 

  

const getStatusClass = (status: string) => { 

  const classes: Record<string, string> = { 

    planned: 'bg-status-blue/10 text-status-blue', 

    dispatched: 'bg-status-purple/10 text-status-purple', 

    delivered: 'bg-status-green/10 text-status-green', 

    cancelled: 'bg-status-red/10 text-status-red', 

  } 

  return classes[status] || '' 

} 

  

const getItemStatusClass = (status: string) => { 

  const classes: Record<string, string> = { 

    stored: 'bg-status-blue/10 text-status-blue', 

    picked_for_consolidation: 'bg-status-yellow/10 text-status-yellow', 

    loaded_on_vehicle: 'bg-status-purple/10 text-status-purple', 

    in_transit: 'bg-status-purple/10 text-status-purple', 

    delivered: 'bg-status-green/10 text-status-green', 

    cancelled: 'bg-status-red/10 text-status-red', 

  } 

  return classes[status] || '' 

} 

  

const formatWeight = (weight: any) => { 

  const w = parseFloat(weight) || 0 

  return w.toLocaleString('en-IN', { maximumFractionDigits: 2 }) 

} 

  

const formatDate = (date: string) => { 

  if (!date) return '-' 

  return new Date(date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: '2-digit' }) 

} 

  

onMounted(() => { 

  loadTrip() 

}) 

</script> 

