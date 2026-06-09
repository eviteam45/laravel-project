<script setup lang="ts">
definePageMeta({ roles: ['admin'] })

const { list } = useAudit()

const filters = reactive<{ action: string, subject_type: string, cursor: string | null }>({
  action: '',
  subject_type: '',
  cursor: null,
})

const { data, pending, error, refresh } = await useAsyncData(
  'audit-logs',
  () => list({
    action: filters.action || undefined,
    subject_type: filters.subject_type || undefined,
    cursor: filters.cursor || undefined,
  }),
  { watch: [() => filters.action, () => filters.subject_type, () => filters.cursor] },
)

const meta = computed(() => data.value?.meta)

function go(cursor: string | null) {
  filters.cursor = cursor
}
</script>

<template>
  <section>
    <h1 class="mb-5">
      Audit log
    </h1>

    <div class="mb-4 flex flex-wrap gap-2">
      <select
        v-model="filters.action"
        class="input max-w-[12rem]"
        @change="filters.cursor = null"
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
        @change="filters.cursor = null"
      >
        <option value="">
          All subjects
        </option>
        <option value="project">
          Project
        </option>
        <option value="application">
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
                <span class="badge">{{ l.action.replaceAll('_', ' ') }}</span>
              </td>
              <td class="px-4 py-3 capitalize text-gray-600">
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
      v-if="meta && (meta.prev_cursor || meta.next_cursor)"
      class="mt-4 flex items-center justify-center gap-3 text-sm"
    >
      <button
        class="btn btn-ghost btn-sm"
        :disabled="!meta.prev_cursor"
        @click="go(meta.prev_cursor)"
      >
        ‹ Prev
      </button>
      <button
        class="btn btn-ghost btn-sm"
        :disabled="!meta.next_cursor"
        @click="go(meta.next_cursor)"
      >
        Next ›
      </button>
    </div>
  </section>
</template>
