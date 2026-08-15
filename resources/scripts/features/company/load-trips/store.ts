import { defineStore } from 'pinia'
import { ref } from 'vue'
import { client } from '@/scripts/api/client'

export const useLoadTripStore = defineStore('loadTrips', () => {
  const trips = ref([])
  const currentTrip = ref(null)
  const loading = ref(false)

  const fetchTrips = async (params: Record<string, any> = {}) => {
    loading.value = true
    try {
      const query = new URLSearchParams()
      if (params.status) query.append('status', params.status)
      if (params.destination) query.append('destination', params.destination)
      const qs = query.toString()
      const url = `/api/v1/load-trips${qs ? `?${qs}` : ''}`
      const response = await client.get(url)
      if (Array.isArray(response)) {
        trips.value = response
      } else if (Array.isArray(response.data)) {
        trips.value = response.data
      } else if (response.data && Array.isArray(response.data.data)) {
        trips.value = response.data.data
      } else {
        trips.value = []
      }
    } catch (error) {
      console.error('Error fetching load trips:', error)
      trips.value = []
    } finally {
      loading.value = false
    }
  }

  const getTrip = async (id: number) => {
    try {
      const response = await client.get(`/api/v1/load-trips/${id}`)
      const data = response.data?.data || response.data
      currentTrip.value = data
      return data
    } catch (error) {
      console.error('Error fetching trip:', error)
      return null
    }
  }

  const createTrip = async (data: any) => {
    try {
      await client.post('/api/v1/load-trips', data)
      await fetchTrips()
    } catch (error) {
      console.error('Error creating trip:', error)
      throw error
    }
  }

  const updateTrip = async (id: number, data: any) => {
    try {
      await client.put(`/api/v1/load-trips/${id}`, data)
      await fetchTrips()
    } catch (error) {
      console.error('Error updating trip:', error)
      throw error
    }
  }

  const deleteTrip = async (id: number) => {
    try {
      await client.delete(`/api/v1/load-trips/${id}`)
      await fetchTrips()
    } catch (error) {
      console.error('Error deleting trip:', error)
      throw error
    }
  }

  const dispatchTrip = async (id: number) => {
    try {
      await client.patch(`/api/v1/load-trips/${id}/dispatch`)
      await fetchTrips()
    } catch (error) {
      console.error('Error dispatching trip:', error)
      throw error
    }
  }

  const markDelivered = async (id: number) => {
    try {
      await client.patch(`/api/v1/load-trips/${id}/deliver`)
      await fetchTrips()
    } catch (error) {
      console.error('Error marking trip delivered:', error)
      throw error
    }
  }

  return {
    trips,
    currentTrip,
    loading,
    fetchTrips,
    getTrip,
    createTrip,
    updateTrip,
    deleteTrip,
    dispatchTrip,
    markDelivered,
  }
})
