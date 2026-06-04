<script setup lang="ts">
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { registerSchema } from '~/schemas/auth'

const { register } = useAuth()
const general = ref('')

const { defineField, handleSubmit, errors, isSubmitting, setErrors } = useForm({
  validationSchema: toTypedSchema(registerSchema),
  initialValues: {
    name: '', email: '', password: '', password_confirmation: '',
    role: 'customer', phone: '', company_name: '', address: '',
  },
})
const [name, nameAttrs] = defineField('name')
const [email, emailAttrs] = defineField('email')
const [password, passwordAttrs] = defineField('password')
const [passwordConfirmation, passwordConfirmationAttrs] = defineField('password_confirmation')
const [role] = defineField('role')
const [phone, phoneAttrs] = defineField('phone')
const [companyName, companyNameAttrs] = defineField('company_name')
const [address, addressAttrs] = defineField('address')

const onSubmit = handleSubmit(async (values) => {
  general.value = ''
  try {
    await register(values)
    await navigateTo('/')
  }
  catch (e: any) {
    if (e?.data?.errors) setErrors(mapServerErrors(e.data.errors))
    else general.value = e?.data?.message ?? 'Registration failed.'
  }
})
</script>

<template>
  <div class="mx-auto max-w-md">
    <div class="card">
      <h1 class="mb-1">
        Create an account
      </h1>
      <p class="mb-5 text-sm text-gray-500">
        Join as a homeowner applicant or a solar contractor.
      </p>

      <p
        v-if="general"
        class="field-error mb-3"
      >
        {{ general }}
      </p>

      <form
        class="space-y-4"
        @submit="onSubmit"
      >
        <div>
          <span class="label">I am a…</span>
          <div class="grid grid-cols-2 gap-2">
            <label
              class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm"
              :class="role === 'customer' ? 'border-emerald-500 bg-emerald-50' : 'border-gray-300'"
            >
              <input
                v-model="role"
                type="radio"
                value="customer"
              > Customer
            </label>
            <label
              class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm"
              :class="role === 'contractor' ? 'border-emerald-500 bg-emerald-50' : 'border-gray-300'"
            >
              <input
                v-model="role"
                type="radio"
                value="contractor"
              > Contractor
            </label>
          </div>
        </div>

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
            autocomplete="name"
          >
          <p
            v-if="errors.name"
            class="field-error"
          >
            {{ errors.name }}
          </p>
        </div>

        <div>
          <label
            for="email"
            class="label"
          >Email</label>
          <input
            id="email"
            v-model="email"
            v-bind="emailAttrs"
            class="input"
            type="email"
            autocomplete="email"
          >
          <p
            v-if="errors.email"
            class="field-error"
          >
            {{ errors.email }}
          </p>
        </div>

        <div v-if="role === 'contractor'">
          <label
            for="company_name"
            class="label"
          >Company name</label>
          <input
            id="company_name"
            v-model="companyName"
            v-bind="companyNameAttrs"
            class="input"
            type="text"
          >
          <p
            v-if="errors.company_name"
            class="field-error"
          >
            {{ errors.company_name }}
          </p>
        </div>

        <div v-if="role === 'customer'">
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

        <div>
          <label
            for="phone"
            class="label"
          >Phone</label>
          <input
            id="phone"
            v-model="phone"
            v-bind="phoneAttrs"
            class="input"
            type="tel"
          >
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label
              for="password"
              class="label"
            >Password</label>
            <input
              id="password"
              v-model="password"
              v-bind="passwordAttrs"
              class="input"
              type="password"
              autocomplete="new-password"
            >
            <p
              v-if="errors.password"
              class="field-error"
            >
              {{ errors.password }}
            </p>
          </div>
          <div>
            <label
              for="password_confirmation"
              class="label"
            >Confirm</label>
            <input
              id="password_confirmation"
              v-model="passwordConfirmation"
              v-bind="passwordConfirmationAttrs"
              class="input"
              type="password"
              autocomplete="new-password"
            >
            <p
              v-if="errors.password_confirmation"
              class="field-error"
            >
              {{ errors.password_confirmation }}
            </p>
          </div>
        </div>

        <button
          type="submit"
          class="btn btn-primary w-full"
          :disabled="isSubmitting"
        >
          {{ isSubmitting ? 'Creating…' : 'Register' }}
        </button>
      </form>
    </div>

    <p class="mt-4 text-center text-sm text-gray-500">
      Already have an account? <NuxtLink to="/login">
        Log in
      </NuxtLink>
    </p>
  </div>
</template>
