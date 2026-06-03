<script setup lang="ts">
const { register } = useAuth()

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'customer' as 'contractor' | 'customer',
  phone: '',
  company_name: '',
  address: '',
})
const errors = ref<Record<string, string[]>>({})
const error = ref('')
const loading = ref(false)

async function onSubmit() {
  error.value = ''
  errors.value = {}
  loading.value = true
  try {
    await register({ ...form })
    await navigateTo('/')
  }
  catch (e: any) {
    if (e?.data?.errors) errors.value = e.data.errors
    else error.value = e?.data?.message ?? 'Registration failed.'
  }
  finally {
    loading.value = false
  }
}
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
          <span class="label">I am a…</span>
          <div class="grid grid-cols-2 gap-2">
            <label
              class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm"
              :class="form.role === 'customer' ? 'border-emerald-500 bg-emerald-50' : 'border-gray-300'"
            >
              <input
                v-model="form.role"
                type="radio"
                value="customer"
                class="text-emerald-600"
              > Customer
            </label>
            <label
              class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm"
              :class="form.role === 'contractor' ? 'border-emerald-500 bg-emerald-50' : 'border-gray-300'"
            >
              <input
                v-model="form.role"
                type="radio"
                value="contractor"
                class="text-emerald-600"
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
            v-model="form.name"
            class="input"
            type="text"
            required
            autocomplete="name"
          >
          <p
            v-if="errors.name"
            class="field-error"
          >
            {{ errors.name[0] }}
          </p>
        </div>

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
          <p
            v-if="errors.email"
            class="field-error"
          >
            {{ errors.email[0] }}
          </p>
        </div>

        <div v-if="form.role === 'contractor'">
          <label
            for="company_name"
            class="label"
          >Company name</label>
          <input
            id="company_name"
            v-model="form.company_name"
            class="input"
            type="text"
          >
          <p
            v-if="errors.company_name"
            class="field-error"
          >
            {{ errors.company_name[0] }}
          </p>
        </div>

        <div v-if="form.role === 'customer'">
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

        <div>
          <label
            for="phone"
            class="label"
          >Phone</label>
          <input
            id="phone"
            v-model="form.phone"
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
              v-model="form.password"
              class="input"
              type="password"
              required
              autocomplete="new-password"
            >
            <p
              v-if="errors.password"
              class="field-error"
            >
              {{ errors.password[0] }}
            </p>
          </div>
          <div>
            <label
              for="password_confirmation"
              class="label"
            >Confirm</label>
            <input
              id="password_confirmation"
              v-model="form.password_confirmation"
              class="input"
              type="password"
              required
              autocomplete="new-password"
            >
          </div>
        </div>

        <button
          type="submit"
          class="btn btn-primary w-full"
          :disabled="loading"
        >
          {{ loading ? 'Creating…' : 'Register' }}
        </button>
      </form>
    </div>

    <p class="mt-4 text-center text-sm text-gray-500">
      Already have an account? <NuxtLink to="/login">Log in</NuxtLink>
    </p>
  </div>
</template>
