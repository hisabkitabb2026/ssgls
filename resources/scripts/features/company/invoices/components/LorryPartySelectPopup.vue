<script setup lang="ts"> 
/** 
* LorryPartySelectPopup — Party selector for Lorry Receipts. 
* 
* Replaces the Customer-based "Party" field with a LorryPartyProfile-based 
* selector that shows only OWNER and BROKER profiles.  The selected profile's 
* ID is stored in invoiceStore.newInvoice.party_profile_id. 
* 
* The selected card shows only the profile Name (matching the compact style 
* of the Owner/Driver/Broker cards in LorryReceiptPartyFields.vue). 
*/ 
import { ref, computed, nextTick, watch } from 'vue' 
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue' 
import { useDebounceFn } from '@vueuse/core' 
import { useInvoiceStore } from '../store' 
import { useModalStore } from '@/scripts/stores/modal.store' 
import { useLorryPartyProfileStore } from '@/scripts/features/company/lorry-party-profiles/store' 
import type { LorryPartyProfile } from '@/scripts/types/domain/lorry-party-profile' 
  
  
const invoiceStore = useInvoiceStore() 
const modalStore = useModalStore() 
const profileStore = useLorryPartyProfileStore() 
  
const search = ref<string>('') 
const profiles = ref<LorryPartyProfile[]>([]) 
const selectedProfile = ref<LorryPartyProfile | null>(null) 
  
const selectedProfileName = computed<string>(() => { 
  return selectedProfile.value?.name ?? 'Party' 
}) 
  
async function fetchProfiles(): Promise<void> { 
  try { 
    // Fetch all OWNER and BROKER profiles (two requests, then merge) 
    const [ownerRes, brokerRes] = await Promise.all([ 
      profileStore.fetchProfiles({ search: search.value, type: 'OWNER', limit: 'all' }), 
      profileStore.fetchProfiles({ search: search.value, type: 'BROKER', limit: 'all' }), 
    ]) 
    profiles.value = [ 
      ...(ownerRes.data || []), 
      ...(brokerRes.data || []), 
    ] 
  } catch { 
    profiles.value = [] 
  } 
} 
  
const debounceSearch = useDebounceFn(() => { 
  fetchProfiles() 
}, 500) 
  
function ensureProfilesLoaded(): void { 
  if (!profiles.value.length) { 
    fetchProfiles() 
  } 
} 
  
function selectProfile(profile: LorryPartyProfile, close: () => void): void { 
  selectedProfile.value = profile 
  invoiceStore.newInvoice.party_profile_id = profile.id ?? null 
  
  // Auto-fill "Paid To" (Hire Particulars) and "Final Paid To" (Final Payment 
  // Details) with the selected party's name so the user doesn't have to 
  // re-enter it manually. 
  invoiceStore.newInvoice.paid_to = profile.name ?? null 
  invoiceStore.newInvoice.final_paid_to = profile.name ?? null 
  
  close() 
  search.value = '' 
} 
  
function resetProfile(): void { 
  selectedProfile.value = null 
  invoiceStore.newInvoice.party_profile_id = null 
  
  // Clear the auto-filled fields when the party is deselected 
  invoiceStore.newInvoice.paid_to = null 
  invoiceStore.newInvoice.final_paid_to = null 
} 
  
async function editProfile(): Promise<void> { 
  if (!selectedProfile.value?.id) return 
  
  try { 
    const response = await profileStore.fetchProfile(selectedProfile.value.id) 
    if (response.data) { 
      profileStore.setCurrentProfile(response.data) 
    } 
  } catch { 
    return 
  } 
  
  modalStore.openModal({ 
    title: 'Edit Party', 
    componentName: 'LorryPartyProfileModal', 
    size: 'lg', 
  }) 
} 
  
async function openProfileCreate(close?: () => void): Promise<void> { 
  close?.() 
  
  // Wait for the Popover to finish closing and restoring focus before 
  // opening the modal.  Without this delay, the Popover's focus restoration 
  // fires AFTER the Dialog opens, and the Dialog interprets the focus shift 
  // as an outside interaction → immediately emits `close` → modal vanishes. 
  await nextTick() 
  setTimeout(() => { 
    profileStore.setCurrentProfile({ 
      type: 'OWNER', 
      name: '', 
      phone: '', 
      address: '', 
      bank_account_no: '', 
    }) 
    modalStore.openModal({ 
      title: 'New Party', 
      componentName: 'LorryPartyProfileModal', 
      size: 'lg', 
    }) 
  }, 150) 
} 
  
// React to profile saves from the shared (page-level) LorryPartyProfileModal. 
// The modal calls profileStore.notifyProfileSaved() on successful save, and 
// we pick it up here via the store — no @saved emit needed. 
watch( 
  () => profileStore.lastSavedAt, 
  (ts) => { 
    if (!ts) return 
    const saved = profileStore.lastSavedProfile 
    if (!saved) return 
    // Only react to OWNER and BROKER profiles (this popup's scope) 
    if (saved.type !== 'OWNER' && saved.type !== 'BROKER') return 
    selectedProfile.value = saved 
    invoiceStore.newInvoice.party_profile_id = saved.id ?? null 
    invoiceStore.newInvoice.paid_to = saved.name ?? null 
    invoiceStore.newInvoice.final_paid_to = saved.name ?? null 
  }, 
) 
  
  
function initGenerator(name?: string): string { 
  if (name) { 
    return name.charAt(0).toUpperCase() 
  } 
  return '' 
} 
</script> 
  
<template> 
  <div> 
    <!-- Selected profile card (name only) --> 
  
    <div 
      v-if="selectedProfile" 
      class="flex flex-col p-4 bg-surface border border-line-default border-solid min-h-[170px] rounded-md" 
      @click.stop 
    > 
      <div class="flex relative justify-between gap-3 mb-2"> 
        <BaseText 
          :text="selectedProfileName" 
          class="flex-1 text-base font-medium text-left text-heading" 
        /> 
        <div class="flex flex-wrap justify-end gap-x-4 gap-y-2"> 
          <a 
            v-if="selectedProfile.id" 
            class="relative my-0 text-sm flex items-center font-medium cursor-pointer text-primary-500" 
            @click="editProfile" 
          > 
            <BaseIcon name="PencilIcon" class="text-muted h-4 w-4 mr-1" /> 
            {{ $t('general.edit') }} 
          </a> 
          <a 
            class="relative my-0 text-sm flex items-center font-medium cursor-pointer text-primary-500" 
            @click="resetProfile" 
          > 
            <BaseIcon name="XCircleIcon" class="text-muted h-4 w-4 mr-1" /> 
            {{ $t('general.deselect') }} 
          </a> 
        </div> 
      </div> 
  
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-8 mt-2"> 
        <div class="flex flex-col"> 
          <label class="mb-1 text-sm font-medium text-left text-muted uppercase whitespace-nowrap"> 
            Party Name 
          </label> 
          <div class="flex flex-col flex-1 p-0 text-left"> 
            <label class="relative w-11/12 text-sm truncate"> 
              {{ selectedProfile.name }} 
            </label> 
            <label v-if="selectedProfile.type" class="relative w-11/12 text-xs text-muted mt-1"> 
              <span class="inline-block px-2 py-0.5 rounded-full bg-primary-100 text-primary-700 font-medium"> 
                {{ selectedProfile.type }} 
              </span> 
            </label> 
          </div> 
        </div> 
      </div> 
    </div> 
  
    <!-- Unselected: Popover search selector --> 
    <Popover v-else v-slot="{ open }" class="relative flex flex-col rounded-md"> 
      <PopoverButton 
        :class="{ 
          'focus:ring-2 focus:ring-primary-400': !open, 
        }" 
        class="w-full outline-hidden rounded-md" 
        @click="ensureProfilesLoaded" 
      > 
        <div class="relative flex justify-center px-0 p-0 py-16 bg-surface border border-line-default border-solid rounded-md min-h-[170px]"> 
          <BaseIcon 
            name="UserIcon" 
            class="flex justify-center !w-10 !h-10 p-2 mr-5 text-sm text-white bg-surface-muted rounded-full font-base" 
          /> 
          <div class="mt-1"> 
            <label class="text-lg font-medium text-heading"> 
              Party Name 
            </label> 
          </div> 
        </div> 
      </PopoverButton> 
  
      <transition 
        enter-active-class="transition duration-200 ease-out" 
        enter-from-class="translate-y-1 opacity-0" 
        enter-to-class="translate-y-0 opacity-100" 
        leave-active-class="transition duration-150 ease-in" 
        leave-from-class="translate-y-0 opacity-100" 
        leave-to-class="translate-y-1 opacity-0" 
      > 
        <div v-if="open" class="absolute min-w-full z-10"> 
          <PopoverPanel 
            v-slot="{ close }" 
            static 
            class="overflow-hidden rounded-md shadow-lg ring-1 ring-black/5 bg-surface" 
          > 
            <div class="relative"> 
              <BaseInput 
                v-model="search" 
                container-class="m-4" 
                :placeholder="$t('general.search')" 
                type="text" 
                icon="search" 
                @update:model-value="debounceSearch" 
              /> 
  
              <ul class="max-h-80 flex flex-col overflow-auto list border-t border-line-light"> 
                <li 
                  v-for="option in profiles" 
                  :key="option.id" 
                  class="flex px-6 py-2 border-b border-line-light border-solid cursor-pointer hover:cursor-pointer hover:bg-hover focus:outline-hidden focus:bg-hover" 
                  @click="selectProfile(option, close)" 
                > 
                  <div class="flex items-center justify-center h-10 w-10 mr-4 rounded-full bg-surface-muted uppercase text-primary-500"> 
                    {{ initGenerator(option.name) }} 
                  </div> 
                  <div class="flex-1 flex flex-col text-left"> 
                    <div class="flex items-center gap-2"> 
                      <span class="text-sm font-medium text-heading"> 
                        {{ option.name }} 
                      </span> 
                      <span class="text-xs px-2 py-0.5 rounded-full bg-primary-100 text-primary-700 font-medium"> 
                        {{ option.type }} 
                      </span> 
                    </div> 
                    <span class="text-xs text-muted"> 
                      {{ option.phone || option.address }} 
                    </span> 
                  </div> 
                </li> 
                <div 
                  v-if="profiles.length === 0" 
                  class="flex justify-center p-5 text-subtle" 
                > 
                  <label class="text-base text-muted cursor-pointer"> 
                    No party profiles found 
                  </label> 
                </div> 
              </ul> 
  
              <button 
                type="button" 
                class="flex items-center justify-center w-full px-6 py-3 bg-hover cursor-pointer" 
                @click="openProfileCreate(close)" 
              > 
                <BaseIcon name="PlusIcon" class="h-5 text-primary-400" /> 
                <label class="m-0 ml-3 text-sm leading-none cursor-pointer font-base text-primary-400"> 
                  Add New Party 
                </label> 
              </button> 
            </div> 
          </PopoverPanel> 
        </div> 
      </transition> 
    </Popover> 
  </div> 
</template> 