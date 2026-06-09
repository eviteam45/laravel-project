<script setup lang="ts">
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { projectSchema } from '~/schemas/project'

const props = defineProps<{
  initial?: Record<string, any>
  submitLabel?: string
  onSubmit: (payload: Record<string, any>) => Promise<void>
}>()

const { user } = useAuth()
const { contractorOptions, customerOptions } = useProjects()

const showContractorPicker = computed(() => user.value?.role === 'admin' && !props.initial?.id)

const general = ref('')

const { defineField, handleSubmit, errors, isSubmitting, setErrors } = useForm({
  validationSchema: toTypedSchema(projectSchema),
  initialValues: {
    name: props.initial?.name ?? '',
    contractor_id: props.initial?.contractor_id ?? null,
    customer_id: props.initial?.customer_id ?? null,
    address: props.initial?.address ?? '',
    capacity_kw: props.initial?.capacity_kw ?? null,
    install_date: props.initial?.install_date ?? '',
  },
})
const [name, nameAttrs] = defineField('name')
const [contractorId] = defineField('contractor_id')
const [customerId] = defineField('customer_id')
const [address, addressAttrs] = defineField('address')
const [capacityKw, capacityKwAttrs] = defineField('capacity_kw')
const [installDate, installDateAttrs] = defineField('install_date')

const submit = handleSubmit(async (values) => {
  general.value = ''
  try {
    await props.onSubmit(values)
  }
  catch (e) {
    general.value = applyServerErrors(e, setErrors) ?? 'Something went wrong.'
  }
})
</script>

<template>
  <form
    class="space-y-4"
    @submit="submit"
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
        v-model="name"
        v-bind="nameAttrs"
        class="input"
        type="text"
      >
      <p
        v-if="errors.name"
        class="field-error"
      >
        {{ errors.name }}
      </p>
    </div>

    <div v-if="showContractorPicker">
      <label class="label">Contractor</label>
      <EntitySelect
        v-model="contractorId"
        :search="contractorOptions"
        label-key="company_name"
        placeholder="Search contractor by company…"
        noun="contractors"
        :initial-label="initial?.contractor?.company_name"
      />
      <p
        v-if="errors.contractor_id"
        class="field-error"
      >
        {{ errors.contractor_id }}
      </p>
    </div>

    <div>
      <label class="label">Customer</label>
      <EntitySelect
        v-model="customerId"
        :search="customerOptions"
        label-key="full_name"
        placeholder="Search customer by name…"
        noun="customers"
        :initial-label="initial?.customer?.full_name"
      />
      <p
        v-if="errors.customer_id"
        class="field-error"
      >
        {{ errors.customer_id }}
      </p>
    </div>

    <div>
      <label
        for="address"
        class="label"
      >Address</label>
      <input
        id="address"
        v-model="address"
        v-bind="addressAttrs"
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
          v-model="capacityKw"
          v-bind="capacityKwAttrs"
          class="input"
          type="number"
          step="0.01"
        >
        <p
          v-if="errors.capacity_kw"
          class="field-error"
        >
          {{ errors.capacity_kw }}
        </p>
      </div>
      <div>
        <label
          for="install_date"
          class="label"
        >Install date</label>
        <input
          id="install_date"
          v-model="installDate"
          v-bind="installDateAttrs"
          class="input"
          type="date"
        >
      </div>
    </div>

    <button
      type="submit"
      class="btn btn-primary"
      :disabled="isSubmitting"
    >
      {{ isSubmitting ? 'Saving…' : (submitLabel ?? 'Save') }}
    </button>
  </form>
</template>
