<script setup lang="ts">
import { APPLICATION_STATUSES } from '~/composables/useApplications'
import { REGIONS } from '~/composables/useProjects'

const { list } = useApplications()

const filters = reactive({ search: '', status: '', region: '', sort: 'created_at', dir: 'desc', per_page: 15, page: 1 })

const { data, pending, error, refresh } = await useAsyncData(
  'applications',
  () =>
    list({
      search: filters.search || undefined,
      status: filters.status || undefined,
      region: filters.region || undefined,
      sort: filters.sort,
      dir: filters.dir,
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

function fmtDate(d: string | null) {
  return d ? new Date(d).toLocaleDateString() : '—'
}

const meta = computed(() => data.value?.meta)
</script>

<template>
  <section>
    <h1 class="mb-5">
      Applications
    </h1>

    <form
      class="mb-4 flex flex-wrap gap-2"
      @submit.prevent="applySearch"
    >
      <input
        v-model="filters.search"
        class="input max-w-xs flex-1"
        type="search"
        placeholder="Search project or contractor…"
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
          v-for="s in APPLICATION_STATUSES"
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

    <AsyncState
      :pending="pending"
      :error="error"
      error-text="Couldn't load applications."
      @retry="refresh"
    >
      <div
        v-if="data?.data?.length"
        class="card overflow-hidden !p-0"
      >
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
            <tr>
              <th class="px-4 py-3 font-medium">
                Project
              </th>
              <th
                class="cursor-pointer select-none px-4 py-3 font-medium hover:text-gray-700"
                @click="sortBy('status')"
              >
                Status{{ sortIcon('status') }}
              </th>
              <th
                class="cursor-pointer select-none px-4 py-3 font-medium hover:text-gray-700"
                @click="sortBy('submitted_at')"
              >
                Submitted{{ sortIcon('submitted_at') }}
              </th>
              <th
                class="cursor-pointer select-none px-4 py-3 font-medium hover:text-gray-700"
                @click="sortBy('created_at')"
              >
                Created{{ sortIcon('created_at') }}
              </th>
              <th class="px-4 py-3" />
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr
              v-for="a in data.data"
              :key="a.id"
              class="hover:bg-gray-50"
            >
              <td class="px-4 py-3 font-medium text-gray-900">
                {{ a.project?.name ?? `Application #${a.id}` }}
                <span
                  v-if="a.project?.contractor?.company_name"
                  class="block text-xs font-normal text-gray-400"
                >
                  {{ a.project.contractor.company_name }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span class="badge badge-emerald">{{ a.status.replace('_', ' ') }}</span>
              </td>
              <td class="px-4 py-3 text-gray-600">
                {{ fmtDate(a.submitted_at) }}
              </td>
              <td class="px-4 py-3 text-gray-600">
                {{ fmtDate(a.created_at) }}
              </td>
              <td class="px-4 py-3 text-right">
                <NuxtLink
                  :to="`/applications/${a.id}`"
                  class="font-medium"
                >Open →</NuxtLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        v-else
        class="card text-center text-gray-500"
      >
        No applications found.
      </div>
    </AsyncState>

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
  </section>
</template>
