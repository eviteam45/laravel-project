<script setup lang="ts">
interface ContractorOption {
  id: number
  company_name: string
}

const props = defineProps<{
  modelValue: number | null | undefined
  initialLabel?: string
}>()

const emit = defineEmits<{ 'update:modelValue': [number | null] }>()

const { contractorOptions } = useProjects()

const query = ref(props.initialLabel ?? '')
const results = ref<ContractorOption[]>([])
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
      results.value = (await contractorOptions(query.value || undefined)).data ?? []
    }
    catch (e: any) {
      results.value = []
      error.value = e?.data?.message ?? 'Could not load contractors.'
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

function select(c: ContractorOption) {
  emit('update:modelValue', c.id)
  query.value = c.company_name
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
      placeholder="Search contractor by company…"
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
        No contractors found.
      </li>
      <li
        v-for="c in results"
        :key="c.id"
        class="cursor-pointer px-3 py-2 text-sm text-gray-900 hover:bg-emerald-50"
        @click="select(c)"
      >
        {{ c.company_name }}
      </li>
    </ul>
  </div>
</template>
