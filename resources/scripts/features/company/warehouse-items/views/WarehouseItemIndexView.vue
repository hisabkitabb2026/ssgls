<template>
  <div class="p-6">
    <!-- Header -->
    <div class="flex justify-between items-start mb-6 gap-4">
      <h1 class="text-2xl font-bold text-heading">Warehouse Operations</h1>
      <div v-if="activeTab === 'warehouse'" class="flex gap-2">
        <button
          class="px-4 py-2 bg-surface-secondary text-body rounded border border-line-default hover:bg-hover whitespace-nowrap"
          @click="$router.push({ name: 'warehouse-items.create' })"
        >
          + Receive Material
        </button>
      </div>
    </div>

    <!-- Tab Navigation -->
    <div class="flex gap-1 mb-6 border-b border-line-default">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        class="px-4 py-2 text-sm font-semibold whitespace-nowrap border-b-2 transition"
        :class="activeTab === tab.key
          ? 'border-primary-500 text-primary-500'
          : 'border-transparent text-muted hover:text-body'"
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
        <span
          v-if="tab.count !== null"
          class="ml-1 text-xs px-1.5 py-0.5 rounded-full"
          :class="activeTab === tab.key ? 'bg-primary-500/10 text-primary-500' : 'bg-surface-tertiary text-muted'"
        >
          {{ tab.count }}
        </span>
      </button>
    </div>

    <!-- ==================== TAB 1: IN WAREHOUSE ==================== -->
    <div v-if="activeTab === 'warehouse'">
      <!-- KPI Strip -->
      <div v-if="store.dashboardStats" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-surface rounded shadow p-4">
          <p class="text-sm text-muted">Items in Warehouse</p>
          <p class="text-2xl font-bold text-heading">{{ store.dashboardStats.stored_items || 0 }}</p>
        </div>
        <div
          class="bg-surface rounded shadow p-4"
          :class="{ 'border-2 border-status-red': (store.dashboardStats.overdue_items || 0) > 0 }"
        >
          <p class="text-sm text-muted">Overdue Items</p>
          <p
            class="text-2xl font-bold"
            :class="(store.dashboardStats.overdue_items || 0) > 0 ? 'text-status-red' : 'text-heading'"
          >
            {{ store.dashboardStats.overdue_items || 0 }}
          </p>
        </div>
        <div class="bg-surface rounded shadow p-4">
          <p class="text-sm text-muted">Total Weight</p>
          <p class="text-2xl font-bold text-heading">{{ formatWeight(store.dashboardStats.total_weight_kg) }} kg</p>
        </div>
        <div class="bg-surface rounded shadow p-4">
          <p class="text-sm text-muted">Destinations</p>
          <p class="text-2xl font-bold text-heading">{{ store.dashboardStats.unique_destinations || 0 }}</p>
        </div>
      </div>

      <!-- Aging Buckets -->
      <div v-if="store.dashboardStats?.aging_buckets" class="flex gap-3 mb-6">
        <div
          v-for="(count, bucket) in store.dashboardStats.aging_buckets"
          :key="bucket"
          class="flex-1 bg-surface rounded shadow p-3 text-center"
          :class="getAgingClass(bucket)"
        >
          <p class="text-xs text-muted">{{ bucket }} days</p>
          <p class="text-xl font-bold">{{ count }}</p>
        </div>
      </div>

      <!-- Filters -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-body">Filter by Status:</label>
          <select
            v-model="selectedStatus"
            class="px-3 py-2 border border-line-default rounded bg-surface text-body"
          >
            <option value="">All Statuses</option>
            <option value="stored">Stored</option>
            <option value="picked_for_consolidation">Picked for Consolidation</option>
            <option value="loaded_on_vehicle">Loaded on Vehicle</option>
            <option value="in_transit">In Transit</option>
            <option value="delivered">Delivered</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-semibold text-body">Filter by Destination:</label>
          <select
            v-model="selectedDestination"
            class="px-3 py-2 border border-line-default rounded bg-surface text-body"
          >
            <option value="">All Destinations</option>
            <option v-for="dest in allDestinations" :key="dest" :value="dest">{{ dest }}</option>
          </select>
        </div>
      </div>

      <!-- Loading / Empty -->
      <div v-if="store.loading" class="text-center text-muted py-8">Loading...</div>
      <div
        v-else-if="groupedItems.length === 0"
        class="text-center text-muted py-12 bg-surface-secondary rounded"
      >
        <p class="text-lg">No warehouse items yet</p>
        <p class="text-sm mt-2">Click "Receive Material" to add items to your warehouse</p>
      </div>

      <!-- Items grouped by destination -->
      <div v-else class="space-y-8">
        <div v-for="group in groupedItems" :key="group.destination" class="space-y-3">
          <!-- Destination header with summary -->
          <div class="flex items-center justify-between bg-surface sticky top-0 z-10 py-3 px-4 rounded shadow">
            <div class="flex items-center gap-3">
              <h2 class="text-xl font-bold text-heading">{{ group.destination }}</h2>
              <span class="text-sm bg-primary-500/10 text-primary-500 px-3 py-1 rounded-full">
                {{ group.items.length }} item{{ group.items.length !== 1 ? 's' : '' }}
              </span>
            </div>
            <div class="flex items-center gap-4 text-sm">
              <span class="text-muted">
                Weight: <span class="font-semibold text-body">{{ formatWeight(group.totalWeight) }} kg</span>
              </span>
              <span v-if="group.overdueCount > 0" class="text-status-red font-semibold">
                ⚠ {{ group.overdueCount }} overdue
              </span>
              <button
                class="px-3 py-1 bg-primary-600 text-white text-xs rounded hover:bg-primary-700"
                @click="goToConsolidation(group.destination)"
              >
                Create Consolidation
              </button>
            </div>
          </div>

          <!-- Item cards -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="item in group.items" :key="item.id" class="bg-surface rounded shadow">
              <div class="p-4">
                <!-- Header -->
                <div class="flex justify-between items-start mb-4 pb-4 border-b border-line-light gap-2">
                  <div class="flex-1 min-w-0">
                    <p class="text-xs text-muted uppercase tracking-wide">LR Receipt</p>
                    <p class="font-bold text-lg text-heading break-words">
                      {{ item.lr?.invoice_number || '-' }}
                    </p>
                    <div class="flex gap-2 mt-1">
                      <span
                        v-if="item.load_type === 'full_load'"
                        class="text-xs bg-status-purple/10 text-status-purple px-2 py-0.5 rounded"
                      >
                        Full Load
                      </span>
                      <span v-else class="text-xs bg-status-blue/10 text-status-blue px-2 py-0.5 rounded">
                        Part Load
                      </span>
                      <span
                        v-if="item.priority !== 'normal'"
                        class="text-xs px-2 py-0.5 rounded"
                        :class="getPriorityClass(item.priority)"
                      >
                        {{ item.priority }}
                      </span>
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <select
                      :value="item.status"
                      class="text-xs px-2 py-1 rounded border border-line-default cursor-pointer font-bold bg-surface text-body"
                      @change="(e) => updateStatus(item.id, e.target.value)"
                    >
                      <option value="stored">Stored</option>
                      <option value="picked_for_consolidation">Picked</option>
                      <option value="loaded_on_vehicle">Loaded</option>
                      <option value="in_transit">In Transit</option>
                      <option value="delivered">Delivered</option>
                      <option value="cancelled">Cancelled</option>
                    </select>
                    <button
                      class="text-status-red hover:bg-status-red/10 p-2 rounded transition"
                      title="Delete"
                      @click="deleteItem(item.id)"
                    >
                      <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path
                          fill-rule="evenodd"
                          d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                          clip-rule="evenodd"
                        />
                      </svg>
                    </button>
                  </div>
                </div>

                <!-- Consignor & Consignee -->
                <div class="grid grid-cols-2 gap-2 mb-3">
                  <div>
                    <p class="text-xs text-muted">Consignor</p>
                    <p class="font-semibold text-sm text-body break-words">
                      {{ item.consignor_name || item.lr?.customer?.name || '-' }}
                    </p>
                  </div>
                  <div>
                    <p class="text-xs text-muted">Consignee</p>
                    <p class="font-semibold text-sm text-body break-words">
                      {{ item.consignee_name || '-' }}
                    </p>
                  </div>
                </div>

                <!-- Weight & Packages -->
                <div class="grid grid-cols-3 gap-2 mb-3 pb-3 border-b border-line-light">
                  <div>
                    <p class="text-xs text-muted">Weight</p>
                    <p class="font-semibold text-body">{{ formatWeight(item.weight_kg) }} kg</p>
                  </div>
                  <div>
                    <p class="text-xs text-muted">Packages</p>
                    <p class="font-semibold text-body">{{ item.no_of_packages || 0 }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-muted">Location</p>
                    <p class="font-semibold text-body">{{ item.warehouse_location || '-' }}</p>
                  </div>
                </div>

                <!-- Dates & Deadline -->
                <div class="grid grid-cols-2 gap-2 text-xs">
                  <div>
                    <p class="text-muted">Received Date</p>
                    <p class="font-semibold text-body">{{ formatDate(item.date_received) }}</p>
                  </div>
                  <div>
                    <p class="text-muted">Days in Warehouse</p>
                    <p class="font-semibold" :class="getDaysClass(item.days_in_warehouse)">
                      {{ item.days_in_warehouse || 0 }} days
                    </p>
                  </div>
                  <div v-if="item.promised_dispatch_date">
                    <p class="text-muted">Promised Dispatch</p>
                    <p class="font-semibold" :class="item.is_overdue ? 'text-status-red' : 'text-body'">
                      {{ formatDate(item.promised_dispatch_date) }}
                      <span v-if="item.is_overdue" class="ml-1">⚠ OVERDUE</span>
                    </p>
                  </div>
                  <div v-if="item.consolidation_id">
                    <p class="text-muted">Consolidation</p>
                    <p class="font-semibold text-status-purple">Assigned to group</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== TAB 2: CONSOLIDATION ==================== -->
    <div v-if="activeTab === 'consolidation'">
      <!-- KPI Strip -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-surface rounded shadow p-4">
          <p class="text-sm text-muted">Open Groups</p>
          <p class="text-2xl font-bold text-heading">{{ consolidationStats.open }}</p>
        </div>
        <div class="bg-surface rounded shadow p-4">
          <p class="text-sm text-muted">Ready to Dispatch</p>
          <p class="text-2xl font-bold text-status-green">{{ consolidationStats.ready }}</p>
        </div>
        <div class="bg-surface rounded shadow p-4">
          <p class="text-sm text-muted">Dispatched</p>
          <p class="text-2xl font-bold text-status-blue">{{ consolidationStats.dispatched }}</p>
        </div>
        <div class="bg-surface rounded shadow p-4">
          <p class="text-sm text-muted">Total Weight (Open)</p>
          <p class="text-2xl font-bold text-heading">{{ formatWeight(consolidationStats.totalWeight) }} kg</p>
        </div>
      </div>

      <!-- Unassigned Material (Available for Consolidation) -->
      <div v-if="consolidationStore.candidates.length > 0" class="mb-8">
        <h2 class="text-lg font-bold text-heading mb-3">
          Unassigned Material (Available for Consolidation)
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="candidate in consolidationStore.candidates"
            :key="candidate.destination"
            class="bg-surface rounded shadow p-4 border-l-4 border-status-yellow"
          >
            <div class="flex justify-between items-start mb-2">
              <h3 class="font-bold text-heading">{{ candidate.destination }}</h3>
              <span class="text-xs bg-status-yellow/10 text-status-yellow px-2 py-1 rounded-full">
                {{ candidate.item_count }} item{{ candidate.item_count !== 1 ? 's' : '' }}
              </span>
            </div>
            <div class="grid grid-cols-2 gap-2 text-sm mb-3">
              <div>
                <p class="text-xs text-muted">Total Weight</p>
                <p class="font-semibold text-body">{{ formatWeight(candidate.total_weight_kg) }} kg</p>
              </div>
              <div>
                <p class="text-xs text-muted">Total Packages</p>
                <p class="font-semibold text-body">{{ candidate.total_packages || 0 }}</p>
              </div>
            </div>
            <button
              class="w-full px-3 py-2 bg-primary-600 text-white text-sm rounded hover:bg-primary-700"
              @click="openCreateForDestination(candidate.destination)"
            >
              Create Group for {{ candidate.destination }}
            </button>
          </div>
        </div>
      </div>

      <!-- Consolidation Groups -->
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-bold text-heading">Consolidation Groups</h2>
        <button
          class="px-4 py-2 bg-surface-secondary text-body rounded border border-line-default hover:bg-hover text-sm"
          @click="showCreateModal = true"
        >
          + New Group
        </button>
      </div>

      <div v-if="consolidationStore.loading" class="text-center text-muted py-8">Loading...</div>
      <div
        v-else-if="consolidationStore.groups.length === 0"
        class="text-center text-muted py-12 bg-surface-secondary rounded"
      >
        <p class="text-lg">No consolidation groups yet</p>
        <p class="text-sm mt-2">
          Create a group to start combining part-load material for a destination.
        </p>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="group in consolidationStore.groups"
          :key="group.id"
          class="bg-surface rounded shadow cursor-pointer hover:shadow-lg transition"
          @click="$router.push({ name: 'consolidation.show', params: { id: group.id } })"
        >
          <div class="p-4">
            <div class="flex justify-between items-start mb-3">
              <div>
                <p class="font-bold text-heading">{{ group.group_number }}</p>
                <p class="text-sm text-muted">→ {{ group.destination_city }}</p>
              </div>
              <span class="text-xs px-2 py-1 rounded font-bold" :class="getConsolidationStatusClass(group.status)">
                {{ group.status }}
              </span>
            </div>
            <!-- Fill Progress Bar -->
            <div class="mb-3">
              <div class="flex justify-between text-xs text-muted mb-1">
                <span>Truck Fill</span>
                <span class="font-semibold text-body">
                  {{ formatWeight(group.total_weight_kg) }} / {{ formatWeight(group.truck_capacity_kg) }} kg
                </span>
              </div>
              <div class="w-full bg-surface-tertiary rounded-full h-2.5 overflow-hidden">
                <div
                  class="h-full rounded-full transition-all"
                  :class="getFillBarClass(group.fill_percentage)"
                  :style="{ width: Math.min(group.fill_percentage || 0, 100) + '%' }"
                ></div>
              </div>
              <p
                class="text-xs text-right mt-1"
                :class="group.is_ready_to_dispatch ? 'text-status-green font-semibold' : 'text-muted'"
              >
                {{ (group.fill_percentage || 0).toFixed(1) }}% filled
                <span v-if="group.is_ready_to_dispatch"> ✓ Ready!</span>
              </p>
            </div>
            <div class="grid grid-cols-3 gap-2 text-sm pt-3 border-t border-line-light">
              <div>
                <p class="text-xs text-muted">Items</p>
                <p class="font-semibold text-body">{{ group.total_items || 0 }}</p>
              </div>
              <div>
                <p class="text-xs text-muted">Packages</p>
                <p class="font-semibold text-body">{{ group.total_packages || 0 }}</p>
              </div>
              <div>
                <p class="text-xs text-muted">Weight</p>
                <p class="font-semibold text-body">{{ formatWeight(group.total_weight_kg) }} kg</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Create Modal -->
      <div
        v-if="showCreateModal"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        @click.self="closeCreateModal"
      >
        <div class="bg-surface rounded-lg shadow-xl max-w-md w-full p-6">
          <h2 class="text-xl font-bold text-heading mb-4">New Consolidation Group</h2>
          <div class="space-y-4">
            <div>
              <label class="text-sm font-semibold text-body block mb-1">Destination City *</label>
              <input
                v-model="createForm.destination_city"
                type="text"
                placeholder="e.g. Mumbai"
                class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
              />
            </div>
            <div>
              <label class="text-sm font-semibold text-body block mb-1">Truck Capacity (kg)</label>
              <input
                v-model.number="createForm.truck_capacity_kg"
                type="number"
                placeholder="e.g. 9000"
                class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
              />
              <p class="text-xs text-muted mt-1">Default: 9000 kg (standard truck load)</p>
            </div>
            <div>
              <label class="text-sm font-semibold text-body block mb-1">Notes</label>
              <textarea
                v-model="createForm.notes"
                rows="2"
                class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
              ></textarea>
            </div>
          </div>
          <div class="flex justify-end gap-2 mt-6">
            <button
              class="px-4 py-2 bg-surface-secondary text-body rounded border border-line-default hover:bg-hover"
              @click="closeCreateModal"
            >
              Cancel
            </button>
            <button
              class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700"
              :disabled="!createForm.destination_city"
              @click="submitCreate"
            >
              Create Group
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== TAB 3: DISPATCHED ==================== -->
    <div v-if="activeTab === 'dispatched'">
      <!-- KPI Strip -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-surface rounded shadow p-4">
          <p class="text-sm text-muted">Planned</p>
          <p class="text-2xl font-bold text-status-blue">{{ tripStats.planned }}</p>
        </div>
        <div class="bg-surface rounded shadow p-4">
          <p class="text-sm text-muted">Dispatched</p>
          <p class="text-2xl font-bold text-status-purple">{{ tripStats.dispatched }}</p>
        </div>
        <div class="bg-surface rounded shadow p-4">
          <p class="text-sm text-muted">Delivered</p>
          <p class="text-2xl font-bold text-status-green">{{ tripStats.delivered }}</p>
        </div>
        <div class="bg-surface rounded shadow p-4">
          <p class="text-sm text-muted">Total Items Moved</p>
          <p class="text-2xl font-bold text-heading">{{ tripStats.totalItems }}</p>
        </div>
      </div>

      <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-bold text-heading">Load Trips</h2>
        <button
          class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 text-sm"
          @click="showTripModal = true"
        >
          + New Load Trip
        </button>
      </div>

      <div v-if="loadTripStore.loading" class="text-center text-muted py-8">Loading...</div>
      <div
        v-else-if="loadTripStore.trips.length === 0"
        class="text-center text-muted py-12 bg-surface-secondary rounded"
      >
        <p class="text-lg">No load trips yet</p>
        <p class="text-sm mt-2">
          Create a load trip from a ready consolidation group to dispatch a truck.
        </p>
      </div>

      <div v-else class="overflow-x-auto bg-surface rounded shadow">
        <table class="w-full text-sm">
          <thead class="bg-surface-secondary text-muted text-xs uppercase">
            <tr>
              <th class="text-left px-4 py-3">Trip Number</th>
              <th class="text-left px-4 py-3">Route</th>
              <th class="text-left px-4 py-3">Truck / Driver</th>
              <th class="text-left px-4 py-3">Consolidation</th>
              <th class="text-center px-4 py-3">Items</th>
              <th class="text-center px-4 py-3">Dispatch Date</th>
              <th class="text-center px-4 py-3">Status</th>
              <th class="text-center px-4 py-3">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line-light">
            <tr
              v-for="trip in loadTripStore.trips"
              :key="trip.id"
              class="hover:bg-hover cursor-pointer"
              @click="$router.push({ name: 'load-trips.show', params: { id: trip.id } })"
            >
              <td class="px-4 py-3 font-semibold text-heading">{{ trip.trip_number }}</td>
              <td class="px-4 py-3 text-body">
                {{ trip.origin_city || '—' }} → {{ trip.destination_city }}
              </td>
              <td class="px-4 py-3 text-body">
                <p class="font-medium">{{ trip.truck_number || '—' }}</p>
                <p class="text-xs text-muted">{{ trip.driver_name || '—' }}</p>
              </td>
              <td class="px-4 py-3 text-body">
                <span v-if="trip.consolidation_group" class="text-primary-500">
                  {{ trip.consolidation_group.group_number }}
                </span>
                <span v-else class="text-muted">—</span>
              </td>
              <td class="px-4 py-3 text-center text-body">
                {{ trip.warehouse_items?.length || 0 }}
              </td>
              <td class="px-4 py-3 text-center text-body">
                {{ trip.dispatch_date ? formatDate(trip.dispatch_date) : '—' }}
              </td>
              <td class="px-4 py-3 text-center">
                <span class="text-xs px-2 py-1 rounded font-bold" :class="getTripStatusClass(trip.status)">
                  {{ trip.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-center" @click.stop>
                <div class="flex justify-center gap-1">
                  <button
                    v-if="trip.status === 'planned'"
                    class="px-2 py-1 bg-status-purple text-white text-xs rounded hover:bg-status-purple/90"
                    @click="dispatchTrip(trip.id)"
                  >
                    Dispatch
                  </button>
                  <button
                    v-if="trip.status === 'dispatched'"
                    class="px-2 py-1 bg-status-green text-white text-xs rounded hover:bg-status-green/90"
                    @click="markDelivered(trip.id)"
                  >
                    Deliver
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Create Trip Modal -->
      <div
        v-if="showTripModal"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        @click.self="closeTripModal"
      >
        <div class="bg-surface rounded-lg shadow-xl max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto">
          <h2 class="text-xl font-bold text-heading mb-4">New Load Trip</h2>
          <div class="space-y-4">
            <div>
              <label class="text-sm font-semibold text-body block mb-1">Consolidation Group *</label>
              <select
                v-model="tripForm.consolidation_group_id"
                class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
              >
                <option value="">Select a ready group...</option>
                <option v-for="group in readyGroups" :key="group.id" :value="group.id">
                  {{ group.group_number }} → {{ group.destination_city }}
                  ({{ formatWeight(group.total_weight_kg) }} kg)
                </option>
              </select>
              <p v-if="readyGroups.length === 0" class="text-xs text-status-red mt-1">
                No ready consolidation groups available. Mark a group as ready first.
              </p>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-sm font-semibold text-body block mb-1">Truck Number *</label>
                <input
                  v-model="tripForm.truck_number"
                  type="text"
                  placeholder="e.g. KA01 AB 1234"
                  class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
                />
              </div>
              <div>
                <label class="text-sm font-semibold text-body block mb-1">Origin City</label>
                <input
                  v-model="tripForm.origin_city"
                  type="text"
                  placeholder="e.g. Bangalore"
                  class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
                />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-sm font-semibold text-body block mb-1">Driver Name *</label>
                <input
                  v-model="tripForm.driver_name"
                  type="text"
                  placeholder="Driver name"
                  class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
                />
              </div>
              <div>
                <label class="text-sm font-semibold text-body block mb-1">Driver Phone</label>
                <input
                  v-model="tripForm.driver_phone"
                  type="text"
                  placeholder="Phone number"
                  class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
                />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-sm font-semibold text-body block mb-1">Dispatch Date</label>
                <input
                  v-model="tripForm.dispatch_date"
                  type="date"
                  class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
                />
              </div>
              <div>
                <label class="text-sm font-semibold text-body block mb-1">Expected Delivery</label>
                <input
                  v-model="tripForm.expected_delivery_date"
                  type="date"
                  class="w-full px-3 py-2 border border-line-default rounded bg-surface text-body"
                />
              </div>
            </div>
          </div>
          <div class="flex justify-end gap-2 mt-6">
            <button
              class="px-4 py-2 bg-surface-secondary text-body rounded border border-line-default hover:bg-hover"
              @click="closeTripModal"
            >
              Cancel
            </button>
            <button
              class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700"
              :disabled="!canSubmitTrip"
              @click="submitTrip"
            >
              Create Trip
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useWarehouseItemStore } from '../store'
import { useConsolidationStore } from '../../consolidation/store'
import { useLoadTripStore } from '../../load-trips/store'

const store = useWarehouseItemStore()
const consolidationStore = useConsolidationStore()
const loadTripStore = useLoadTripStore()
const router = useRouter()

const activeTab = ref('warehouse')
const selectedStatus = ref('')
const selectedDestination = ref('')
const showCreateModal = ref(false)
const showTripModal = ref(false)

const createForm = ref({
  destination_city: '',
  truck_capacity_kg: 9000,
  notes: '',
})

const tripForm = ref({
  consolidation_group_id: '',
  truck_number: '',
  driver_name: '',
  driver_phone: '',
  origin_city: '',
  destination_city: '',
  dispatch_date: '',
  expected_delivery_date: '',
  notes: '',
})

const tabs = computed(() => [
  { key: 'warehouse', label: 'In Warehouse', count: store.dashboardStats?.stored_items ?? null },
  { key: 'consolidation', label: 'Consolidation', count: consolidationStore.groups.length || null },
  { key: 'dispatched', label: 'Dispatched', count: loadTripStore.trips.length || null },
])

// --- Computed: Warehouse Tab ---
const allDestinations = computed(() => {
  const destinations = new Set<string>()
  store.items.forEach((item: any) => {
    if (item.destination_city) {
      destinations.add(item.destination_city)
    }
  })
  return Array.from(destinations).sort()
})

const groupedItems = computed(() => {
  const groups: Record<string, any> = {}

  store.items.forEach((item: any) => {
    if (selectedStatus.value && item.status !== selectedStatus.value) return
    if (selectedDestination.value && item.destination_city !== selectedDestination.value) return

    const destination = item.destination_city || 'Unknown Destination'
    if (!groups[destination]) {
      groups[destination] = { destination, items: [], totalWeight: 0, overdueCount: 0 }
    }
    groups[destination].items.push(item)
    groups[destination].totalWeight += parseFloat(item.weight_kg) || 0
    if (item.is_overdue) groups[destination].overdueCount++
  })

  return Object.values(groups)
    .sort((a: any, b: any) => a.destination.localeCompare(b.destination))
    .map((group: any) => ({
      ...group,
      items: group.items.sort(
        (a: any, b: any) => new Date(b.date_received).getTime() - new Date(a.date_received).getTime()
      ),
    }))
})

// --- Computed: Consolidation Tab ---
const consolidationStats = computed(() => {
  const s = { open: 0, ready: 0, dispatched: 0, totalWeight: 0 }
  consolidationStore.groups.forEach((g: any) => {
    if (g.status === 'open') {
      s.open++
      s.totalWeight += parseFloat(g.total_weight_kg) || 0
    } else if (g.status === 'ready') {
      s.ready++
    } else if (g.status === 'dispatched') {
      s.dispatched++
    }
  })
  return s
})

// --- Computed: Dispatched Tab ---
const readyGroups = computed(() => {
  return consolidationStore.groups.filter((g: any) => g.status === 'ready')
})

const canSubmitTrip = computed(() => {
  return (
    tripForm.value.consolidation_group_id &&
    tripForm.value.truck_number &&
    tripForm.value.driver_name
  )
})

const tripStats = computed(() => {
  const s = { planned: 0, dispatched: 0, delivered: 0, totalItems: 0 }
  loadTripStore.trips.forEach((t: any) => {
    if (t.status === 'planned') s.planned++
    else if (t.status === 'dispatched') s.dispatched++
    else if (t.status === 'delivered') s.delivered++
    s.totalItems += t.warehouse_items?.length || 0
  })
  return s
})

// --- Watchers: Load data when tab changes ---
watch(activeTab, (newTab) => {
  if (newTab === 'consolidation') {
    consolidationStore.fetchGroups()
    consolidationStore.fetchCandidates()
  } else if (newTab === 'dispatched') {
    loadTripStore.fetchTrips()
    consolidationStore.fetchGroups({ status: 'ready' })
  }
})

// --- Methods: Warehouse Tab ---
const getAgingClass = (bucket: string) => {
  const classes: Record<string, string> = {
    '0-3': 'border-l-4 border-status-green',
    '4-7': 'border-l-4 border-status-yellow',
    '8-15': 'border-l-4 border-status-blue',
    '15+': 'border-l-4 border-status-red',
  }
  return classes[bucket] || ''
}

const getDaysClass = (days: number) => {
  if (days <= 3) return 'text-status-green'
  if (days <= 7) return 'text-status-yellow'
  if (days <= 15) return 'text-status-blue'
  return 'text-status-red'
}

const getPriorityClass = (priority: string) => {
  const classes: Record<string, string> = {
    urgent: 'bg-status-yellow/10 text-status-yellow',
    critical: 'bg-status-red/10 text-status-red',
  }
  return classes[priority] || ''
}

const deleteItem = async (id: number) => {
  if (confirm('Delete this warehouse item?')) {
    await store.deleteItem(id)
  }
}

const updateStatus = async (id: number, newStatus: string) => {
  try {
    await store.updateItem(id, { status: newStatus })
  } catch (error) {
    console.error('Error updating status:', error)
    alert('Failed to update status')
    await store.fetchItems()
  }
}

const goToConsolidation = (destination: string) => {
  activeTab.value = 'consolidation'
  consolidationStore.fetchGroups({ destination })
}

// --- Methods: Consolidation Tab ---
const openCreateForDestination = (destination: string) => {
  createForm.value.destination_city = destination
  showCreateModal.value = true
}

const closeCreateModal = () => {
  showCreateModal.value = false
  createForm.value = { destination_city: '', truck_capacity_kg: 9000, notes: '' }
}

const submitCreate = async () => {
  try {
    await consolidationStore.createGroup(createForm.value)
    closeCreateModal()
    await consolidationStore.fetchCandidates()
  } catch {
    alert('Failed to create consolidation group')
  }
}

const getConsolidationStatusClass = (status: string) => {
  const classes: Record<string, string> = {
    open: 'bg-status-blue/10 text-status-blue',
    ready: 'bg-status-green/10 text-status-green',
    dispatched: 'bg-status-purple/10 text-status-purple',
    completed: 'bg-surface-tertiary text-muted',
    cancelled: 'bg-status-red/10 text-status-red',
  }
  return classes[status] || ''
}

const getFillBarClass = (percentage: number) => {
  const p = percentage || 0
  if (p >= 80) return 'bg-status-green'
  if (p >= 50) return 'bg-status-yellow'
  if (p > 0) return 'bg-status-blue'
  return 'bg-surface-tertiary'
}

// --- Methods: Dispatched Tab ---
const closeTripModal = () => {
  showTripModal.value = false
  tripForm.value = {
    consolidation_group_id: '',
    truck_number: '',
    driver_name: '',
    driver_phone: '',
    origin_city: '',
    destination_city: '',
    dispatch_date: '',
    expected_delivery_date: '',
    notes: '',
  }
}

const submitTrip = async () => {
  try {
    const group = readyGroups.value.find(
      (g: any) => g.id === parseInt(tripForm.value.consolidation_group_id)
    )
    if (group) {
      tripForm.value.destination_city = group.destination_city
    }
    await loadTripStore.createTrip(tripForm.value)
    closeTripModal()
  } catch {
    alert('Failed to create load trip')
  }
}

const dispatchTrip = async (id: number) => {
  if (confirm('Dispatch this trip? Items will be marked as in transit.')) {
    try {
      await loadTripStore.dispatchTrip(id)
    } catch {
      alert('Failed to dispatch trip')
    }
  }
}

const markDelivered = async (id: number) => {
  if (confirm('Mark this trip as delivered?')) {
    try {
      await loadTripStore.markDelivered(id)
    } catch {
      alert('Failed to mark trip as delivered')
    }
  }
}

const getTripStatusClass = (status: string) => {
  const classes: Record<string, string> = {
    planned: 'bg-status-blue/10 text-status-blue',
    dispatched: 'bg-status-purple/10 text-status-purple',
    delivered: 'bg-status-green/10 text-status-green',
    cancelled: 'bg-status-red/10 text-status-red',
  }
  return classes[status] || ''
}

// --- Shared ---
const formatWeight = (weight: any) => {
  const w = parseFloat(weight) || 0
  return w.toLocaleString('en-IN', { maximumFractionDigits: 2 })
}

const formatDate = (date: string) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('en-IN', {
    day: '2-digit',
    month: 'short',
    year: '2-digit',
  })
}

onMounted(() => {
  store.fetchItems()
  store.fetchDashboard()
})
</script>
