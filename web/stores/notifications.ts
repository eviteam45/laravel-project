import { defineStore } from 'pinia'
import type { AppNotification } from '~/types'

export const useNotificationsStore = defineStore('notifications', () => {
  const { notifications, markAllRead, markRead } = useDashboard()

  const items = ref<AppNotification[]>([])

  const unreadCount = computed(() => items.value.filter(n => !n.is_read).length)

  async function load(): Promise<void> {
    const res = await notifications().catch(() => null)
    if (res) items.value = res.data ?? []
  }

  async function markAll(): Promise<void> {
    await markAllRead()
    await load()
  }

  async function markOne(id: number): Promise<void> {
    await markRead(id)
    await load()
  }

  return { items, unreadCount, load, markAll, markOne }
})
