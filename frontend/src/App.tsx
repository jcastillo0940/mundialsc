import { useEffect, useMemo, useRef, useState } from 'react'
import type { ChangeEvent, FormEvent } from 'react'
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode'
import { useLocation, useNavigate } from 'react-router-dom'
import { createWorker, PSM } from 'tesseract.js'
import { api, setApiToken } from './api'
import { InvoiceRegistrationView } from './components/InvoiceRegistrationView'
import { CuentaView } from './components/CuentaView'
import { VestuarioView } from './components/VestuarioView'
import { VitrinaView } from './components/VitrinaView'
import type {
  ClientBootstrap,
  DashboardSnapshot,
  Prediction,
  Prize,
  RegisteredInvoice,
  ResolvedInvoiceData,
  TournamentMatch,
  TournamentPhase,
  User,
  WalletSnapshot,
} from './types'

const TOKEN_KEY = 'super-carnes-token'
const CONTEST_NAME = 'Polla Mundialista Super Carnes 2026'
const REGISTRATION_DEADLINE = '10 de junio de 2026'
const WINNERS_ANNOUNCEMENT = '10 de julio de 2026'
const PANAMA_TIMEZONE = 'America/Panama'
const DEFAULT_AUTH_BG_YOUTUBE_ID = import.meta.env.VITE_AUTH_BG_YOUTUBE_ID ?? 'O9diw9_5pys'
const DEFAULT_AUTH_LOGO_URL = import.meta.env.VITE_AUTH_LOGO_URL ?? ''
const STADIUM_IMAGE_URL =
  'https://lh3.googleusercontent.com/aida-public/AB6AXuBEx-hRFUMZ710fF7EatYLLO_SftyRg0ww2GvBNKWHSjPObe2Hu17fXzKDy8LOFbxMv93SOa0IWNTCINLfrcTI4Gv7Fb8T-KRHOU6iyLxekm6vci5QI1h6h-jtqFVtscsl4aPJJld2V-TOyhBaZNKlPweuhcxfvNwlUxFNiz07sFuBIttiDysG-4NIdDsaDGIygvIgQn-m1chePGiwL3D2k8IOl-CypudZp6J8U6ve38WWsbNyTIdWbQWlJlq2K7BKdk_nqv4a5KH8'

type AuthMode = 'login' | 'register'
type MainView = 'cancha' | 'reglas' | 'facturas' | 'perfil' | 'cuenta'
type PredictionMode = 'pending' | 'mine'
type InvoiceEntryMode = 'scan' | 'manual'

interface ParticipantBrand {
  name: string
  logo_url?: string
}

const CLIENT_VIEW_PATHS: Record<MainView, string> = {
  cancha: '/cancha',
  facturas: '/entrenamiento',
  perfil: '/vestuario',
  reglas: '/vitrina',
  cuenta: '/cuenta',
}

const CLIENT_VIEW_LABELS: Record<MainView, string> = {
  cancha: 'La Cancha',
  facturas: 'Entrenamiento',
  perfil: 'Vestuario',
  reglas: 'Vitrina',
  cuenta: 'Mi Cuenta',
}

interface PredictionDraft {
  home: string
  away: string
}

interface PhasesResponse {
  data: TournamentPhase[]
}

interface MatchesResponse {
  data: TournamentMatch[]
}

interface PredictionsResponse {
  data: Prediction[]
}

interface InvoicesResponse {
  data: RegisteredInvoice[]
  totals?: {
    goals?: number
  }
}

interface ClientBootstrapResponse extends ClientBootstrap {}

interface DashboardResponse extends DashboardSnapshot {}

interface WalletResponse extends WalletSnapshot {}

interface PrizesResponse {
  data: Prize[]
}

interface AuthFormState {
  full_name: string
  document_type: 'cedula' | 'passport' | 'residente'
  cedula: string
  email: string
  phone: string
  birthdate: string
  resides_in_panama: boolean
  is_employee: boolean
  accepted_terms: boolean
  group_stage_goal_prediction: string
  password: string
  password_confirmation: string
}

interface InvoiceFormState {
  rawInput: string
  invoice_number: string
  purchase_amount: string
  issued_at: string
}

interface InvoiceScannerDebugInfo {
  origin: string
  protocol: string
  hostname: string
  isSecureContext: boolean
  hasMediaDevices: boolean
  hasGetUserMedia: boolean
  cameraPermission: string
  fileReaderSupported: boolean
  userAgent: string
  likelyCameraBlockedBySecurity: boolean
  lastStage: string
  lastError: string | null
  barcodeDetectorAvailable: boolean
  scannerType: 'native' | 'html5-qrcode' | 'none'
  activeFormats: string[]
  cameraResolution: string
}

interface InvoiceScannerRef {
  stop: () => Promise<unknown>
  clear: () => unknown
}

interface CanvasCropBounds {
  topRatio: number
  leftRatio: number
  widthRatio: number
  heightRatio: number
}

const QR_READER_ELEMENT_ID = 'dgi-qr-reader'
const INVOICE_SCANNER_FORMATS = [
  Html5QrcodeSupportedFormats.QR_CODE,
  Html5QrcodeSupportedFormats.DATA_MATRIX,
  Html5QrcodeSupportedFormats.PDF_417,
  Html5QrcodeSupportedFormats.AZTEC,
]

function createInvoiceScanner() {
  return new Html5Qrcode(QR_READER_ELEMENT_ID, {
    verbose: false,
    formatsToSupport: INVOICE_SCANNER_FORMATS,
    useBarCodeDetectorIfSupported: true,
    experimentalFeatures: {
      useBarCodeDetectorIfSupported: true,
    },
  })
}

function buildInvoiceCameraScanConfig() {
  return {
    fps: 15,
    qrbox: (viewfinderWidth: number, viewfinderHeight: number) => {
      // QR denso necesita el mayor área posible en píxeles
      const minEdge = Math.min(viewfinderWidth, viewfinderHeight)
      const targetSize = Math.floor(minEdge * 0.92)
      return {
        width: Math.min(targetSize, viewfinderWidth),
        height: Math.min(targetSize, viewfinderHeight),
      }
    },
    aspectRatio: 1.333334,
    disableFlip: true,
    videoConstraints: {
      facingMode: { ideal: 'environment' },
      width: { ideal: 1920 },
      height: { ideal: 1080 },
    },
  }
}

async function applyAutofocusToActiveStream() {
  try {
    const videoEl = document.querySelector<HTMLVideoElement>(`#${QR_READER_ELEMENT_ID} video`)
    if (!(videoEl?.srcObject instanceof MediaStream)) return
    const track = videoEl.srcObject.getVideoTracks()[0]
    if (!track) return
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const caps = track.getCapabilities() as any
    if (caps?.focusMode?.includes('continuous')) {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      await track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] } as any)
    }
  } catch {
    // autofocus no soportado en este dispositivo, no es bloqueante
  }
}

// Scanner nativo usando BarcodeDetector a resolución completa (sin crop/escala).
// Detecta QR densos que html5-qrcode no puede leer porque trabaja a resolución visual.
async function startNativeBarcodeScanner(
  elementId: string,
  formats: string[],
  onSuccess: (text: string) => void,
  onError: (msg: string) => void,
  onActive: (resolution: string) => void,
): Promise<{ stop: () => Promise<void>; clear: () => void }> {
  const container = document.getElementById(elementId)
  if (!container) throw new Error('Contenedor del escaner no encontrado')

  const video = document.createElement('video')
  video.setAttribute('playsinline', 'true')
  video.muted = true
  video.style.cssText = 'width:100%;border-radius:12px;display:block;background:#000;'
  container.innerHTML = ''
  container.appendChild(video)

  // Intentar primero con alta resolución; si el dispositivo rechaza, reintentar sin constraints de res.
  let stream: MediaStream
  try {
    stream = await navigator.mediaDevices.getUserMedia({
      audio: false,
      video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } },
    })
  } catch {
    stream = await navigator.mediaDevices.getUserMedia({
      audio: false,
      video: { facingMode: { ideal: 'environment' } },
    })
  }

  video.srcObject = stream
  await video.play()

  // Autofocus continuo — opcional, ignorar si el dispositivo no lo soporta
  const track = stream.getVideoTracks()[0]
  try {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const caps = track.getCapabilities() as any
    if (caps?.focusMode?.includes('continuous')) {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      await track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] } as any)
    }
  } catch { /* autofocus opcional */ }

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const detector = new (window as any).BarcodeDetector({ formats: formats })
  let stopped = false
  let timerId: ReturnType<typeof setTimeout> | null = null
  let activeNotified = false

  async function scanFrame() {
    if (stopped) return
    if (video.readyState >= video.HAVE_ENOUGH_DATA) {
      if (!activeNotified) {
        activeNotified = true
        onActive(`${video.videoWidth}x${video.videoHeight}`)
      }
      let bitmap: ImageBitmap | null = null
      try {
        // createImageBitmap captura el frame a la resolución real de la cámara (no la visual del elemento).
        // detect(video) en Chrome Android usa la resolución de renderizado CSS, que puede ser mucho menor.
        bitmap = await createImageBitmap(video)
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const codes: any[] = await detector.detect(bitmap)
        if (codes.length > 0 && !stopped) {
          onSuccess(codes[0].rawValue as string)
          return
        }
      } catch (e) {
        onError(String(e))
      } finally {
        bitmap?.close()
      }
    }
    if (!stopped) timerId = setTimeout(() => { void scanFrame() }, 80)
  }

  void scanFrame()

  return {
    stop: async () => {
      stopped = true
      if (timerId !== null) clearTimeout(timerId)
      stream.getTracks().forEach((t) => t.stop())
    },
    clear: () => { container.innerHTML = '' },
  }
}

function buildInvoiceScannerDebugInfo(partial?: Partial<InvoiceScannerDebugInfo>): InvoiceScannerDebugInfo {
  if (typeof window === 'undefined') {
    return {
      origin: 'server',
      protocol: 'server',
      hostname: 'server',
      isSecureContext: false,
      hasMediaDevices: false,
      hasGetUserMedia: false,
      cameraPermission: 'unknown',
      fileReaderSupported: false,
      userAgent: 'server',
      likelyCameraBlockedBySecurity: false,
      lastStage: partial?.lastStage ?? 'idle',
      lastError: partial?.lastError ?? null,
      barcodeDetectorAvailable: false,
      scannerType: partial?.scannerType ?? 'none',
      activeFormats: partial?.activeFormats ?? [],
      cameraResolution: partial?.cameraResolution ?? '',
    }
  }

  const hasMediaDevices = typeof navigator !== 'undefined' && 'mediaDevices' in navigator
  const hasGetUserMedia = hasMediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function'
  const hostname = window.location.hostname
  const isLocalhost = ['localhost', '127.0.0.1', '::1'].includes(hostname)
  const likelyCameraBlockedBySecurity = !window.isSecureContext && !isLocalhost

  return {
    origin: window.location.origin,
    protocol: window.location.protocol,
    hostname,
    isSecureContext: window.isSecureContext,
    hasMediaDevices,
    hasGetUserMedia,
    cameraPermission: partial?.cameraPermission ?? 'unknown',
    fileReaderSupported: typeof FileReader !== 'undefined',
    userAgent: navigator.userAgent,
    likelyCameraBlockedBySecurity,
    lastStage: partial?.lastStage ?? 'idle',
    lastError: partial?.lastError ?? null,
    barcodeDetectorAvailable: 'BarcodeDetector' in window,
    scannerType: partial?.scannerType ?? 'none',
    activeFormats: partial?.activeFormats ?? [],
    cameraResolution: partial?.cameraResolution ?? '',
  }
}

function normalizeIdentityNumber(documentType: AuthFormState['document_type'], value: string) {
  const trimmed = value.trim().toUpperCase()

  if (documentType === 'cedula') {
    return trimmed.replace(/[^A-Z0-9-]/g, '')
  }

  return trimmed.replace(/[^A-Z0-9-]/g, '')
}

function documentNumberLabel(documentType: AuthFormState['document_type']) {
  if (documentType === 'cedula') return 'Cedula'
  if (documentType === 'residente') return 'Documento de residente'
  return 'Pasaporte'
}

function documentNumberPlaceholder(documentType: AuthFormState['document_type']) {
  if (documentType === 'cedula') return 'Ej: 8-864-1164, PE-123-456'
  if (documentType === 'residente') return 'Ej: E-123456 o PE-123-456'
  return 'Ej: PA1234567'
}

function validateDocumentNumber(documentType: AuthFormState['document_type'], value: string) {
  const normalized = normalizeIdentityNumber(documentType, value)

  if (!normalized) {
    return `Debes ingresar ${documentNumberLabel(documentType).toLowerCase()}.`
  }

  if (documentType === 'cedula') {
    if (!/^(\d{1,2}-\d{1,4}-\d{1,6}|PE-\d{1,4}-\d{1,6}|E-\d{1,4}-\d{1,6}|N-\d{1,4}-\d{1,6})$/.test(normalized)) {
      return 'La cedula debe usar formato de Panama, por ejemplo 8-864-1164, PE-123-456, E-123-456 o N-123-456.'
    }

    return null
  }

  if (documentType === 'passport') {
    if (!/^(?=.*[A-Z])[A-Z0-9-]{5,20}$/.test(normalized)) {
      return 'El pasaporte debe ser alfanumerico y contener al menos una letra.'
    }

    return null
  }

  if (!/^(?=.*[A-Z])(?=.*\d)[A-Z0-9-]{3,25}$/.test(normalized)) {
    return 'El documento de residente debe mezclar letras y numeros. Puedes usar guiones si aplica.'
  }

  return null
}

async function getCameraPermissionState() {
  if (typeof window === 'undefined' || !('permissions' in navigator) || typeof navigator.permissions.query !== 'function') {
    return 'unsupported'
  }

  try {
    const status = await navigator.permissions.query({ name: 'camera' as PermissionName })
    return status.state
  } catch {
    return 'unsupported'
  }
}

function normalizeError(rawError: unknown): string {
  const fallback = 'Ocurrio un error inesperado.'

  if (typeof rawError !== 'object' || !rawError) return fallback
  const candidate = rawError as {
    code?: string
    message?: string
    response?: { data?: { message?: string; errors?: Record<string, string[]> } }
  }

  if (!candidate.response) {
    const networkCodes = new Set(['ERR_NETWORK', 'ECONNABORTED'])

    if (networkCodes.has(candidate.code ?? '') || /network error/i.test(candidate.message ?? '')) {
      return 'No se pudo conectar con el servidor. Verifica la URL del API o tu conexion e intenta nuevamente.'
    }
  }

  const firstError = candidate.response?.data?.errors ? Object.values(candidate.response.data.errors)[0]?.[0] : null
  return firstError ?? candidate.response?.data?.message ?? fallback
}

function normalizePanamaPhone(value: string) {
  const digits = value.replace(/\D/g, '')
  const localDigits = digits.startsWith('507') ? digits.slice(3, 11) : digits.slice(0, 8)
  return localDigits ? `+507${localDigits}` : ''
}

function validatePanamaPhone(value: string) {
  return /^\+507\d{8}$/.test(value)
}

function parseParticipantBrands(value?: string | null): ParticipantBrand[] {
  if (!value) return []

  return value
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line) => {
      const [name, logoUrl] = line.split('|').map((part) => part.trim())
      return { name, logo_url: logoUrl || undefined }
    })
    .filter((brand) => Boolean(brand.name))
}

function formatUpperDate(dateValue: string) {
  const date = new Date(dateValue)
  if (Number.isNaN(date.getTime())) return dateValue

  const day = date.toLocaleDateString('es-PA', { day: 'numeric', timeZone: PANAMA_TIMEZONE })
  const month = date.toLocaleDateString('es-PA', { month: 'long', timeZone: PANAMA_TIMEZONE })
  return `${day} ${month.charAt(0).toUpperCase()}${month.slice(1)}`
}

function formatTime(dateValue: string) {
  const date = new Date(dateValue)
  if (Number.isNaN(date.getTime())) return '--:--'

  return date.toLocaleTimeString('en-US', {
    timeZone: PANAMA_TIMEZONE,
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  })
}

function formatDateTime(dateValue: string) {
  const date = new Date(dateValue)
  if (Number.isNaN(date.getTime())) return dateValue

  return `${formatUpperDate(dateValue)}, ${formatTime(dateValue)}`
}

function formatCurrency(value: number | string | null | undefined) {
  const amount = Number(value ?? 0)
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number.isFinite(amount) ? amount : 0)
}

function extractInvoiceCufe(value: string) {
  const trimmedValue = value.trim()
  if (!trimmedValue) return ''

  try {
    const parsed = JSON.parse(trimmedValue) as {
      datos?: { cufe?: string }
      data?: { cufe?: string }
      cufe?: string
    }
    const jsonCufe = parsed?.datos?.cufe ?? parsed?.data?.cufe ?? parsed?.cufe

    if (typeof jsonCufe === 'string' && jsonCufe.trim()) {
      return jsonCufe.trim().toUpperCase()
    }
  } catch {
    // Ignorar JSON invalido y seguir con regex.
  }

  const decodedValue = decodeURIComponent(trimmedValue)
  const patterns = [
    /[?&]cufe=([A-Z0-9-]{16,255})/i,
    /(?:cufe|CUFE)[=:\s"]+([A-Z0-9-]{16,255})/i,
    /((?:FE|CS)[A-Z0-9-]{16,255})/i,
    /([A-Z0-9]{8,}-[A-Z0-9-]{8,})/i,
  ]

  for (const pattern of patterns) {
    const matches = decodedValue.match(pattern)
    if (matches?.[1]) {
      return matches[1].trim().toUpperCase()
    }
  }

  return trimmedValue.toUpperCase().replace(/\s+/g, '')
}

function normalizeInvoiceQrPayload(value: string) {
  const trimmedValue = value.trim()
  if (!trimmedValue) return ''

  if (/https?:\/\//i.test(trimmedValue) && /cufe=/i.test(trimmedValue)) {
    return trimmedValue
  }

  if (/^cufe=/i.test(trimmedValue)) {
    return `cufe=${extractInvoiceCufe(trimmedValue)}`
  }

  const extractedCufe = extractInvoiceCufe(trimmedValue)
  return extractedCufe ? `cufe=${extractedCufe}` : trimmedValue
}

function extractInvoiceCufeFromOcrText(value: string) {
  const normalizedText = value
    .toUpperCase()
    .replace(/[|]/g, 'I')
    .replace(/[“”"]/g, '')
    .replace(/\r/g, '\n')

  const cufeIndex = normalizedText.indexOf('CUFE')
  const searchWindow =
    cufeIndex >= 0
      ? normalizedText.slice(cufeIndex, cufeIndex + 420)
      : normalizedText

  const blockMatch = searchWindow.match(/CUFE[\s:.-]*([\s\S]{0,260}?)(?:SERIE|DOCUMENTO|VALIDADO|PROVEEDOR|RESOLUCION|$)/i)
  const candidateBlock = blockMatch?.[1] ?? searchWindow
  const candidateLines = candidateBlock
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean)

  for (let index = 0; index < candidateLines.length; index += 1) {
    const currentLine = candidateLines[index]
    const nextLine = candidateLines[index + 1] ?? ''
    const compactCurrentLine = currentLine.replace(/[^A-Z0-9-]/g, '')
    const compactNextLine = nextLine.replace(/[^A-Z0-9-]/g, '')
    const mergedLine =
      compactCurrentLine.endsWith('-') || /^\d{6,}/.test(compactNextLine)
        ? `${compactCurrentLine}${compactNextLine}`
        : compactCurrentLine
    const directLineMatch = mergedLine.match(/(?:FE|CS)[A-Z0-9-]{20,255}/)

    if (directLineMatch?.[0]) {
      return normalizeStructuredCufeCandidate(directLineMatch[0])
    }
  }

  const compactCandidate = candidateBlock.replace(/[^A-Z0-9-\n]/g, '')
  const directMatch = compactCandidate.match(/(?:FE|CS)[A-Z0-9-]{20,255}/)
  if (directMatch?.[0]) return normalizeStructuredCufeCandidate(directMatch[0])

  const narrowedWindow = searchWindow.replace(/[^A-Z0-9-\n]/g, '')
  const fallbackMatch = narrowedWindow.match(/(?:FE|CS)[A-Z0-9-]{20,255}/)
  return fallbackMatch?.[0] ? normalizeStructuredCufeCandidate(fallbackMatch[0]) : ''
}

function normalizeStructuredCufeCandidate(value: string) {
  const cleaned = value.toUpperCase().replace(/[^A-Z0-9-]/g, '').replace(/-+/g, '-')
  const segments = cleaned.split('-').filter(Boolean)

  if (segments.length < 4) return cleaned

  const normalizedSegments = segments.map((segment, index) => {
    if (index === 0) return segment
    return segment
      .replace(/[OQD]/g, '0')
      .replace(/[ILT]/g, '1')
      .replace(/Z/g, '2')
      .replace(/E/g, '3')
      .replace(/A/g, '4')
      .replace(/S/g, '5')
      .replace(/[GP]/g, '6')
      .replace(/B/g, '8')
  })

  return normalizedSegments.join('-')
}

function isAtLeast18(dateStr: string): boolean {
  if (!dateStr) return false
  const birth = new Date(dateStr)
  if (Number.isNaN(birth.getTime())) return false
  const today = new Date()
  const cutoff = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate())
  return birth <= cutoff
}

async function cropAvatarToSquare(file: File): Promise<File> {
  const objectUrl = URL.createObjectURL(file)
  const image = await new Promise<HTMLImageElement>((resolve, reject) => {
    const img = new Image()
    img.onload = () => resolve(img)
    img.onerror = () => reject(new Error('No se pudo cargar la imagen.'))
    img.src = objectUrl
  })
  URL.revokeObjectURL(objectUrl)

  const size = Math.min(image.width, image.height)
  const sourceX = Math.floor((image.width - size) / 2)
  const sourceY = Math.floor((image.height - size) / 2)
  const canvas = document.createElement('canvas')
  canvas.width = 500
  canvas.height = 500
  const ctx = canvas.getContext('2d')
  if (!ctx) throw new Error('No se pudo procesar la imagen.')
  ctx.drawImage(image, sourceX, sourceY, size, size, 0, 0, 500, 500)

  const blob = await new Promise<Blob>((resolve, reject) => {
    canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('Error al exportar imagen.'))), 'image/jpeg', 0.92)
  })
  return new File([blob], `avatar-${Date.now()}.jpg`, { type: 'image/jpeg' })
}

async function loadImageElement(file: File) {
  const objectUrl = URL.createObjectURL(file)

  try {
    const image = await new Promise<HTMLImageElement>((resolve, reject) => {
      const nextImage = new Image()
      nextImage.onload = () => resolve(nextImage)
      nextImage.onerror = () => reject(new Error('No se pudo cargar la imagen seleccionada para OCR.'))
      nextImage.src = objectUrl
    })

    return image
  } finally {
    URL.revokeObjectURL(objectUrl)
  }
}

async function createCroppedImageBlob(file: File, bounds: CanvasCropBounds) {
  const image = await loadImageElement(file)
  const canvas = document.createElement('canvas')
  const sourceX = Math.max(0, Math.floor(image.width * bounds.leftRatio))
  const sourceY = Math.max(0, Math.floor(image.height * bounds.topRatio))
  const sourceWidth = Math.max(1, Math.floor(image.width * bounds.widthRatio))
  const sourceHeight = Math.max(1, Math.floor(image.height * bounds.heightRatio))

  canvas.width = sourceWidth
  canvas.height = sourceHeight

  const context = canvas.getContext('2d')
  if (!context) throw new Error('No se pudo preparar el recorte para OCR.')

  context.filter = 'grayscale(1) contrast(1.4) brightness(1.05)'
  context.drawImage(image, sourceX, sourceY, sourceWidth, sourceHeight, 0, 0, sourceWidth, sourceHeight)

  const blob = await new Promise<Blob>((resolve, reject) => {
    canvas.toBlob((nextBlob) => {
      if (nextBlob) {
        resolve(nextBlob)
        return
      }

      reject(new Error('No se pudo exportar el recorte de la imagen para OCR.'))
    }, 'image/png')
  })

  return new File([blob], `ocr-crop-${Date.now()}.png`, { type: 'image/png' })
}

async function extractInvoiceCufeViaOcr(file: File) {
  const ocrTargets: Array<{ file: File; mode: string }> = [{ file, mode: 'full' }]

  try {
    const lowerCrop = await createCroppedImageBlob(file, {
      topRatio: 0.58,
      leftRatio: 0.08,
      widthRatio: 0.84,
      heightRatio: 0.26,
    })
    ocrTargets.push({ file: lowerCrop, mode: 'lower-cufe' })
  } catch {
    // no-op
  }

  try {
    const focusedCrop = await createCroppedImageBlob(file, {
      topRatio: 0.66,
      leftRatio: 0.12,
      widthRatio: 0.76,
      heightRatio: 0.14,
    })
    ocrTargets.push({ file: focusedCrop, mode: 'focused-cufe' })
  } catch {
    // no-op
  }

  let bestCandidate = ''
  const worker = await createWorker('eng')

  await worker.setParameters({
    tessedit_pageseg_mode: PSM.SINGLE_BLOCK,
    tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789:-/',
  })

  try {
    for (const target of ocrTargets) {
      const ocrResult = await worker.recognize(target.file)
      const candidate = extractInvoiceCufeFromOcrText(ocrResult.data.text)

      if (candidate.length > bestCandidate.length) {
        bestCandidate = candidate
      }

      if (/^(?:FE|CS)[A-Z0-9-]{35,255}$/.test(candidate) && /-\d{20,}$/.test(candidate)) {
        return candidate
      }
    }
  } finally {
    await worker.terminate()
  }

  return bestCandidate
}

function invoiceStatusMeta(status: string) {
  if (status === 'approved') return { label: 'Validada', badge: 'OK', tone: 'approved' as const }
  if (status === 'pending') return { label: 'En revision', badge: 'API', tone: 'pending' as const }
  return { label: 'No valida', badge: 'X', tone: 'rejected' as const }
}

function formatCountdown(totalSeconds: number) {
  const safeSeconds = Math.max(0, totalSeconds)
  const days = Math.floor(safeSeconds / 86400)
  const hours = Math.floor((safeSeconds % 86400) / 3600)
  const minutes = Math.floor((safeSeconds % 3600) / 60)
  const seconds = safeSeconds % 60

  return [
    { label: 'Dias', value: String(days).padStart(2, '0') },
    { label: 'Horas', value: String(hours).padStart(2, '0') },
    { label: 'Min', value: String(minutes).padStart(2, '0') },
    { label: 'Seg', value: String(seconds).padStart(2, '0') },
  ]
}

function groupLabelValue(groupLabel: string | null | undefined) {
  if (!groupLabel) return ''
  const trimmed = groupLabel.trim()
  return trimmed.toUpperCase().startsWith('GRUPO ') ? trimmed.slice(6).trim().toUpperCase() : trimmed.toUpperCase()
}

function fullGroupLabel(groupLabel: string | null | undefined) {
  const value = groupLabelValue(groupLabel)
  return value ? `Grupo ${value}` : 'Sin grupo'
}

function matchTimeValue(dateValue: string) {
  const date = new Date(dateValue)
  return Number.isNaN(date.getTime()) ? Number.MAX_SAFE_INTEGER : date.getTime()
}

function getFavoriteTeam(match: TournamentMatch) {
  const homeTeam = match.homeTeam ?? match.home_team
  const awayTeam = match.awayTeam ?? match.away_team

  if (match.favorite_side === 'home') return homeTeam
  if (match.favorite_side === 'away') return awayTeam

  const homeRanking = homeTeam?.ranking_fifa
  const awayRanking = awayTeam?.ranking_fifa

  if (typeof homeRanking === 'number' && typeof awayRanking === 'number') {
    return homeRanking < awayRanking ? homeTeam : awayRanking < homeRanking ? awayTeam : null
  }

  return null
}

function teamBadgeText(name: string) {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase()
}

function userInitials(name: string | null | undefined) {
  if (!name) return 'SC'
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase()
}

function TeamBadge({
  team,
  featured = false,
}: {
  team: TournamentMatch['homeTeam'] | TournamentMatch['awayTeam'] | undefined
  featured?: boolean
}) {
  return (
    <div className={featured ? 'team-badge featured' : 'team-badge'}>
      {team?.provider_logo_url ? (
        <img alt={`Escudo de ${team.name}`} className="team-logo-image" src={team.provider_logo_url} />
      ) : team?.flag_url ? (
        <img alt={`Bandera de ${team.name}`} className="team-flag-image" src={team.flag_url} />
      ) : team?.flag_emoji ? (
        <span className="team-emoji">{team.flag_emoji}</span>
      ) : (
        <span className="team-fallback">{teamBadgeText(team?.name ?? 'SC')}</span>
      )}
    </div>
  )
}

function currentViewFromPath(pathname: string): MainView {
  if (pathname === CLIENT_VIEW_PATHS.facturas) return 'facturas'
  if (pathname === CLIENT_VIEW_PATHS.perfil) return 'perfil'
  if (pathname === CLIENT_VIEW_PATHS.reglas) return 'reglas'
  if (pathname === CLIENT_VIEW_PATHS.cuenta) return 'cuenta'
  return 'cancha'
}

const OFFICIAL_TERMS_TEXT = `TÉRMINOS Y CONDICIONES: POLLA MUNDIALISTA SUPER CARNES 2026

1. GENERALIDADES DEL CONCURSO
La promoción comercial denominada "Polla Mundialista Super Carnes" (en adelante, "El Concurso") es organizada por Super Carnes. El objetivo es premiar la fidelidad y el conocimiento futbolístico de nuestros clientes durante la Fase de Grupos de la Copa Mundial de la FIFA 2026.

2. PREMIOS
Se premiará a los participantes que acumulen la mayor cantidad de puntos al finalizar la Fase de Grupos. Cada ganador recibirá un premio definido por Super Carnes para esta promoción. Los premios no son transferibles ni canjeables por dinero en efectivo.

3. ELEGIBILIDAD Y PARTICIPACIÓN
Podrán participar personas naturales, mayores de 18 años, residentes en Panamá, que posean Cédula de Identidad Personal o Pasaporte vigente.
No podrán participar empleados directos de Super Carnes.
Para participar, el usuario debe registrarse en la plataforma a más tardar el día 10 de junio de 2026, proporcionando: nombre completo, cédula o pasaporte, correo electrónico, teléfono de contacto y su pronóstico del total de goles de la fase de grupos.

4. SISTEMA DE PUNTUACIÓN
Los participantes acumularán puntos según la precisión de sus pronósticos en los partidos de la Fase de Grupos, bajo la siguiente estructura:
1 punto: Por acertar la victoria del equipo "Favorito".
Nota aclaratoria sobre el favoritismo: se considerará como equipo "Favorito" en un partido a la selección nacional que ocupe la mejor posición, es decir, el número más bajo, en el Ranking Mundial Masculino de la FIFA vigente a la fecha de lanzamiento de esta promoción. Esta clasificación será la única fuente oficial para determinar los puntos de victoria y no se alterará durante la fase de grupos, independientemente de actualizaciones posteriores del ranking de la FIFA.
2 puntos: Por acertar que el resultado final del partido será un empate.
3 puntos: Por acertar la victoria del equipo "No Favorito".
3 puntos adicionales: Por acertar el marcador o resultado exacto del partido.
1 punto adicional por compras: Por ingresar en la plataforma el CUFE, Código Único de Facturación Electrónica, de una factura de compra realizada en Super Carnes por un monto mayor a $25.00, sin incluir ITBMS. La factura debe tener fecha de emisión de máximo un día anterior a la fecha en que se registra en la plataforma.

5. CRITERIOS DE DESEMPATE
En caso de que dos o más participantes culminen la Fase de Grupos con la misma puntuación total, afectando la selección de los ganadores, se aplicarán los siguientes filtros en estricto orden hasta obtener un ganador único:
Mayor cantidad de resultados exactos.
Mayor volumen de facturas válidas.
Mayor valor de compras acumulado en dólares entre todas las facturas válidas registradas.
Acierto global de goles: quien haya acertado o se haya acercado más, por exceso o defecto, a la cantidad total de goles anotados en toda la Fase de Grupos, según la predicción hecha al momento de registrarse.
Registro de tiempo: el participante que haya enviado y completado su registro de pronósticos primero, validado por la fecha, hora, minuto y segundo en el sistema de la plataforma.

6. NOTIFICACIÓN Y ENTREGA DEL PREMIO
Los ganadores oficiales serán anunciados el día 10 de julio de 2026 a través de las redes sociales oficiales de Super Carnes.
Los ganadores serán contactados vía correo electrónico y llamada telefónica. Si un potencial ganador no responde a los intentos de contacto en un plazo de 24 horas, perderá automáticamente el derecho al premio y Super Carnes adjudicará el mismo al siguiente participante en la tabla de posiciones.

7. PROTECCIÓN DE DATOS Y ACEPTACIÓN
Al registrarse, el participante acepta estos términos y condiciones, y autoriza a Super Carnes al uso de sus datos personales exclusivamente para fines de este concurso y contacto comercial, en cumplimiento de las leyes de protección de datos vigentes.

8. VALIDACIÓN DE FACTURAS (CUFE)
Toda factura ingresada para obtener puntos adicionales o para ser utilizada como criterio de desempate será verificada estrictamente contra el sistema de la Dirección General de Ingresos (DGI). Solo serán válidos los Códigos Únicos de Facturación Electrónica (CUFE) legítimos, que no hayan sido registrados previamente por otro participante, y que cumplan con los montos y fechas estipuladas. El intento de registro de un CUFE falso, alterado o perteneciente a otra persona resultará en la descalificación inmediata del participante.`

export function App() {
  const location = useLocation()
  const navigate = useNavigate()
  const [token, setToken] = useState<string | null>(localStorage.getItem(TOKEN_KEY))
  const [user, setUser] = useState<User | null>(null)
  const [authMode, setAuthMode] = useState<AuthMode>('login')
  const [predictionMode, setPredictionMode] = useState<PredictionMode>('pending')
  const [phases, setPhases] = useState<TournamentPhase[]>([])
  const [matches, setMatches] = useState<TournamentMatch[]>([])
  const [predictionsList, setPredictionsList] = useState<Prediction[]>([])
  const [invoices, setInvoices] = useState<RegisteredInvoice[]>([])
  const [clientOverview, setClientOverview] = useState<ClientBootstrap | null>(null)
  const [dashboardSnapshot, setDashboardSnapshot] = useState<DashboardSnapshot | null>(null)
  const [walletSnapshot, setWalletSnapshot] = useState<WalletSnapshot | null>(null)
  const [prizes, setPrizes] = useState<Prize[]>([])
  const [invoiceLookupValue, setInvoiceLookupValue] = useState('')
  const [invoiceEntryMode, setInvoiceEntryMode] = useState<InvoiceEntryMode>('scan')
  const [invoiceForm, setInvoiceForm] = useState<InvoiceFormState>({
    rawInput: '',
    invoice_number: '',
    purchase_amount: '',
    issued_at: '',
  })
  const [invoiceSubmitting, setInvoiceSubmitting] = useState(false)
  const [invoiceResolving, setInvoiceResolving] = useState(false)
  const [resolvedInvoiceData, setResolvedInvoiceData] = useState<ResolvedInvoiceData | null>(null)
  const [invoiceScannerError, setInvoiceScannerError] = useState<string | null>(null)
  const [invoiceScannerDebug, setInvoiceScannerDebug] = useState<InvoiceScannerDebugInfo>(() =>
    buildInvoiceScannerDebugInfo({ lastStage: 'idle' }),
  )
  const [invoiceGalleryProcessing, setInvoiceGalleryProcessing] = useState(false)
  const [sidebarOpen, setSidebarOpen] = useState(true)
  const [selectedGroupLabel, setSelectedGroupLabel] = useState<string | null>(null)
  const [predictionDrafts, setPredictionDrafts] = useState<Record<number, PredictionDraft>>({})
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [profileSaving, setProfileSaving] = useState(false)
  const [loading, setLoading] = useState(false)
  const [authBootstrapping, setAuthBootstrapping] = useState<boolean>(Boolean(localStorage.getItem(TOKEN_KEY)))
  const [registerStep, setRegisterStep] = useState(1)
  const [authBgVideoId, setAuthBgVideoId] = useState(DEFAULT_AUTH_BG_YOUTUBE_ID)
  const [authLogoUrl, setAuthLogoUrl] = useState(DEFAULT_AUTH_LOGO_URL)
  const [participantBrands, setParticipantBrands] = useState<ParticipantBrand[]>([])
  const [termsText, setTermsText] = useState(OFFICIAL_TERMS_TEXT)
  const [recaptchaSiteKey, setRecaptchaSiteKey] = useState('')
  const [termsModalOpen, setTermsModalOpen] = useState(false)
  const [termsScrolledEnd, setTermsScrolledEnd] = useState(false)
  const [savingPredictionIds, setSavingPredictionIds] = useState<number[]>([])
  const [now, setNow] = useState(() => Date.now())
  const invoiceScannerRef = useRef<InvoiceScannerRef | null>(null)
  const lastResolvedInvoiceCufeRef = useRef<string | null>(null)
  const [authForm, setAuthForm] = useState<AuthFormState>({
    full_name: '',
    document_type: 'cedula',
    cedula: '',
    email: '',
    phone: '',
    birthdate: '',
    resides_in_panama: true,
    is_employee: false,
    accepted_terms: false,
    group_stage_goal_prediction: '',
    password: '',
    password_confirmation: '',
  })
  const [registrationAvatarFile, setRegistrationAvatarFile] = useState<File | null>(null)
  const [registrationAvatarPreview, setRegistrationAvatarPreview] = useState<string | null>(null)
  const currentView = currentViewFromPath(location.pathname)
  const isAuthRoute = location.pathname === '/login'
  const currentViewLabel = CLIENT_VIEW_LABELS[currentView]
  const authCarouselBrands = useMemo(() => {
    const baseBrands = participantBrands.length > 0
      ? participantBrands
      : [
          { name: 'Super Carnes' },
          { name: 'Importadora Virzi' },
          { name: 'Marcas participantes' },
        ]
    const repetitionsPerLoop = Math.max(4, Math.ceil(12 / baseBrands.length))
    const loopBrands = Array.from({ length: repetitionsPerLoop }, () => baseBrands).flat()

    return [...loopBrands, ...loopBrands]
  }, [participantBrands])

  useEffect(() => {
    if (!registrationAvatarFile) {
      setRegistrationAvatarPreview(null)
      return
    }

    const objectUrl = URL.createObjectURL(registrationAvatarFile)
    setRegistrationAvatarPreview(objectUrl)

    return () => URL.revokeObjectURL(objectUrl)
  }, [registrationAvatarFile])

  useEffect(() => {
    api.get<{ auth_bg_youtube_id: string; auth_logo_url?: string; participant_brands?: string; terms_and_conditions?: string; recaptcha_site_key?: string }>('/public/settings')
      .then((res) => {
        if (res.data.auth_bg_youtube_id) setAuthBgVideoId(res.data.auth_bg_youtube_id)
        if (res.data.auth_logo_url) setAuthLogoUrl(res.data.auth_logo_url)
        if (res.data.terms_and_conditions?.trim()) setTermsText(res.data.terms_and_conditions)
        if (res.data.recaptcha_site_key) setRecaptchaSiteKey(res.data.recaptcha_site_key)
        setParticipantBrands(parseParticipantBrands(res.data.participant_brands))
      })
      .catch(() => null)
  }, [])

  useEffect(() => {
    if (!recaptchaSiteKey || document.querySelector('script[data-recaptcha="v3"]')) return

    const script = document.createElement('script')
    script.src = `https://www.google.com/recaptcha/api.js?render=${recaptchaSiteKey}`
    script.async = true
    script.defer = true
    script.dataset.recaptcha = 'v3'
    document.head.appendChild(script)
  }, [recaptchaSiteKey])

  useEffect(() => {
    setApiToken(token)

    if (!token) {
      setUser(null)
      setAuthBootstrapping(false)
      return
    }

    setAuthBootstrapping(true)
    void bootstrap().finally(() => setAuthBootstrapping(false))
  }, [token])

  useEffect(() => {
    if (authBootstrapping) return
    const isKnownClientRoute = Object.values(CLIENT_VIEW_PATHS).includes(location.pathname)

    if (!token) {
      if (!isAuthRoute) {
        navigate('/login', { replace: true })
      }
      return
    }

    if (user && isAuthRoute) {
      navigate(CLIENT_VIEW_PATHS.cancha, { replace: true })
      return
    }

    if (user && !isKnownClientRoute) {
      navigate(CLIENT_VIEW_PATHS.cancha, { replace: true })
    }
  }, [authBootstrapping, isAuthRoute, location.pathname, navigate, token, user])

  useEffect(() => {
    const timer = window.setInterval(() => {
      setNow(Date.now())
    }, 1000)

    return () => window.clearInterval(timer)
  }, [])

  useEffect(() => {
    if (!user || currentView !== 'facturas') return

    const inputs = Array.from(document.querySelectorAll('.client-shell .facturas-view input'))
    const observerTargets = Array.from(document.querySelectorAll('.client-shell .facturas-view section > div'))

    const handleFocus = (event: Event) => {
      const input = event.currentTarget as HTMLInputElement
      input.parentElement?.parentElement?.classList.add('pitch-glow')
    }

    const handleBlur = (event: Event) => {
      const input = event.currentTarget as HTMLInputElement
      input.parentElement?.parentElement?.classList.remove('pitch-glow')
    }

    inputs.forEach((input) => {
      input.addEventListener('focus', handleFocus)
      input.addEventListener('blur', handleBlur)
    })

    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('opacity-100', 'translate-y-0')
          entry.target.classList.remove('opacity-0', 'translate-y-4')
        }
      })
    }, { threshold: 0.1 })

    observerTargets.forEach((element) => {
      element.classList.add('transition-all', 'duration-700', 'opacity-0', 'translate-y-4')
      observer.observe(element)
    })

    return () => {
      inputs.forEach((input) => {
        input.removeEventListener('focus', handleFocus)
        input.removeEventListener('blur', handleBlur)
      })
      observer.disconnect()
    }
  }, [currentView, user])

  useEffect(() => {
    if (typeof window === 'undefined') return

    const media = window.matchMedia('(max-width: 767px)')
    const syncSidebar = () => setSidebarOpen(!media.matches)

    syncSidebar()
    media.addEventListener('change', syncSidebar)
    return () => media.removeEventListener('change', syncSidebar)
  }, [])

  useEffect(() => {
    if (currentView !== 'facturas') return

    const detectedCufe = extractInvoiceCufe(invoiceForm.rawInput)

    if (!detectedCufe) {
      lastResolvedInvoiceCufeRef.current = null
      setResolvedInvoiceData(null)
      return
    }

    if (lastResolvedInvoiceCufeRef.current === detectedCufe && resolvedInvoiceData?.cufe === detectedCufe) {
      return
    }

    let isCancelled = false

    async function resolveInvoiceData() {
      setInvoiceResolving(true)
      setInvoiceScannerError(null)

      try {
        const response = await api.post<{ data: ResolvedInvoiceData }>('/client/invoices/resolve', {
          qr_raw_text: detectedCufe,
        })

        if (isCancelled) return

        const resolvedData = response.data.data
        lastResolvedInvoiceCufeRef.current = resolvedData.cufe
        setResolvedInvoiceData(resolvedData)
        setInvoiceForm((current) => ({
          ...current,
          rawInput: resolvedData.cufe,
          invoice_number: resolvedData.invoice_number,
          purchase_amount: resolvedData.purchase_amount,
          issued_at: resolvedData.issued_at,
        }))
      } catch (resolutionError) {
        if (isCancelled) return

        lastResolvedInvoiceCufeRef.current = null
        setResolvedInvoiceData(null)
        setInvoiceScannerError(normalizeError(resolutionError))
      } finally {
        if (!isCancelled) {
          setInvoiceResolving(false)
        }
      }
    }

    void resolveInvoiceData()

    return () => {
      isCancelled = true
    }
  }, [currentView, invoiceForm.rawInput, resolvedInvoiceData?.cufe])

  useEffect(() => {
    let isMounted = true

    async function refreshScannerDiagnostics() {
      const cameraPermission = await getCameraPermissionState()
      if (!isMounted) return

      setInvoiceScannerDebug((current) =>
        buildInvoiceScannerDebugInfo({
          ...current,
          cameraPermission,
          lastStage: current.lastStage,
          lastError: current.lastError,
        }),
      )
    }

    void refreshScannerDiagnostics()

    return () => {
      isMounted = false
    }
  }, [])

  async function stopInvoiceScanner() {
    const activeScanner = invoiceScannerRef.current
    invoiceScannerRef.current = null

    if (!activeScanner) return

    await activeScanner
      .stop()
      .catch(() => null)
      .then(() => {
        try {
          activeScanner.clear()
        } catch {
          return null
        }

        return null
      })
  }

  useEffect(() => {
    if (currentView !== 'facturas' || invoiceEntryMode !== 'scan') return

    let isCancelled = false

    async function setupScanner() {
      const cameraPermission = await getCameraPermissionState()

      setInvoiceScannerDebug((current) =>
        buildInvoiceScannerDebugInfo({
          ...current,
          cameraPermission,
          lastStage: 'preflight',
          lastError: null,
        }),
      )

      if (typeof window === 'undefined' || !('mediaDevices' in navigator)) {
        setInvoiceScannerError('Tu navegador no permite abrir la camara. Puedes pegar el contenido del QR o escribir el CUFE manualmente.')
        setInvoiceScannerDebug((current) => ({
          ...current,
          lastStage: 'media-devices-missing',
          lastError: 'mediaDevices no disponible',
        }))
        return
      }

      if (!window.isSecureContext && !['localhost', '127.0.0.1', '::1'].includes(window.location.hostname)) {
        setInvoiceScannerError('La camara del navegador requiere HTTPS o localhost. En esta URL el escaner puede fallar; usa Subir desde galeria o abre el sitio con HTTPS.')
        setInvoiceScannerDebug((current) => ({
          ...current,
          lastStage: 'insecure-context',
          lastError: 'Contexto inseguro para acceso a camara',
        }))
        return
      }

      try {
        setInvoiceScannerError(null)

        if (isCancelled) return

        setInvoiceScannerDebug((current) => ({
          ...current,
          lastStage: 'starting-camera',
          lastError: null,
        }))

        const useNative = 'BarcodeDetector' in window
        let nativeStarted = false
        let nativeFormats: string[] = []

        if (useNative) {
          // Ruta nativa: full-frame 1080p, autofocus, sin crop — maneja QR densos
          try {
            // Filtrar a formatos soportados por el dispositivo antes de construir el detector
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            const BarcodeDetectorClass = (window as any).BarcodeDetector
            const wanted = ['qr_code', 'data_matrix', 'pdf417', 'aztec']
            try {
              const supported: string[] = await BarcodeDetectorClass.getSupportedFormats()
              nativeFormats = wanted.filter((f) => supported.includes(f))
              if (!nativeFormats.includes('qr_code')) nativeFormats = ['qr_code']
            } catch {
              nativeFormats = ['qr_code']
            }

            const nativeScanner = await startNativeBarcodeScanner(
              QR_READER_ELEMENT_ID,
              nativeFormats,
              (decodedText: string) => {
                if (isCancelled) return
                setInvoiceForm((current) => ({ ...current, rawInput: decodedText }))
                setInvoiceScannerDebug((current) => ({ ...current, lastStage: 'decoded-camera', lastError: null }))
                setInvoiceEntryMode('manual')
              },
              (errorMessage: string) => {
                setInvoiceScannerDebug((current) =>
                  current.lastStage === 'decoded-camera' ? current : { ...current, lastStage: 'scanning-camera', lastError: errorMessage },
                )
              },
              (resolution: string) => {
                setInvoiceScannerDebug((current) =>
                  current.lastStage === 'decoded-camera'
                    ? current
                    : { ...current, lastStage: 'scanning-camera', lastError: null, cameraResolution: resolution },
                )
              },
            )
            if (isCancelled) {
              await nativeScanner.stop()
              nativeScanner.clear()
              return
            }
            invoiceScannerRef.current = nativeScanner
            nativeStarted = true
          } catch {
            // BarcodeDetector no disponible o sin soporte suficiente — caer a html5-qrcode
          }
        }

        if (!nativeStarted) {
          // Fallback: html5-qrcode (Safari iOS y dispositivos sin BarcodeDetector)
          const scanner = createInvoiceScanner()
          invoiceScannerRef.current = scanner
          await scanner.start(
            { facingMode: { ideal: 'environment' } },
            buildInvoiceCameraScanConfig(),
            (decodedText: string) => {
              if (isCancelled) return
              setInvoiceForm((current) => ({ ...current, rawInput: decodedText }))
              setInvoiceScannerDebug((current) => ({ ...current, lastStage: 'decoded-camera', lastError: null }))
              setInvoiceEntryMode('manual')
            },
            (errorMessage: string) => {
              setInvoiceScannerDebug((current) =>
                current.lastStage === 'decoded-camera'
                  ? current
                  : {
                      ...current,
                      lastStage: 'scanning-camera',
                      lastError: errorMessage.includes('No MultiFormat Readers were able to detect the code')
                        ? current.lastError
                        : errorMessage,
                    },
              )
            },
          )
          void applyAutofocusToActiveStream()
        }

        setInvoiceScannerDebug((current) => ({
          ...current,
          lastStage: 'camera-active',
          lastError: null,
          scannerType: nativeStarted ? 'native' : 'html5-qrcode',
          activeFormats: nativeStarted ? nativeFormats : [],
        }))
      } catch (scannerError) {
        const message = scannerError instanceof Error ? scannerError.message : 'Error desconocido al iniciar la camara'
        setInvoiceScannerError('No se pudo iniciar el escaner. Revisa permisos, HTTPS/SSL en el telefono, o usa Subir desde galeria.')
        setInvoiceScannerDebug((current) => ({
          ...current,
          lastStage: 'camera-start-failed',
          lastError: message,
        }))
      }
    }

    void setupScanner()

    return () => {
      isCancelled = true
      void stopInvoiceScanner()
    }
  }, [currentView, invoiceEntryMode])

  async function handleInvoiceGalleryUpload(event: ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0]
    event.target.value = ''

    if (!file) return

    setInvoiceGalleryProcessing(true)
    setInvoiceScannerError(null)
    setInvoiceScannerDebug((current) => ({
      ...current,
      lastStage: 'gallery-selected',
      lastError: null,
    }))

    try {
      await stopInvoiceScanner()
      const scanner = createInvoiceScanner()

      try {
        const result = await scanner.scanFile(file, false)
        scanner.clear()

        setInvoiceForm((current) => ({
          ...current,
          rawInput: result,
        }))
        setInvoiceEntryMode('manual')
        setInvoiceScannerDebug((current) => ({
          ...current,
          lastStage: 'decoded-gallery',
          lastError: null,
        }))
      } catch (qrError) {
        try {
          scanner.clear()
        } catch {
          // no-op
        }

        setInvoiceScannerDebug((current) => ({
          ...current,
          lastStage: 'ocr-running',
          lastError: qrError instanceof Error ? qrError.message : 'Fallo la lectura directa del QR desde galeria',
        }))

        const extractedCufe = await extractInvoiceCufeViaOcr(file)

        if (!extractedCufe) {
          throw new Error('OCR no encontro un CUFE con patron FE/CS en la imagen')
        }

        setInvoiceForm((current) => ({
          ...current,
          rawInput: extractedCufe,
        }))
        setInvoiceEntryMode('manual')
        setInvoiceScannerDebug((current) => ({
          ...current,
          lastStage: 'decoded-ocr',
          lastError: null,
        }))
      }
    } catch (galleryError) {
      const message = galleryError instanceof Error ? galleryError.message : 'No se pudo leer la imagen seleccionada.'
      setInvoiceScannerError('No se pudo leer el QR ni extraer el CUFE por OCR. Asegurate de que la foto muestre la linea de CUFE con buena nitidez o pega el CUFE manualmente.')
      setInvoiceScannerDebug((current) => ({
        ...current,
        lastStage: 'gallery-failed',
        lastError: message,
      }))
    } finally {
      setInvoiceGalleryProcessing(false)
    }
  }

  const predictionMap = useMemo(
    () => new Map(predictionsList.map((prediction) => [prediction.match_id, prediction])),
    [predictionsList],
  )

  const groupStagePhase = useMemo(() => {
    const groupedPhase = phases.find((phase) => /grupo/i.test(phase.name) || /group/i.test(phase.name))
    return groupedPhase ?? phases[0] ?? null
  }, [phases])

  const groupStageMatches = useMemo(() => {
    const baseMatches = groupStagePhase ? matches.filter((match) => match.phase_id === groupStagePhase.id) : matches

    return baseMatches
      .filter((match) => Boolean(groupLabelValue(match.group_label)))
      .slice()
      .sort((left, right) => matchTimeValue(left.kickoff_at) - matchTimeValue(right.kickoff_at))
  }, [groupStagePhase, matches])

  const groupLabels = useMemo(() => {
    return Array.from(new Set(groupStageMatches.map((match) => groupLabelValue(match.group_label)).filter(Boolean))).sort((left, right) =>
      left.localeCompare(right, 'es', { numeric: true }),
    )
  }, [groupStageMatches])

  useEffect(() => {
    if (!groupLabels.length) {
      setSelectedGroupLabel(null)
      return
    }

    setSelectedGroupLabel((current) => (current && groupLabels.includes(current) ? current : groupLabels[0]))
  }, [groupLabels])

  const visibleMatches = useMemo(() => {
    const selectedGroupMatches = groupStageMatches.filter((match) => groupLabelValue(match.group_label) === selectedGroupLabel)

    if (predictionMode === 'mine') {
      return selectedGroupMatches.filter((match) => predictionMap.has(match.id))
    }

    return selectedGroupMatches.filter((match) => !predictionMap.has(match.id))
  }, [groupStageMatches, predictionMap, predictionMode, selectedGroupLabel])

  const progress = useMemo(() => {
    const total = groupStageMatches.length
    const completed = groupStageMatches.filter((match) => predictionMap.has(match.id)).length
    const percentage = total > 0 ? Math.round((completed / total) * 100) : 0

    return { total, completed, percentage }
  }, [groupStageMatches, predictionMap])

  const nextDeadlineMatch = useMemo(() => {
    return groupStageMatches.find((match) => !predictionMap.has(match.id) && matchTimeValue(match.kickoff_at) > now) ?? null
  }, [groupStageMatches, now, predictionMap])

  const countdownParts = useMemo(() => {
    if (!nextDeadlineMatch) return null

    const seconds = Math.floor((matchTimeValue(nextDeadlineMatch.kickoff_at) - now) / 1000)
    return formatCountdown(seconds)
  }, [nextDeadlineMatch, now])

  async function bootstrap() {
    try {
      const meResponse = await api.get('/auth/me')

      setUser(meResponse.data.user)

      const [phasesResponse, matchesResponse, predictionsResponse, overviewResponse, dashboardResponse, walletResponse, prizesResponse] = await Promise.all([
        api.get<PhasesResponse>('/client/phases'),
        api.get<MatchesResponse>('/client/matches'),
        api.get<PredictionsResponse>('/client/predictions'),
        api.get<ClientBootstrapResponse>('/client/bootstrap'),
        api.get<DashboardResponse>('/dashboard'),
        api.get<WalletResponse>('/wallet'),
        api.get<PrizesResponse>('/prizes/store'),
      ])

      const nextPhases = phasesResponse.data.data
      const nextMatches = matchesResponse.data.data
      const nextPredictions = predictionsResponse.data.data

      setPhases(nextPhases)
      setMatches(nextMatches)
      setPredictionsList(nextPredictions)
      setClientOverview({
        active_phase: overviewResponse.data.active_phase,
        phase_goals: Number(overviewResponse.data.phase_goals ?? 0),
        general_goals: Number(overviewResponse.data.general_goals ?? 0),
        leaderboard: overviewResponse.data.leaderboard ?? [],
      })
      setDashboardSnapshot(dashboardResponse.data)
      setWalletSnapshot(walletResponse.data)
      setPrizes(prizesResponse.data.data ?? [])

      const drafts = nextMatches.reduce<Record<number, PredictionDraft>>((accumulator, match) => {
        const existing = nextPredictions.find((prediction) => prediction.match_id === match.id)
        accumulator[match.id] = {
          home: existing ? String(existing.predicted_home_score) : '0',
          away: existing ? String(existing.predicted_away_score) : '0',
        }
        return accumulator
      }, {})

      setPredictionDrafts(drafts)
      await loadInvoices()
    } catch (bootstrapError) {
      const status = typeof bootstrapError === 'object' && bootstrapError
        ? (bootstrapError as { response?: { status?: number } }).response?.status
        : undefined

      if (status === 401 || status === 403) {
        persistToken(null)
        return
      }

      setError('La sesion sigue activa, pero no se pudieron cargar todos los datos. Recarga nuevamente en unos segundos.')
    }
  }

  async function loadInvoices() {
    try {
      const invoicesResponse = await api.get<InvoicesResponse>('/client/invoices')
      setInvoices(invoicesResponse.data.data ?? [])
    } catch {
      setInvoices([])
    }
  }

  function persistToken(nextToken: string | null) {
    if (nextToken) {
      localStorage.setItem(TOKEN_KEY, nextToken)
      setApiToken(nextToken)
      setToken(nextToken)
    } else {
      localStorage.removeItem(TOKEN_KEY)
      setApiToken(null)
      setToken(null)
    }
  }

  async function handleAuthSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setLoading(true)
    setError(null)
    setMessage(null)

    try {
      if (authMode === 'register') {
        const documentError = validateDocumentNumber(authForm.document_type, authForm.cedula)

        if (documentError) {
          throw new Error(documentError)
        }
      }

      const endpoint = authMode === 'login' ? '/auth/login' : '/auth/register'
      const response =
        authMode === 'login'
          ? await api.post(endpoint, { email: authForm.email, password: authForm.password })
          : await (async () => {
              if (!registrationAvatarFile) {
                throw new Error('Debes subir una foto para completar tu registro.')
              }

              const payload = new FormData()
              payload.append('full_name', authForm.full_name)
              payload.append('document_type', authForm.document_type)
              payload.append('cedula', normalizeIdentityNumber(authForm.document_type, authForm.cedula))
              payload.append('email', authForm.email)
              payload.append('phone', authForm.phone)
              payload.append('birthdate', authForm.birthdate)
              payload.append('resides_in_panama', authForm.resides_in_panama ? '1' : '0')
              payload.append('is_employee', authForm.is_employee ? '1' : '0')
              payload.append('accepted_terms', authForm.accepted_terms ? '1' : '0')
              payload.append('group_stage_goal_prediction', String(Number(authForm.group_stage_goal_prediction)))
              payload.append('password', authForm.password)
              payload.append('password_confirmation', authForm.password_confirmation)
              payload.append('avatar', registrationAvatarFile)
              const recaptcha = (window as unknown as { grecaptcha?: { ready: (cb: () => void) => void; execute: (key: string, opts: { action: string }) => Promise<string> } }).grecaptcha
              if (recaptchaSiteKey && recaptcha) {
                const recaptchaToken = await new Promise<string>((resolve) => {
                  recaptcha.ready(() => {
                    recaptcha.execute(recaptchaSiteKey, { action: 'register' }).then(resolve)
                  })
                })
                payload.append('recaptcha_token', recaptchaToken)
              }

              return api.post(endpoint, payload, {
                headers: {
                  'Content-Type': 'multipart/form-data',
                },
              })
            })()
      persistToken(response.data.token)
      setUser(response.data.user)
      setRegistrationAvatarFile(null)
      setMessage(authMode === 'login' ? 'Sesion iniciada.' : 'Registro completado.')
      navigate(CLIENT_VIEW_PATHS.cancha, { replace: true })
    } catch (authError) {
      if (
        authError instanceof Error &&
        (
          authError.message === 'Debes subir una foto para completar tu registro.' ||
          authError.message.includes('La cedula debe usar formato de Panama') ||
          authError.message.includes('El pasaporte debe ser alfanumerico') ||
          authError.message.includes('El documento de residente debe mezclar letras y numeros') ||
          authError.message.includes('Debes ingresar')
        )
      ) {
        setError(authError.message)
      } else {
        setError(normalizeError(authError))
      }
    } finally {
      setLoading(false)
    }
  }

  async function handleLogout() {
    try {
      await api.post('/auth/logout')
    } catch {
      // no-op
    } finally {
      persistToken(null)
      setUser(null)
      navigate('/login', { replace: true })
    }
  }

  async function handleProfileSave(payload: { email: string; phone: string; avatarFile: File | null }) {
    setProfileSaving(true)
    setError(null)
    setMessage(null)

    try {
      const formData = new FormData()
      formData.append('email', payload.email)
      formData.append('phone', payload.phone)

      if (payload.avatarFile) {
        formData.append('avatar', payload.avatarFile)
      }

      const response = await api.post('/auth/profile', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      })

      setUser(response.data.user)
      setMessage(response.data.message ?? 'Cuenta actualizada.')
    } catch (profileError) {
      setError(normalizeError(profileError))
    } finally {
      setProfileSaving(false)
    }
  }

  function navigateToView(target: MainView) {
    const nextPath = CLIENT_VIEW_PATHS[target]
    if (location.pathname !== nextPath) {
      navigate(nextPath)
    }
  }

  function updateScore(matchId: number, side: 'home' | 'away', value: string) {
    const sanitized = value.replace(/[^\d]/g, '').slice(0, 2)
    setPredictionDrafts((current) => ({
      ...current,
      [matchId]: {
        ...current[matchId],
        [side]: sanitized,
      },
    }))
  }

  async function handlePredictionSubmit(match: TournamentMatch) {
    setSavingPredictionIds((current) => [...current, match.id])
    setError(null)
    setMessage(null)

    try {
      await api.post(`/client/matches/${match.id}/predict`, {
        predicted_home_score: Number(predictionDrafts[match.id]?.home ?? 0),
        predicted_away_score: Number(predictionDrafts[match.id]?.away ?? 0),
      })

      setMessage(`Pronostico enviado para ${match.homeTeam?.name ?? match.home_team?.name ?? 'este partido'}.`)
      await bootstrap()
    } catch (predictionError) {
      setError(normalizeError(predictionError))
    } finally {
      setSavingPredictionIds((current) => current.filter((id) => id !== match.id))
    }
  }

  function updateInvoiceForm<K extends keyof InvoiceFormState>(field: K, value: InvoiceFormState[K]) {
    if (field !== 'rawInput') return

    lastResolvedInvoiceCufeRef.current = null
    setResolvedInvoiceData(null)
    setInvoiceForm((current) => ({
      ...current,
      [field]: value,
      ...(field === 'rawInput'
        ? {
            invoice_number: '',
            purchase_amount: '',
            issued_at: '',
          }
        : {}),
    }))
  }

  async function handleInvoiceSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setInvoiceSubmitting(true)
    setError(null)
    setMessage(null)

    try {
      await api.post('/client/invoices', {
        qr_raw_text: normalizeInvoiceQrPayload(invoiceForm.rawInput),
      })

      setMessage('Factura enviada para validacion.')
      lastResolvedInvoiceCufeRef.current = null
      setResolvedInvoiceData(null)
      setInvoiceForm({
        rawInput: '',
        invoice_number: '',
        purchase_amount: '',
        issued_at: '',
      })
      await bootstrap()
    } catch (invoiceError) {
      setError(normalizeError(invoiceError))
    } finally {
      setInvoiceSubmitting(false)
    }
  }

  function renderCancha() {
    const activeGroupTitle = fullGroupLabel(selectedGroupLabel)

    return (
      <>
        <section className="marea-phase-section">
          <div className="marea-phase-header">
            <div className="marea-headline-wrap">
              <span className="marea-kicker">SUPER CARNES 2026</span>
              <h1>{CONTEST_NAME}</h1>
            </div>

            <div className="marea-phase-meta">
              <div className="marea-progress-card">
                <div className="marea-progress-head">
                  <span>PROGRESO DE PRONOSTICOS</span>
                  <strong>
                    {progress.completed}/{progress.total} PARTIDOS
                  </strong>
                </div>
                <div className="marea-progress-track">
                  <div className="marea-progress-fill" style={{ width: `${progress.percentage}%` }} />
                </div>
              </div>
            </div>
          </div>

          <div className="marea-alert-banner">
            <span className="material-symbols-outlined">campaign</span>
            <div className="marea-alert-copy">
              <p>
                <strong>Atencion, seleccionado.</strong>{' '}
                {nextDeadlineMatch
                  ? `Tienes hasta el ${formatDateTime(nextDeadlineMatch.kickoff_at)} para enviar tus resultados de ${activeGroupTitle}.`
                  : `Registro legal hasta el ${REGISTRATION_DEADLINE}. Los ganadores se anuncian el ${WINNERS_ANNOUNCEMENT}.`}
              </p>
              {countdownParts ? (
                <div className="marea-countdown" aria-label="Cuenta regresiva para cierre de pronosticos">
                  {countdownParts.map((part) => (
                    <div key={part.label} className="marea-countdown-chip">
                      <strong>{part.value}</strong>
                      <span>{part.label}</span>
                    </div>
                  ))}
                </div>
              ) : null}
            </div>
          </div>

          <div className="marea-subnav-tabs">
            <button className={predictionMode === 'pending' ? 'subnav-tab active' : 'subnav-tab'} type="button" onClick={() => setPredictionMode('pending')}>
              PRONOSTICOS PENDIENTES
            </button>
            <button className={predictionMode === 'mine' ? 'subnav-tab active' : 'subnav-tab'} type="button" onClick={() => setPredictionMode('mine')}>
              MIS PRONOSTICOS
            </button>
          </div>

          <div className="marea-round-tabs">
            {groupLabels.map((groupLabel) => (
              <button
                key={groupLabel}
                className={groupLabel === selectedGroupLabel ? 'round-tab active' : 'round-tab'}
                type="button"
                onClick={() => setSelectedGroupLabel(groupLabel)}
              >
                {fullGroupLabel(groupLabel)}
              </button>
            ))}
          </div>
        </section>

        <section className="marea-group-stack">
          {visibleMatches.length ? (
            <div className="marea-match-grid">
              {visibleMatches.map((match) => {
                const homeTeam = match.homeTeam ?? match.home_team
                const awayTeam = match.awayTeam ?? match.away_team
                const favoriteTeam = getFavoriteTeam(match)
                const draft = predictionDrafts[match.id] ?? { home: '0', away: '0' }
                const prediction = predictionMap.get(match.id)
                const isSaving = savingPredictionIds.includes(match.id)

                return (
                  <article key={match.id} className={favoriteTeam ? 'marea-match-card featured' : 'marea-match-card'}>
                    <div className="marea-match-topline">
                      <div className="marea-match-banner">
                        <span>{fullGroupLabel(match.group_label)}</span>
                      </div>
                      <span className="marea-time">{formatDateTime(match.kickoff_at)}</span>
                    </div>

                    <div className="marea-match-banner meta">
                      <span>{match.venue_name ?? 'Sede por confirmar'}</span>
                      <span>{match.round_label ?? match.stage_label ?? 'Calendario oficial'}</span>
                    </div>

                    {favoriteTeam ? (
                      <div className="marea-favorite-ribbon">
                        <span className="material-symbols-outlined">workspace_premium</span>
                        <span>
                          Favorito: {favoriteTeam.name}
                          {typeof favoriteTeam.ranking_fifa === 'number' ? ` · Ranking FIFA #${favoriteTeam.ranking_fifa}` : ''}
                        </span>
                      </div>
                    ) : null}

                    <div className="marea-teams-row">
                      <div className="marea-team-col">
                        <TeamBadge team={homeTeam} featured={favoriteTeam?.id === homeTeam?.id} />
                        <span className="team-name">{homeTeam?.name ?? 'Local'}</span>
                      </div>

                      <div className="marea-score-inputs">
                        <input
                          aria-label={`Marcador local ${homeTeam?.name ?? 'Local'}`}
                          type="text"
                          inputMode="numeric"
                          value={draft.home}
                          onChange={(event) => updateScore(match.id, 'home', event.target.value)}
                        />
                        <span>-</span>
                        <input
                          aria-label={`Marcador visitante ${awayTeam?.name ?? 'Visitante'}`}
                          type="text"
                          inputMode="numeric"
                          value={draft.away}
                          onChange={(event) => updateScore(match.id, 'away', event.target.value)}
                        />
                      </div>

                      <div className="marea-team-col">
                        <TeamBadge team={awayTeam} featured={favoriteTeam?.id === awayTeam?.id} />
                        <span className="team-name">{awayTeam?.name ?? 'Visitante'}</span>
                      </div>
                    </div>

                    <div className="marea-betting-close">
                      <span className="material-symbols-outlined">warning</span>
                      <span>
                        {prediction ? 'PRONOSTICO ENVIADO' : 'CIERRE DE PRONOSTICOS'}: {formatUpperDate(match.kickoff_at).toUpperCase()} - {formatTime(match.kickoff_at)}
                      </span>
                    </div>

                    <div className="marea-card-action">
                      <button className="marea-card-send-button" disabled={isSaving || Boolean(prediction)} type="button" onClick={() => void handlePredictionSubmit(match)}>
                        <span>{prediction ? 'Pronostico enviado' : isSaving ? 'Enviando...' : 'Enviar pronostico'}</span>
                      </button>
                    </div>
                  </article>
                )
              })}
            </div>
          ) : (
            <article className="marea-match-card empty">
              <div className="marea-empty-copy">
                <span className="material-symbols-outlined">sports_soccer</span>
                <h3>{predictionMode === 'mine' ? 'Todavia no has enviado pronosticos en esta fase.' : 'No hay partidos pendientes en esta fase.'}</h3>
                <p>Cuando haya partidos habilitados para {activeGroupTitle} los veras aqui.</p>
              </div>
            </article>
          )}
        </section>
      </>
    )
  }

  function renderFacturas() {
    return (
      <InvoiceRegistrationView
        invoiceEntryMode={invoiceEntryMode}
        invoiceForm={invoiceForm}
        invoiceGalleryProcessing={invoiceGalleryProcessing}
        invoiceResolving={invoiceResolving}
        invoiceScannerError={invoiceScannerError}
        invoiceScannerDebug={invoiceScannerDebug}
        invoiceSubmitting={invoiceSubmitting}
        invoices={invoices}
        onFieldChange={updateInvoiceForm}
        onGalleryUpload={handleInvoiceGalleryUpload}
        onModeChange={setInvoiceEntryMode}
        onSubmit={handleInvoiceSubmit}
        resolvedInvoiceData={resolvedInvoiceData}
      />
    )

    const approvedInvoices = invoices.filter((invoice) => invoice.validation_status === 'approved')
    const totalInvoicePoints = approvedInvoices.reduce((total, invoice) => total + Number(invoice.points_awarded ?? 0), 0)
    const invoiceCards = invoices.length
      ? invoices.slice(0, 4).map((invoice) => {
          const status = invoiceStatusMeta(invoice.validation_status)
          const borderTone =
            status.tone === 'approved' ? 'border-primary-container' : status.tone === 'pending' ? 'border-secondary' : 'border-error'
          const iconTone =
            status.tone === 'approved'
              ? 'bg-primary-container/10 border-primary-container/20 text-primary-container'
              : status.tone === 'pending'
                ? 'bg-secondary/10 border-secondary/20 text-secondary'
                : 'bg-error/10 border-error/20 text-error'
          const scoreTone = status.tone === 'approved' ? 'text-primary-container' : status.tone === 'pending' ? 'text-secondary' : 'text-error'
          const badgeText = status.tone === 'approved' ? `+${Number(invoice.points_awarded ?? 0).toFixed(1)}` : status.tone === 'pending' ? '+0.0' : '0.0'
          const labelText = status.tone === 'approved' ? 'GOL VÁLIDO' : status.tone === 'pending' ? 'REVISIÓN VAR' : 'FUERA DE JUEGO'
          const iconName = status.tone === 'approved' ? 'verified' : status.tone === 'pending' ? 'update' : 'dangerous'
          const title = invoice.invoice_number ? `#${invoice.invoice_number}` : `#PAN-${String(invoice.id).padStart(6, '0')}`
          const referenceDate = invoice.issued_at ?? invoice.created_at
          const subtitle = `${referenceDate ? formatUpperDate(referenceDate) : 'Fecha pendiente'} • ${formatCurrency(invoice.purchase_amount)}`

          return (
            <div
              key={invoice.id}
              className={`bg-surface-container-lowest p-4 rounded-2xl border-l-4 ${borderTone} flex items-center justify-between hover:bg-surface-container transition-colors group`}
            >
              <div className="flex items-center gap-4">
                <div className={`w-12 h-12 rounded-full ${iconTone} flex items-center justify-center border`}>
                  <span className={`material-symbols-outlined ${scoreTone}`} data-weight="fill">
                    {iconName}
                  </span>
                </div>
                <div>
                  <div className="font-title-md text-on-surface">{title}</div>
                  <div className="text-body-sm text-on-surface-variant">{subtitle}</div>
                </div>
              </div>
              <div className="text-right">
                <div className={`${scoreTone} font-display-lg text-headline-lg`}>{badgeText}</div>
                <div className={`text-[10px] font-bold uppercase ${scoreTone} tracking-tighter`}>{labelText}</div>
              </div>
            </div>
          )
        })
      : null

    return (
      <div className="facturas-view">
        <div className="relative w-full rounded-3xl overflow-hidden mb-gutter h-48 md:h-64 flex items-end p-6 md:p-10 border border-outline-variant">
              <div className="absolute inset-0 z-0">
                <img
                  alt="Estadio Rommel Fernández"
                  className="w-full h-full object-cover opacity-50 scale-105"
                  src="https://lh3.googleusercontent.com/aida-public/AB6AXuBEx-hRFUMZ710fF7EatYLLO_SftyRg0ww2GvBNKWHSjPObe2Hu17fXzKDy8LOFbxMv93SOa0IWNTCINLfrcTI4Gv7Fb8T-KRHOU6iyLxekm6vci5QI1h6h-jtqFVtscsl4aPJJld2V-TOyhBaZNKlPweuhcxfvNwlUxFNiz07sFuBIttiDysG-4NIdDsaDGIygvIgQn-m1chePGiwL3D2k8IOl-CypudZp6J8U6ve38WWsbNyTIdWbQWlJlq2K7BKdk_nqv4a5KH8"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-background via-background/60 to-transparent"></div>
              </div>
              <div className="relative z-10">
                <h1 className="font-display-lg text-headline-lg md:text-display-lg text-on-surface leading-none mb-2">EL ENTRENAMIENTO NACIONAL</h1>
                <p className="text-secondary font-title-md text-title-md uppercase tracking-widest">Sube tus facturas y anota goles por Panamá</p>
              </div>
            </div>
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
          <section className="lg:col-span-5 flex flex-col gap-gutter">
                <div className="bg-surface-container-low p-6 rounded-[1.5rem] border border-outline-variant pitch-glow">
                  <div className="flex items-center gap-4 mb-6">
                    <div className="bg-primary-container/20 p-3 rounded-xl border border-primary-container/30">
                      <span className="material-symbols-outlined text-primary-container text-3xl" data-weight="fill">
                        confirmation_number
                      </span>
                    </div>
                    <div>
                      <h2 className="font-headline-lg text-headline-lg leading-none">REGISTRO</h2>
                      <p className="text-on-surface-variant text-body-sm">Ingresa los datos de tu ticket</p>
                    </div>
                  </div>
                  <form className="flex flex-col gap-6" onSubmit={(event) => event.preventDefault()}>
                    <div className="flex flex-col gap-2">
                      <label className="font-label-caps text-label-caps text-on-surface-variant ml-1">NÚMERO DE FACTURA</label>
                      <input
                        className="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 text-on-surface font-title-md transition-all"
                        placeholder="Ej: PAN-990234"
                        required
                        type="text"
                        value={invoiceLookupValue}
                        onChange={(event) => setInvoiceLookupValue(event.target.value)}
                      />
                    </div>
                    <div className="flex flex-col gap-2">
                      <label className="font-label-caps text-label-caps text-on-surface-variant ml-1">MONTO TOTAL ($)</label>
                      <input
                        className="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 text-on-surface font-title-md transition-all"
                        placeholder="0.00"
                        required
                        step="0.01"
                        type="number"
                      />
                    </div>
                    <div className="mt-4 flex flex-col gap-3">
                      <button className="bg-primary-container text-white font-display-lg text-headline-lg py-4 rounded-xl flex items-center justify-center gap-3 hover:opacity-90 active:scale-[0.98] transition-all group" type="submit">
                        <span>ANOTAR 0.5 GOLES</span>
                        <span className="material-symbols-outlined group-hover:translate-x-1 transition-transform">sports_soccer</span>
                      </button>
                      <p className="text-center text-body-sm text-on-surface-variant italic">Cada factura válida te acerca a la Copa.</p>
                    </div>
                  </form>
                </div>
                <div className="bg-surface-container rounded-[1.5rem] p-6 border-l-4 border-secondary flex items-start gap-4">
                  <span className="material-symbols-outlined text-secondary text-3xl">lightbulb</span>
                  <div>
                    <h3 className="font-title-md text-title-md text-secondary uppercase">TIP DEL CAPITÁN</h3>
                    <p className="text-body-sm text-on-surface-variant">
                      "No permitas que el VAR anule tu gol. Asegúrate de que el monto coincida exactamente con tu factura."
                    </p>
                  </div>
                </div>
          </section>
          <section className="lg:col-span-7">
                <div className="bg-surface-container-low rounded-[1.5rem] border border-outline-variant h-full flex flex-col overflow-hidden">
                  <div className="p-6 border-b border-outline-variant flex justify-between items-center">
                    <h2 className="font-headline-lg text-headline-lg">HISTORIAL DE PARTIDOS</h2>
                    <div className="flex gap-2">
                      <span className="bg-primary-container/10 text-primary-container px-3 py-1 rounded-full text-[10px] font-bold border border-primary-container/20 uppercase">
                        {totalInvoicePoints.toFixed(1)} Goles Totales
                      </span>
                    </div>
                  </div>
                  <div className="flex-1 overflow-y-auto p-4 space-y-3 max-h-[600px]">
                    {invoiceCards ?? (
                      <div className="bg-surface-container-lowest p-8 rounded-2xl border border-outline-variant text-center">
                        <div className="font-title-md text-on-surface mb-2">Sin facturas registradas todavía</div>
                        <div className="text-body-sm text-on-surface-variant">Cuando existan registros reales del cliente, aparecerán aquí sin datos de ejemplo.</div>
                      </div>
                    )}
                  </div>
                  <button className="w-full py-4 text-center text-on-surface-variant hover:text-on-surface font-label-caps transition-all border-t border-outline-variant uppercase" type="button">
                    {invoices.length ? `Ver ${invoices.length} facturas registradas` : 'Esperando facturas reales'}
                  </button>
                </div>
          </section>
        </div>
      </div>
    )
  }

  function renderMainView() {
    if (currentView === 'reglas') {
      return (
        <VitrinaView
          user={user!}
          walletSnapshot={walletSnapshot}
        />
      )
    }

    if (currentView === 'facturas') {
      return renderFacturas()
    }

    if (currentView === 'perfil') {
      return user ? (
        <VestuarioView
          dashboard={dashboardSnapshot}
          invoices={invoices}
          overview={clientOverview}
          predictions={predictionsList}
          prizes={prizes}
          user={user}
          walletSnapshot={walletSnapshot}
        />
      ) : null
    }

    if (currentView === 'cuenta') {
      return user ? <CuentaView user={user} saving={profileSaving} onSave={handleProfileSave} /> : null
    }

    return renderCancha()
  }

  const playerName = user?.full_name ?? 'Participante'
  const playerBadge = userInitials(user?.full_name)
  const playerAvatarUrl = user?.avatar_url ?? null
  const topNavButton = (target: MainView) =>
    `transition-colors duration-200 ${
      currentView === target ? 'text-primary-container font-bold border-b-2 border-primary-container pb-1' : 'text-on-surface-variant font-body-lg hover:text-primary'
    }`

  const sideNavButton = (target: MainView) =>
    currentView === target
      ? 'flex items-center gap-4 bg-primary-container text-white rounded-xl p-3 mx-2 transition-all'
      : 'flex items-center gap-4 text-on-surface-variant p-3 mx-2 hover:bg-surface-variant hover:text-on-surface transition-all hover:translate-x-1 duration-300'

  if (authBootstrapping) {
    return (
      <div className="marea-app-shell app-loading-shell">
        <section className="app-loading-panel">
          <p className="app-loading-kicker">SUPER CARNES 2026</p>
          <h1>{isAuthRoute ? 'Cargando acceso' : `Abriendo ${currentViewLabel}`}</h1>
          <p>
            {isAuthRoute
              ? 'Estamos validando tu sesion para entrar sin saltos visuales innecesarios.'
              : `Estamos validando tu sesion para llevarte directo a ${currentViewLabel}.`}
          </p>
          <div className="app-loading-progress" aria-hidden="true">
            <span />
          </div>
        </section>
      </div>
    )
  }

  const TERMS_TEXT = termsText.trim() ? termsText : OFFICIAL_TERMS_TEXT

  return (
    <div className="marea-app-shell">
      {message ? <div className="feedback success">{message}</div> : null}
      {error ? <div className="feedback error">{error}</div> : null}

      {/* Modal de Términos y Condiciones */}
      {termsModalOpen ? (
        <div className="terms-overlay" onClick={() => setTermsModalOpen(false)}>
          <div className="terms-modal" onClick={(e) => e.stopPropagation()}>
            <div className="terms-modal-header">
              <span className="material-symbols-outlined">gavel</span>
              <span>Términos y Condiciones</span>
              <button type="button" className="terms-modal-close" onClick={() => setTermsModalOpen(false)}>
                <span className="material-symbols-outlined">close</span>
              </button>
            </div>
            <div
              className="terms-modal-body"
              onScroll={(e) => {
                const el = e.currentTarget
                if (!termsScrolledEnd && el.scrollTop + el.clientHeight >= el.scrollHeight - 24) {
                  setTermsScrolledEnd(true)
                }
              }}
            >
              <pre className="terms-text">{TERMS_TEXT}</pre>
            </div>
            <div className="terms-modal-footer">
              {!termsScrolledEnd ? (
                <p className="terms-scroll-hint">
                  <span className="material-symbols-outlined">arrow_downward</span>
                  Desplázate hasta el final para habilitar la aceptación
                </p>
              ) : null}
              <button
                type="button"
                className="auth-submit"
                disabled={!termsScrolledEnd}
                onClick={() => {
                  setAuthForm((f) => ({ ...f, accepted_terms: true }))
                  setTermsModalOpen(false)
                }}
              >
                {termsScrolledEnd ? 'Acepto los términos y condiciones' : 'Lee el documento completo primero'}
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {!user ? (
        <section className="auth-shell">
          <div className="auth-ambient" aria-hidden="true" />
          {authBgVideoId ? (
            <div className="auth-hero-video">
              <iframe
                src={`https://www.youtube.com/embed/${authBgVideoId}?autoplay=1&mute=1&loop=1&controls=0&playlist=${authBgVideoId}&rel=0&modestbranding=1&iv_load_policy=3&disablekb=1&fs=0&playsinline=1`}
                allow="autoplay; encrypted-media"
                frameBorder={0}
                title="Video de fondo de la promocion"
              />
            </div>
          ) : null}
          <div className="auth-top-logo">
            {authLogoUrl ? <img alt="Logo de la promocion" src={authLogoUrl} /> : <span>Super Carnes</span>}
          </div>
          <div className="auth-hero">
            <div className="auth-hero-overlay" />
            <div className="auth-hero-content">
              <p className="auth-kicker">PROMOCION SUPER CARNES 2026</p>
              <h1 className="auth-campaign-title">
                <span>Panama va</span>
                <span>al Mundial</span>
                <span>y tu ganas</span>
              </h1>
              <p>Registra tus facturas, pronostica partidos y participa por premios durante la ruta mundialista.</p>
              <div className="auth-hero-chips" aria-label="Beneficios de la promocion">
                <span>Facturas</span>
                <span>Pronosticos</span>
                <span>Premios</span>
              </div>
              <div className="auth-promo-strip">
                <article>
                  <span>01</span>
                  <strong>Compra</strong>
                </article>
                <article>
                  <span>02</span>
                  <strong>Registra</strong>
                </article>
                <article>
                  <span>03</span>
                  <strong>Gana</strong>
                </article>
              </div>
            </div>
          </div>

          <form className="auth-panel" onSubmit={handleAuthSubmit}>
            <div className="auth-tabs">
              <button className={authMode === 'login' ? 'active' : ''} type="button" onClick={() => { setAuthMode('login'); setRegisterStep(1) }}>
                Iniciar sesión
              </button>
              <button className={authMode === 'register' ? 'active' : ''} type="button" onClick={() => { setAuthMode('register'); setRegisterStep(1) }}>
                Registrarse
              </button>
            </div>

            <div className="auth-panel-header">
              <p>{authMode === 'login' ? 'Acceso privado' : 'Nuevo participante'}</p>
              <h2>{authMode === 'login' ? 'Entra a la promo' : 'Unete a la promocion'}</h2>
              <span>
                {authMode === 'login'
                  ? 'Continua registrando facturas y jugando tus predicciones.'
                  : 'Completa tus datos para participar por premios de Super Carnes.'}
              </span>
            </div>

            {authMode === 'register' ? (
              <>
                {/* Indicador de pasos */}
                <div className="auth-steps-indicator">
                  {[1, 2, 3, 4].map((s) => (
                    <div key={s} className={`auth-step-dot${registerStep === s ? ' is-active' : ''}${registerStep > s ? ' is-done' : ''}`}>
                      {registerStep > s
                        ? <span className="material-symbols-outlined">check</span>
                        : s}
                    </div>
                  ))}
                </div>

                {/* ── Paso 1: Tu perfil ── */}
                {registerStep === 1 ? (
                  <>
                    <div className="auth-section-label">
                      <span className="material-symbols-outlined">person</span>
                      <span>Tu perfil</span>
                    </div>
                    <button
                      type="button"
                      className="auth-avatar-zone"
                      onClick={() => { document.getElementById('reg-avatar-input')?.click() }}
                    >
                      {registrationAvatarPreview ? (
                        <img alt="Vista previa del avatar" className="auth-avatar-zone-img" src={registrationAvatarPreview} />
                      ) : (
                        <span className="auth-avatar-zone-placeholder">
                          <span className="material-symbols-outlined">add_a_photo</span>
                          <span>Toca para subir tu foto de perfil</span>
                        </span>
                      )}
                    </button>
                    <input
                      accept="image/png,image/jpeg,image/webp"
                      id="reg-avatar-input"
                      style={{ display: 'none' }}
                      type="file"
                      onChange={async (event) => {
                        const file = event.target.files?.[0]
                        if (!file) return
                        try {
                          const cropped = await cropAvatarToSquare(file)
                          setRegistrationAvatarFile(cropped)
                        } catch {
                          setRegistrationAvatarFile(file)
                        }
                      }}
                    />
                    <div className="auth-field-grid">
                      <label>
                        Nombre completo *
                        <input
                          required
                          placeholder="Ej: María González"
                          value={authForm.full_name}
                          onChange={(event) => setAuthForm({ ...authForm, full_name: event.target.value })}
                        />
                      </label>
                      <label>
                        Teléfono *
                        <input
                          required
                          placeholder="+50761234567"
                          value={authForm.phone}
                          onChange={(event) => setAuthForm({ ...authForm, phone: normalizePanamaPhone(event.target.value) })}
                        />
                      </label>
                    </div>
                  </>
                ) : null}

                {/* ── Paso 2: Documento ── */}
                {registerStep === 2 ? (
                  <>
                    <div className="auth-section-label">
                      <span className="material-symbols-outlined">badge</span>
                      <span>Documento de identidad</span>
                    </div>
                    <div className="auth-doc-pills">
                      {(['cedula', 'passport', 'residente'] as const).map((dt) => (
                        <button
                          key={dt}
                          type="button"
                          className={authForm.document_type === dt ? 'active' : ''}
                          onClick={() => setAuthForm({ ...authForm, document_type: dt })}
                        >
                          <span className="material-symbols-outlined">
                            {dt === 'cedula' ? 'id_card' : dt === 'passport' ? 'flight' : 'description'}
                          </span>
                          {dt === 'cedula' ? 'Cédula' : dt === 'passport' ? 'Pasaporte' : 'Residente'}
                        </button>
                      ))}
                    </div>
                    <div className="auth-field-grid">
                      <label>
                        {documentNumberLabel(authForm.document_type)} *
                        <input
                          required
                          placeholder={documentNumberPlaceholder(authForm.document_type)}
                          value={authForm.cedula}
                          onChange={(event) => setAuthForm({ ...authForm, cedula: normalizeIdentityNumber(authForm.document_type, event.target.value) })}
                        />
                      </label>
                      <label>
                        Fecha de nacimiento *
                        <input
                          required
                          type="date"
                          max={(() => {
                            const d = new Date()
                            d.setFullYear(d.getFullYear() - 18)
                            return d.toISOString().split('T')[0]
                          })()}
                          value={authForm.birthdate}
                          onChange={(event) => {
                            setAuthForm({ ...authForm, birthdate: event.target.value })
                            if (event.target.value && !isAtLeast18(event.target.value)) {
                              setError('Debes ser mayor de 18 años para participar.')
                            } else {
                              setError(null)
                            }
                          }}
                        />
                      </label>
                    </div>
                  </>
                ) : null}

                {/* ── Paso 3: Pronóstico + Confirmaciones ── */}
                {registerStep === 3 ? (
                  <>
                    <div className="auth-section-label">
                      <span className="material-symbols-outlined">sports_soccer</span>
                      <span>Pronóstico de desempate</span>
                    </div>
                    <div className="auth-goal-stepper">
                      <button
                        type="button"
                        onClick={() =>
                          setAuthForm((f) => ({
                            ...f,
                            group_stage_goal_prediction: String(Math.max(0, Number(f.group_stage_goal_prediction || 0) - 1)),
                          }))
                        }
                      >
                        −
                      </button>
                      <input
                        className="auth-goal-input"
                        type="number"
                        inputMode="numeric"
                        min="0"
                        max="300"
                        placeholder="0"
                        value={authForm.group_stage_goal_prediction}
                        onChange={(event) => {
                          const raw = event.target.value.replace(/\D/g, '')
                          setAuthForm({ ...authForm, group_stage_goal_prediction: raw === '' ? '' : String(Math.min(300, Number(raw))) })
                        }}
                      />
                      <button
                        type="button"
                        onClick={() =>
                          setAuthForm((f) => ({
                            ...f,
                            group_stage_goal_prediction: String(Math.min(300, Number(f.group_stage_goal_prediction || 0) + 1)),
                          }))
                        }
                      >
                        +
                      </button>
                    </div>
                    <p className="auth-goal-hint">¿Cuántos goles totales habrá en la fase de grupos del Mundial 2026?</p>

                    <div className="auth-section-label">
                      <span className="material-symbols-outlined">check_circle</span>
                      <span>Confirmaciones</span>
                    </div>
                    <button
                      type="button"
                      className={`auth-toggle-card${authForm.resides_in_panama ? ' is-on' : ''}`}
                      onClick={() => setAuthForm((f) => ({ ...f, resides_in_panama: !f.resides_in_panama }))}
                    >
                      <span className="material-symbols-outlined auth-toggle-card-icon">location_on</span>
                      <div className="auth-toggle-card-text">
                        <strong>Resido en Panamá y soy mayor de 18 años</strong>
                        <span>Obligatorio para participar</span>
                      </div>
                      <div className="auth-toggle-switch" />
                    </button>
                    <button
                      type="button"
                      className={`auth-toggle-card${authForm.accepted_terms ? ' is-on' : ''}`}
                      onClick={() => { setTermsScrolledEnd(false); setTermsModalOpen(true) }}
                    >
                      <span className="material-symbols-outlined auth-toggle-card-icon">gavel</span>
                      <div className="auth-toggle-card-text">
                        <strong>
                          {authForm.accepted_terms ? 'Términos aceptados' : 'Leer y aceptar términos y condiciones'}
                        </strong>
                        <span>Obligatorio · Toca para leer el documento completo</span>
                      </div>
                      {authForm.accepted_terms
                        ? <span className="material-symbols-outlined" style={{ color: 'var(--secondary)', flexShrink: 0 }}>check_circle</span>
                        : <span className="material-symbols-outlined" style={{ color: 'rgba(225,226,236,0.4)', flexShrink: 0 }}>chevron_right</span>}
                    </button>
                  </>
                ) : null}

                {/* ── Paso 4: Cuenta ── */}
                {registerStep === 4 ? (
                  <div className="auth-section-label">
                    <span className="material-symbols-outlined">lock</span>
                    <span>Correo y contraseña</span>
                  </div>
                ) : null}
              </>
            ) : null}

            {/* Campos de email/contraseña: login siempre, registro solo en paso 4 */}
            {(authMode === 'login' || registerStep === 4) ? (
              <>
                <label>
                  Correo *
                  <input required type="email" value={authForm.email} onChange={(event) => setAuthForm({ ...authForm, email: event.target.value })} />
                </label>
                <label>
                  Contraseña *
                  <input required type="password" value={authForm.password} onChange={(event) => setAuthForm({ ...authForm, password: event.target.value })} />
                </label>
                {authMode === 'register' ? (
                  <label>
                    Confirmar contraseña *
                    <input
                      required
                      type="password"
                      value={authForm.password_confirmation}
                      onChange={(event) => setAuthForm({ ...authForm, password_confirmation: event.target.value })}
                    />
                  </label>
                ) : null}
              </>
            ) : null}

            {/* Navegación de pasos / submit */}
            {authMode === 'register' ? (
              <div className="auth-step-nav">
                {registerStep > 1 ? (
                  <button type="button" className="auth-step-back" onClick={() => setRegisterStep((s) => s - 1)}>
                    <span className="material-symbols-outlined">arrow_back</span>
                    Anterior
                  </button>
                ) : <span />}
                {registerStep < 4 ? (
                  <button
                    type="button"
                    className="auth-step-next"
                    onClick={() => {
                      setError(null)
                      if (registerStep === 1) {
                        if (!registrationAvatarFile) { setError('Debes subir tu foto de perfil.'); return }
                        if (!authForm.full_name.trim()) { setError('Debes ingresar tu nombre completo.'); return }
                        if (!authForm.phone.trim()) { setError('Debes ingresar tu número de teléfono.'); return }
                        if (!validatePanamaPhone(authForm.phone)) { setError('El telefono debe usar formato panameno +507 seguido de 8 digitos.'); return }
                      }
                      if (registerStep === 2) {
                        const docError = validateDocumentNumber(authForm.document_type, authForm.cedula)
                        if (docError) { setError(docError); return }
                        if (!authForm.birthdate) { setError('Debes ingresar tu fecha de nacimiento.'); return }
                        if (!isAtLeast18(authForm.birthdate)) { setError('Debes ser mayor de 18 años para participar.'); return }
                      }
                      if (registerStep === 3) {
                        if (!authForm.resides_in_panama) { setError('Debes confirmar que resides en Panamá.'); return }
                        if (!authForm.accepted_terms) { setError('Debes leer y aceptar los términos y condiciones.'); return }
                      }
                      setRegisterStep((s) => s + 1)
                    }}
                  >
                    Siguiente
                    <span className="material-symbols-outlined">arrow_forward</span>
                  </button>
                ) : (
                  <button className="auth-submit auth-submit-step" disabled={loading || !isAtLeast18(authForm.birthdate)} type="submit">
                    {loading ? 'Procesando...' : 'Completar registro'}
                  </button>
                )}
              </div>
            ) : (
              <button className="auth-submit" disabled={loading} type="submit">
                {loading ? 'Procesando...' : 'Entrar'}
              </button>
            )}

            <p className="auth-deadline">
              <span className="material-symbols-outlined">calendar_month</span>
              {authMode === 'register' ? `Registro hasta el ${REGISTRATION_DEADLINE}` : 'Mundialista · Super Carnes 2026'}
            </p>
          </form>
          <div className="auth-football-ticker" aria-hidden="true">
            <div className="auth-brand-track">
              {authCarouselBrands.map((brand, index) => (
                <span className="auth-brand-item" key={`${brand.name}-${index}`}>
                  {brand.logo_url ? <img alt="" src={brand.logo_url} /> : null}
                  <strong>{brand.name}</strong>
                </span>
            ))}
            </div>
          </div>
          <footer className="auth-footer">
            <strong>Mundialista 2026</strong>
            <nav aria-label="Legal">
              <button type="button" onClick={() => { setTermsScrolledEnd(false); setTermsModalOpen(true) }}>Terminos y Condiciones</button>
              <span>Privacidad</span>
              <span>Contacto</span>
            </nav>
            <small>Super Carnes</small>
          </footer>
        </section>
      ) : (
        <div className="client-shell bg-background text-on-background font-body-lg min-h-screen">
          <header className="bg-background/80 backdrop-blur-xl border-b border-outline-variant bg-surface-container-lowest/90 docked full-width top-0 sticky z-50 shadow-md">
            <div className="flex justify-between items-center px-4 md:px-margin-desktop w-full max-w-7xl mx-auto h-16">
              <div className="flex items-center gap-3">
                <button
                  className="material-symbols-outlined text-on-surface-variant hover:text-primary transition-all"
                  type="button"
                  onClick={() => setSidebarOpen((value) => !value)}
                >
                  menu
                </button>
                <div className="font-display-lg text-headline-lg text-primary-container tracking-tight">GOAL RUSH</div>
              </div>
              <nav className="hidden md:flex items-center gap-8">
                <button className={topNavButton('cancha')} type="button" onClick={() => navigateToView('cancha')}>
                  La Cancha
                </button>
                <button className={topNavButton('facturas')} type="button" onClick={() => navigateToView('facturas')}>
                  Entrenamiento
                </button>
                <button className={topNavButton('perfil')} type="button" onClick={() => navigateToView('perfil')}>
                  Vestuario
                </button>
                <button className={topNavButton('reglas')} type="button" onClick={() => navigateToView('reglas')}>
                  Vitrina
                </button>
              </nav>
              <div className="flex items-center gap-4">
                <button className="material-symbols-outlined text-on-surface-variant hover:text-primary transition-all" type="button">
                  notifications
                </button>
                <button className="material-symbols-outlined text-on-surface-variant hover:text-primary transition-all" type="button" onClick={() => navigateToView('cuenta')}>
                  person
                </button>
                <button className="bg-primary-container text-on-tertiary-container font-display-lg px-4 py-1 rounded-lg text-sm hover:opacity-80 active:scale-95 transition-all" type="button">
                  Mi Gaceta
                </button>
              </div>
            </div>
          </header>

          {sidebarOpen ? <button className="fixed inset-0 z-30 bg-black/45 md:hidden" type="button" onClick={() => setSidebarOpen(false)} /> : null}

          <div className="flex w-full">
            <aside
              className={
                sidebarOpen
                  ? 'fixed inset-y-16 left-0 z-40 flex w-64 flex-col bg-surface-container-low border-r border-outline-variant py-unit md:sticky md:top-16 md:h-[calc(100vh-64px)]'
                  : 'hidden'
              }
            >
              <div className="px-6 py-4 mb-4">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 rounded-full bg-secondary border-2 border-primary-container flex items-center justify-center font-display-lg text-sm text-on-secondary overflow-hidden">
                    {playerAvatarUrl ? <img alt={`Avatar de ${playerName}`} className="user-avatar-image" src={playerAvatarUrl} /> : playerBadge}
                  </div>
                  <div>
                    <div className="font-title-md text-title-md text-on-surface leading-tight">{playerName}</div>
                    <div className="font-label-caps text-label-caps text-primary-container">Facturas registradas: {invoices.length}</div>
                  </div>
                </div>
              </div>
              <nav className="flex-1 flex flex-col gap-1">
                <button className={sideNavButton('cancha')} type="button" onClick={() => navigateToView('cancha')}>
                  <span className="material-symbols-outlined">sports_soccer</span>
                  <span className="font-label-caps text-label-caps">La Cancha</span>
                </button>
                <button className={sideNavButton('facturas')} type="button" onClick={() => navigateToView('facturas')}>
                  <span className="material-symbols-outlined">receipt_long</span>
                  <span className="font-label-caps text-label-caps">Entrenamiento</span>
                </button>
                <button className={sideNavButton('reglas')} type="button" onClick={() => navigateToView('reglas')}>
                  <span className="material-symbols-outlined">storefront</span>
                  <span className="font-label-caps text-label-caps">Vitrina</span>
                </button>
                <button className={sideNavButton('perfil')} type="button" onClick={() => navigateToView('perfil')}>
                  <span className="material-symbols-outlined">checkroom</span>
                  <span className="font-label-caps text-label-caps">Vestuario</span>
                </button>
              </nav>
              <div className="mt-auto border-t border-outline-variant pt-4 flex flex-col gap-1">
                <button className={sideNavButton('cuenta')} type="button" onClick={() => navigateToView('cuenta')}>
                  <span className="material-symbols-outlined">settings</span>
                  <span className="font-label-caps text-label-caps">Ajustes</span>
                </button>
                <button className="flex items-center gap-4 text-error p-3 mx-2 hover:bg-error-container/20 transition-all" type="button" onClick={() => void handleLogout()}>
                  <span className="material-symbols-outlined">logout</span>
                  <span className="font-label-caps text-label-caps">Cerrar Sesión</span>
                </button>
              </div>
            </aside>

            <main className="flex-1 min-w-0">
              {currentView === 'facturas' ? <div className="max-w-7xl mx-auto p-margin-mobile md:p-margin-desktop">{renderFacturas()}</div> : (
                <>
                  <div className="marea-hero-background">
                    <img alt="Ambiente del estadio para la polla mundialista" src={STADIUM_IMAGE_URL} />
                    <div className="marea-hero-overlay" />
                  </div>
                  <div className="marea-content">{renderMainView()}</div>
                </>
              )}
            </main>
          </div>

          <nav className="md:hidden fixed bottom-0 left-0 right-0 h-16 bg-surface-container-lowest/90 backdrop-blur-xl border-t border-outline-variant flex justify-around items-center px-4 z-50">
            <button className={`flex flex-col items-center gap-1 ${currentView === 'cancha' ? 'text-primary-container' : 'text-on-surface-variant'}`} type="button" onClick={() => navigateToView('cancha')}>
              <span className="material-symbols-outlined">sports_soccer</span>
              <span className="text-[10px] font-bold">Cancha</span>
            </button>
            <button className={`flex flex-col items-center gap-1 ${currentView === 'facturas' ? 'text-primary-container' : 'text-on-surface-variant'}`} type="button" onClick={() => navigateToView('facturas')}>
              <span className="material-symbols-outlined" style={{ fontVariationSettings: "'FILL' 1" }}>
                receipt_long
              </span>
              <span className="text-[10px] font-bold">Entrena</span>
            </button>
            <button className={`flex flex-col items-center gap-1 ${currentView === 'perfil' ? 'text-primary-container' : 'text-on-surface-variant'}`} type="button" onClick={() => navigateToView('perfil')}>
              <span className="material-symbols-outlined">checkroom</span>
              <span className="text-[10px] font-bold">Vestuario</span>
            </button>
            <button className={`flex flex-col items-center gap-1 ${currentView === 'reglas' ? 'text-primary-container' : 'text-on-surface-variant'}`} type="button" onClick={() => navigateToView('reglas')}>
              <span className="material-symbols-outlined">storefront</span>
              <span className="text-[10px] font-bold">Vitrina</span>
            </button>
          </nav>

          <button className="fixed bottom-20 right-6 md:bottom-8 md:right-8 w-14 h-14 bg-primary-container text-white rounded-full shadow-2xl flex items-center justify-center hover:scale-110 active:scale-95 transition-all md:hidden z-40" type="button" onClick={() => setSidebarOpen((value) => !value)}>
            <span className="material-symbols-outlined text-3xl">{sidebarOpen ? 'close' : 'menu'}</span>
          </button>
        </div>
      )}
    </div>
  )
}
