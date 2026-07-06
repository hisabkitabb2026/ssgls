<template>
  <BaseDropdown>
    <template #activator>
      <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-muted" />
    </template>

    <!-- View Lorry Receipt -->
    <router-link
      v-if="canView"
      :to="`/admin/lorry-receipts/${row.id}/view`"
    >
      <BaseDropdownItem>
        <BaseIcon
          name="EyeIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        View
      </BaseDropdownItem>
    </router-link>

    <!-- Edit Lorry Receipt -->
    <router-link
      v-if="canEdit"
      :to="`/admin/lorry-receipts/${row.id}/edit`"
    >
      <BaseDropdownItem v-show="row.allow_edit">
        <BaseIcon
          name="PencilIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        Edit
      </BaseDropdownItem>
    </router-link>

    <!-- Send Lorry Receipt -->
    <BaseDropdownItem v-if="canSendInvoice" @click="sendInvoice">
      <BaseIcon
        name="PaperAirplaneIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      Send Lorry Receipt
    </BaseDropdownItem>

    <!-- Resend Lorry Receipt -->
    <BaseDropdownItem v-if="canReSendInvoice" @click="sendInvoice">
      <BaseIcon
        name="PaperAirplaneIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      Resend Lorry Receipt
    </BaseDropdownItem>

    <!-- Download Lorry Receipt (single PDF) -->
    <BaseDropdownItem @click="downloadLorryReceipt">
      <BaseIcon
        name="ArrowDownTrayIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      Download Lorry Receipt
    </BaseDropdownItem>

    <!-- Delete Lorry Receipt -->
    <BaseDropdownItem v-if="canDelete" @click="removeInvoice">
      <BaseIcon
        name="TrashIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      Delete
    </BaseDropdownItem>
  </BaseDropdown>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useInvoiceStore } from '../store'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { useModalStore } from '@/scripts/stores/modal.store'
import type { Invoice } from '@/scripts/types/domain/invoice'

interface TableRef {
  refresh: () => void
}

interface Props {
  row: Invoice & Record<string, unknown>
  table?: TableRef | null
  canEdit?: boolean
  canView?: boolean
  canCreate?: boolean
  canDelete?: boolean
  canSend?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  table: null,
  canEdit: false,
  canView: false,
  canCreate: false,
  canDelete: false,
  canSend: false,
})

const invoiceStore = useInvoiceStore()
const dialogStore = useDialogStore()
const modalStore = useModalStore()
const router = useRouter()

const canReSendInvoice = computed<boolean>(() => {
  return (
    (props.row.status === 'SENT' || props.row.status === 'VIEWED') &&
    props.canSend
  )
})

const canSendInvoice = computed<boolean>(() => {
  return (
    props.row.status === 'DRAFT' &&
    props.canSend
  )
})

function removeInvoice(): void {
  dialogStore.openDialog({
    title: 'Are you sure?',
    message: 'Are you sure you want to delete this Lorry Receipt?',
    yesLabel: 'OK',
    noLabel: 'Cancel',
    variant: 'danger',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      const response = await invoiceStore.deleteInvoice({ ids: [props.row.id] })
      if (response.data.success) {
        router.push('/admin/lorry-receipts')
        props.table?.refresh()
        invoiceStore.$patch((state) => {
          state.selectedInvoices = []
          state.selectAllField = false
        })
      }
    }
  })
}

function sendInvoice(): void {
  modalStore.openModal({
    title: 'Send Lorry Receipt',
    componentName: 'SendInvoiceModal',
    id: props.row.id,
    data: props.row,
    variant: 'sm',
  })
}

function downloadLorryReceipt(): void {
  const pdfUrl = `${window.location.origin}/invoices/pdf/${props.row.unique_hash}`
  window.open(pdfUrl, '_blank')
}
</script>
