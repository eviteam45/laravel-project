<script setup lang="ts">
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { loginSchema } from '~/schemas/auth'

const { login } = useAuth()
const general = ref('')

const { defineField, handleSubmit, errors, isSubmitting, setErrors } = useForm({
  validationSchema: toTypedSchema(loginSchema),
  initialValues: { email: '', password: '' },
})
const [email, emailAttrs] = defineField('email')
const [password, passwordAttrs] = defineField('password')

const onSubmit = handleSubmit(async (values) => {
  general.value = ''
  try {
    await login(values)
    await navigateTo('/')
  }
  catch (e) {
    general.value = applyServerErrors(e, setErrors) ?? 'Login failed.'
  }
})
</script>

<template>
  <div class="mx-auto max-w-sm">
    <div class="card">
      <h1 class="mb-1">
        Log in
      </h1>
      <p class="mb-5 text-sm text-gray-500">
        Welcome back to the incentive portal.
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
        <div>
          <label
            for="password"
            class="label"
          >Password</label>
          <PasswordInput
            id="password"
            v-model="password"
            v-bind="passwordAttrs"
            class="input"
            autocomplete="current-password"
          />
          <p
            v-if="errors.password"
            class="field-error"
          >
            {{ errors.password }}
          </p>
        </div>
        <button
          type="submit"
          class="btn btn-primary w-full"
          :disabled="isSubmitting"
        >
          {{ isSubmitting ? 'Logging in…' : 'Log in' }}
        </button>
      </form>

      <p class="mt-4 text-right text-sm">
        <NuxtLink to="/forgot-password">
          Forgot password?
        </NuxtLink>
      </p>
    </div>

    <p class="mt-4 text-center text-sm text-gray-500">
      No account? <NuxtLink to="/register">
        Create one
      </NuxtLink>
    </p>
  </div>
</template>
