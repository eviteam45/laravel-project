<script setup lang="ts">
defineProps<{
  pending?: boolean
  error?: unknown
  loadingText?: string
  errorText?: string
}>()

defineEmits<{ (e: 'retry'): void }>()
</script>

<template>
  <div
    v-if="error"
    class="card flex flex-col items-center gap-3 text-center"
  >
    <p class="text-sm text-red-600">
      {{ errorText || 'Something went wrong while loading this content.' }}
    </p>
    <button
      type="button"
      class="btn btn-primary btn-sm"
      @click="$emit('retry')"
    >
      Try again
    </button>
  </div>

  <p
    v-else-if="pending"
    class="flex items-center gap-2 text-gray-500"
  >
    <span class="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-gray-600" />
    {{ loadingText || 'Loading…' }}
  </p>

  <slot v-else />
</template>
