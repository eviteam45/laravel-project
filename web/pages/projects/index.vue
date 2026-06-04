<script setup lang="ts">
import { PROJECT_STATUSES, REGIONS } from '~/composables/useProjects'

const { list, create } = useProjects()
const { user } = useAuth()

const canCreateProject = computed(() => ['contractor', 'admin'].includes(user.value?.role ?? ''))

const filters = reactive({ search: '', status: '', region: '', sort: 'created_at', dir: 'desc', per_page: 15, page: 1 })
const showCreate = ref(false)

async function onCreate(payload: Record<string, any>) {
  await create(payload)

  showCreate.value = false
  filters.page = 1
  await refresh()
}

const { data, pending, refresh } = await useAsyncData(
  'projects',
  () =>
    list({
      search: filters.search || undefined,
      status: filters.status || undefined,
      region: filters.region || undefined,
      sort: filters.sort,
      dir: filters.dir as 'asc' | 'desc',
      per_page: filters.per_page,
      page: filters.page,
    }),
  { watch: [() => filters.status, () => filters.region, () => filters.sort, () => filters.dir, () => filters.per_page, () => filters.page] },
)

function applySearch() {
  filters.page = 1
  refresh()
}

function onFilterChange() {
  filters.page = 1
}

function setPage(p: number) {
  filters.page = p
}

function sortBy(col: string) {
  if (filters.sort === col) {
    filters.dir = filters.dir === 'asc' ? 'desc' : 'asc'
  }
  else {
    filters.sort = col
    filters.dir = 'asc'
  }
  filters.page = 1
}

function sortIcon(col: string) {
  if (filters.sort !== col) return ''
  return filters.dir === 'asc' ? ' ↑' : ' ↓'
}

const meta = computed(() => data.value?.meta)
</script>

<template>
  <section>
    <div class="mb-5 flex items-center justify-between">
      <h1>Projects</h1>
      <button
        v-if="canCreateProject"
        type="button"
        class="btn btn-primary btn-sm"
        @click="showCreate = true"
      >
        + New project
      </button>
    </div>

    <form
      class="mb-4 flex flex-wrap gap-2"
      @submit.prevent="applySearch"
    >
      <input
        v-model="filters.search"
        class="input max-w-xs flex-1"
        type="search"
        placeholder="Search name or address…"
      >
      <select
        v-model="filters.status"
        class="input max-w-[11rem]"
        @change="onFilterChange"
      >
        <option value="">
          All statuses
        </option>
        <option
          v-for="s in PROJECT_STATUSES"
          :key="s"
          :value="s"
        >
          {{ s.replace('_', ' ') }}
        </option>
      </select>
      <select
        v-model="filters.region"
        class="input max-w-[10rem]"
        @change="onFilterChange"
      >
        <option value="">
          All regions
        </option>
        <option
          v-for="r in REGIONS"
          :key="r"
          :value="r"
        >
          {{ r }}
        </option>
      </select>
      <select
        v-model.number="filters.per_page"
        class="input max-w-[8rem]"
        @change="onFilterChange"
      >
        <option
          v-for="n in [10, 15, 25, 50]"
          :key="n"
          :value="n"
        >
          {{ n }} / page
        </option>
      </select>
      <button
        type="submit"
        class="btn btn-ghost"
      >
        Search
      </button>
    </form>

    <p
      v-if="pending"
      class="text-gray-500"
    >
      Loading…
    </p>

    <div
      v-else-if="data?.data?.length"
      class="card overflow-hidden !p-0"
    >
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
          <tr>
            <th
              class="cursor-pointer select-none px-4 py-3 font-medium hover:text-gray-700"
              @click="sortBy('name')"
            >
              Name{{ sortIcon('name') }}
            </th>
            <th
              class="cursor-pointer select-none px-4 py-3 font-medium hover:text-gray-700"
              @click="sortBy('status')"
            >
              Status{{ sortIcon('status') }}
            </th>
            <th
              class="cursor-pointer select-none px-4 py-3 font-medium hover:text-gray-700"
              @click="sortBy('capacity_kw')"
            >
              Capacity{{ sortIcon('capacity_kw') }}
            </th>
            <th class="px-4 py-3 font-medium">
              Batteries
            </th>
            <th class="px-4 py-3" />
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr
            v-for="p in data.data"
            :key="p.id"
            class="hover:bg-gray-50"
          >
            <td class="px-4 py-3 font-medium text-gray-900">
              {{ p.name }}
            </td>
            <td class="px-4 py-3">
              <span class="badge badge-blue">{{ p.status.replace('_', ' ') }}</span>
            </td>
            <td class="px-4 py-3 text-gray-600">
              {{ p.capacity_kw ?? '—' }} kW
            </td>
            <td class="px-4 py-3 text-gray-600">
              {{ p.battery_systems_count ?? 0 }}
            </td>
            <td class="px-4 py-3 text-right">
              <NuxtLink
                :to="`/projects/${p.id}`"
                class="font-medium"
              >View →</NuxtLink>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div
      v-else
      class="card text-center text-gray-500"
    >
      No projects found.
    </div>

    <div
      v-if="meta && meta.last_page > 1"
      class="mt-4 flex items-center justify-center gap-3 text-sm"
    >
      <button
        class="btn btn-ghost btn-sm"
        :disabled="meta.current_page <= 1"
        @click="setPage(meta.current_page - 1)"
      >
        ‹ Prev
      </button>
      <span class="text-gray-500">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <button
        class="btn btn-ghost btn-sm"
        :disabled="meta.current_page >= meta.last_page"
        @click="setPage(meta.current_page + 1)"
      >
        Next ›
      </button>
    </div>

    <Modal
      :open="showCreate"
      title="New project"
      @close="showCreate = false"
    >
      <ProjectForm
        :on-submit="onCreate"
        submit-label="Create project"
      />
    </Modal>
  </section>
</template>
