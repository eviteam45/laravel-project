<script setup lang="ts">
import { fieldErrors, validateStep, APPLICATION_TRANSITIONS } from '~/composables/useApplications'
import { transitionsFor } from '~/composables/useProjects'

const route = useRoute()
const id = route.params.id as string
const { get, saveStep, uploadDocument, deleteDocument, submit, transition } = useApplications()
const { user } = useAuth()

const { data, pending, error, refresh } = await useAsyncData(`application-${id}`, () => get(id))
const app = computed(() => data.value?.data)

const STEPS = [
  { key: 'eligibility', label: 'Eligibility' },
  { key: 'system', label: 'System' },
  { key: 'documents', label: 'Documents' },
  { key: 'banking', label: 'Banking' },
  { key: 'review', label: 'Review & submit' },
]

const forms = reactive<Record<string, any>>({
  eligibility: { owns_property: false, utility_provider: '', average_monthly_bill: null },
  system: { battery_oem: '', battery_model: '', quantity: 1, usable_capacity_kwh: null },
  banking: { account_holder_name: '', bank_name: '', routing_number: '', account_number: '', account_type: 'checking' },
  review: { accepted_terms: false },
})

function hydrate() {
  for (const step of app.value?.steps ?? []) {
    if (forms[step.step_key]) Object.assign(forms[step.step_key], step.fields ?? {})
  }
}
hydrate()

const resumeStep = app.value?.current_step ?? 'eligibility'
const active = ref<string>(STEPS.some(s => s.key === resumeStep) ? resumeStep : 'eligibility')
const errors = ref<Record<string, string>>({})
const general = ref('')
const saving = ref(false)

const locked = computed(() => !['started', 'in_progress'].includes(app.value?.status ?? ''))

function isComplete(key: string): boolean {
  return !!app.value?.steps?.find((s: any) => s.step_key === key)?.is_complete
}

function indexOf(key: string) {
  return STEPS.findIndex(s => s.key === key)
}

function clientErrors(key: string): Record<string, string> {
  if (key === 'documents') {
    return app.value?.documents?.length ? {} : { documents: 'Upload at least one document.' }
  }
  return validateStep(key, forms[key] ?? {})
}

function canVisit(key: string): boolean {
  if (locked.value) return true
  const idx = indexOf(key)
  if (idx <= 0) return true
  return STEPS.slice(0, idx).every(s => isComplete(s.key))
}

function goTo(key: string) {
  if (!canVisit(key)) return
  errors.value = {}
  general.value = ''
  active.value = key
}

async function persist(key: string, complete: boolean) {
  errors.value = {}
  general.value = ''
  if (complete) {
    const ce = clientErrors(key)
    if (Object.keys(ce).length) {
      errors.value = ce
      return
    }
  }
  saving.value = true
  try {
    await saveStep(id, key, forms[key] ?? {}, complete)
    await refresh()
    if (complete) {
      const next = STEPS[indexOf(key) + 1]
      if (next) active.value = next.key
    }
  }
  catch (e: any) {
    errors.value = fieldErrors(e)
    if (!Object.keys(errors.value).length) general.value = e?.data?.message ?? 'Could not save step.'
  }
  finally {
    saving.value = false
  }
}

const fileInput = ref<HTMLInputElement | null>(null)
const docType = ref('proof')
const uploading = ref(false)

async function onUpload(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return

  const uploadError = validateUpload(file)
  if (uploadError) {
    general.value = uploadError
    if (fileInput.value) fileInput.value.value = ''
    return
  }

  uploading.value = true
  general.value = ''
  try {
    await uploadDocument(id, file, docType.value)
    await refresh()
  }
  catch (e: any) {
    general.value = e?.data?.message ?? 'Upload failed.'
  }
  finally {
    uploading.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

async function removeDoc(docId: number) {
  await deleteDocument(docId)
  await refresh()
}

async function onSubmit() {
  errors.value = {}
  general.value = ''
  const ce = clientErrors('review')
  if (Object.keys(ce).length) {
    errors.value = ce
    return
  }
  const incomplete = STEPS.filter(s => s.key !== 'review' && !isComplete(s.key)).map(s => s.label)
  if (incomplete.length) {
    general.value = `Finish these steps first: ${incomplete.join(', ')}.`
    return
  }
  saving.value = true
  try {
    await submit(id)
    await refresh()
  }
  catch (e: any) {
    general.value = e?.data?.errors
      ? (Object.values(e.data.errors)[0] as string[])[0]
      : (e?.data?.message ?? 'Could not submit.')
  }
  finally {
    saving.value = false
  }
}

const statusActions = computed(() =>
  transitionsFor(APPLICATION_TRANSITIONS, app.value?.status, user.value?.role)
    .filter(t => !['in_progress', 'submitted'].includes(t)),
)
const reserveAmount = ref<number | null>(null)
const actionReason = ref('')
const transitioning = ref('')

async function doTransition(to: string) {
  general.value = ''
  transitioning.value = to
  try {
    const extra: Record<string, any> = {}
    if (to === 'reserved') extra.incentive_amount = reserveAmount.value
    if ((to === 'rejected' || to === 'withdrawn') && actionReason.value) extra.reason = actionReason.value
    await transition(id, to, extra)
    await refresh()
  }
  catch (e: any) {
    general.value = e?.data?.errors
      ? (Object.values(e.data.errors)[0] as string[])[0]
      : (e?.data?.message ?? 'Transition failed.')
  }
  finally {
    transitioning.value = ''
  }
}
</script>

<template>
  <section v-if="app">
    <NuxtLink
      :to="`/projects/${app.project_id}`"
      class="text-sm"
    >← Back to project</NuxtLink>

    <div class="mb-4 mt-2 flex items-center gap-3">
      <h1>Incentive application</h1>
      <span class="badge badge-emerald">{{ app.status.replace('_', ' ') }}</span>
      <span
        v-if="app.submitted_at"
        class="text-sm text-gray-400"
      >
        submitted {{ new Date(app.submitted_at).toLocaleDateString() }}
      </span>
    </div>

    <p
      v-if="locked"
      class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800"
    >
      This application has been submitted and is now read-only.
    </p>

    <div
      v-if="statusActions.length"
      class="mb-6 rounded-xl border border-blue-100 bg-blue-50 p-4"
    >
      <h2 class="mb-3 text-blue-900">
        Status actions
      </h2>
      <div class="flex flex-wrap items-center gap-3">
        <template
          v-for="t in statusActions"
          :key="t"
        >
          <div
            v-if="t === 'reserved'"
            class="flex gap-2"
          >
            <input
              v-model.number="reserveAmount"
              class="input w-32"
              type="number"
              step="0.01"
              placeholder="Incentive $"
            >
            <button
              class="btn btn-blue btn-sm"
              :disabled="!!transitioning"
              @click="doTransition('reserved')"
            >
              Reserve
            </button>
          </div>
          <div
            v-else-if="t === 'rejected' || t === 'withdrawn'"
            class="flex gap-2"
          >
            <input
              v-model="actionReason"
              class="input w-56"
              type="text"
              :placeholder="`Reason for ${t} (optional)`"
            >
            <button
              class="btn btn-danger btn-sm capitalize"
              :disabled="!!transitioning"
              @click="doTransition(t)"
            >
              {{ t }}
            </button>
          </div>
          <button
            v-else
            class="btn btn-blue btn-sm capitalize"
            :disabled="!!transitioning"
            @click="doTransition(t)"
          >
            {{ t.replace('_', ' ') }}
          </button>
        </template>
      </div>
    </div>

    <p
      v-if="general"
      class="field-error mb-3"
    >
      {{ general }}
    </p>

    <nav class="mb-5 flex flex-wrap gap-2">
      <button
        v-for="s in STEPS"
        :key="s.key"
        class="flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm transition"
        :disabled="!canVisit(s.key)"
        :title="canVisit(s.key) ? '' : 'Complete the earlier steps first'"
        :class="active === s.key
          ? 'border-emerald-500 bg-white font-semibold text-gray-900'
          : isComplete(s.key)
            ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
            : !canVisit(s.key)
              ? 'cursor-not-allowed border-gray-100 bg-white text-gray-300'
              : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
        @click="goTo(s.key)"
      >
        <span
          v-if="isComplete(s.key)"
          class="text-emerald-600"
        >✓</span>
        <span
          v-else-if="!canVisit(s.key)"
          aria-hidden="true"
        >🔒</span>
        {{ s.label }}
      </button>
    </nav>

    <div class="card max-w-2xl">
      <div
        v-show="active === 'eligibility'"
        class="space-y-3"
      >
        <h2>Eligibility</h2>
        <label class="flex items-center gap-2 text-sm">
          <input
            v-model="forms.eligibility.owns_property"
            type="checkbox"
            :disabled="locked"
          > I own the property
        </label>
        <div>
          <label class="label">Utility provider</label>
          <input
            v-model="forms.eligibility.utility_provider"
            class="input"
            :disabled="locked"
            type="text"
          >
          <p
            v-if="errors.utility_provider"
            class="field-error"
          >
            {{ errors.utility_provider }}
          </p>
        </div>
        <div>
          <label class="label">Average monthly bill ($)</label>
          <input
            v-model.number="forms.eligibility.average_monthly_bill"
            class="input"
            :disabled="locked"
            type="number"
            step="0.01"
          >
          <p
            v-if="errors.average_monthly_bill"
            class="field-error"
          >
            {{ errors.average_monthly_bill }}
          </p>
        </div>
      </div>

      <div
        v-show="active === 'system'"
        class="space-y-3"
      >
        <h2>Battery system</h2>
        <div class="grid gap-3 sm:grid-cols-2">
          <div>
            <label class="label">OEM</label>
            <input
              v-model="forms.system.battery_oem"
              class="input"
              :disabled="locked"
              type="text"
            >
            <p
              v-if="errors.battery_oem"
              class="field-error"
            >
              {{ errors.battery_oem }}
            </p>
          </div>
          <div>
            <label class="label">Model</label>
            <input
              v-model="forms.system.battery_model"
              class="input"
              :disabled="locked"
              type="text"
            >
            <p
              v-if="errors.battery_model"
              class="field-error"
            >
              {{ errors.battery_model }}
            </p>
          </div>
          <div>
            <label class="label">Quantity</label>
            <input
              v-model.number="forms.system.quantity"
              class="input"
              :disabled="locked"
              type="number"
              min="1"
            >
          </div>
          <div>
            <label class="label">Usable capacity (kWh)</label>
            <input
              v-model.number="forms.system.usable_capacity_kwh"
              class="input"
              :disabled="locked"
              type="number"
              step="0.01"
            >
            <p
              v-if="errors.usable_capacity_kwh"
              class="field-error"
            >
              {{ errors.usable_capacity_kwh }}
            </p>
          </div>
        </div>
      </div>

      <div
        v-show="active === 'documents'"
        class="space-y-3"
      >
        <h2>Documents</h2>
        <div
          v-if="!locked"
          class="flex flex-wrap items-center gap-2"
        >
          <select
            v-model="docType"
            class="input w-auto"
          >
            <option value="proof">
              Proof of ownership
            </option>
            <option value="utility_bill">
              Utility bill
            </option>
            <option value="invoice">
              Invoice
            </option>
            <option value="photo">
              Photo
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
        <ul
          v-if="app.documents?.length"
          class="divide-y divide-gray-100 text-sm"
        >
          <li
            v-for="d in app.documents"
            :key="d.id"
            class="flex items-center gap-3 py-2"
          >
            <a
              :href="d.download_url"
              target="_blank"
              class="font-medium"
            >{{ d.file_name }}</a>
            <span class="badge">{{ d.type }}</span>
            <button
              v-if="!locked"
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
          No documents uploaded yet.
        </p>
        <p
          v-if="errors.documents"
          class="field-error"
        >
          {{ errors.documents }}
        </p>
      </div>

      <div
        v-show="active === 'banking'"
        class="space-y-3"
      >
        <h2>Banking details</h2>
        <p class="text-sm text-gray-500">
          Where the incentive payment will be deposited once approved.
        </p>
        <div class="grid gap-3 sm:grid-cols-2">
          <div>
            <label class="label">Account holder name</label>
            <input
              v-model="forms.banking.account_holder_name"
              class="input"
              :disabled="locked"
              type="text"
              autocomplete="off"
            >
            <p
              v-if="errors.account_holder_name"
              class="field-error"
            >
              {{ errors.account_holder_name }}
            </p>
          </div>
          <div>
            <label class="label">Bank name</label>
            <input
              v-model="forms.banking.bank_name"
              class="input"
              :disabled="locked"
              type="text"
              autocomplete="off"
            >
            <p
              v-if="errors.bank_name"
              class="field-error"
            >
              {{ errors.bank_name }}
            </p>
          </div>
          <div>
            <label class="label">Routing number</label>
            <input
              v-model="forms.banking.routing_number"
              class="input"
              :disabled="locked"
              type="text"
              inputmode="numeric"
              maxlength="9"
              autocomplete="off"
            >
            <p
              v-if="errors.routing_number"
              class="field-error"
            >
              {{ errors.routing_number }}
            </p>
          </div>
          <div>
            <label class="label">Account number</label>
            <input
              v-model="forms.banking.account_number"
              class="input"
              :disabled="locked"
              type="text"
              inputmode="numeric"
              maxlength="17"
              autocomplete="off"
            >
            <p
              v-if="errors.account_number"
              class="field-error"
            >
              {{ errors.account_number }}
            </p>
          </div>
          <div>
            <label class="label">Account type</label>
            <select
              v-model="forms.banking.account_type"
              class="input"
              :disabled="locked"
            >
              <option value="checking">
                Checking
              </option>
              <option value="savings">
                Savings
              </option>
            </select>
            <p
              v-if="errors.account_type"
              class="field-error"
            >
              {{ errors.account_type }}
            </p>
          </div>
        </div>
      </div>

      <div
        v-show="active === 'review'"
        class="space-y-4"
      >
        <h2>Review &amp; submit</h2>

        <dl class="divide-y divide-gray-100 rounded-lg border border-gray-100 text-sm">
          <div class="grid grid-cols-3 gap-2 px-3 py-2">
            <dt class="font-medium text-gray-500">
              Property owner
            </dt>
            <dd class="col-span-2">
              {{ forms.eligibility.owns_property ? 'Yes' : 'No' }}
            </dd>
          </div>
          <div class="grid grid-cols-3 gap-2 px-3 py-2">
            <dt class="font-medium text-gray-500">
              Utility provider
            </dt>
            <dd class="col-span-2">
              {{ forms.eligibility.utility_provider || '—' }}
            </dd>
          </div>
          <div class="grid grid-cols-3 gap-2 px-3 py-2">
            <dt class="font-medium text-gray-500">
              Average monthly bill
            </dt>
            <dd class="col-span-2">
              {{ forms.eligibility.average_monthly_bill != null ? `$${forms.eligibility.average_monthly_bill}` : '—' }}
            </dd>
          </div>
          <div class="grid grid-cols-3 gap-2 px-3 py-2">
            <dt class="font-medium text-gray-500">
              Battery system
            </dt>
            <dd class="col-span-2">
              {{ [forms.system.battery_oem, forms.system.battery_model].filter(Boolean).join(' ') || '—' }}
              <span v-if="forms.system.quantity">× {{ forms.system.quantity }}</span>
              <span v-if="forms.system.usable_capacity_kwh">· {{ forms.system.usable_capacity_kwh }} kWh</span>
            </dd>
          </div>
          <div class="grid grid-cols-3 gap-2 px-3 py-2">
            <dt class="font-medium text-gray-500">
              Deposit account
            </dt>
            <dd class="col-span-2">
              <template v-if="forms.banking.account_number">
                {{ forms.banking.bank_name }} · {{ forms.banking.account_type }} ••••{{ String(forms.banking.account_number).slice(-4) }}
              </template>
              <template v-else>
                —
              </template>
            </dd>
          </div>
          <div class="grid grid-cols-3 gap-2 px-3 py-2">
            <dt class="font-medium text-gray-500">
              Documents
            </dt>
            <dd class="col-span-2">
              <span v-if="app.documents?.length">{{ app.documents.map((d: any) => d.file_name).join(', ') }}</span>
              <span
                v-else
                class="text-red-600"
              >none uploaded</span>
            </dd>
          </div>
        </dl>

        <ul class="space-y-1 text-sm">
          <li
            v-for="st in STEPS.slice(0, 4)"
            :key="st.key"
          >
            {{ st.label }}:
            <span :class="isComplete(st.key) ? 'font-semibold text-emerald-700' : 'font-semibold text-red-600'">
              {{ isComplete(st.key) ? 'complete' : 'incomplete' }}
            </span>
          </li>
        </ul>

        <label class="flex items-center gap-2 text-sm">
          <input
            v-model="forms.review.accepted_terms"
            type="checkbox"
            :disabled="locked"
          >
          I confirm the information is accurate.
        </label>
        <p
          v-if="errors.accepted_terms"
          class="field-error"
        >
          {{ errors.accepted_terms }}
        </p>
      </div>

      <div
        v-if="!locked"
        class="mt-5 flex gap-2 border-t border-gray-100 pt-4"
      >
        <button
          v-if="active !== 'documents'"
          class="btn btn-ghost"
          :disabled="saving"
          @click="persist(active, false)"
        >
          Save draft
        </button>
        <button
          v-if="active !== 'review'"
          class="btn btn-primary"
          :disabled="saving"
          @click="persist(active, true)"
        >
          {{ saving ? 'Saving…' : 'Save & continue' }}
        </button>
        <template v-else>
          <button
            class="btn btn-ghost"
            :disabled="saving"
            @click="persist('review', true)"
          >
            Save step
          </button>
          <button
            class="btn btn-primary"
            :disabled="saving"
            @click="onSubmit"
          >
            Submit application
          </button>
        </template>
      </div>
    </div>
  </section>

  <AsyncState
    v-else
    :pending="pending"
    :error="error"
    error-text="Couldn't load this application."
    @retry="refresh"
  />
</template>
