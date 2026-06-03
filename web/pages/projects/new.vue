<script setup lang="ts">
const { create } = useProjects()

async function submit(payload: Record<string, any>) {
  const res = await create(payload)
  await navigateTo(`/projects/${res.data.id}`)
}
</script>

<template>
  <section>
    <NuxtLink
      to="/projects"
      class="text-sm"
    >← Back to projects</NuxtLink>
    <h1 class="mb-5 mt-2">
      New project
    </h1>
    <div class="card max-w-lg">
      <ProjectForm
        :on-submit="submit"
        submit-label="Create project"
      />
    </div>
  </section>
</template>
