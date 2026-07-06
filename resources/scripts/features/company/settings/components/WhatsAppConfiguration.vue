<script setup lang="ts">
import { reactive, ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'

const { t } = useI18n()
const companyStore = useCompanyStore()
const notificationStore = useNotificationStore()

// Get the webhook URL for display
const webhookUrl = computed(() => {
  const baseUrl = window.location.origin
  return `${baseUrl}/api/v1/whatsapp/webhook`
})

async function copyWebhookUrl(): Promise<void> {
  try {
    await navigator.clipboard.writeText(webhookUrl.value)
    notificationStore.showNotification({
      type: 'success',
      message: 'Webhook URL copied to clipboard',
    })
  } catch {
    notificationStore.showNotification({
      type: 'error',
      message: 'Failed to copy webhook URL',
    })
  }
}

const isLoading = ref(false)
const isTesting = ref(false)

const whatsappConfig = reactive({
  whatsapp_enabled: companyStore.selectedCompanySettings.whatsapp_enabled ?? 'NO',
  whatsapp_server_url: companyStore.selectedCompanySettings.whatsapp_server_url ?? '',
  whatsapp_api_key: companyStore.selectedCompanySettings.whatsapp_api_key ?? '',
  whatsapp_session_id: companyStore.selectedCompanySettings.whatsapp_session_id ?? 'default',
})

const whatsappEnabled = computed<boolean>({
  get: () => whatsappConfig.whatsapp_enabled === 'YES',
  set: (value: boolean) => {
    whatsappConfig.whatsapp_enabled = value ? 'YES' : 'NO'
  },
})

async function saveWhatsAppConfig(): Promise<void> {
  try {
    isLoading.value = true

    await companyStore.updateCompanySettings({
      data: {
        settings: {
          whatsapp_enabled: whatsappConfig.whatsapp_enabled,
          whatsapp_server_url: whatsappConfig.whatsapp_server_url,
          whatsapp_api_key: whatsappConfig.whatsapp_api_key,
          whatsapp_session_id: whatsappConfig.whatsapp_session_id,
        },
      },
      message: 'general.setting_updated',
    })

    notificationStore.showNotification({
      type: 'success',
      message: 'settings.whatsapp.connection_successful',
    })
  } catch {
    notificationStore.showNotification({
      type: 'error',
      message: 'settings.whatsapp.connection_failed',
    })
  } finally {
    isLoading.value = false
  }
}

async function testConnection(): Promise<void> {
  if (!whatsappConfig.whatsapp_server_url || !whatsappConfig.whatsapp_api_key) {
    notificationStore.showNotification({
      type: 'error',
      message: 'Please configure server URL and API key first',
    })
    return
  }

  try {
    isTesting.value = true

    // Simple test - just check if the server is reachable
    const response = await fetch(whatsappConfig.whatsapp_server_url, {
      method: 'GET',
      headers: {
        'X-API-Key': whatsappConfig.whatsapp_api_key,
      },
    }).catch(() => null)

    if (response?.ok) {
      notificationStore.showNotification({
        type: 'success',
        message: 'settings.whatsapp.connection_successful',
      })
    } else {
      throw new Error('Connection failed')
    }
  } catch {
    notificationStore.showNotification({
      type: 'error',
      message: 'settings.whatsapp.connection_failed',
    })
  } finally {
    isTesting.value = false
  }
}
</script>

<template>
  <BaseCard>
    <template #header>
      <BaseCard.Title>
        {{ $t('settings.whatsapp.title') }}
      </BaseCard.Title>
      <BaseCard.Description>
        {{ $t('settings.whatsapp.section_description') }}
      </BaseCard.Description>
    </template>

    <div class="mt-6 space-y-6">
      <BaseSwitchSection
        v-model="whatsappEnabled"
        :title="$t('settings.whatsapp.enabled')"
        :description="$t('settings.whatsapp.enabled_description')"
      />

      <BaseInputGrid layout="two-column">
        <BaseInputGroup
          :label="$t('settings.whatsapp.server_url')"
          :description="$t('settings.whatsapp.server_url_description')"
        >
          <BaseInput
            v-model="whatsappConfig.whatsapp_server_url"
            type="text"
            placeholder="http://localhost:2785"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('settings.whatsapp.api_key')"
          :description="$t('settings.whatsapp.api_key_description')"
        >
          <BaseInput
            v-model="whatsappConfig.whatsapp_api_key"
            type="password"
            placeholder="your-api-key"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('settings.whatsapp.session_id')"
          :description="$t('settings.whatsapp.session_id_description')"
        >
          <BaseInput
            v-model="whatsappConfig.whatsapp_session_id"
            type="text"
            placeholder="default"
          />
        </BaseInputGroup>
      </BaseInputGrid>

      <!-- Webhook URL Section -->
      <div class="p-4 bg-surface-secondary rounded-lg border border-line-default">
        <h4 class="text-heading font-medium mb-2">
          📥 {{ $t('settings.whatsapp.webhook_title') }}
        </h4>
        <p class="text-body text-sm mb-3">
          {{ $t('settings.whatsapp.webhook_description') }}
        </p>
        <div class="flex items-center gap-2">
          <code class="flex-1 px-3 py-2 bg-surface-muted rounded text-sm text-body font-mono">
            {{ webhookUrl }}
          </code>
          <BaseButton
            variant="primary-outline"
            size="sm"
            @click="copyWebhookUrl"
          >
            {{ $t('general.copy') }}
          </BaseButton>
        </div>
        <p class="text-subtle text-xs mt-3">
          {{ $t('settings.whatsapp.webhook_instructions') }}
        </p>
      </div>

      <div class="flex gap-4">
        <BaseButton
          :loading="isLoading"
          :disabled="isLoading"
          variant="primary"
          @click="saveWhatsAppConfig"
        >
          {{ $t('general.save') }}
        </BaseButton>

        <BaseButton
          :loading="isTesting"
          :disabled="isTesting"
          variant="primary-outline"
          @click="testConnection"
        >
          {{ $t('settings.whatsapp.test_connection') }}
        </BaseButton>
      </div>
    </div>
  </BaseCard>
</template>
