<script setup lang="ts">
import type { AuditLog, Paginated } from '~/types'

definePageMeta({ roles: ['admin'] })

const { user } = useAuth()
const isAdmin = computed(() => user.value?.role === 'admin')

const { list } = useAudit()

const filters = reactive({ action: '', subject_type: '', page: 1 })

const emptyPage: Paginated<AuditLog> = {
  data: [],
  links: {},
  meta: { current_page: 1, last_page: 1, per_page: 0, total: 0 },
}

const { data, pending, error, refresh } = await useAsyncData(
  'audit-logs',
  () => {
    if (!isAdmin.value) return Promise.resolve(emptyPage)
    return list({
      action: filters.action || undefined,
      subject_type: filters.subject_type || undefined,
      page: filters.page,
    })
  },
  { watch: [() => filters.action, () => filters.subject_type, () => filters.page] },
)

const meta = computed(() => data.value?.meta)

function setPage(p: number) {
  filters.page = p
}
</script>

<template>
  <section v-if="isAdmin">
    <h1 class="mb-5">
      Audit log
    </h1>

    <div class="mb-4 flex flex-wrap gap-2">
      <select
        v-model="filters.action"
        class="input max-w-[12rem]"
        @change="filters.page = 1"
      >
        <option value="">
          All actions
        </option>
        <option value="status_changed">
          status changed
        </option>
        <option value="created">
          created
        </option>
        <option value="updated">
          updated
        </option>
        <option value="deleted">
          deleted
        </option>
      </select>
      <select
        v-model="filters.subject_type"
        class="input max-w-[14rem]"
        @change="filters.page = 1"
      >
        <option value="">
          All subjects
        </option>
        <option value="Project">
          Project
        </option>
        <option value="IncentiveApplication">
          Application
        </option>
      </select>
    </div>

    <AsyncState
      :pending="pending"
      :error="error"
      error-text="Couldn't load the audit log."
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
                When
              </th>
              <th class="px-4 py-3 font-medium">
                User
              </th>
              <th class="px-4 py-3 font-medium">
                Action
              </th>
              <th class="px-4 py-3 font-medium">
                Subject
              </th>
              <th class="px-4 py-3 font-medium">
                Changes
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr
              v-for="l in data.data"
              :key="l.id"
              class="hover:bg-gray-50"
            >
              <td class="whitespace-nowrap px-4 py-3 text-gray-500">
                {{ new Date(l.created_at).toLocaleString() }}
              </td>
              <td class="px-4 py-3 text-gray-700">
                {{ l.user?.name ?? '—' }}
              </td>
              <td class="px-4 py-3">
                <span class="badge">{{ l.action.replace('_', ' ') }}</span>
              </td>
              <td class="px-4 py-3 text-gray-600">
                {{ l.subject_type }} #{{ l.subject_id }}
              </td>
              <td class="max-w-xs truncate px-4 py-3 text-xs text-gray-500">
                {{ JSON.stringify(l.changes) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        v-else
        class="card text-center text-gray-500"
      >
        No audit entries.
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

  <section v-else>
    <div class="card text-center text-gray-500">
      Admins only.
    </div>
  </section>
</template>
