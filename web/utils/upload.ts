export const MAX_UPLOAD_BYTES = 10 * 1024 * 1024
export const ALLOWED_UPLOAD_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png']
export const ALLOWED_UPLOAD_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png']

export function validateUpload(file: File): string | null {
  const extension = file.name.split('.').pop()?.toLowerCase() ?? ''
  const typeOk = ALLOWED_UPLOAD_MIME_TYPES.includes(file.type)
    || ALLOWED_UPLOAD_EXTENSIONS.includes(extension)

  if (!typeOk) {
    return 'File must be a PDF, JPG, or PNG.'
  }
  if (file.size > MAX_UPLOAD_BYTES) {
    return 'File must be 10 MB or smaller.'
  }
  return null
}
