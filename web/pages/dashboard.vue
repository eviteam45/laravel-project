<script setup lang="ts">
import { formatMoney } from '~/composables/useDashboard'

const { user, fetchUser } = useAuth()
const { stats, notifications, markAllRead } = useDashboard()

if (!user.value) await fetchUser()

const { data: statsData } = await useAsyncData('dashboard-stats', () => stats())
const { data: notifData, refresh: refreshNotifs } = await useAsyncData('dashboard-notifs', () => notifications())

const s = computed(() => statsData.value)
const notifs = computed(() => notifData.value?.data ?? [])

async function clearNotifs() {
  await markAllRead()
  await refreshNotifs()
}

function prettyType(type: string) {
  return type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}
</script>

<template>
  <section v-if="s">
    <h1>Dashboard</h1>
    <p class="mb-6 text-sm text-gray-500">
      Welcome back, <span class="font-medium text-gray-700">{{ user?.name }}</span>
      <span class="badge ml-1">{{ user?.role }}</span>
    </p>

    <!-- Stat cards -->
    <div class="grid gap-4 sm:grid-cols-3">
      <div class="card">
        <p class="text-3xl font-bold">
          {{ s.projects.total }}
        </p>
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
          Projects
        </p>
        <ul class="mt-3 space-y-0.5 text-sm text-gray-600">
          <li
            v-for="(n, k) in s.projects.by_status"
            :key="k"
          >
            <span class="font-semibold text-gray-900">{{ n }}</span> {{ String(k).replace('_', ' ') }}
          </li>
        </ul>
      </div>

      <div class="card">
        <p class="text-3xl font-bold">
          {{ s.applications.total }}
        </p>
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
          Applications
        </p>
        <ul class="mt-3 space-y-0.5 text-sm text-gray-600">
          <li
            v-for="(n, k) in s.applications.by_status"
            :key="k"
          >
            <span class="font-semibold text-gray-900">{{ n }}</span> {{ String(k).replace('_', ' ') }}
          </li>
        </ul>
      </div>

      <div class="card">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
          Incentives
        </p>
        <ul class="mt-3 space-y-1.5 text-sm">
          <li><span class="font-semibold text-gray-900">{{ formatMoney(s.incentives.reserved_total) }}</span> reserved</li>
          <li><span class="font-semibold text-emerald-700">{{ formatMoney(s.incentives.paid_total) }}</span> paid</li>
          <li><span class="font-semibold text-amber-700">{{ formatMoney(s.incentives.scheduled_total) }}</span> scheduled</li>
        </ul>
      </div>
    </div>

    <div class="mt-6 grid gap-6 md:grid-cols-2">
      <!-- Recent applications -->
      <div class="card">
        <h2 class="mb-3">
          Recent applications
        </h2>
        <ul
          v-if="s.recent_applications?.length"
          class="divide-y divide-gray-100 text-sm"
        >
          <li
            v-for="a in s.recent_applications"
            :key="a.id"
            class="flex items-center justify-between py-2"
          >
            <NuxtLink
              :to="`/applications/${a.id}`"
              class="font-medium"
            >{{ a.project?.name ?? `Application #${a.id}` }}</NuxtLink>
            <span class="badge badge-emerald">{{ a.status.replace('_', ' ') }}</span>
          </li>
        </ul>
        <p
          v-else
          class="text-sm text-gray-500"
        >
          No applications yet.
        </p>
      </div>

      <!-- Notifications -->
      <div class="card">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="flex items-center gap-2">
            Notifications
            <span
              v-if="s.notifications.unread_count"
              class="badge badge-emerald"
            >{{ s.notifications.unread_count }}</span>
          </h2>
          <button
            v-if="notifs.length"
            class="text-sm text-blue-600 hover:underline"
            @click="clearNotifs"
          >
            Mark all read
          </button>
        </div>
        <ul
          v-if="notifs.length"
          class="divide-y divide-gray-100 text-sm"
        >
          <li
            v-for="n in notifs"
            :key="n.id"
            class="flex items-center justify-between py-2"
            :class="{ 'font-semibold': !n.is_read }"
          >
            <span>{{ prettyType(n.type) }}</span>
            <span class="text-xs text-gray-400">{{ new Date(n.created_at).toLocaleDateString() }}</span>
          </li>
        </ul>
        <p
          v-else
          class="text-sm text-gray-500"
        >
          You're all caught up.
        </p>
      </div>
    </div>
  </section>

  <section v-else>
    <p class="text-gray-500">
      Loading…
    </p>
  </section>
</template>
