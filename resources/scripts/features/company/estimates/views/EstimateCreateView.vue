<template>
  <BasePage class="relative estimate-create-page">
    <form @submit.prevent="submitForm">
      <BasePageHeader :title="pageTitle">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
          <BaseBreadcrumbItem
            :title="$t('estimates.estimate', 2)"
            to="/admin/estimates"
          />
          <BaseBreadcrumbItem
            v-if="isEdit"
            :title="$t('estimates.edit_estimate')"
            to="#"
            active
          />
          <BaseBreadcrumbItem v-else :title="$t('estimates.new_estimate')" to="#" active />
        </BaseBreadcrumb>

        <template #actions>
          <router-link
            v-if="isEdit"
            :to="`/estimates/pdf/${estimateStore.newEstimate.unique_hash}`"
            target="_blank"
          >
            <BaseButton class="mr-3" variant="primary-outline" type="button">
              <span class="flex">
                {{ $t('general.view_pdf') }}
              </span>
            </BaseButton>
          </router-link>

          <BaseButton
            :loading="isSaving"
            :disabled="isSaving"
            :content-loading="isLoadingContent"
            variant="primary"
            type="submit"
          >
            <template #left="slotProps">
              <BaseIcon
                v-if="!isSaving"
                :class="slotProps.class"
                name="ArrowDownOnSquareIcon"
              />
            </template>
            {{ $t('estimates.save_estimate') }}
          </BaseButton>
        </template>
      </BasePageHeader>

      <!-- Select Customer & Basic Fields -->
      <EstimateBasicFields
        :v="v$"
        :is-loading="isLoadingContent"
        :is-edit="isEdit"
      />

      <BaseScrollPane>
        <!-- Estimate Items (hidden for quotation template) -->
        <DocumentItemsTable
          v-if="!isQuotationTemplate"
          :currency="estimateStore.newEstimate.selectedCurrency"
          :is-loading="isLoadingContent"
          :item-validation-scope="estimateValidationScope"
          :store="estimateStore"
          store-prop="newEstimate"
        />

        <!-- Quotation Rate Sheet (only for quotation template) -->
        <div v-if="isQuotationTemplate" class="px-6 py-4">
          <QuotationRateSection :store="estimateStore" />
        </div>

        <!-- Estimate Footer Section -->
        <div
          class="block mt-10 estimate-foot"
          :class="isQuotationTemplate
            ? 'block mt-10 estimate-foot px-6'
            : 'block mt-10 estimate-foot lg:flex lg:justify-between lg:items-start'
          "
        >
          <div :class="isQuotationTemplate ? 'relative w-full' : 'relative w-full lg:w-1/2'">
            <!-- Estimate Custom Notes -->
            <DocumentNotes
              :store="estimateStore"
              store-prop="newEstimate"
              :fields="estimateNoteFieldList"
              type="Estimate"
            />


          </div>

          <DocumentTotals
            v-if="!isQuotationTemplate"
            :currency="estimateStore.newEstimate.selectedCurrency"
            :is-loading="isLoadingContent"
            :store="estimateStore"
            store-prop="newEstimate"
            tax-popup-type="estimate"
          />
        </div>
      </BaseScrollPane>
    </form>
  </BasePage>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import cloneDeep from 'lodash/cloneDeep'
import {
  required,
  maxLength,
  helpers,
  requiredIf,
  decimal,
} from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useEstimateStore } from '../store'
import EstimateBasicFields from '../components/EstimateBasicFields.vue'
import {
  DocumentItemsTable,
  DocumentTotals,
  DocumentNotes,
  QuotationRateSection,
} from '../../../shared/document-form'

const estimateStore = useEstimateStore()
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const estimateValidationScope = 'newEstimate'
const isSaving = ref<boolean>(false)
const isMarkAsDefault = ref<boolean>(false)

const estimateNoteFieldList = ref<string[]>(['customer', 'company', 'estimate'])

const isLoadingContent = computed<boolean>(
  () => estimateStore.isFetchingInitialSettings,
)

const isQuotationTemplate = computed<boolean>(
  () => estimateStore.newEstimate.template_name === 'quotation',
)

const pageTitle = computed<string>(() =>
  isEdit.value ? t('estimates.edit_estimate') : t('estimates.new_estimate'),
)

const isEdit = computed<boolean>(() => route.name === 'estimates.edit')

const rules = {
  estimate_date: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  estimate_number: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  reference_number: {
    maxLength: helpers.withMessage(t('validation.price_maxlength'), maxLength(255)),
  },
  customer_id: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  exchange_rate: {
    required: requiredIf(() => estimateStore.showExchangeRate),
  },
}

const v$ = useVuelidate(
  rules,
  computed(() => estimateStore.newEstimate),
  { $scope: estimateValidationScope },
)

// Initialization
estimateStore.resetCurrentEstimate()
v$.value.$reset
estimateStore.fetchEstimateInitialSettings(
  isEdit.value,
  { id: route.params.id as string, query: route.query as Record<string, string> },
)

watch(
  () => estimateStore.newEstimate.customer,
  (newVal) => {
    if (newVal && (newVal as Record<string, unknown>).currency) {
      estimateStore.newEstimate.selectedCurrency = (
        newVal as Record<string, unknown>
      ).currency as Record<string, unknown>
    }
  },
)

async function submitForm(): Promise<void> {
  v$.value.$touch()

  if (v$.value.$invalid) {
    console.log('Estimate form invalid. Errors:', JSON.stringify(
      v$.value.$errors.map((e: { $property: string; $message: string }) => `${e.$property}: ${e.$message}`)
    ))
    return
  }

  isSaving.value = true

  const data: Record<string, unknown> = {
    ...cloneDeep(estimateStore.newEstimate),
    sub_total: isQuotationTemplate.value ? 0 : Math.round(estimateStore.getSubTotal),
    total: isQuotationTemplate.value ? 0 : Math.round(estimateStore.getTotal),
    tax: isQuotationTemplate.value ? 0 : Math.round(estimateStore.getTotalTax),
  }

  if (isQuotationTemplate.value) {
    data.items = []
    data.taxes = []
  } else {
    const items = data.items as Array<Record<string, unknown>>
    if (data.discount_per_item === 'YES') {
      items.forEach((item, index) => {
        if (item.discount_type === 'fixed') {
          items[index].discount = Math.round((item.discount as number) * 100)
        }
      })
    } else {
      if (data.discount_type === 'fixed') {
        data.discount = Math.round((data.discount as number) * 100)
      }
    }

    const taxes = data.taxes as Array<Record<string, unknown>>
    if (data.tax_per_item !== 'YES' && taxes.length) {
      data.tax_type_ids = taxes.map((tax) => tax.tax_type_id)
    }
  }

  try {
    const action = isEdit.value
      ? estimateStore.updateEstimate
      : estimateStore.addEstimate

    const res = await action(data)
    if (res.data.data) {
      router.push(`/admin/estimates/${res.data.data.id}/view`)
    }
  } catch (err) {
    console.error(err)
  }

  isSaving.value = false
}
</script>
