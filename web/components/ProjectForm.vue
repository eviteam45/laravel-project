<script setup lang="ts">
const props = defineProps<{
  initial?: Record<string, any>
  submitLabel?: string
  onSubmit: (payload: Record<string, any>) => Promise<void>
}>()

const { user } = useAuth()
// Contractors create under their own profile; admins must pick a contractor.
// Only on create — reassigning a project's contractor isn't supported on edit.
const showContractorPicker = computed(() => user.value?.role === 'admin' && !props.initial?.id)

const form = reactive({
  name: props.initial?.name ?? '',
  contractor_id: props.initial?.contractor_id ?? null,
  customer_id: props.initial?.customer_id ?? null,
  address: props.initial?.address ?? '',
  capacity_kw: props.initial?.capacity_kw ?? null,
  install_date: props.initial?.install_date ?? '',
})

const errors = ref<Record<string, string[]>>({})
const general = ref('')
const loading = ref(false)

async function handle() {
  loading.value = true
  errors.value = {}
  general.value = ''
  try {
    await props.onSubmit({ ...form })
  }
  catch (e: any) {
    if (e?.data?.errors) errors.value = e.data.errors
    else general.value = e?.data?.message ?? 'Something went wrong.'
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <form
    class="space-y-4"
    @submit.prevent="handle"
  >
    <p
      v-if="general"
      class="field-error"
    >
      {{ general }}
    </p>

    <div>
      <label
        for="name"
        class="label"
      >Name</label>
      <input
        id="name"
        v-model="form.name"
        class="input"
        type="text"
        required
      >
      <p
        v-if="errors.name"
        class="field-error"
      >
        {{ errors.name[0] }}
      </p>
    </div>

    <div v-if="showContractorPicker">
      <label class="label">Contractor</label>
      <ContractorSelect
        v-model="form.contractor_id"
        :initial-label="initial?.contractor?.company_name"
      />
      <p
        v-if="errors.contractor_id"
        class="field-error"
      >
        {{ errors.contractor_id[0] }}
      </p>
    </div>

    <div>
      <label class="label">Customer</label>
      <CustomerSelect
        v-model="form.customer_id"
        :initial-label="initial?.customer?.full_name"
      />
      <p
        v-if="errors.customer_id"
        class="field-error"
      >
        {{ errors.customer_id[0] }}
      </p>
    </div>

    <div>
      <label
        for="address"
        class="label"
      >Address</label>
      <input
        id="address"
        v-model="form.address"
        class="input"
        type="text"
      >
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div>
        <label
          for="capacity_kw"
          class="label"
        >Capacity (kW)</label>
        <input
          id="capacity_kw"
          v-model.number="form.capacity_kw"
          class="input"
          type="number"
          step="0.01"
        >
        <p
          v-if="errors.capacity_kw"
          class="field-error"
        >
          {{ errors.capacity_kw[0] }}
        </p>
      </div>
      <div>
        <label
          for="install_date"
          class="label"
        >Install date</label>
        <input
          id="install_date"
          v-model="form.install_date"
          class="input"
          type="date"
        >
      </div>
    </div>

    <button
      type="submit"
      class="btn btn-primary"
      :disabled="loading"
    >
      {{ loading ? 'Saving…' : (submitLabel ?? 'Save') }}
    </button>
  </form>
</template>
