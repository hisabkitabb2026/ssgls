<template>
  <BaseSettingCard
    :title="$t('settings.customization.custom_template_title')"
    :description="$t('settings.customization.custom_template_description')"
  >
    <div class="mt-4">
      <!-- Built-in Templates -->
      <div v-if="builtinTemplates.length" class="mb-6">
        <h4 class="text-sm font-semibold text-heading mb-3">Built-in Templates</h4>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
          <div
            v-for="tpl in builtinTemplates"
            :key="tpl.name"
            class="relative border border-line-default rounded-lg p-3 hover:border-primary-400 transition-colors"
          >
            <img
              v-if="tpl.path"
              :src="tpl.path"
              :alt="tpl.name"
              class="w-full h-32 object-contain mb-2"
            />
            <div v-else class="w-full h-32 flex items-center justify-center mb-2 bg-surface-secondary rounded">
              <BaseIcon name="DocumentTextIcon" class="w-8 h-8 text-subtle" />
            </div>
            <p class="text-xs font-medium text-heading truncate">{{ tpl.name }}</p>
            <button
              class="mt-1 text-xs text-primary-500 hover:text-primary-600"
              @click="downloadTemplate(tpl.name)"
            >
              Download Code
            </button>
          </div>
        </div>
      </div>

      <!-- Custom Templates -->
      <div v-if="customTemplates.length" class="mb-6">
        <h4 class="text-sm font-semibold text-heading mb-3">Custom Templates</h4>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
          <div
            v-for="tpl in customTemplates"
            :key="tpl.name"
            class="relative border border-line-default rounded-lg p-3 hover:border-primary-400 transition-colors"
          >
            <span class="absolute top-2 right-2 px-1.5 py-0.5 text-[10px] font-medium bg-primary-50 text-primary-600 rounded">Custom</span>
            <img
              v-if="tpl.path"
              :src="tpl.path"
              :alt="tpl.name"
              class="w-full h-32 object-contain mb-2"
            />
            <div v-else class="w-full h-32 flex items-center justify-center mb-2 bg-surface-secondary rounded">
              <BaseIcon name="DocumentTextIcon" class="w-8 h-8 text-subtle" />
            </div>
            <p class="text-xs font-medium text-heading truncate">{{ tpl.name }}</p>
            <div class="flex gap-2 mt-1">
              <button
                class="text-xs text-primary-500 hover:text-primary-600"
                @click="downloadTemplate(tpl.name)"
              >
                Download
              </button>
              <button
                class="text-xs text-red-500 hover:text-red-600"
                @click="confirmDelete(tpl.name)"
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty state for custom templates -->
      <div v-if="!customTemplates.length && !isLoading" class="mb-6 text-sm text-muted">
        No custom templates uploaded yet. Download a built-in template's code, modify it, and upload as a new template.
      </div>

      <!-- Actions -->
      <div class="flex flex-wrap gap-3">
        <BaseButton
          variant="primary-outline"
          :loading="isDownloading"
          @click="downloadBuiltin"
        >
          <template #left="slotProps">
            <BaseIcon name="ArrowDownTrayIcon" :class="slotProps.class" />
          </template>
          Download Built-in Code
        </BaseButton>
        <BaseButton
          variant="primary"
          @click="showUploadDialog = true"
        >
          <template #left="slotProps">
            <BaseIcon name="ArrowUpTrayIcon" :class="slotProps.class" />
          </template>
          Upload New Template
        </BaseButton>
      </div>
    </div>

    <!-- Upload Dialog -->
    <BaseModal
      :show="showUploadDialog"
      @close="closeUploadDialog"
    >
      <template #header>
        <p>Upload Custom Template</p>
      </template>

      <div class="px-6 py-4 space-y-4">
        <BaseInputGroup
          label="Template Name"
          :error="uploadError"
        >
          <BaseInput
            v-model="templateName"
            placeholder="e.g. My Custom LR Template"
          />
        </BaseInputGroup>

        <BaseInputGroup label="Select .blade.php file">
          <input
            ref="fileInput"
            type="file"
            accept=".php,.blade.php"
            class="block w-full text-sm text-muted file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-600 hover:file:bg-primary-100"
            @change="onFileSelected"
          />
        </BaseInputGroup>

        <p class="text-xs text-muted">
          Document type: <span class="font-medium text-heading">{{ documentType }}</span>
        </p>
      </div>

      <template #footer>
        <div class="flex justify-end gap-3 px-6 py-4">
          <BaseButton variant="gray" @click="closeUploadDialog">
            {{ $t('general.cancel') }}
          </BaseButton>
          <BaseButton
            variant="primary"
            :loading="isUploading"
            :disabled="!templateName || !selectedFile"
            @click="uploadTemplate"
          >
            Upload
          </BaseButton>
        </div>
      </template>
    </BaseModal>
  </BaseSettingCard>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { customTemplateService } from '@/scripts/api/services/custom-template.service'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import type { CustomTemplate } from '@/scripts/api/services/custom-template.service'

interface Props {
  documentType: string
}

const props = defineProps<Props>()

const notificationStore = useNotificationStore()
const dialogStore = useDialogStore()

const builtinTemplates = ref<CustomTemplate[]>([])
const customTemplates = ref<CustomTemplate[]>([])
const isLoading = ref(false)
const isDownloading = ref(false)
const isUploading = ref(false)
const showUploadDialog = ref(false)
const templateName = ref('')
const selectedFile = ref<File | null>(null)
const uploadError = ref('')
const fileInput = ref<HTMLInputElement | null>(null)

async function fetchTemplates() {
  isLoading.value = true
  try {
    const response = await customTemplateService.list(props.documentType)
    builtinTemplates.value = response.builtinTemplates
    customTemplates.value = response.customTemplates
  } catch {
    notificationStore.showNotification({
      type: 'error',
      message: 'Failed to load templates.',
    })
  } finally {
    isLoading.value = false
  }
}

async function downloadTemplate(templateName: string) {
  isDownloading.value = true
  try {
    const blob = await customTemplateService.download(templateName)
    triggerDownload(blob, `${templateName}.blade.php`)
  } catch {
    notificationStore.showNotification({
      type: 'error',
      message: 'Failed to download template.',
    })
  } finally {
    isDownloading.value = false
  }
}

async function downloadBuiltin() {
  isDownloading.value = true
  try {
    const blob = await customTemplateService.downloadBuiltin(props.documentType)
    triggerDownload(blob, `${props.documentType}.blade.php`)
  } catch {
    notificationStore.showNotification({
      type: 'error',
      message: 'Failed to download built-in template.',
    })
  } finally {
    isDownloading.value = false
  }
}

function triggerDownload(blob: Blob, filename: string) {
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  window.URL.revokeObjectURL(url)
}

function onFileSelected(event: Event) {
  const target = event.target as HTMLInputElement
  selectedFile.value = target.files?.[0] ?? null
}

function closeUploadDialog() {
  showUploadDialog.value = false
  templateName.value = ''
  selectedFile.value = null
  uploadError.value = ''
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

async function uploadTemplate() {
  if (!templateName.value || !selectedFile.value) return

  isUploading.value = true
  uploadError.value = ''

  try {
    await customTemplateService.upload(
      templateName.value,
      props.documentType,
      selectedFile.value,
    )
    notificationStore.showNotification({
      type: 'success',
      message: 'Template uploaded successfully.',
    })
    closeUploadDialog()
    await fetchTemplates()
  } catch (error) {
    const err = error as { response?: { data?: { error?: string } } }
    uploadError.value = err?.response?.data?.error || 'Failed to upload template.'
  } finally {
    isUploading.value = false
  }
}

function confirmDelete(templateName: string) {
  dialogStore.openDialog({
    title: 'Delete Template',
    message: `Delete "${templateName}"? This cannot be undone.`,
    yesLabel: 'Delete',
    noLabel: 'Cancel',
    variant: 'danger',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      try {
        await customTemplateService.delete(templateName)
        notificationStore.showNotification({
          type: 'success',
          message: 'Template deleted successfully.',
        })
        await fetchTemplates()
      } catch {
        notificationStore.showNotification({
          type: 'error',
          message: 'Failed to delete template.',
        })
      }
    }
  })
}

onMounted(() => {
  fetchTemplates()
})
</script>
