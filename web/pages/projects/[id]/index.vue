<script setup lang="ts">
import { PROJECT_TRANSITIONS, transitionsFor } from '~/composables/useProjects'

const route = useRoute()
const id = route.params.id as string
const { get, update, createApplication, remove, transition, uploadDocument, deleteDocument } = useProjects()
const { user } = useAuth()

const { data, pending, error, refresh } = await useAsyncData(`project-${id}`, () => get(id))
const project = computed(() => data.value?.data)

const busy = ref(false)
const transitionError = ref('')
const showEdit = ref(false)

const canManageProject = computed(() => ['admin', 'contractor'].includes(user.value?.role ?? ''))
const canStartApplication = computed(() => ['admin', 'contractor', 'customer'].includes(user.value?.role ?? ''))

async function onEdit(payload: Record<string, any>) {
  await update(id, payload)

  showEdit.value = false
  await refresh()
}

const docType = ref('contract')
const { fileInput, uploading, error: docError, onUpload } = useFileUpload({
  upload: async (file, type) => {
    const res = await uploadDocument(id, file, type)
    if (data.value?.data) (data.value.data.documents ??= []).push(res.data)
    return res
  },
  type: docType,
})

async function removeDoc(docId: number) {
  await deleteDocument(docId)
  if (data.value?.data?.documents) {
    data.value.data.documents = data.value.data.documents.filter(d => d.id !== docId)
  }
}

const statusActions = computed(() =>
  transitionsFor(PROJECT_TRANSITIONS, project.value?.status, user.value?.role),
)

async function doTransition(to: string) {
  transitionError.value = ''
  busy.value = true
  try {
    const res = await transition(id, to)
    if (data.value) data.value.data.status = res.data.status
  }
  catch (e) {
    transitionError.value = apiErrorMessage(e, 'Transition failed.')
  }
  finally {
    busy.value = false
  }
}

async function startApplication() {
  busy.value = true
  try {
    const res = await createApplication(id)
    await navigateTo(`/applications/${res.data.id}`)
  }
  finally {
    busy.value = false
  }
}

async function destroy() {
  if (!confirm('Delete this project?')) return
  try {
    await remove(id)
    await navigateTo('/projects')
  }
  catch (e) {
    transitionError.value = apiErrorMessage(e, 'Delete failed.')
  }
}
</script>

<template>
  <section v-if="project">
    <NuxtLink
      to="/projects"
      class="text-sm"
    >← Back to projects</NuxtLink>

    <div class="mb-5 mt-2 flex items-start justify-between gap-4">
      <div>
        <h1>{{ project.name }}</h1>
        <span class="badge badge-blue mt-1">{{ project.status.replaceAll('_', ' ') }}</span>
      </div>
      <div
        v-if="canManageProject"
        class="flex gap-2"
      >
        <button
          type="button"
          class="btn btn-ghost btn-sm"
          @click="showEdit = true"
        >
          Edit
        </button>
        <button
          class="btn btn-danger btn-sm"
          @click="destroy"
        >
          Delete
        </button>
      </div>
    </div>

    <p
      v-if="transitionError"
      class="field-error mb-3"
    >
      {{ transitionError }}
    </p>

    <div
      v-if="statusActions.length"
      class="mb-6 flex flex-wrap items-center gap-2 rounded-xl border border-blue-100 bg-blue-50 p-4"
    >
      <span class="text-sm font-medium text-blue-900">Move project to:</span>
      <button
        v-for="t in statusActions"
        :key="t"
        class="btn btn-blue btn-sm capitalize"
        :disabled="busy"
        @click="doTransition(t)"
      >
        {{ t.replaceAll('_', ' ') }}
      </button>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
      <div class="card">
        <h2 class="mb-3">
          Details
        </h2>
        <dl class="grid grid-cols-[7rem_1fr] gap-y-2 text-sm">
          <dt class="font-medium text-gray-500">
            Capacity
          </dt><dd>{{ project.capacity_kw ?? '—' }} kW</dd>
          <dt class="font-medium text-gray-500">
            Address
          </dt><dd>{{ project.address ?? '—' }}</dd>
          <dt class="font-medium text-gray-500">
            Install date
          </dt><dd>{{ project.install_date ?? '—' }}</dd>
          <dt class="font-medium text-gray-500">
            Customer
          </dt><dd>{{ project.customer?.full_name ?? project.customer_id }}</dd>
        </dl>
      </div>

      <div class="card">
        <h2 class="mb-3">
          Battery systems
        </h2>
        <ul
          v-if="project.battery_systems?.length"
          class="space-y-1 text-sm text-gray-700"
        >
          <li
            v-for="b in project.battery_systems"
            :key="b.id"
          >
            {{ b.quantity }}× {{ b.oem }} {{ b.model }} — {{ b.usable_capacity_kwh }} kWh
          </li>
        </ul>
        <p
          v-else
          class="text-sm text-gray-500"
        >
          No battery systems recorded.
        </p>
      </div>
    </div>

    <div class="card mt-6">
      <h2 class="mb-3">
        Incentive application
      </h2>
      <div
        v-if="project.application"
        class="flex items-center justify-between"
      >
        <p class="text-sm text-gray-700">
          <span class="badge badge-emerald">{{ project.application.status.replaceAll('_', ' ') }}</span>
          <span class="ml-2 text-gray-500">current step: {{ project.application.current_step ?? '—' }}</span>
        </p>
        <NuxtLink
          :to="`/applications/${project.application.id}`"
          class="btn btn-ghost btn-sm"
        >Open →</NuxtLink>
      </div>
      <div
        v-else
        class="flex items-center justify-between"
      >
        <p class="text-sm text-gray-500">
          No application yet.
        </p>
        <button
          v-if="canStartApplication"
          class="btn btn-primary btn-sm"
          :disabled="busy"
          @click="startApplication"
        >
          {{ busy ? 'Creating…' : 'Start application' }}
        </button>
      </div>
    </div>

    <div class="card mt-6">
      <h2 class="mb-3">
        Documents
      </h2>
      <div
        v-if="canManageProject"
        class="mb-3 flex flex-wrap items-center gap-2"
      >
        <select
          v-model="docType"
          class="input w-auto"
        >
          <option value="contract">
            Contract
          </option>
          <option value="permit">
            Permit
          </option>
          <option value="invoice">
            Invoice
          </option>
          <option value="photo">
            Photo
          </option>
          <option value="spec_sheet">
            Spec sheet
          </option>
        </select>
        <input
          ref="fileInput"
          type="file"
          accept=".pdf,.jpg,.jpeg,.png"
          class="text-sm"
          @change="onUpload"
        >
        <span
          v-if="uploading"
          class="text-sm text-gray-500"
        >Uploading…</span>
      </div>
      <p
        v-if="docError"
        class="field-error mb-2"
      >
        {{ docError }}
      </p>
      <ul
        v-if="project.documents?.length"
        class="divide-y divide-gray-100 text-sm"
      >
        <li
          v-for="d in project.documents"
          :key="d.id"
          class="flex items-center gap-3 py-2"
        >
          <a
            :href="d.download_url"
            target="_blank"
            rel="noopener noreferrer"
            class="font-medium"
          >{{ d.file_name }}</a>
          <span class="badge">{{ d.type }}</span>
          <button
            v-if="canManageProject"
            class="ml-auto text-xs text-red-600 hover:underline"
            @click="removeDoc(d.id)"
          >
            remove
          </button>
        </li>
      </ul>
      <p
        v-else
        class="text-sm text-gray-500"
      >
        No documents uploaded.
      </p>
    </div>

    <Modal
      :open="showEdit"
      title="Edit project"
      @close="showEdit = false"
    >
      <ProjectForm
        :initial="project"
        :on-submit="onEdit"
        submit-label="Save changes"
      />
    </Modal>
  </section>

  <AsyncState
    v-else
    :pending="pending"
    :error="error"
    error-text="Couldn't load this project."
    @retry="refresh"
  />
</template>
