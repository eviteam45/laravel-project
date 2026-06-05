<script setup lang="ts">
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { resetPasswordSchema } from '~/schemas/auth'

const route = useRoute()
const { resetPassword } = useAuth()

const token = (route.query.token as string) || ''
const email = (route.query.email as string) || ''
const hasLink = computed(() => !!token && !!email)

const general = ref('')
const done = ref(false)

const { defineField, handleSubmit, errors, isSubmitting, setErrors } = useForm({
  validationSchema: toTypedSchema(resetPasswordSchema),
  initialValues: { password: '', password_confirmation: '' },
})
const [password, passwordAttrs] = defineField('password')
const [passwordConfirmation, passwordConfirmationAttrs] = defineField('password_confirmation')

const onSubmit = handleSubmit(async (values) => {
  general.value = ''
  try {
    await resetPassword({ token, email, ...values })
    done.value = true
    setTimeout(() => navigateTo('/login'), 1500)
  }
  catch (e: any) {
    if (e?.data?.errors) setErrors(mapServerErrors(e.data.errors))
    else general.value = e?.data?.message ?? 'Could not reset your password.'
  }
})
</script>

<template>
  <div class="mx-auto max-w-sm">
    <div class="card">
      <h1 class="mb-1">
        Reset password
      </h1>
      <p class="mb-5 text-sm text-gray-500">
        Choose a new password for <span class="font-medium text-gray-700">{{ email || 'your account' }}</span>.
      </p>

      <p
        v-if="done"
        class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
      >
        Password reset. Redirecting you to log in…
      </p>

      <p
        v-else-if="!hasLink"
        class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
      >
        This reset link is invalid or incomplete. Please
        <NuxtLink to="/forgot-password">
          request a new one
        </NuxtLink>.
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
              for="password"
              class="label"
            >New password</label>
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
            >Confirm password</label>
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
          <button
            type="submit"
            class="btn btn-primary w-full"
            :disabled="isSubmitting"
          >
            {{ isSubmitting ? 'Resetting…' : 'Reset password' }}
          </button>
        </form>
      </template>
    </div>

    <p class="mt-4 text-center text-sm text-gray-500">
      <NuxtLink to="/login">
        Back to log in
      </NuxtLink>
    </p>
  </div>
</template>
