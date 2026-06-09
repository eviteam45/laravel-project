import { defineStore } from 'pinia'
import type { AppNotification } from '~/types'

export const useNotificationsStore = defineStore('notifications', () => {
  const { notifications, unreadCount: fetchUnreadCount, markAllRead, markRead } = useDashboard()

  const items = ref<AppNotification[]>([])
  const unreadCount = ref(0)

  async function loadCount(): Promise<void> {
    const res = await fetchUnreadCount().catch(() => null)
    if (res) unreadCount.value = res.unread_count
  }

  async function load(): Promise<void> {
    const res = await notifications().catch(() => null)
    if (res) {
      items.value = res.data ?? []
      unreadCount.value = res.unread_count ?? unreadCount.value
    }
  }

  async function markAll(): Promise<void> {
    await markAllRead()
    await load()
  }

  async function markOne(id: number): Promise<void> {
    await markRead(id)
    await load()
  }

  return { items, unreadCount, load, loadCount, markAll, markOne }
})
