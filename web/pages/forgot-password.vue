<script setup lang="ts">
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { forgotPasswordSchema } from '~/schemas/auth'

const { forgotPassword } = useAuth()
const general = ref('')
const sent = ref(false)

const { defineField, handleSubmit, errors, isSubmitting, setErrors } = useForm({
  validationSchema: toTypedSchema(forgotPasswordSchema),
  initialValues: { email: '' },
})
const [email, emailAttrs] = defineField('email')

const onSubmit = handleSubmit(async (values) => {
  general.value = ''
  try {
    await forgotPassword(values.email)
    sent.value = true
  }
  catch (e) {
    general.value = applyServerErrors(e, setErrors) ?? 'Could not send the reset link.'
  }
})
</script>

<template>
  <div class="mx-auto max-w-sm">
    <div class="card">
      <h1 class="mb-1">
        Forgot password
      </h1>
      <p class="mb-5 text-sm text-gray-500">
        Enter your email and we'll send you a reset link.
      </p>

      <p
        v-if="sent"
        class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
      >
        If an account matches that email, a reset link is on its way. Check your inbox.
      </p>

      <template v-else>
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
          <button
            type="submit"
            class="btn btn-primary w-full"
            :disabled="isSubmitting"
          >
            {{ isSubmitting ? 'Sending…' : 'Send reset link' }}
          </button>
        </form>
      </template>
    </div>

    <p class="mt-4 text-center text-sm text-gray-500">
      Remembered it? <NuxtLink to="/login">
        Back to log in
      </NuxtLink>
    </p>
  </div>
</template>
