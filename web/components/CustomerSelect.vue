<script setup lang="ts">
interface CustomerOption {
  id: number
  full_name: string
  account_email?: string
}

const props = defineProps<{
  modelValue: number | null
  initialLabel?: string
}>()

const emit = defineEmits<{ 'update:modelValue': [number | null] }>()

const { customerOptions } = useProjects()

const query = ref(props.initialLabel ?? '')
const results = ref<CustomerOption[]>([])
const open = ref(false)
const loading = ref(false)
const error = ref('')
let timer: ReturnType<typeof setTimeout> | undefined

function runSearch() {
  open.value = true
  loading.value = true
  error.value = ''
  clearTimeout(timer)
  timer = setTimeout(async () => {
    try {
      results.value = (await customerOptions(query.value || undefined)).data ?? []
    }
    catch (e: any) {
      results.value = []
      const status = e?.status ?? e?.statusCode ?? e?.response?.status
      error.value
        = status === 403
          ? 'Only contractors and admins can list customers — check which role you’re logged in as.'
          : status === 401
            ? 'Your session expired — please log in again.'
            : (e?.data?.message ?? 'Could not load customers (is the API running on :8000?).')
    }
    finally {
      loading.value = false
    }
  }, 250)
}

function onInput() {
  // Typing invalidates the current selection until a new one is picked.
  emit('update:modelValue', null)
  runSearch()
}

function onFocus() {
  open.value = true
  if (!results.value.length) runSearch()
}

function select(c: CustomerOption) {
  emit('update:modelValue', c.id)
  query.value = c.full_name
  open.value = false
}

// Close when clicking outside.
const root = ref<HTMLElement | null>(null)
function onDocClick(e: MouseEvent) {
  if (root.value && !root.value.contains(e.target as Node)) open.value = false
}
onMounted(() => document.addEventListener('click', onDocClick))
onBeforeUnmount(() => document.removeEventListener('click', onDocClick))
</script>

<template>
  <div
    ref="root"
    class="relative"
  >
    <input
      v-model="query"
      class="input"
      type="text"
      placeholder="Search customer by name…"
      autocomplete="off"
      @focus="onFocus"
      @input="onInput"
    >

    <ul
      v-if="open"
      class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
    >
      <li
        v-if="loading"
        class="px-3 py-2 text-sm text-gray-400"
      >
        Searching…
      </li>
      <li
        v-else-if="error"
        class="px-3 py-2 text-sm text-red-600"
      >
        {{ error }}
      </li>
      <li
        v-else-if="!results.length"
        class="px-3 py-2 text-sm text-gray-400"
      >
        No customers found.
      </li>
      <li
        v-for="c in results"
        :key="c.id"
        class="cursor-pointer px-3 py-2 text-sm hover:bg-emerald-50"
        @click="select(c)"
      >
        <span class="font-medium text-gray-900">{{ c.full_name }}</span>
        <span
          v-if="c.account_email"
          class="text-gray-400"
        > — {{ c.account_email }}</span>
      </li>
    </ul>
  </div>
</template>
