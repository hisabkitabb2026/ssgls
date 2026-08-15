<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  required,
  minLength,
  maxLength,
  helpers,
} from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useItemStore } from '../store'
import { useTaxTypes } from '../use-tax-types'
import { useCompanyStore } from '../../../../stores/company.store'
import { useUserStore } from '../../../../stores/user.store'
import { useNotificationStore } from '../../../../stores/notification.store'
import type { TaxType } from '@/scripts/types/domain/tax'

interface TaxOption {
  id: number
  name: string
  percent: number
  fixed_amount: number
  calculation_type: string | null
  tax_type_id: number
  tax_name: string
}

const ABILITIES = {
  VIEW_TAX_TYPE: 'view-tax-type',
} as const

const itemStore = useItemStore()
const { taxTypes, fetchTaxTypes } = useTaxTypes()
const companyStore = useCompanyStore()
const userStore = useUserStore()
const notificationStore = useNotificationStore()

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const isSaving = ref<boolean>(false)
const isAddingUnit = ref<boolean>(false)
const taxPerItem = ref<string>(companyStore.selectedCompanySettings.tax_per_item || 'NO')
const isFetchingInitialData = ref<boolean>(false)
const isEdit = computed<boolean>(() => route.name === 'items.edit')

// Inline "Add Weight + Rate" form state
const newUnitForm = reactive({
  name: '',
  rate: 0, // in display units (will be converted to cents)
})

itemStore.resetCurrentItem()
loadData()

const taxes = computed({
  get: () =>
    itemStore.currentItem.taxes?.map((tax) => {
      if (tax) {
        const currencyCode = companyStore.selectedCompanyCurrency?.code ?? 'USD'
        return {
          ...tax,
          tax_type_id: tax.id,
          tax_name: `${tax.name} (${
            tax.calculation_type === 'fixed'
              ? new Intl.NumberFormat(undefined, {
                  style: 'currency',
                  currency: currencyCode,
                }).format(tax.fixed_amount / 100)
              : `${tax.percent}%`
          })`,
        }
      }
      return tax
    }) ?? [],
  set: (value: TaxOption[]) => {
    itemStore.currentItem.taxes = value as unknown as typeof itemStore.currentItem.taxes
  },
})

const pageTitle = computed<string>(() =>
  isEdit.value ? t('items.edit_item') : t('items.new_item')
)

const getTaxTypes = computed<TaxOption[]>(() => {
  return taxTypes.value.map((tax: TaxType) => {
    const currencyCode = companyStore.selectedCompanyCurrency?.code ?? 'USD'
    return {
      ...tax,
      tax_type_id: tax.id,
      tax_name: `${tax.name} (${
        tax.calculation_type === 'fixed'
          ? new Intl.NumberFormat(undefined, {
              style: 'currency',
              currency: currencyCode,
            }).format(tax.fixed_amount / 100)
          : `${tax.percent}%`
      })`,
    }
  }) as TaxOption[]
})

const isTaxPerItem = computed<boolean>(() => taxPerItem.value === 'YES')

const rules = computed(() => ({
  currentItem: {
    name: {
      required: helpers.withMessage(t('validation.required'), required),
      minLength: helpers.withMessage(
        t('validation.name_min_length', { count: 2 }),
        minLength(2)
      ),
    },
    description: {
      maxLength: helpers.withMessage(
        t('validation.description_maxlength'),
        maxLength(65000)
      ),
    },
  },
}))

const v$ = useVuelidate(rules, itemStore)

// --- Rate Card helpers ---
// rate_card is { "unitId": rateInCents }.  Columns come from the units table
// (itemStore.itemUnits).  The inline "Add Weight + Rate" form lets users
// create a new unit and set its rate without leaving this page.

function getRateCardRate(unitId: number | string): number {
  return (itemStore.currentItem.rate_card ?? {})[String(unitId)] ?? 0
}

function setRateCardRate(unitId: number | string, valueCents: number): void {
  if (!itemStore.currentItem.rate_card) {
    itemStore.currentItem.rate_card = {}
  }
  itemStore.currentItem.rate_card[String(unitId)] = valueCents
}

function removeRateCardEntry(unitId: number | string): void {
  if (itemStore.currentItem.rate_card) {
    delete itemStore.currentItem.rate_card[String(unitId)]
  }
}

// Add a new weight type + rate inline.  Creates the unit via the API,
// which adds it to itemStore.itemUnits (so it appears as a new row
// in the Rate Card editor), then sets the rate in the rate_card JSON.
async function addInlineWeightRate(): Promise<void> {
  const name = newUnitForm.name.trim()
  if (!name) {
    notificationStore.showNotification({
      type: 'error',
      message: t('validation.required'),
    })
    return
  }

  isAddingUnit.value = true
  try {
    const res = await itemStore.addItemUnit({ name })
    const newUnitId = res.data.id

    // Set the rate for the newly created unit
    if (newUnitForm.rate > 0) {
      setRateCardRate(newUnitId, Math.round(newUnitForm.rate * 100))
    }

    // Reset the inline form
    newUnitForm.name = ''
    newUnitForm.rate = 0
  } catch {
    // Error handled by store
  } finally {
    isAddingUnit.value = false
  }
}

async function loadData(): Promise<void> {
  isFetchingInitialData.value = true

  await itemStore.fetchItemUnits({ limit: 'all' })
  if (userStore.hasAbilities(ABILITIES.VIEW_TAX_TYPE)) {
    await fetchTaxTypes()
  }

  if (isEdit.value) {
    const id = Number(route.params.id)
    await itemStore.fetchItem(id)
    taxPerItem.value =
      itemStore.currentItem.tax_per_item === 1 ||
      itemStore.currentItem.tax_per_item === '1' ||
      itemStore.currentItem.tax_per_item === true
        ? 'YES'
        : 'NO'
  }

  isFetchingInitialData.value = false
}

async function submitItem(): Promise<void> {
  v$.value.currentItem.$touch()

  if (v$.value.currentItem.$invalid) {
    return
  }

  isSaving.value = true

  try {
    const data: Record<string, unknown> = {
      id: route.params.id,
      ...itemStore.currentItem,
    }

    if (itemStore.currentItem.taxes) {
      // Use the first rate from rate_card as the base price for tax calc,
      // or fall back to 0 if no rates are set.
      const rateCard = itemStore.currentItem.rate_card ?? {}
      const firstRate = Object.values(rateCard)[0] ?? 0
      data.taxes = itemStore.currentItem.taxes.map((tax) => ({
        tax_type_id: (tax as Record<string, unknown>).tax_type_id ?? tax.id,
        calculation_type: tax.calculation_type,
        fixed_amount: tax.fixed_amount,
        amount:
          tax.calculation_type === 'fixed'
            ? tax.fixed_amount
            : Math.round((firstRate / 100) * tax.percent),
        percent: tax.percent,
        name: tax.name,
        collective_tax: 0,
      }))
    }

    const action = isEdit.value ? itemStore.updateItem : itemStore.addItem
    await action(data)
    isSaving.value = false
    router.push('/admin/items')
  } catch {
    isSaving.value = false
  }
}
</script>

<template>
  <BasePage>
    <BasePageHeader :title="pageTitle">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('items.item', 2)" to="/admin/items" />
        <BaseBreadcrumbItem :title="pageTitle" to="#" active />
      </BaseBreadcrumb>
    </BasePageHeader>

    <form
      class="grid lg:grid-cols-2 mt-6"
      action="submit"
      @submit.prevent="submitItem"
    >
      <BaseCard class="w-full">
        <BaseInputGrid layout="one-column">
          <BaseInputGroup
            :label="$t('items.station_name')"
            :content-loading="isFetchingInitialData"
            required
            :error="
              v$.currentItem.name.$error &&
              v$.currentItem.name.$errors[0].$message
            "
          >
            <BaseInput
              v-model="itemStore.currentItem.name"
              :content-loading="isFetchingInitialData"
              :invalid="v$.currentItem.name.$error"
              @input="v$.currentItem.name.$touch()"
            />
          </BaseInputGroup>

          <!-- Rate Card Editor: weight → rate rows.
               Each row shows a weight type (from the units table) and an
               editable rate.  The "Add Weight + Rate" button below lets
               users create a new weight type and set its rate inline,
               without navigating to Settings. -->
          <BaseInputGroup
            :label="$t('items.rate_card')"
            :content-loading="isFetchingInitialData"
          >
            <div class="space-y-2">
              <div
                v-for="unit in itemStore.itemUnits"
                :key="unit.id"
                class="flex items-center gap-2"
              >
                <span class="w-24 text-sm text-body">{{ unit.name }}</span>
                <BaseMoney
                  :model-value="getRateCardRate(unit.id) / 100"
                  :currency="companyStore.selectedCompanyCurrency"
                  class="relative w-full"
                  @update:model-value="(val: number) => setRateCardRate(unit.id, Math.round(val * 100))"
                />
                <BaseIcon
                  name="TrashIcon"
                  class="h-5 w-5 text-muted cursor-pointer hover:text-status-red"
                  @click="removeRateCardEntry(unit.id)"
                />
              </div>
              <p
                v-if="itemStore.itemUnits.length === 0"
                class="text-sm text-muted"
              >
                {{ $t('items.no_units_available') }}
              </p>

              <!-- Inline Add Weight + Rate form -->
              <div class="pt-2 border-t border-line-light">
                <div class="flex items-center gap-2">
                  <BaseInput
                    v-model="newUnitForm.name"
                    type="text"
                    :placeholder="$t('items.weight_name_placeholder')"
                    class="w-24"
                    :disabled="isAddingUnit"
                  />
                  <BaseMoney
                    v-model="newUnitForm.rate"
                    :currency="companyStore.selectedCompanyCurrency"
                    class="relative w-full"
                    :disabled="isAddingUnit"
                  />
                  <BaseButton
                    variant="primary-outline"
                    type="button"
                    size="sm"
                    :loading="isAddingUnit"
                    :disabled="isAddingUnit"
                    @click="addInlineWeightRate"
                  >
                    <template #left="slotProps">
                      <BaseIcon
                        name="PlusIcon"
                        :class="slotProps.class"
                      />
                    </template>
                    {{ $t('items.add_weight_rate') }}
                  </BaseButton>
                </div>
              </div>
            </div>
          </BaseInputGroup>

          <BaseInputGroup
            v-if="isTaxPerItem"
            :label="$t('items.taxes')"
            :content-loading="isFetchingInitialData"
          >
            <BaseMultiselect
              v-model="taxes"
              :content-loading="isFetchingInitialData"
              :options="getTaxTypes"
              mode="tags"
              label="tax_name"
              class="w-full"
              value-prop="id"
              :can-deselect="false"
              :can-clear="false"
              searchable
              track-by="tax_name"
              object
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('items.description')"
            :content-loading="isFetchingInitialData"
            :error="
              v$.currentItem.description.$error &&
              v$.currentItem.description.$errors[0].$message
            "
          >
            <BaseTextarea
              v-model="itemStore.currentItem.description"
              :content-loading="isFetchingInitialData"
              name="description"
              :row="2"
              rows="2"
              @input="v$.currentItem.description.$touch()"
            />
          </BaseInputGroup>

          <div>
            <BaseButton
              :content-loading="isFetchingInitialData"
              type="submit"
              :loading="isSaving"
            >
              <template #left="slotProps">
                <BaseIcon
                  v-if="!isSaving"
                  name="ArrowDownOnSquareIcon"
                  :class="slotProps.class"
                />
              </template>
              {{ isEdit ? $t('items.update_item') : $t('items.save_item') }}
            </BaseButton>
          </div>
        </BaseInputGrid>
      </BaseCard>
    </form>
  </BasePage>
</template>
