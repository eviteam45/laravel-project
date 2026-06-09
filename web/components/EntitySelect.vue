<script setup lang="ts">
import type { ApiError } from '~/types'

interface Option { id: number, [key: string]: unknown }

const props = defineProps<{
  modelValue: number | null | undefined
  search: (q?: string) => Promise<{ data?: Option[] }>
  labelKey: string
  placeholder?: string
  noun?: string
  initialLabel?: string
}>()

const emit = defineEmits<{ 'update:modelValue': [number | null] }>()

const noun = computed(() => props.noun ?? 'results')
const query = ref(props.initialLabel ?? '')
const results = ref<Option[]>([])
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
      results.value = (await props.search(query.value || undefined)).data ?? []
    }
    catch (e) {
      results.value = []
      const err = e as ApiError
      const status = err?.status ?? err?.statusCode ?? err?.response?.status
      error.value
        = status === 403
          ? `You don’t have permission to list ${noun.value} — check which role you’re logged in as.`
          : status === 401
            ? 'Your session expired — please log in again.'
            : (err?.data?.message ?? `Could not load ${noun.value} (is the API running on :8000?).`)
    }
    finally {
      loading.value = false
    }
  }, 250)
}

function onInput() {
  emit('update:modelValue', null)
  runSearch()
}

function onFocus() {
  open.value = true
  if (!results.value.length) runSearch()
}

function select(item: Option) {
  emit('update:modelValue', item.id)
  query.value = String(item[props.labelKey] ?? '')
  open.value = false
}

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
      :placeholder="placeholder"
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
        No {{ noun }} found.
      </li>
      <li
        v-for="item in results"
        :key="item.id"
        class="cursor-pointer px-3 py-2 text-sm hover:bg-emerald-50"
        @click="select(item)"
      >
        <span class="font-medium text-gray-900">{{ item[labelKey] }}</span>
      </li>
    </ul>
  </div>
</template>
