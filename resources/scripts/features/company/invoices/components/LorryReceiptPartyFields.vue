<script setup lang="ts">
import { reactive, watch } from 'vue'
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import { useDebounceFn } from '@vueuse/core'
import { useInvoiceStore } from '../store'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useLorryPartyProfileStore } from '@/scripts/features/company/lorry-party-profiles/store'
import type { LorryPartyProfile, LorryPartyProfileType } from '@/scripts/types/domain/lorry-party-profile'
import type { CustomFieldValue } from '@/scripts/types/domain/custom-field'
import LorryPartyProfileModal from './LorryPartyProfileModal.vue'


interface ProfileConfig {
  key: 'owner' | 'driver' | 'broker'
  type: LorryPartyProfileType
  label: string
  singular: string
  summaryLabel: string
}

interface FieldMapping {
  0: string // label
  1: string // profile key
}

type FieldMappings = Record<string, FieldMapping[]>

const invoiceStore = useInvoiceStore()
const modalStore = useModalStore()
const profileStore = useLorryPartyProfileStore()

const selectedProfiles = reactive<Record<string, LorryPartyProfile | null>>({
  owner: null,
  driver: null,
  broker: null,
})

const profileOptions = reactive<Record<string, LorryPartyProfile[]>>({
  owner: [],
  driver: [],
  broker: [],
})

const profileSearch = reactive<Record<string, string>>({
  owner: '',
  driver: '',
  broker: '',
})

const profileResolveInProgress = reactive<Record<string, boolean>>({
  owner: false,
  driver: false,
  broker: false,
})

const profileInputs: ProfileConfig[] = [
  { key: 'owner', type: 'OWNER', label: 'Owner Name', singular: 'Owner', summaryLabel: 'Owner Details' },
  { key: 'driver', type: 'DRIVER', label: 'Driver Name', singular: 'Driver', summaryLabel: 'Driver Details' },
  { key: 'broker', type: 'BROKER', label: 'Broker Name', singular: 'Broker', summaryLabel: 'Broker Details' },
]

const fieldMappingsByType: FieldMappings = {
  OWNER: [
    ['Owner Name', 'name'],
    ['Owner Address', 'address'],
    ['Owner Phone No', 'phone'],
    ['Owner PAN No', 'financer_name'],
    ['Owner Bank Account No', 'bank_account_no'],
  ],
  DRIVER: [
    ['Driver Name', 'name'],
    ['Driver Address', 'address'],
    ['Driver Licence No', 'licence_no'],
    ['Issued Dt.', 'licence_date'],
    ['Driver RTO', 'rto_address'],
    ['Driver Valid Up To', 'valid_up_to'],
    ['Driver Bank Account No', 'bank_account_no'],
  ],
  BROKER: [
    ['Broker Name', 'name'],
    ['Broker Address', 'address'],
    ['Broker Pan No', 'advice_no'],
    ['Advice Date', 'advice_date'],
    ['Broker Phone No', 'phone'],
    ['Broker Bank Account No', 'bank_account_no'],
  ],
}

const debounceSearchProfiles = useDebounceFn((profile: ProfileConfig) => {
  fetchProfileOptions(profile)
}, 500)

function getInvoiceField(label: string): CustomFieldValue | undefined {
  return invoiceStore.newInvoice.customFields?.find(
    (field) => field.custom_field?.label === label,
  )
}

function setField(label: string, value: string | null | undefined): void {
  const field = invoiceStore.newInvoice.customFields?.find(
    (f) => f.custom_field?.label === label,
  )

  if (field) {
    field.string_answer = label === 'Broker Pan No'
      ? String(value || '').toUpperCase()
      : (value as string) || ''
  }
}

function getFieldValue(label: string): string {
  const field = getInvoiceField(label)
  return (field?.string_answer as string) || ''
}

async function fetchProfileOptions(profile: ProfileConfig): Promise<void> {
  try {
    const response = await profileStore.fetchProfiles({
      search: profileSearch[profile.key],
      type: profile.type,
      limit: 'all',
    })
    profileOptions[profile.key] = response.data || []
  } catch {
    profileOptions[profile.key] = []
  }
}

function ensureProfilesLoaded(profile: ProfileConfig): void {
  if (!profileOptions[profile.key].length) {
    fetchProfileOptions(profile)
  }
}

function selectProfile(profile: ProfileConfig, option: LorryPartyProfile, close: () => void): void {
  selectedProfiles[profile.key] = option
  fillProfileFields(profile.type, option)
  close()
  profileSearch[profile.key] = ''
}

function resetProfile(profile: ProfileConfig): void {
  selectedProfiles[profile.key] = null
  fillProfileFields(profile.type, null)
}

function openProfileCreate(profile: ProfileConfig, close?: () => void): void {
  close?.()
  profileStore.setCurrentProfile({
    type: profile.type,
    name: '',
    phone: '',
    address: '',
    bank_account_no: '',
  })
  modalStore.openModal({
    title: `New ${profile.singular}`,
    componentName: 'LorryPartyProfileModal',
    size: 'lg',
  })
}

async function openProfileEdit(profile: ProfileConfig): Promise<void> {
  if (!selectedProfiles[profile.key]?.id) {
    await resolveSelectedProfile(profile)
  }

  const selectedProfile = selectedProfiles[profile.key]

  if (!selectedProfile?.id) {
    return
  }

  try {
    const response = await profileStore.fetchProfile(selectedProfile.id)
    if (response.data) {
      profileStore.setCurrentProfile(response.data)
    }
  } catch {
    return
  }

  modalStore.openModal({
    title: `Edit ${profile.singular}`,
    componentName: 'LorryPartyProfileModal',
    size: 'lg',
  })
}

function handleProfileSaved({ type, profile }: { type: LorryPartyProfileType; profile: LorryPartyProfile }): void {
  const key = type.toLowerCase()
  selectedProfiles[key] = profile
  fillProfileFields(type, profile)
}

function fillProfileFields(type: LorryPartyProfileType, profile: LorryPartyProfile | null): void {
  // 1. Fill custom fields by label (for backward compat / PDF templates)
  const mappings = fieldMappingsByType[type]
  if (mappings) {
    mappings.forEach((mapping) => {
      const [label, key] = mapping
      setField(label, profile?.[key as keyof LorryPartyProfile] as string)
    })
  }

  if (type === 'OWNER') {
    setField('Paid To', profile?.name)
    setField('Final Paid To', profile?.name)
  }
}

function compact(value: string | null | undefined): string {
  return value ? String(value).trim() : ''
}

function normalize(value: string | null | undefined): string {
  return compact(value).toLowerCase().replace(/[^a-z0-9]+/g, '')
}

function formatProfileLines(profile: LorryPartyProfile | null): string[] {
  if (!profile) return []
  return [
    compact(profile.address),
    compact(profile.phone),
    compact(profile.code),
  ].filter(Boolean)
}

function getProfileDocuments(
  profile: LorryPartyProfile | null,
  type: LorryPartyProfileType,
): Array<{ label: string; path: string | null }> {
  if (!profile) return []

  if (type === 'OWNER') {
    return [
      { label: 'RC Front', path: profile.rc_front_path ?? null },
      { label: 'RC Back', path: profile.rc_back_path ?? null },
      { label: 'PAN Front', path: profile.pan_front_path ?? null },
      { label: 'Insurance Copy', path: profile.insurance_path ?? null },
    ]
  }

  if (type === 'DRIVER') {
    return [
      { label: 'License Front', path: profile.license_front_path ?? null },
      { label: 'License Back', path: profile.license_back_path ?? null },
    ]
  }

  if (type === 'BROKER') {
    return [{ label: 'PAN Front', path: profile.pan_front_path_broker ?? null }]
  }

  return []
}

async function resolveSelectedProfile(profile: ProfileConfig): Promise<void> {
  const selectedProfile = selectedProfiles[profile.key]
  const profileName = compact(selectedProfile?.name)

  if (
    !profileName
    || selectedProfile?.id
    || profileResolveInProgress[profile.key]
  ) {
    return
  }

  profileResolveInProgress[profile.key] = true

  try {
    await fetchProfileOptions(profile)
    const matchedProfile = profileOptions[profile.key].find(
      (option) => normalize(option.name) === normalize(profileName),
    )

    if (matchedProfile && !selectedProfiles[profile.key]?.id) {
      selectedProfiles[profile.key] = matchedProfile
      fillProfileFields(profile.type, matchedProfile)
    }
  } finally {
    profileResolveInProgress[profile.key] = false
  }
}

function hydrateSelectedProfilesFromFields(): void {
  profileInputs.forEach((profile) => {
    if (selectedProfiles[profile.key]?.id) {
      return
    }

    if (selectedProfiles[profile.key]) {
      resolveSelectedProfile(profile)
      return
    }

    const mappings = fieldMappingsByType[profile.type]
    if (!mappings) return

    const hydratedProfile: LorryPartyProfile = { type: profile.type }

    mappings.forEach((mapping) => {
      const [label, key] = mapping
      hydratedProfile[key as keyof LorryPartyProfile] = getFieldValue(label) as never
    })

    if (
      compact(hydratedProfile.name)
      || compact(hydratedProfile.address)
      || compact(hydratedProfile.phone)
    ) {
      selectedProfiles[profile.key] = hydratedProfile
      resolveSelectedProfile(profile)
    }
  })
}

watch(
  () => invoiceStore.newInvoice.customFields,
  () => {
    hydrateSelectedProfilesFromFields()
  },
  { deep: true, immediate: true },
)
</script>

<template>
  <div class="mb-8">
    <div class="grid gap-y-6 gap-x-4 grid-cols-1 md:grid-cols-2">
      <div
        v-for="profile in profileInputs"
        :key="profile.key"
        class="relative flex flex-col rounded-md"
      >
        <!-- Selected profile card -->
        <div
          v-if="selectedProfiles[profile.key]"
          class="flex flex-col p-4 bg-surface border border-line-default border-solid min-h-[170px] rounded-md"
        >
          <div class="flex relative justify-between gap-3 mb-2">
            <BaseText
              :text="selectedProfiles[profile.key]!.name ?? profile.singular"
              class="flex-1 text-base font-medium text-left text-heading"
            />
            <div class="flex flex-wrap justify-end gap-x-4 gap-y-2">
              <a
                v-if="selectedProfiles[profile.key]!.id"
                class="relative my-0 text-sm flex items-center font-medium cursor-pointer text-primary-500"
                @click.stop="openProfileEdit(profile)"
              >
                <BaseIcon name="PencilIcon" class="text-muted h-4 w-4 mr-1" />
                {{ $t('general.edit') }}
              </a>
              <a
                class="relative my-0 text-sm flex items-center font-medium cursor-pointer text-primary-500"
                @click="resetProfile(profile)"
              >
                <BaseIcon name="XCircleIcon" class="text-muted h-4 w-4 mr-1" />
                {{ $t('general.deselect') }}
              </a>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-8 mt-2">
            <div class="flex flex-col">
              <label class="mb-1 text-sm font-medium text-left text-muted uppercase whitespace-nowrap">
                {{ profile.summaryLabel }}
              </label>
              <div class="flex flex-col flex-1 p-0 text-left">
                <label
                  v-for="line in formatProfileLines(selectedProfiles[profile.key])"
                  :key="`${profile.key}-${line}`"
                  class="relative w-11/12 text-sm truncate"
                >
                  {{ line }}
                </label>
              </div>
            </div>

            <!-- Document Attachments Section -->
            <div class="flex flex-col">
              <label class="mb-1 text-sm font-medium text-left text-muted uppercase whitespace-nowrap">
                Attached Documents
              </label>
              <div class="flex flex-col flex-1 p-0 text-left">
                <template
                  v-for="doc in getProfileDocuments(selectedProfiles[profile.key], profile.type)"
                  :key="doc.label"
                >
                  <a
                    v-if="doc.path"
                    :href="doc.path"
                    target="_blank"
                    class="relative w-11/12 text-sm truncate text-primary-500 hover:text-primary-600 flex items-center gap-1 mb-1"
                  >
                    <BaseIcon name="DocumentIcon" class="h-4 w-4" />
                    {{ doc.label }}
                  </a>
                  <span v-else class="relative w-11/12 text-sm truncate text-muted mb-1 block">
                    {{ doc.label }} (Not attached)
                  </span>
                </template>
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
            @click="ensureProfilesLoaded(profile)"
          >
            <div class="relative flex justify-center px-0 p-0 py-16 bg-surface border border-line-default border-solid rounded-md min-h-[170px]">
              <BaseIcon
                name="UserIcon"
                class="flex justify-center !w-10 !h-10 p-2 mr-5 text-sm text-white bg-surface-muted rounded-full font-base"
              />
              <div class="mt-1">
                <label class="text-lg font-medium text-heading">
                  {{ profile.label }}
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
                    v-model="profileSearch[profile.key]"
                    container-class="m-4"
                    :placeholder="$t('general.search')"
                    type="text"
                    icon="search"
                    @update:model-value="debounceSearchProfiles(profile)"
                  />

                  <ul class="max-h-80 flex flex-col overflow-auto list border-t border-line-light">
                    <li
                      v-for="option in profileOptions[profile.key]"
                      :key="option.id"
                      class="flex px-6 py-2 border-b border-line-light border-solid cursor-pointer hover:cursor-pointer hover:bg-hover focus:outline-hidden focus:bg-hover"
                      @click="selectProfile(profile, option, close)"
                    >
                      <div class="flex items-center justify-center h-10 w-10 mr-4 rounded-full bg-surface-muted uppercase text-primary-500">
                        {{ (option.name || profile.singular).charAt(0) }}
                      </div>
                      <div class="flex-1 flex flex-col text-left">
                        <span class="text-sm font-medium text-heading">
                          {{ option.name }}
                        </span>
                        <span class="text-xs text-muted">
                          {{ option.phone || option.address }}
                        </span>
                      </div>
                    </li>
                  </ul>

                  <button
                    type="button"
                    class="flex items-center justify-center w-full px-6 py-3 bg-hover cursor-pointer"
                    @click="openProfileCreate(profile, close)"
                  >
                    <BaseIcon name="PlusIcon" class="h-5 text-primary-400" />
                    <label class="m-0 ml-3 text-sm leading-none cursor-pointer font-base text-primary-400">
                      Add New {{ profile.singular }}
                    </label>
                  </button>
                </div>
              </PopoverPanel>
            </div>
          </transition>
        </Popover>
      </div>
    </div>
  </div>
  <LorryPartyProfileModal @saved="handleProfileSaved" />
</template>

