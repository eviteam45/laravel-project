<script setup lang="ts">
import type { Role } from '~/types'
import { ROLES } from '~/composables/useUsers'

definePageMeta({ roles: ['admin'] })

const { list, updateRole } = useUsers()
const { user: currentUser } = useAuth()

const filters = reactive({ search: '', role: '', page: 1 })
const error = ref('')
const savingId = ref<number | null>(null)

const { data, pending, refresh } = await useAsyncData(
  'users',
  () => list({ search: filters.search || undefined, role: filters.role || undefined, page: filters.page }),
  { watch: [() => filters.role, () => filters.page] },
)

function applySearch() {
  filters.page = 1
  refresh()
}

function setPage(p: number) {
  filters.page = p
}

async function changeRole(id: number, role: Role) {
  error.value = ''
  savingId.value = id
  try {
    await updateRole(id, role)
    await refresh()
  }
  catch (e: any) {
    error.value = e?.data?.errors?.role?.[0] ?? e?.data?.message ?? 'Could not change role.'
    await refresh()
  }
  finally {
    savingId.value = null
  }
}

const meta = computed(() => data.value?.meta)

const users = computed(() =>
  (data.value?.data ?? []).filter((u: any) => u.id !== currentUser.value?.id),
)

function roleBadge(role: string) {
  return role === 'admin' ? 'badge-blue' : role === 'contractor' ? 'badge-emerald' : 'badge-amber'
}
</script>

<template>
  <section>
    <h1 class="mb-5">
      Users
    </h1>

    <p
      v-if="error"
      class="field-error mb-3"
    >
      {{ error }}
    </p>

    <form
      class="mb-4 flex flex-wrap gap-2"
      @submit.prevent="applySearch"
    >
      <input
        v-model="filters.search"
        class="input max-w-xs flex-1"
        type="search"
        placeholder="Search name or email…"
      >
      <select
        v-model="filters.role"
        class="input max-w-[11rem]"
        @change="filters.page = 1"
      >
        <option value="">
          All roles
        </option>
        <option
          v-for="r in ROLES"
          :key="r"
          :value="r"
        >
          {{ r }}
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
      v-else-if="users.length"
      class="card overflow-hidden !p-0"
    >
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
          <tr>
            <th class="px-4 py-3 font-medium">
              Name
            </th>
            <th class="px-4 py-3 font-medium">
              Email
            </th>
            <th class="px-4 py-3 font-medium">
              Role
            </th>
            <th class="px-4 py-3 font-medium">
              Last login
            </th>
            <th class="px-4 py-3 font-medium">
              Change role
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr
            v-for="u in users"
            :key="u.id"
            class="hover:bg-gray-50"
          >
            <td class="px-4 py-3 font-medium text-gray-900">
              {{ u.name }}
            </td>
            <td class="px-4 py-3 text-gray-600">
              {{ u.email }}
            </td>
            <td class="px-4 py-3">
              <span
                class="badge"
                :class="roleBadge(u.role)"
              >{{ u.role }}</span>
            </td>
            <td class="px-4 py-3 text-gray-500">
              {{ u.last_login_at ? new Date(u.last_login_at).toLocaleDateString() : '—' }}
            </td>
            <td class="px-4 py-3">
              <select
                class="input w-auto py-1 text-sm"
                :value="u.role"
                :disabled="savingId === u.id"
                @change="changeRole(u.id, ($event.target as HTMLSelectElement).value as Role)"
              >
                <option
                  v-for="r in ROLES"
                  :key="r"
                  :value="r"
                >
                  {{ r }}
                </option>
              </select>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div
      v-else
      class="card text-center text-gray-500"
    >
      No users found.
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
  </section>
</template>
