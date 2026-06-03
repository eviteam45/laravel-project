<script setup lang="ts">
const { login } = useAuth()

const form = reactive({ email: '', password: '' })
const error = ref('')
const loading = ref(false)

async function onSubmit() {
  error.value = ''
  loading.value = true
  try {
    await login({ ...form })
    await navigateTo('/')
  }
  catch (e: any) {
    error.value = e?.data?.message ?? 'Login failed.'
  }
  finally {
    loading.value = false
  }
}
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
        v-if="error"
        class="field-error mb-3"
      >
        {{ error }}
      </p>

      <form
        class="space-y-4"
        @submit.prevent="onSubmit"
      >
        <div>
          <label
            for="email"
            class="label"
          >Email</label>
          <input
            id="email"
            v-model="form.email"
            class="input"
            type="email"
            required
            autocomplete="email"
          >
        </div>
        <div>
          <label
            for="password"
            class="label"
          >Password</label>
          <input
            id="password"
            v-model="form.password"
            class="input"
            type="password"
            required
            autocomplete="current-password"
          >
        </div>
        <button
          type="submit"
          class="btn btn-primary w-full"
          :disabled="loading"
        >
          {{ loading ? 'Logging in…' : 'Log in' }}
        </button>
      </form>
    </div>

    <p class="mt-4 text-center text-sm text-gray-500">
      No account? <NuxtLink to="/register">Create one</NuxtLink>
    </p>
  </div>
</template>
