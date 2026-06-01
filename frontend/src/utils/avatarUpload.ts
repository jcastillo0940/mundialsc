const MAX_AVATAR_BYTES = 500 * 1024
const MAX_AVATAR_DIMENSION = 1600
const AVATAR_EXPORT_TYPE = 'image/jpeg'
const AVATAR_EXPORT_NAME = 'avatar'
const AVATAR_QUALITIES = [0.86, 0.8, 0.74, 0.68, 0.62, 0.56, 0.5, 0.44]
const AVATAR_DIMENSION_STEPS = [1, 0.9, 0.8, 0.7, 0.6]

async function loadImageFromFile(file: File): Promise<HTMLImageElement> {
  const objectUrl = URL.createObjectURL(file)

  try {
    return await new Promise<HTMLImageElement>((resolve, reject) => {
      const image = new Image()
      image.onload = () => resolve(image)
      image.onerror = () => reject(new Error('No se pudo cargar la imagen.'))
      image.src = objectUrl
    })
  } finally {
    URL.revokeObjectURL(objectUrl)
  }
}

async function canvasToBlob(canvas: HTMLCanvasElement, quality: number): Promise<Blob> {
  return await new Promise<Blob>((resolve, reject) => {
    canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('No se pudo exportar la imagen.'))), AVATAR_EXPORT_TYPE, quality)
  })
}

function toAvatarFile(blob: Blob, basename: string) {
  return new File([blob], `${basename}-${Date.now()}.jpg`, { type: AVATAR_EXPORT_TYPE })
}

async function exportOptimizedAvatar(canvas: HTMLCanvasElement, basename: string): Promise<File> {
  const sourceSize = Math.max(canvas.width, canvas.height)

  for (const dimensionStep of AVATAR_DIMENSION_STEPS) {
    const scaledCanvas = document.createElement('canvas')
    scaledCanvas.width = Math.max(1, Math.round(canvas.width * dimensionStep))
    scaledCanvas.height = Math.max(1, Math.round(canvas.height * dimensionStep))

    const context = scaledCanvas.getContext('2d')
    if (!context) throw new Error('No se pudo procesar la imagen.')
    context.drawImage(canvas, 0, 0, scaledCanvas.width, scaledCanvas.height)

    for (const quality of AVATAR_QUALITIES) {
      const blob = await canvasToBlob(scaledCanvas, quality)
      if (blob.size <= MAX_AVATAR_BYTES) {
        return toAvatarFile(blob, basename)
      }
    }

    if (sourceSize <= 500 && dimensionStep === 1) {
      break
    }
  }

  const fallbackBlob = await canvasToBlob(canvas, 0.4)
  if (fallbackBlob.size <= MAX_AVATAR_BYTES) {
    return toAvatarFile(fallbackBlob, basename)
  }

  throw new Error('No fue posible optimizar la imagen a menos de 500 KB.')
}

export async function optimizeAvatarFile(file: File, basename = AVATAR_EXPORT_NAME): Promise<File> {
  const image = await loadImageFromFile(file)
  const largestSide = Math.max(image.width, image.height)
  const scale = largestSide > MAX_AVATAR_DIMENSION ? MAX_AVATAR_DIMENSION / largestSide : 1
  const canvas = document.createElement('canvas')
  canvas.width = Math.max(1, Math.round(image.width * scale))
  canvas.height = Math.max(1, Math.round(image.height * scale))
  const context = canvas.getContext('2d')

  if (!context) throw new Error('No se pudo procesar la imagen.')

  context.drawImage(image, 0, 0, canvas.width, canvas.height)

  return exportOptimizedAvatar(canvas, basename)
}

export async function cropAvatarToSquare(file: File): Promise<File> {
  const image = await loadImageFromFile(file)
  const size = Math.min(image.width, image.height)
  const sourceX = Math.floor((image.width - size) / 2)
  const sourceY = Math.floor((image.height - size) / 2)
  const canvas = document.createElement('canvas')
  canvas.width = 500
  canvas.height = 500
  const context = canvas.getContext('2d')

  if (!context) throw new Error('No se pudo procesar la imagen.')

  context.drawImage(image, sourceX, sourceY, size, size, 0, 0, 500, 500)

  return exportOptimizedAvatar(canvas, 'avatar-square')
}
