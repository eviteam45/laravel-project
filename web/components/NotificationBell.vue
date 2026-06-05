<script setup lang="ts">
import { storeToRefs } from 'pinia'

const store = useNotificationsStore()
const { items, unreadCount: unread } = storeToRefs(store)

const open = ref(false)

function prettyType(t: string) {
  return t.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

const root = ref<HTMLElement | null>(null)
function onDocClick(e: MouseEvent) {
  if (root.value && !root.value.contains(e.target as Node)) open.value = false
}

let timer: ReturnType<typeof setInterval> | undefined

onMounted(() => {
  store.load()
  timer = setInterval(() => store.load(), 30000)
  document.addEventListener('click', onDocClick)
})
onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
  document.removeEventListener('click', onDocClick)
})
</script>

<template>
  <div
    ref="root"
    class="relative"
  >
    <button
      type="button"
      class="relative rounded-md p-1.5 text-gray-600 hover:bg-gray-100"
      aria-label="Notifications"
      @click="open = !open"
    >
      <svg
        class="h-5 w-5"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        stroke-width="1.8"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M14.857 17.082a23.8 23.8 0 0 0 5.454-1.31A8.97 8.97 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.97 8.97 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m6.714 0a24 24 0 0 1-6.714 0m6.714 0a3 3 0 1 1-6.714 0"
        />
      </svg>
      <span
        v-if="unread"
        class="absolute -right-0.5 -top-0.5 grid h-4 min-w-[1rem] place-items-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white"
      >{{ unread > 9 ? '9+' : unread }}</span>
    </button>

    <div
      v-if="open"
      class="absolute right-0 z-30 mt-2 w-80 rounded-xl border border-gray-200 bg-white shadow-lg"
    >
      <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2.5">
        <span class="text-sm font-semibold text-gray-900">Notifications</span>
        <button
          v-if="unread"
          type="button"
          class="text-xs text-blue-600 hover:underline"
          @click="store.markAll()"
        >
          Mark all read
        </button>
      </div>
      <ul
        v-if="items.length"
        class="max-h-80 divide-y divide-gray-50 overflow-auto"
      >
        <li
          v-for="n in items"
          :key="n.id"
          class="px-4 py-2.5 text-sm"
          :class="n.is_read ? 'text-gray-500' : 'bg-emerald-50/50 font-medium text-gray-900'"
        >
          <div class="flex items-center justify-between gap-2">
            <span>{{ prettyType(n.type) }}</span>
            <button
              v-if="!n.is_read"
              type="button"
              class="shrink-0 text-xs text-gray-400 hover:text-gray-600"
              @click="store.markOne(n.id)"
            >
              mark read
            </button>
          </div>
          <span class="text-xs text-gray-400">{{ new Date(n.created_at).toLocaleDateString() }}</span>
        </li>
      </ul>
      <p
        v-else
        class="px-4 py-6 text-center text-sm text-gray-400"
      >
        No notifications.
      </p>
    </div>
  </div>
</template>
