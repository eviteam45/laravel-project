<script setup lang="ts">
const route = useRoute()
const id = route.params.id as string
const { get, update } = useProjects()

const { data } = await useAsyncData(`project-${id}-edit`, () => get(id))
const project = computed(() => data.value?.data)

async function submit(payload: Record<string, any>) {
  await update(id, payload)
  await navigateTo(`/projects/${id}`)
}
</script>

<template>
  <section v-if="project">
    <NuxtLink
      :to="`/projects/${id}`"
      class="text-sm"
    >← Back to project</NuxtLink>
    <h1 class="mb-5 mt-2">
      Edit project
    </h1>
    <ProjectForm
      :initial="project"
      :on-submit="submit"
      submit-label="Save changes"
    />
  </section>
</template>
