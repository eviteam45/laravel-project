<script setup lang="ts">
const { isLoggedIn, user, logout, fetchUser } = useAuth()

await fetchUser()

async function onLogout() {
  await logout()
  await navigateTo('/login')
}
</script>

<template>
  <div class="min-h-screen">
    <header class="border-b border-gray-200 bg-white">
      <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
        <NuxtLink
          to="/"
          class="flex items-center gap-2 font-bold text-gray-900 hover:text-gray-900"
        >
          <span class="grid h-7 w-7 place-items-center rounded-lg bg-emerald-500 text-sm text-emerald-950">☀</span>
          SolarIncentives
        </NuxtLink>

        <nav class="flex items-center gap-1 text-sm">
          <template v-if="isLoggedIn">
            <NuxtLink
              to="/dashboard"
              class="rounded-md px-3 py-1.5 text-gray-600 hover:bg-gray-100 hover:text-gray-900"
            >Dashboard</NuxtLink>
            <NuxtLink
              to="/projects"
              class="rounded-md px-3 py-1.5 text-gray-600 hover:bg-gray-100 hover:text-gray-900"
            >Projects</NuxtLink>
            <NuxtLink
              to="/applications"
              class="rounded-md px-3 py-1.5 text-gray-600 hover:bg-gray-100 hover:text-gray-900"
            >Applications</NuxtLink>
            <NuxtLink
              v-if="user?.role === 'admin'"
              to="/users"
              class="rounded-md px-3 py-1.5 text-gray-600 hover:bg-gray-100 hover:text-gray-900"
            >Users</NuxtLink>
            <NuxtLink
              v-if="user?.role === 'admin'"
              to="/audit-logs"
              class="rounded-md px-3 py-1.5 text-gray-600 hover:bg-gray-100 hover:text-gray-900"
            >Audit</NuxtLink>
            <NotificationBell class="ml-1" />
            <span class="ml-1 hidden text-gray-400 sm:inline">{{ user?.name }}</span>
            <button
              type="button"
              class="btn btn-ghost btn-sm ml-2"
              @click="onLogout"
            >
              Log out
            </button>
          </template>
          <template v-else>
            <NuxtLink
              to="/login"
              class="rounded-md px-3 py-1.5 text-gray-600 hover:bg-gray-100 hover:text-gray-900"
            >Login</NuxtLink>
            <NuxtLink
              to="/register"
              class="btn btn-primary btn-sm"
            >Register</NuxtLink>
          </template>
        </nav>
      </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8">
      <NuxtPage />
    </main>
  </div>
</template>
