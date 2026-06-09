import type { MaybeRefOrGetter } from 'vue'

/**
 * Shared document-upload handler: client-side validation, an `uploading` flag,
 * file-input reset, and consistent error extraction. Pages supply the actual
 * upload call (project vs application) and a post-upload refresh.
 */
export function useFileUpload(opts: {
  upload: (file: File, type: string) => Promise<unknown>
  type: MaybeRefOrGetter<string>
  onUploaded?: () => Promise<void> | void
  onError?: (message: string) => void
}) {
  const fileInput = ref<HTMLInputElement | null>(null)
  const uploading = ref(false)
  const error = ref('')

  function fail(message: string) {
    error.value = message
    opts.onError?.(message)
  }

  function resetInput() {
    if (fileInput.value) fileInput.value.value = ''
  }

  async function onUpload(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0]
    if (!file) return

    const validationError = validateUpload(file)
    if (validationError) {
      fail(validationError)
      resetInput()
      return
    }

    uploading.value = true
    error.value = ''
    try {
      await opts.upload(file, toValue(opts.type))
      await opts.onUploaded?.()
    }
    catch (e) {
      fail(apiErrorMessage(e, 'Upload failed.'))
    }
    finally {
      uploading.value = false
      resetInput()
    }
  }

  return { fileInput, uploading, error, onUpload }
}
