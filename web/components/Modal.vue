<script setup lang="ts">
const props = defineProps<{
  open: boolean
  title?: string
}>()

const emit = defineEmits<{ close: [] }>()

function close() {
  emit('close')
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && props.open) close()
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <div
          class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm"
          @click="close"
        />

        <div class="relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
          <div class="mb-4 flex items-center justify-between">
            <h2 v-if="title">
              {{ title }}
            </h2>
            <button
              type="button"
              class="text-xl leading-none text-gray-400 hover:text-gray-600"
              aria-label="Close"
              @click="close"
            >
              ✕
            </button>
          </div>
          <slot />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.15s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
