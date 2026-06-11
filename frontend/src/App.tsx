import { useEffect, useMemo, useRef, useState } from 'react'
import type { ChangeEvent, FormEvent } from 'react'
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode'
import { useLocation, useNavigate } from 'react-router-dom'
import { createWorker, PSM } from 'tesseract.js'
import { api, setApiToken } from './api'
import { InvoiceRegistrationView } from './components/InvoiceRegistrationView'
import { TerminosPage } from './components/TerminosPage'
import { PrivacidadPage } from './components/PrivacidadPage'
import { ContactoPage } from './components/ContactoPage'
import { CuentaView } from './components/CuentaView'
import { ClientDemoTour } from './components/ClientDemoTour'
import { VestuarioView } from './components/VestuarioView'
import { VitrinaView } from './components/VitrinaView'
import { DEMO_TOUR_STEPS, loadDemoTourState, saveDemoTourState } from './demoTour'
import { cropAvatarToSquare, optimizeAvatarFile } from './utils/avatarUpload'
import type {
  Branch,
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
const REGISTRATION_DEADLINE = '10 de junio de 2026 a las 11:59 p. m.'
const WINNERS_ANNOUNCEMENT = 'dentro de los 5 dias calendario siguientes al cierre de cada ronda'
const PANAMA_TIMEZONE = 'America/Panama'
const DEFAULT_AUTH_BG_YOUTUBE_ID = import.meta.env.VITE_AUTH_BG_YOUTUBE_ID ?? 'O9diw9_5pys'
const DEFAULT_AUTH_LOGO_URL = import.meta.env.VITE_AUTH_LOGO_URL ?? ''
const FEEDBACK_DISMISS_MS = 15_000
const AUTH_REFERENCE_ASSETS = {
  logo: '/redesign/auth-logo-super-carnes.png',
  player: '/redesign/auth-player-left.png',
  mascot: '/redesign/auth-mascot-center.png',
  ball: '/redesign/auth-ball-center.png',
  stadium: '/redesign/auth-stadium-bg.jpg',
  crowd: '/redesign/auth-crowd-overlay.png',
  field: '/redesign/layerback.svg',
  confetti: '/redesign/auth-confetti-layer.svg',
}
const STADIUM_IMAGE_URL = AUTH_REFERENCE_ASSETS.stadium

type AuthMode = 'login' | 'register' | 'forgot-password' | 'reset-password'
type MainView = 'cancha' | 'reglas' | 'facturas' | 'perfil' | 'cuenta'
type PredictionMode = 'pending' | 'mine'
type InvoiceEntryMode = 'scan' | 'manual'
type AccountSection = 'perfil' | 'terminos'

interface PublicSettingsResponse {
  auth_bg_youtube_id: string
  auth_logo_url?: string
  header_logo_url?: string
  hero_video_url?: string
  participant_brands?: string
  terms_and_conditions?: string
  recaptcha_enabled?: boolean
  recaptcha_site_key?: string
  allow_google_auth?: boolean
  google_client_id?: string
  registration_deadline?: string
  theme_background?: string
  theme_surface_low?: string
  theme_surface?: string
  theme_surface_high?: string
  theme_primary?: string
  theme_secondary?: string
  theme_text_main?: string
  theme_outline_variant?: string
  show_scanner_debug?: boolean
  show_auth_ticker?: boolean
  contact_email?: string
  contact_phone?: string
  contact_address?: string
  contact_hours?: string
}

interface AuthResponse {
  token?: string
  user: User
  message?: string
  requires_registration_completion?: boolean
}

interface GenericMessageResponse {
  message: string
}

interface ParticipantBrand {
  name: string
  logo_url?: string
}

declare global {
  interface Window {
    google?: {
      accounts: {
        id: {
          initialize: (options: {
            client_id: string
            callback: (response: { credential: string }) => void
            auto_select?: boolean
            cancel_on_tap_outside?: boolean
          }) => void
          renderButton: (
            element: HTMLElement,
            options: {
              theme?: 'outline' | 'filled_blue' | 'filled_black'
              size?: 'large' | 'medium' | 'small'
              type?: 'standard' | 'icon'
              text?: 'signin_with' | 'signup_with' | 'continue_with'
              shape?: 'rectangular' | 'pill' | 'circle' | 'square'
              logo_alignment?: 'left' | 'center'
              width?: number
            },
          ) => void
        }
      }
    }
    grecaptcha?: {
      ready: (cb: () => void) => void
      render: (
        container: HTMLElement,
        parameters: {
          sitekey: string
          size: 'invisible'
          callback: (token: string) => void
          'error-callback': () => void
          'expired-callback': () => void
        },
      ) => number
      execute: (widgetId?: number) => void
      reset: (widgetId?: number) => void
    }
  }
}

const CLIENT_VIEW_PATHS: Record<MainView, string> = {
  cancha: '/cancha',
  facturas: '/entrenamiento',
  perfil: '/ranking',
  reglas: '/vitrina',
  cuenta: '/cuenta',
}

const CLIENT_VIEW_LABELS: Record<MainView, string> = {
  cancha: 'La Cancha',
  facturas: 'Entrenamiento',
  perfil: 'Ranking',
  reglas: 'Vitrina',
  cuenta: 'Mi Cuenta',
}

const VIEW_DEMO_TARGETS: Record<MainView, string> = {
  cancha: 'demo-view-cancha',
  facturas: 'demo-view-facturas',
  perfil: 'demo-view-perfil',
  reglas: 'demo-view-reglas',
  cuenta: 'demo-view-cuenta',
}

const ALL_GROUPS_KEY = '__all__'

function useTimedFeedback(initialValue: string | null = null): [string | null, (value: string | null) => void] {
  const [value, setValue] = useState<string | null>(initialValue)
  const [version, setVersion] = useState(0)

  function setTimedValue(nextValue: string | null) {
    setValue(nextValue)
    setVersion((current) => current + 1)
  }

  useEffect(() => {
    if (!value) return undefined

    const timeoutId = window.setTimeout(() => setValue(null), FEEDBACK_DISMISS_MS)

    return () => window.clearTimeout(timeoutId)
  }, [value, version])

  return [value, setTimedValue]
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
  totals?: InvoiceTotals
}

interface InvoiceTotals {
  goals?: number
  amount?: number
  phase_goals?: number
  phase_amount?: number
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
  branch_id: string
}

const EMPTY_AUTH_FORM: AuthFormState = {
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
  branch_id: '',
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
      // QR denso necesita el mayor Ã¡rea posible en pÃ­xeles
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

// Scanner nativo usando BarcodeDetector a resoluciÃ³n completa (sin crop/escala).
// Detecta QR densos que html5-qrcode no puede leer porque trabaja a resoluciÃ³n visual.
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

  // Intentar primero con alta resoluciÃ³n; si el dispositivo rechaza, reintentar sin constraints de res.
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

  // Autofocus continuo - opcional, ignorar si el dispositivo no lo soporta
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
        // createImageBitmap captura el frame a la resoluciÃ³n real de la cÃ¡mara (no la visual del elemento).
        // detect(video) en Chrome Android usa la resoluciÃ³n de renderizado CSS, que puede ser mucho menor.
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
  if (documentType === 'cedula') return '8-888-8888'
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

function getPanamaLocalPhone(value: string) {
  return value.startsWith('+507') ? value.slice(4, 12) : value.replace(/\D/g, '').slice(0, 8)
}

function validatePanamaPhone(value: string) {
  return /^\+507\d{8}$/.test(value)
}

function validateEmail(value: string) {
  if (!value.trim()) return 'Debes ingresar tu correo.'
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())) return 'Usa un correo valido, por ejemplo nombre@correo.com.'
  return null
}

function validatePassword(value: string) {
  if (!value) return 'Debes ingresar una contrasena.'
  if (value.length < 8) return 'La contrasena debe tener al menos 8 caracteres.'
  return null
}

function isRegistrationComplete(user: User | null) {
  if (!user) return false

  return Boolean(
    user.registration_completed_at &&
    user.accepted_terms_at &&
    user.birthdate &&
    user.resides_in_panama &&
    user.phone &&
    user.avatar_url &&
    user.group_stage_goal_prediction !== null &&
    user.group_stage_goal_prediction !== undefined &&
    user.cedula,
  )
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

function extractInvoiceCufeFromOcrText(value: string) {
  const normalizedText = value
    .toUpperCase()
    .replace(/[|]/g, 'I')
    .replace(/[â€œâ€"]/g, '')
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

function normalizeStageBucket(value: string | null | undefined) {
  return value?.trim().replace(/\s+/g, ' ') ?? ''
}

function matchBucket(match: TournamentMatch, phaseName?: string | null) {
  const groupValue = groupLabelValue(match.group_label)
  if (groupValue) {
    return {
      key: `group:${groupValue}`,
      label: fullGroupLabel(groupValue),
      selectorLabel: 'Grupo',
    }
  }

  const roundValue = normalizeStageBucket(match.round_label)
  if (roundValue) {
    return {
      key: `round:${roundValue.toLowerCase()}`,
      label: roundValue,
      selectorLabel: 'Llave',
    }
  }

  const stageValue = normalizeStageBucket(match.stage_label)
  if (stageValue) {
    return {
      key: `stage:${stageValue.toLowerCase()}`,
      label: stageValue,
      selectorLabel: 'Etapa',
    }
  }

  const fallback = normalizeStageBucket(phaseName) || 'Partidos'

  return {
    key: `phase:${fallback.toLowerCase()}`,
    label: fallback,
    selectorLabel: 'Fase',
  }
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

const OFFICIAL_TERMS_TEXT = `TÃ‰RMINOS Y CONDICIONES: POLLA MUNDIALISTA SUPER CARNES 2026

1. GENERALIDADES DEL CONCURSO
La promociÃ³n comercial denominada "PRONOSTICA EL MUNDIAL Y GANA" es organizada por Super Carnes y tendrÃ¡ vigencia desde el 4 de junio de 2026 hasta el 29 de junio de 2026.
Super Carnes podrÃ¡ modificar la vigencia por razones operativas, tÃ©cnicas, regulatorias o de fuerza mayor, previa comunicaciÃ³n al pÃºblico participante.

2. ELEGIBILIDAD
PodrÃ¡n participar Ãºnicamente personas naturales mayores de 18 aÃ±os, residentes en la RepÃºblica de PanamÃ¡, portadoras de cÃ©dula de identidad personal o pasaporte vigente, que completen correctamente el proceso de registro.
No podrÃ¡n participar colaboradores directos de Super Carnes, personas vinculadas directa o indirectamente con la organizaciÃ³n, administraciÃ³n o auditorÃ­a de la promociÃ³n, ni sus familiares dentro del cuarto grado de consanguinidad y segundo de afinidad.

3. MECÃNICA DE PARTICIPACIÃ“N
La promociÃ³n premia el conocimiento y habilidad de los participantes respecto a los resultados deportivos de los partidos habilitados en la plataforma oficial.
La primera ronda de pronÃ³sticos estarÃ¡ habilitada desde el 4 de junio de 2026 hasta el 10 de junio de 2026 a las 11:59 p. m.
La segunda ronda de pronÃ³sticos estarÃ¡ habilitada desde el 28 de junio de 2026 hasta el 29 de junio de 2026, una vez definidos los clasificados al cierre de la fase de grupos del 27 de junio de 2026.
Cada participante deberÃ¡ completar correctamente su registro en la plataforma oficial con nombre, documento, correo y telÃ©fono, ademÃ¡s de registrar sus pronÃ³sticos dentro de las fechas habilitadas para cada ronda.

4. NATURALEZA DEL CONCURSO
La promociÃ³n constituye un concurso basado en habilidad, conocimiento, anÃ¡lisis y destreza deportiva. La asignaciÃ³n de premios se determina exclusivamente conforme al sistema de puntuaciÃ³n establecido en estos tÃ©rminos y condiciones.

5. SISTEMA DE PUNTUACIÃ“N
Los participantes acumularÃ¡n puntos conforme a la precisiÃ³n de sus pronÃ³sticos en los partidos habilitados en cada ronda:
1 punto: Por acertar la victoria del equipo Favorito.
2 puntos: Por acertar que el partido finalizarÃ¡ en empate.
3 puntos: Por acertar la victoria del equipo No Favorito.
3 puntos adicionales: Por acertar el marcador exacto.
Para efectos exclusivos de la promociÃ³n, se considerarÃ¡ Favorito al equipo que ocupe la mejor posiciÃ³n en el Ranking Mundial Masculino de la FIFA vigente al inicio de la promociÃ³n.
1 punto adicional por compras: Por ingresar en la plataforma el CUFE de una factura de compra realizada en Super Carnes por un monto mayor a USD 25.00, sin incluir ITBMS. La factura debe haber sido emitida el mismo dÃ­a del registro o el dÃ­a calendario inmediatamente anterior.

6. PREMIOS
Los premios de cada ronda se otorgarÃ¡n de manera independiente.
Primera ronda: los participantes ubicados entre las posiciones 1 y 10 de la tabla de puntuaciÃ³n recibirÃ¡n 1 televisor nuevo de 50 pulgadas cada uno.
Primera ronda: los participantes ubicados entre las posiciones 11 y 110 de la tabla de puntuaciÃ³n recibirÃ¡n 1 balÃ³n original cada uno.
Segunda ronda: los 20 participantes con mayor cantidad de puntos recibirÃ¡n 1 tarjeta de regalo para compras en Super Carnes por USD 200.00 cada uno.
Los premios no son transferibles ni canjeables por dinero en efectivo.

7. CRITERIOS DE DESEMPATE
En caso de empate, se aplicarÃ¡n en este orden: mayor cantidad de marcadores exactos acertados, mayor cantidad de facturas vÃ¡lidas registradas, mayor monto acumulado en compras vÃ¡lidas, mayor aproximaciÃ³n al total de goles anotados en la primera ronda y fecha y hora de registro mÃ¡s temprana.

8. NOTIFICACIÃ“N Y ENTREGA DEL PREMIO
Los ganadores oficiales de cada ronda serÃ¡n anunciados dentro de los cinco dÃ­as calendario siguientes al cierre de la ronda correspondiente a travÃ©s de las redes sociales oficiales de Super Carnes.
Los ganadores serÃ¡n contactados vÃ­a correo electrÃ³nico y llamada telefÃ³nica. Si un potencial ganador no responde a los intentos de contacto dentro de los cinco dÃ­as calendario siguientes al primer intento, perderÃ¡ el derecho al premio y Super Carnes podrÃ¡ adjudicarlo al siguiente participante en la tabla de posiciones.

9. PROTECCIÃ“N DE DATOS Y ACEPTACIÃ“N
Al registrarse, el participante acepta estos tÃ©rminos y condiciones, y autoriza a Super Carnes al uso de sus datos personales exclusivamente para fines de este concurso y contacto comercial, en cumplimiento de las leyes de protecciÃ³n de datos vigentes.

8. VALIDACIÃ“N DE FACTURAS (CUFE)
Toda factura ingresada para obtener puntos adicionales o para ser utilizada como criterio de desempate serÃ¡ verificada estrictamente contra el sistema de la DirecciÃ³n General de Ingresos (DGI). Solo serÃ¡n vÃ¡lidos los CÃ³digos Ãšnicos de FacturaciÃ³n ElectrÃ³nica (CUFE) legÃ­timos, que no hayan sido registrados previamente por otro participante, y que cumplan con los montos y fechas estipuladas. El intento de registro de un CUFE falso, alterado o perteneciente a otra persona resultarÃ¡ en la descalificaciÃ³n inmediata del participante.`

const OFFICIAL_TERMS_TEXT_V2 = `TÃ‰RMINOS Y CONDICIONES: POLLA MUNDIALISTA SUPER CARNES 2026

1. GENERALIDADES DEL CONCURSO
La promociÃ³n comercial denominada "PRONOSTICA EL MUNDIAL Y GANA" es organizada por Super Carnes y tendrÃ¡ vigencia desde el 4 de junio de 2026 hasta el 29 de junio de 2026.
Super Carnes podrÃ¡ modificar la vigencia por razones operativas, tÃ©cnicas, regulatorias o de fuerza mayor, previa comunicaciÃ³n al pÃºblico participante.

2. ELEGIBILIDAD
PodrÃ¡n participar Ãºnicamente personas naturales mayores de 18 aÃ±os, residentes en la RepÃºblica de PanamÃ¡, portadoras de cÃ©dula de identidad personal o pasaporte vigente, que completen correctamente el proceso de registro.
No podrÃ¡n participar colaboradores directos de Super Carnes, personas vinculadas directa o indirectamente con la organizaciÃ³n, administraciÃ³n o auditorÃ­a de la promociÃ³n, ni sus familiares dentro del cuarto grado de consanguinidad y segundo de afinidad.

3. MECÃNICA DE PARTICIPACIÃ“N
La promociÃ³n premia el conocimiento y habilidad de los participantes respecto a los resultados deportivos de los partidos habilitados en la plataforma oficial.
La primera ronda de pronÃ³sticos estarÃ¡ habilitada desde el 4 de junio de 2026 hasta el 10 de junio de 2026 a las 11:59 p. m.
La segunda ronda de pronÃ³sticos estarÃ¡ habilitada desde el 28 de junio de 2026 hasta el 29 de junio de 2026, una vez definidos los clasificados al cierre de la fase de grupos del 27 de junio de 2026.
Cada participante deberÃ¡ completar correctamente su registro en la plataforma oficial con nombre, documento, correo y telÃ©fono, ademÃ¡s de registrar sus pronÃ³sticos dentro de las fechas habilitadas para cada ronda.

4. NATURALEZA DEL CONCURSO
La promociÃ³n constituye un concurso basado en habilidad, conocimiento, anÃ¡lisis y destreza deportiva. La asignaciÃ³n de premios se determina exclusivamente conforme al sistema de puntuaciÃ³n establecido en estos tÃ©rminos y condiciones.

5. SISTEMA DE PUNTUACIÃ“N
Los participantes acumularÃ¡n puntos conforme a la precisiÃ³n de sus pronÃ³sticos en los partidos habilitados en cada ronda:
- 1 punto por acertar la victoria del equipo Favorito.
- 2 puntos por acertar que el partido finalizarÃ¡ en empate.
- 3 puntos por acertar la victoria del equipo No Favorito.
- 3 puntos adicionales por acertar el marcador exacto.
Para efectos exclusivos de la promociÃ³n, se considerarÃ¡ Favorito al equipo que ocupe la mejor posiciÃ³n en el Ranking Mundial Masculino de la FIFA vigente al inicio de la promociÃ³n.
AdemÃ¡s, el participante podrÃ¡ acumular 1 punto adicional por cada factura vÃ¡lida registrada en la plataforma, siempre que la compra sea en Super Carnes, supere USD 25.00 sin ITBMS, que la factura haya sido emitida el mismo dÃ­a del registro o el dÃ­a calendario inmediatamente anterior, y que el CUFE sea vÃ¡lido.

6. VALIDACIÃ“N DE FACTURAS
Todas las facturas registradas serÃ¡n verificadas contra el sistema de la DirecciÃ³n General de Ingresos (DGI). Solo serÃ¡n vÃ¡lidas las facturas legÃ­timas, con CUFE verificable, no registradas previamente y que cumplan con los montos y fechas requeridas.
El intento de usar facturas falsas, alteradas, duplicadas o pertenecientes a terceros constituye causal inmediata de descalificaciÃ³n.

7. PREMIOS
Los premios de cada ronda se otorgarÃ¡n de manera independiente.
Al finalizar la primera ronda, los participantes ubicados entre las posiciones 1 y 10 de la tabla de puntuaciÃ³n recibirÃ¡n 1 televisor nuevo de 50 pulgadas cada uno.
Al finalizar la primera ronda, los participantes ubicados entre las posiciones 11 y 110 de la tabla de puntuaciÃ³n recibirÃ¡n 1 balÃ³n original cada uno.
Al finalizar la segunda ronda, los 20 participantes con mayor cantidad de puntos recibirÃ¡n 1 tarjeta de regalo para compras en Super Carnes por USD 200.00 cada uno.
Los premios no son transferibles, no son canjeables por dinero en efectivo y no podrÃ¡n ser sustituidos por otros bienes o servicios. Los ganadores podrÃ¡n reclamar su premio dentro de los cinco dÃ­as calendario posteriores al anuncio oficial de la ronda correspondiente, presentando su documento de identidad.

8. CRITERIOS DE DESEMPATE
En caso de empate, se aplicarÃ¡n sucesivamente estos criterios:
1. Mayor cantidad de marcadores exactos acertados.
2. Mayor cantidad de facturas vÃ¡lidas registradas.
3. Mayor monto acumulado en compras vÃ¡lidas.
4. Mayor aproximaciÃ³n al total de goles anotados en la primera ronda, aplicable solo para desempates de la primera ronda.
5. Fecha y hora de registro mÃ¡s temprana en el sistema oficial de la plataforma.

9. NOTIFICACIÃ“N Y ENTREGA DE PREMIOS
Los ganadores oficiales de cada ronda serÃ¡n anunciados dentro de los cinco dÃ­as calendario siguientes al cierre de la ronda correspondiente, a travÃ©s de las redes sociales oficiales de Super Carnes.
AdemÃ¡s, serÃ¡n contactados vÃ­a telefÃ³nica y/o correo electrÃ³nico. Si un ganador potencial no responde dentro de los cinco dÃ­as calendario siguientes al primer intento de contacto, perderÃ¡ el derecho al premio y Super Carnes podrÃ¡ adjudicarlo al siguiente participante con mayor puntuaciÃ³n.

10. DESCALIFICACIÃ“N
Super Carnes podrÃ¡ descalificar inmediatamente a cualquier participante que incumpla estos tÃ©rminos y condiciones, proporcione informaciÃ³n falsa o incompleta, intente manipular la plataforma o el sistema de puntuaciÃ³n, registre facturas fraudulentas o pertenecientes a terceros, o realice actos que afecten la transparencia o integridad de la promociÃ³n.

11. PROTECCIÃ“N DE DATOS PERSONALES
Los datos personales suministrados serÃ¡n utilizados exclusivamente para la administraciÃ³n, desarrollo y ejecuciÃ³n de la promociÃ³n, asÃ­ como para la validaciÃ³n de identidad y entrega de premios, de conformidad con la Ley 81 de 2019 y demÃ¡s normas aplicables de la RepÃºblica de PanamÃ¡.

12. ACEPTACIÃ“N DE LOS TÃ‰RMINOS Y CONDICIONES
La participaciÃ³n en la promociÃ³n implica el conocimiento, aceptaciÃ³n plena e incondicional de los presentes tÃ©rminos y condiciones.`

function normalizeBrandColor(prop: string, value: string) {
  const normalized = value.trim().toLowerCase()
  const legacyPrimaryReds = new Set(['#da291c', '#e3261d', '#ff3349', '#c1122a', '#8f0d1d', '#f53003', '#f61500'])
  const legacyRedOutlines = new Set(['#5c403b', '#ac8883'])

  if (prop === '--primary-container' && legacyPrimaryReds.has(normalized)) {
    return '#007aff'
  }

  if (prop === '--outline-variant' && legacyRedOutlines.has(normalized)) {
    return '#245d9f'
  }

  return value.trim()
}

function applyTheme(vars: Record<string, string | undefined>) {
  const root = document.documentElement
  Object.entries(vars).forEach(([prop, value]) => {
    if (value?.trim()) {
      root.style.setProperty(prop, normalizeBrandColor(prop, value))
    }
  })
}

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
  const [invoiceTotals, setInvoiceTotals] = useState<InvoiceTotals | null>(null)
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
  const [resolvedInvoiceData, setResolvedInvoiceData] = useState<ResolvedInvoiceData | null>(null)
  const [invoiceScannerError, setInvoiceScannerError] = useTimedFeedback()
  const [invoiceScannerDebug, setInvoiceScannerDebug] = useState<InvoiceScannerDebugInfo>(() =>
    buildInvoiceScannerDebugInfo({ lastStage: 'idle' }),
  )
  const [invoiceScannerActivated, setInvoiceScannerActivated] = useState(false)
  const [invoiceGalleryProcessing, setInvoiceGalleryProcessing] = useState(false)
  const [sidebarOpen, setSidebarOpen] = useState(true)
  const [userMenuOpen, setUserMenuOpen] = useState(false)
  const [mobileUserSidebarOpen, setMobileUserSidebarOpen] = useState(false)
  const [selectedGroupLabel, setSelectedGroupLabel] = useState<string | null>(null)
  const [predictionDrafts, setPredictionDrafts] = useState<Record<number, PredictionDraft>>({})
  const [message, setMessage] = useTimedFeedback()
  const [error, setError] = useTimedFeedback()
  const [profileSaving, setProfileSaving] = useState(false)
  const [loading, setLoading] = useState(false)
  const [authBootstrapping, setAuthBootstrapping] = useState<boolean>(Boolean(localStorage.getItem(TOKEN_KEY)))
  const [registerStep, setRegisterStep] = useState(1)
  const [resetPasswordEmail, setResetPasswordEmail] = useState('')
  const [resetPasswordToken, setResetPasswordToken] = useState('')
  const [resetPassword, setResetPassword] = useState('')
  const [resetPasswordConfirmation, setResetPasswordConfirmation] = useState('')
  const [newsletterEmail, setNewsletterEmail] = useState('')
  const [, setAuthBgVideoId] = useState(DEFAULT_AUTH_BG_YOUTUBE_ID)
  const [authLogoUrl, setAuthLogoUrl] = useState(DEFAULT_AUTH_LOGO_URL)
  const [headerLogoUrl, setHeaderLogoUrl] = useState('')
  const [heroVideoUrl, setHeroVideoUrl] = useState('')
  const [, setParticipantBrands] = useState<ParticipantBrand[]>([])
  const [termsText, setTermsText] = useState(OFFICIAL_TERMS_TEXT_V2 || OFFICIAL_TERMS_TEXT)
  const [recaptchaEnabled, setRecaptchaEnabled] = useState(true)
  const [recaptchaSiteKey, setRecaptchaSiteKey] = useState('')
  const [recaptchaScriptReady, setRecaptchaScriptReady] = useState(false)
  const [googleAuthEnabled, setGoogleAuthEnabled] = useState(false)
  const [googleClientId, setGoogleClientId] = useState(import.meta.env.VITE_GOOGLE_CLIENT_ID ?? '')
  const [showScannerDebug, setShowScannerDebug] = useState(false)
  const [, setShowAuthTicker] = useState(true)
  const [contactInfo, setContactInfo] = useState<{ contact_email?: string; contact_phone?: string; contact_address?: string; contact_hours?: string }>({})
  const [predictionCelebration, setPredictionCelebration] = useState<string | null>(null)
  const [termsModalOpen, setTermsModalOpen] = useState(false)
  const [termsScrolledEnd, setTermsScrolledEnd] = useState(false)
  const [accountSection, setAccountSection] = useState<AccountSection>('perfil')
  const [savingPredictionIds, setSavingPredictionIds] = useState<number[]>([])
  const [now, setNow] = useState(() => Date.now())
  const [demoTourState, setDemoTourState] = useState(() => loadDemoTourState())
  const invoiceScannerRef = useRef<InvoiceScannerRef | null>(null)

  const recaptchaContainerRef = useRef<HTMLDivElement | null>(null)
  const recaptchaWidgetIdRef = useRef<number | null>(null)
  const recaptchaResolveRef = useRef<((token: string) => void) | null>(null)
  const recaptchaRejectRef = useRef<((reason?: unknown) => void) | null>(null)
  const googleButtonRef = useRef<HTMLDivElement | null>(null)
  const authHeroRef = useRef<HTMLDivElement | null>(null)
  const userMenuRef = useRef<HTMLDivElement | null>(null)
  const mobileUserSidebarRef = useRef<HTMLDivElement | null>(null)
  const [authForm, setAuthForm] = useState<AuthFormState>(EMPTY_AUTH_FORM)
  const [branches, setBranches] = useState<Branch[]>([])
  const [registrationAvatarFile, setRegistrationAvatarFile] = useState<File | null>(null)
  const [registrationAvatarPreview, setRegistrationAvatarPreview] = useState<string | null>(null)
  const currentView = currentViewFromPath(location.pathname)
  const isAuthRoute = location.pathname === '/login'
  const authSearchParams = useMemo(() => new URLSearchParams(location.search), [location.search])
  const resetPasswordTokenFromUrl = authSearchParams.get('token') ?? ''
  const newsletterTokenFromUrl = authSearchParams.get('newsletter_token') ?? ''
  const isPublicPage = ['/terminos', '/privacidad', '/contacto'].includes(location.pathname)
  const isCompletingGoogleRegistration = Boolean(token && user && !isRegistrationComplete(user))
  const totalRegisterSteps = isCompletingGoogleRegistration ? 4 : 5
  const currentViewLabel = CLIENT_VIEW_LABELS[currentView]
  const demoTourStep = demoTourState.status === 'in_progress'
    ? DEMO_TOUR_STEPS[Math.min(demoTourState.stepIndex, DEMO_TOUR_STEPS.length - 1)] ?? null
    : null
  const showDemoTourBanner = Boolean(
    user
    && !isAuthRoute
    && demoTourState.status === 'not_started'
    && !authBootstrapping,
  )
  const registrationStepErrors = useMemo(() => {
    const errors: Record<string, string> = {}

    if (registerStep === 1) {
      if (!registrationAvatarFile) errors.avatar = 'Debes subir tu foto de perfil.'
      if (!authForm.full_name.trim()) errors.full_name = 'Debes ingresar tu nombre completo.'
      if (!authForm.phone.trim()) {
        errors.phone = 'Debes ingresar los 8 digitos de tu telefono.'
      } else if (!validatePanamaPhone(authForm.phone)) {
        errors.phone = 'Ingresa 8 digitos validos despues del prefijo +507. Ejemplo: 61234567.'
      }
    }

    if (registerStep === 2) {
      const documentError = validateDocumentNumber(authForm.document_type, authForm.cedula)
      if (documentError) errors.cedula = documentError
      if (!authForm.birthdate) {
        errors.birthdate = 'Debes ingresar tu fecha de nacimiento.'
      } else if (!isAtLeast18(authForm.birthdate)) {
        errors.birthdate = 'Debes ser mayor de 18 anos para participar.'
      }
    }

    if (registerStep === 3) {
      if (!authForm.group_stage_goal_prediction.trim()) errors.group_stage_goal_prediction = 'Debes ingresar tu pronostico de desempate.'
      if (!authForm.branch_id) errors.branch_id = 'Debes seleccionar tu sucursal de preferencia.'
    }

    if (registerStep === 4) {
      if (!authForm.resides_in_panama) errors.resides_in_panama = 'Debes confirmar que resides en Panama.'
      if (!authForm.accepted_terms) errors.accepted_terms = 'Debes leer y aceptar los terminos y condiciones.'
    }

    if (!isCompletingGoogleRegistration && registerStep === 5) {
      const emailError = validateEmail(authForm.email)
      const passwordError = validatePassword(authForm.password)
      if (emailError) errors.email = emailError
      if (passwordError) errors.password = passwordError
      if (!authForm.password_confirmation) {
        errors.password_confirmation = 'Debes confirmar tu contrasena.'
      } else if (authForm.password_confirmation !== authForm.password) {
        errors.password_confirmation = 'Las contrasenas deben coincidir.'
      }
    }

    return errors
  }, [authForm, isCompletingGoogleRegistration, registerStep, registrationAvatarFile])
  const registrationStepValid = Object.keys(registrationStepErrors).length === 0

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
    if (!userMenuOpen) return

    function handlePointerDown(event: MouseEvent) {
      if (!userMenuRef.current?.contains(event.target as Node)) {
        setUserMenuOpen(false)
      }
    }

    function handleEscape(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        setUserMenuOpen(false)
      }
    }

    document.addEventListener('mousedown', handlePointerDown)
    document.addEventListener('keydown', handleEscape)

    return () => {
      document.removeEventListener('mousedown', handlePointerDown)
      document.removeEventListener('keydown', handleEscape)
    }
  }, [userMenuOpen])

  useEffect(() => {
    setUserMenuOpen(false)
    setMobileUserSidebarOpen(false)
  }, [location.pathname])

  useEffect(() => {
    if (!mobileUserSidebarOpen) return
    function handleEscape(event: KeyboardEvent) {
      if (event.key === 'Escape') setMobileUserSidebarOpen(false)
    }
    document.addEventListener('keydown', handleEscape)
    return () => document.removeEventListener('keydown', handleEscape)
  }, [mobileUserSidebarOpen])

  useEffect(() => {
    api.get<PublicSettingsResponse>('/public/settings')
      .then((res) => {
        if (res.data.auth_bg_youtube_id) setAuthBgVideoId(res.data.auth_bg_youtube_id)
        if (res.data.auth_logo_url) setAuthLogoUrl(res.data.auth_logo_url)
        if (res.data.header_logo_url) setHeaderLogoUrl(res.data.header_logo_url)
        if (res.data.hero_video_url) setHeroVideoUrl(res.data.hero_video_url)
        if (res.data.terms_and_conditions?.trim()) setTermsText(res.data.terms_and_conditions)
        setRecaptchaEnabled(res.data.recaptcha_enabled !== false)
        setRecaptchaSiteKey(res.data.recaptcha_site_key ?? '')
        setGoogleAuthEnabled(Boolean(res.data.allow_google_auth))
        if (res.data.google_client_id?.trim()) setGoogleClientId(res.data.google_client_id)
        setParticipantBrands(parseParticipantBrands(res.data.participant_brands))
        applyTheme({
          '--background':       res.data.theme_background,
          '--surface-low':      res.data.theme_surface_low,
          '--surface':          res.data.theme_surface,
          '--surface-high':     res.data.theme_surface_high,
          '--primary-container': res.data.theme_primary,
          '--secondary':        res.data.theme_secondary,
          '--text-main':        res.data.theme_text_main,
          '--outline-variant':  res.data.theme_outline_variant,
        })
        setShowScannerDebug(Boolean(res.data.show_scanner_debug))
        setShowAuthTicker(res.data.show_auth_ticker !== false)
        setContactInfo({
          contact_email: res.data.contact_email,
          contact_phone: res.data.contact_phone,
          contact_address: res.data.contact_address,
          contact_hours: res.data.contact_hours,
        })
      })
      .catch(() => null)

    api.get<{ data: Branch[] }>('/public/branches')
      .then((res) => setBranches(res.data.data ?? []))
      .catch(() => null)
  }, [])

  useEffect(() => {
    if (!recaptchaEnabled || !recaptchaSiteKey) return

    if (window.grecaptcha) {
      setRecaptchaScriptReady(true)
      return
    }

    const existingScript = document.querySelector<HTMLScriptElement>('script[data-recaptcha="v2-invisible"]')
    if (existingScript) {
      const handleLoad = () => setRecaptchaScriptReady(true)
      const handleError = () => setError('No se pudo cargar el CAPTCHA de seguridad.')

      existingScript.addEventListener('load', handleLoad)
      existingScript.addEventListener('error', handleError)

      return () => {
        existingScript.removeEventListener('load', handleLoad)
        existingScript.removeEventListener('error', handleError)
      }
    }

    const script = document.createElement('script')
    script.src = 'https://www.google.com/recaptcha/api.js?render=explicit'
    script.async = true
    script.defer = true
    script.dataset.recaptcha = 'v2-invisible'
    script.addEventListener('load', () => setRecaptchaScriptReady(true))
    script.addEventListener('error', () => setError('No se pudo cargar el CAPTCHA de seguridad.'))
    document.head.appendChild(script)
  }, [recaptchaEnabled, recaptchaSiteKey, setError])

  useEffect(() => {
    if (!recaptchaEnabled || !recaptchaSiteKey || !recaptchaScriptReady || !isAuthRoute || !recaptchaContainerRef.current || recaptchaWidgetIdRef.current !== null) {
      return
    }

    window.grecaptcha?.ready(() => {
      if (!window.grecaptcha || !recaptchaContainerRef.current || recaptchaWidgetIdRef.current !== null) return

      recaptchaWidgetIdRef.current = window.grecaptcha.render(recaptchaContainerRef.current, {
        sitekey: recaptchaSiteKey,
        size: 'invisible',
        callback: (token) => {
          const resolve = recaptchaResolveRef.current
          recaptchaResolveRef.current = null
          recaptchaRejectRef.current = null
          resolve?.(token)
        },
        'error-callback': () => {
          const reject = recaptchaRejectRef.current
          recaptchaResolveRef.current = null
          recaptchaRejectRef.current = null
          reject?.(new Error('No se pudo validar el CAPTCHA de seguridad.'))
        },
        'expired-callback': () => {
          const reject = recaptchaRejectRef.current
          recaptchaResolveRef.current = null
          recaptchaRejectRef.current = null
          reject?.(new Error('El CAPTCHA de seguridad vencio. Intenta de nuevo.'))
        },
      })
    })
  }, [isAuthRoute, recaptchaEnabled, recaptchaScriptReady, recaptchaSiteKey])

  useEffect(() => {
    if (!googleAuthEnabled || !googleClientId || document.querySelector('script[data-google-identity="gsi"]')) return

    const script = document.createElement('script')
    script.src = 'https://accounts.google.com/gsi/client'
    script.async = true
    script.defer = true
    script.dataset.googleIdentity = 'gsi'
    document.head.appendChild(script)
  }, [googleAuthEnabled, googleClientId])

  useEffect(() => {
    if (!isCompletingGoogleRegistration || !user) return

    setAuthMode('register')
    setRegisterStep((current) => Math.min(current, 3))
    setAuthForm((current) => ({
      ...current,
      full_name: user.full_name || current.full_name,
      document_type: user.document_type || current.document_type,
      cedula: user.cedula.startsWith('google-') ? '' : user.cedula,
      email: user.email || current.email,
      phone: user.phone || current.phone,
      birthdate: user.birthdate || current.birthdate,
      resides_in_panama: user.resides_in_panama ?? current.resides_in_panama,
      accepted_terms: Boolean(user.accepted_terms_at),
      group_stage_goal_prediction: user.group_stage_goal_prediction != null
        ? String(user.group_stage_goal_prediction)
        : current.group_stage_goal_prediction,
    }))
  }, [isCompletingGoogleRegistration, user])

  useEffect(() => {
    if (!isAuthRoute || !googleAuthEnabled || !googleClientId || !googleButtonRef.current || isCompletingGoogleRegistration) {
      return
    }

    let cancelled = false

    const renderGoogleButton = () => {
      if (cancelled || !window.google?.accounts.id || !googleButtonRef.current) return
      const buttonWidth = Math.max(
        240,
        Math.min(googleButtonRef.current.parentElement?.clientWidth ?? googleButtonRef.current.clientWidth ?? 360, 360),
      )

      window.google.accounts.id.initialize({
        client_id: googleClientId,
        callback: (response) => {
          if (response.credential) {
            void handleGoogleCredential(response.credential)
          }
        },
        auto_select: false,
        cancel_on_tap_outside: true,
      })

      googleButtonRef.current.innerHTML = ''
      window.google.accounts.id.renderButton(googleButtonRef.current, {
        theme: 'outline',
        size: 'large',
        text: authMode === 'login' ? 'signin_with' : 'continue_with',
        shape: 'pill',
        logo_alignment: 'left',
        width: buttonWidth,
      })
    }

    if (window.google?.accounts.id) {
      renderGoogleButton()
      return () => {
        cancelled = true
      }
    }

    const intervalId = window.setInterval(() => {
      if (window.google?.accounts.id) {
        window.clearInterval(intervalId)
        renderGoogleButton()
      }
    }, 250)

    return () => {
      cancelled = true
      window.clearInterval(intervalId)
    }
  }, [authMode, googleAuthEnabled, googleClientId, isAuthRoute, isCompletingGoogleRegistration])

  async function executeInvisibleRecaptcha() {
    if (!recaptchaEnabled || !recaptchaSiteKey) return null

    if (!window.grecaptcha || recaptchaWidgetIdRef.current === null) {
      throw new Error('El CAPTCHA de seguridad aun no esta listo. Intenta de nuevo en unos segundos.')
    }

    if (recaptchaRejectRef.current) {
      recaptchaRejectRef.current(new Error('Se reemplazo una validacion CAPTCHA en curso.'))
      recaptchaResolveRef.current = null
      recaptchaRejectRef.current = null
    }

    return new Promise<string>((resolve, reject) => {
      recaptchaResolveRef.current = resolve
      recaptchaRejectRef.current = reject

      try {
        window.grecaptcha?.reset(recaptchaWidgetIdRef.current ?? undefined)
        window.grecaptcha?.execute(recaptchaWidgetIdRef.current ?? undefined)
      } catch {
        recaptchaResolveRef.current = null
        recaptchaRejectRef.current = null
        reject(new Error('No se pudo iniciar el CAPTCHA de seguridad.'))
      }
    })
  }

  useEffect(() => {
    const trail = document.querySelector('.cursor-trail')
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)')
    const coarsePointer = window.matchMedia('(pointer: coarse)')

    if (!trail || reduceMotion.matches || coarsePointer.matches) return

    let lastTrail = 0
    const addTrail = (x: number, y: number) => {
      const dot = document.createElement('span')
      dot.className = 'trail-dot'
      dot.style.left = `${x}px`
      dot.style.top = `${y}px`
      trail.appendChild(dot)
      window.setTimeout(() => dot.remove(), 760)
    }

    const handleMouseMove = (event: MouseEvent) => {
      const now = performance.now()
      if (now - lastTrail > 46) {
        addTrail(event.clientX, event.clientY)
        lastTrail = now
      }
    }

    window.addEventListener('mousemove', handleMouseMove, { passive: true })

    return () => window.removeEventListener('mousemove', handleMouseMove)
  }, [])

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
    if (isPublicPage) return
    if (isAuthRoute && resetPasswordTokenFromUrl) {
      setAuthMode('reset-password')
      setResetPasswordToken(resetPasswordTokenFromUrl)
      return
    }
    const isKnownClientRoute = Object.values(CLIENT_VIEW_PATHS).includes(location.pathname)
    const registrationIsComplete = isRegistrationComplete(user)

    if (!token) {
      if (!isAuthRoute) {
        navigate('/login', { replace: true })
      }
      return
    }

    if (user && !registrationIsComplete) {
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
  }, [authBootstrapping, authMode, isAuthRoute, location.pathname, navigate, resetPasswordTokenFromUrl, token, user])

  useEffect(() => {
    if (!isAuthRoute || !newsletterTokenFromUrl) return

    void (async () => {
      try {
        const response = await api.post<GenericMessageResponse>('/newsletter/confirm', {
          token: newsletterTokenFromUrl,
        })
        setMessage(response.data.message ?? 'Tu suscripción al newsletter quedó confirmada.')
      } catch {
        setMessage('No pudimos confirmar tu suscripción. Solicita un nuevo enlace.')
      }
    })()
  }, [isAuthRoute, newsletterTokenFromUrl])

  useEffect(() => {
    if (currentView !== 'cuenta') return

    const section = new URLSearchParams(location.search).get('section')
    setAccountSection(section === 'terminos' ? 'terminos' : 'perfil')
  }, [currentView, location.search])

  useEffect(() => {
    const timer = window.setInterval(() => {
      setNow(Date.now())
    }, 1000)

    return () => window.clearInterval(timer)
  }, [])

  useEffect(() => {
    saveDemoTourState(demoTourState)
  }, [demoTourState])

  useEffect(() => {
    if (!user || demoTourState.status !== 'in_progress') return

    const targetView = demoTourStep?.view
    if (!targetView) return

    if (targetView === 'cuenta') {
      if (currentView !== 'cuenta' || accountSection !== 'perfil') {
        openAccountSection('perfil')
      }
    } else {
      const nextPath = CLIENT_VIEW_PATHS[targetView]
      if (location.pathname !== nextPath || location.search) {
        navigate(nextPath)
      }
    }
  }, [accountSection, currentView, demoTourState.status, demoTourStep?.view, location.pathname, location.search, navigate, user])

  useEffect(() => {
    if (demoTourState.status !== 'in_progress' || !demoTourStep?.targetId) return

    const timeoutId = window.setTimeout(() => {
      const targets = Array.from(document.querySelectorAll<HTMLElement>(`[data-demo-target="${demoTourStep.targetId}"]`))
      const target = targets.find((element) => element.getClientRects().length > 0) ?? targets[0]
      target?.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' })
    }, 260)

    return () => window.clearTimeout(timeoutId)
  }, [demoTourState.status, demoTourStep?.targetId, location.pathname, location.search])

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

  useEffect(() => {
    const hero = authHeroRef.current
    if (!hero || user || !isAuthRoute) return

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)')
    const coarsePointer = window.matchMedia('(pointer: coarse)')
    if (reduceMotion.matches || coarsePointer.matches || window.innerWidth < 1024) return

    const layers = Array.from(hero.querySelectorAll<HTMLElement>('[data-auth-parallax]'))
    if (!layers.length) return

    let frameId = 0

    const applyOffset = (clientX: number) => {
      const bounds = hero.getBoundingClientRect()
      const relativeX = (clientX - bounds.left) / Math.max(bounds.width, 1)
      const normalizedX = Math.max(-1, Math.min(1, (relativeX - 0.5) * 2))

      layers.forEach((layer) => {
        const amount = Number(layer.dataset.authParallax ?? 0)
        layer.style.transform = `translate3d(${normalizedX * amount}px, 0, 0)`
      })
    }

    const handleMouseMove = (event: MouseEvent) => {
      if (frameId) window.cancelAnimationFrame(frameId)
      frameId = window.requestAnimationFrame(() => applyOffset(event.clientX))
    }

    const resetLayers = () => {
      if (frameId) window.cancelAnimationFrame(frameId)
      layers.forEach((layer) => {
        layer.style.transform = 'translate3d(0px, 0, 0)'
      })
    }

    hero.addEventListener('mousemove', handleMouseMove)
    hero.addEventListener('mouseleave', resetLayers)

    return () => {
      if (frameId) window.cancelAnimationFrame(frameId)
      hero.removeEventListener('mousemove', handleMouseMove)
      hero.removeEventListener('mouseleave', resetLayers)
      resetLayers()
    }
  }, [isAuthRoute, user])

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
    if (invoiceEntryMode !== 'scan') setInvoiceScannerActivated(false)
  }, [invoiceEntryMode])

  useEffect(() => {
    if (currentView !== 'facturas' || invoiceEntryMode !== 'scan' || !invoiceScannerActivated) return

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
          // Ruta nativa: full-frame 1080p, autofocus, sin crop - maneja QR densos
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
                setInvoiceScannerDebug((current) => ({ ...current, lastStage: 'decoded-camera', lastError: null }))
                void stopInvoiceScanner().then(() => handleQrScanRegister(decodedText))
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
            // BarcodeDetector no disponible o sin soporte suficiente - caer a html5-qrcode
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
              setInvoiceScannerDebug((current) => ({ ...current, lastStage: 'decoded-camera', lastError: null }))
              void stopInvoiceScanner().then(() => handleQrScanRegister(decodedText))
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
  }, [currentView, invoiceEntryMode, invoiceScannerActivated])

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

        setInvoiceScannerDebug((current) => ({ ...current, lastStage: 'decoded-gallery', lastError: null }))
        await handleQrScanRegister(result)
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

        setInvoiceScannerDebug((current) => ({ ...current, lastStage: 'decoded-ocr', lastError: null }))
        await handleQrScanRegister(extractedCufe)
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

  const activePredictionPhase = useMemo(() => {
    if (clientOverview?.active_phase) {
      return clientOverview.active_phase
    }

    return phases[0] ?? null
  }, [clientOverview, phases])

  const activePhaseMatches = useMemo(() => {
    const baseMatches = activePredictionPhase ? matches.filter((match) => match.phase_id === activePredictionPhase.id) : matches

    return baseMatches
      .slice()
      .sort((left, right) => matchTimeValue(left.kickoff_at) - matchTimeValue(right.kickoff_at))
  }, [activePredictionPhase, matches])

  const matchBuckets = useMemo(() => {
    const phaseName = activePredictionPhase?.name ?? null

    return Array.from(
      new Map(
        activePhaseMatches.map((match) => {
          const bucket = matchBucket(match, phaseName)

          return [bucket.key, bucket]
        }),
      ).values(),
    )
  }, [activePhaseMatches, activePredictionPhase])

  const bucketSelectorLabel = useMemo(() => {
    const uniqueLabels = Array.from(new Set(matchBuckets.map((bucket) => bucket.selectorLabel)))
    return uniqueLabels.length === 1 ? uniqueLabels[0] : 'Bloque'
  }, [matchBuckets])

  const groupSummaries = useMemo(() => {
    const phaseName = activePredictionPhase?.name ?? null

    return matchBuckets.map((bucket) => {
      const matchesForGroup = activePhaseMatches.filter((match) => matchBucket(match, phaseName).key === bucket.key)
      const total = matchesForGroup.length
      const completed = matchesForGroup.filter((match) => predictionMap.has(match.id)).length
      const pending = Math.max(total - completed, 0)

      return {
        groupLabel: bucket.key,
        displayLabel: bucket.label,
        total,
        completed,
        pending,
        isComplete: total > 0 && pending === 0,
      }
    })
  }, [activePhaseMatches, activePredictionPhase, matchBuckets, predictionMap])

  useEffect(() => {
    const availableKeys = groupSummaries.map((summary) => summary.groupLabel)

    if (!availableKeys.length) {
      setSelectedGroupLabel(null)
      return
    }

    setSelectedGroupLabel((current) => {
      if (current === ALL_GROUPS_KEY) return ALL_GROUPS_KEY
      return current && availableKeys.includes(current) ? current : ALL_GROUPS_KEY
    })
  }, [groupSummaries])

  const visibleMatches = useMemo(() => {
    const phaseName = activePredictionPhase?.name ?? null
    const selectedGroupMatches =
      selectedGroupLabel === ALL_GROUPS_KEY
        ? activePhaseMatches
        : activePhaseMatches.filter((match) => matchBucket(match, phaseName).key === selectedGroupLabel)

    if (predictionMode === 'mine') {
      return selectedGroupMatches.filter((match) => predictionMap.has(match.id))
    }

    return selectedGroupMatches.filter((match) => !predictionMap.has(match.id))
  }, [activePhaseMatches, activePredictionPhase, predictionMap, predictionMode, selectedGroupLabel])

  const selectedGroupSummary = useMemo(
    () => {
      if (selectedGroupLabel === ALL_GROUPS_KEY) {
        const total = activePhaseMatches.length
        const completed = activePhaseMatches.filter((match) => predictionMap.has(match.id)).length
        const pending = Math.max(total - completed, 0)

        return {
          groupLabel: ALL_GROUPS_KEY,
          displayLabel: 'Todos los grupos',
          total,
          completed,
          pending,
          isComplete: total > 0 && pending === 0,
        }
      }

      return groupSummaries.find((summary) => summary.groupLabel === selectedGroupLabel) ?? null
    },
    [activePhaseMatches, groupSummaries, predictionMap, selectedGroupLabel],
  )

  const progress = useMemo(() => {
    const total = activePhaseMatches.length
    const completed = activePhaseMatches.filter((match) => predictionMap.has(match.id)).length
    const percentage = total > 0 ? Math.round((completed / total) * 100) : 0

    return { total, completed, percentage }
  }, [activePhaseMatches, predictionMap])

  const nextDeadlineMatch = useMemo(() => {
    return activePhaseMatches.find((match) => !predictionMap.has(match.id) && matchTimeValue(match.kickoff_at) > now) ?? null
  }, [activePhaseMatches, now, predictionMap])

  const countdownParts = useMemo(() => {
    if (!nextDeadlineMatch) return null

    const seconds = Math.floor((matchTimeValue(nextDeadlineMatch.kickoff_at) - now) / 1000)
    return formatCountdown(seconds)
  }, [nextDeadlineMatch, now])

  async function bootstrap() {
    try {
      const meResponse = await api.get('/auth/me')
      const nextUser = meResponse.data.user as User

      setUser(nextUser)

      if (!isRegistrationComplete(nextUser)) {
        setPhases([])
        setMatches([])
        setPredictionsList([])
        setInvoices([])
        setInvoiceTotals(null)
        setClientOverview(null)
        setDashboardSnapshot(null)
        setWalletSnapshot(null)
        setPrizes([])
        return
      }

      const [phasesResponse, matchesResponse, predictionsResponse, overviewResponse, dashboardResponse, walletResponse, prizesResponse, invoicesResponse] = await Promise.all([
        api.get<PhasesResponse>('/client/phases'),
        api.get<MatchesResponse>('/client/matches'),
        api.get<PredictionsResponse>('/client/predictions'),
        api.get<ClientBootstrapResponse>('/client/bootstrap'),
        api.get<DashboardResponse>('/dashboard'),
        api.get<WalletResponse>('/wallet'),
        api.get<PrizesResponse>('/prizes/store'),
        api.get<InvoicesResponse>('/client/invoices'),
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
      setInvoices(invoicesResponse.data.data ?? [])
      setInvoiceTotals(invoicesResponse.data.totals ?? null)

      const drafts = nextMatches.reduce<Record<number, PredictionDraft>>((accumulator, match) => {
        const existing = nextPredictions.find((prediction) => prediction.match_id === match.id)
        accumulator[match.id] = {
          home: existing ? String(existing.predicted_home_score) : '0',
          away: existing ? String(existing.predicted_away_score) : '0',
        }
        return accumulator
      }, {})

      setPredictionDrafts(drafts)
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
      if (isCompletingGoogleRegistration) {
        const documentError = validateDocumentNumber(authForm.document_type, authForm.cedula)

        if (documentError) {
          throw new Error(documentError)
        }

        if (!registrationAvatarFile) {
          throw new Error('Debes subir una foto para completar tu registro.')
        }

        const payload = new FormData()
        payload.append('full_name', authForm.full_name)
        payload.append('document_type', authForm.document_type)
        payload.append('cedula', normalizeIdentityNumber(authForm.document_type, authForm.cedula))
        payload.append('phone', authForm.phone)
        payload.append('birthdate', authForm.birthdate)
        payload.append('resides_in_panama', authForm.resides_in_panama ? '1' : '0')
        payload.append('is_employee', authForm.is_employee ? '1' : '0')
        payload.append('accepted_terms', authForm.accepted_terms ? '1' : '0')
        payload.append('group_stage_goal_prediction', String(Number(authForm.group_stage_goal_prediction)))
        payload.append('branch_id', authForm.branch_id)
        payload.append('avatar', registrationAvatarFile)
        const recaptchaToken = await executeInvisibleRecaptcha()
        if (recaptchaToken) payload.append('recaptcha_token', recaptchaToken)

        const response = await api.post<AuthResponse>('/auth/google/complete', payload, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        })

        setUser(response.data.user)
        setRegistrationAvatarFile(null)
        setMessage('Registro completado.')
        navigate(CLIENT_VIEW_PATHS.cancha, { replace: true })
        return
      }

      if (authMode === 'forgot-password') {
        const response = await api.post<GenericMessageResponse>('/auth/forgot-password', {
          email: resetPasswordEmail.trim() || authForm.email,
        })
        setMessage(response.data.message ?? 'Si tu correo esta en nuestra base de datos, te enviaremos un enlace para cambiar tu contraseña.')
        return
      }

      if (authMode === 'reset-password') {
        if (resetPassword !== resetPasswordConfirmation) {
          setMessage('Tu solicitud fue recibida. Si todo es correcto, podrás continuar con el cambio de contraseña.')
          return
        }

        const response = await api.post<GenericMessageResponse>('/auth/reset-password', {
          token: resetPasswordToken,
          email: resetPasswordEmail,
          password: resetPassword,
          password_confirmation: resetPasswordConfirmation,
        })
        setMessage(response.data.message ?? 'Tu contraseña fue actualizada correctamente.')
        setAuthMode('login')
        setResetPassword('')
        setResetPasswordConfirmation('')
        navigate('/login', { replace: true })
        return
      }

      if (authMode === 'register') {
        const documentError = validateDocumentNumber(authForm.document_type, authForm.cedula)

        if (documentError) {
          throw new Error(documentError)
        }
      }

      const endpoint = authMode === 'login' ? '/auth/login' : '/auth/register'
      const response =
        authMode === 'login'
          ? await (async () => {
              const recaptchaToken = await executeInvisibleRecaptcha()
              return api.post(endpoint, {
                email: authForm.email,
                password: authForm.password,
                recaptcha_token: recaptchaToken || undefined,
              })
            })()
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
              payload.append('branch_id', authForm.branch_id)
              payload.append('password', authForm.password)
              payload.append('password_confirmation', authForm.password_confirmation)
              payload.append('avatar', registrationAvatarFile)
              const recaptchaToken = await executeInvisibleRecaptcha()
              if (recaptchaToken) payload.append('recaptcha_token', recaptchaToken)

              return api.post(endpoint, payload, {
                headers: {
                  'Content-Type': 'multipart/form-data',
                },
              })
            })()
      persistToken(response.data.token)
      setUser(response.data.user)
      setRegistrationAvatarFile(null)
      if (response.data.requires_registration_completion) {
        setAuthMode('register')
        setRegisterStep(1)
        setMessage(response.data.message ?? 'Completa tu registro para participar en la promocion.')
        navigate('/login', { replace: true })
      } else {
        setMessage(authMode === 'login' ? 'Sesion iniciada.' : 'Registro completado.')
        navigate(CLIENT_VIEW_PATHS.cancha, { replace: true })
      }
    } catch (authError) {
      if (authMode === 'forgot-password' || authMode === 'reset-password') {
        setMessage('Si tu correo esta en nuestra base de datos, te enviaremos un enlace para cambiar tu contraseña.')
        return
      }
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

  async function handleNewsletterSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setLoading(true)
    setError(null)
    setMessage(null)

    try {
      const response = await api.post<GenericMessageResponse>('/newsletter/subscribe', {
        email: newsletterEmail || authForm.email,
      })
      setMessage(response.data.message ?? 'Gracias. Si tu correo es válido, recibirás novedades del newsletter.')
    } catch (newsletterError) {
      setMessage('Si tu correo es válido, recibirás novedades del newsletter.')
    } finally {
      setLoading(false)
    }
  }

  async function handleGoogleCredential(credential: string) {
    setLoading(true)
    setError(null)
    setMessage(null)

    try {
      const recaptchaToken = await executeInvisibleRecaptcha()
      const response = await api.post<AuthResponse>('/auth/google', {
        credential,
        recaptcha_token: recaptchaToken || undefined,
      })

      if (!response.data.token) {
        throw new Error('No se recibio un token de autenticacion.')
      }

      persistToken(response.data.token)
      setUser(response.data.user)
      setAuthMode('register')
      setRegisterStep(1)

      if (response.data.requires_registration_completion) {
        setMessage(response.data.message ?? 'Completa tu registro para participar en la promocion.')
        navigate('/login', { replace: true })
      } else {
        setMessage('Sesion iniciada con Google.')
        navigate(CLIENT_VIEW_PATHS.cancha, { replace: true })
      }
    } catch (authError) {
      setError(normalizeError(authError))
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
      setAuthMode('login')
      setRegisterStep(1)
      setAuthForm({ ...EMPTY_AUTH_FORM })
      setRegistrationAvatarFile(null)
      setRegistrationAvatarPreview(null)
      setTermsScrolledEnd(false)
      setTermsModalOpen(false)
      setError(null)
      setInvoiceTotals(null)
      navigate('/login', { replace: true })
    }
  }

  async function handleProfileSave(payload: {
    email: string
    phone: string
    avatarFile: File | null
    branchId: string
    currentPassword: string
    newPassword: string
    newPasswordConfirmation: string
  }) {
    setProfileSaving(true)
    setError(null)
    setMessage(null)

    try {
      if (payload.newPassword || payload.newPasswordConfirmation || payload.currentPassword) {
        if (!payload.currentPassword) {
          throw new Error('Debes escribir tu contraseña actual para cambiarla.')
        }
        if (!payload.newPassword) {
          throw new Error('Debes escribir la nueva contraseña.')
        }
        if (payload.newPassword !== payload.newPasswordConfirmation) {
          throw new Error('Las contraseñas nuevas no coinciden.')
        }
      }

      const formData = new FormData()
      formData.append('email', payload.email)
      formData.append('phone', payload.phone)
      if (payload.branchId) formData.append('branch_id', payload.branchId)
      if (payload.currentPassword) formData.append('current_password', payload.currentPassword)
      if (payload.newPassword) {
        formData.append('password', payload.newPassword)
        formData.append('password_confirmation', payload.newPasswordConfirmation)
      }

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

  function startDemoTour() {
    setSidebarOpen(false)
    setUserMenuOpen(false)
    setMobileUserSidebarOpen(false)
    setDemoTourState({ status: 'in_progress', stepIndex: 0 })
  }

  function dismissDemoTourBanner() {
    setDemoTourState({ status: 'dismissed', stepIndex: 0 })
  }

  function closeDemoTour() {
    setDemoTourState({ status: 'dismissed', stepIndex: 0 })
  }

  function completeDemoTour() {
    setDemoTourState({ status: 'completed', stepIndex: DEMO_TOUR_STEPS.length - 1 })
  }

  function advanceDemoTour() {
    setDemoTourState((current) => {
      const nextStepIndex = Math.min(current.stepIndex + 1, DEMO_TOUR_STEPS.length - 1)
      return { status: 'in_progress', stepIndex: nextStepIndex }
    })
  }

  function goBackDemoTour() {
    setDemoTourState((current) => ({
      status: 'in_progress',
      stepIndex: Math.max(current.stepIndex - 1, 0),
    }))
  }

  function navigateToView(target: MainView) {
    const nextPath = CLIENT_VIEW_PATHS[target]
    if (location.pathname !== nextPath || location.search) {
      navigate(nextPath)
    }
  }

  function openAccountSection(section: AccountSection) {
    setAccountSection(section)
    navigate(`${CLIENT_VIEW_PATHS.cuenta}?section=${section}`)
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

  function adjustScore(matchId: number, side: 'home' | 'away', delta: number) {
    setPredictionDrafts((current) => {
      const currentValue = Number(current[matchId]?.[side] ?? 0)
      const nextValue = Math.max(0, Math.min(99, currentValue + delta))

      return {
        ...current,
        [matchId]: {
          ...current[matchId],
          home: current[matchId]?.home ?? '',
          away: current[matchId]?.away ?? '',
          [side]: String(nextValue),
        },
      }
    })
  }

  async function handlePredictionSubmit(match: TournamentMatch) {
    if (match.status !== 'scheduled' || matchTimeValue(match.kickoff_at) <= Date.now()) {
      setError('El partido ya inicio y el pronostico esta cerrado.')
      return
    }

    setSavingPredictionIds((current) => [...current, match.id])
    setError(null)
    setMessage(null)

    try {
      await api.post(`/client/matches/${match.id}/predict`, {
        predicted_home_score: Number(predictionDrafts[match.id]?.home ?? 0),
        predicted_away_score: Number(predictionDrafts[match.id]?.away ?? 0),
      })

      const homeName = match.homeTeam?.name ?? match.home_team?.name ?? 'este partido'
      setMessage(`Pronostico enviado para ${homeName}.`)
      setPredictionCelebration(homeName)
      window.setTimeout(() => setPredictionCelebration(null), 2800)
      await bootstrap()
    } catch (predictionError) {
      setError(normalizeError(predictionError))
    } finally {
      setSavingPredictionIds((current) => current.filter((id) => id !== match.id))
    }
  }

  function updateInvoiceForm<K extends keyof InvoiceFormState>(field: K, value: InvoiceFormState[K]) {
    if (field !== 'rawInput') return

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

  async function handleQrScanRegister(rawText: string) {
    setInvoiceSubmitting(true)
    setInvoiceScannerError(null)
    setError(null)

    try {
      await api.post('/client/invoices', {
        qr_raw_text: rawText,
      })
      await bootstrap()
      navigateToView('reglas')
    } catch (err) {
      setInvoiceScannerError(normalizeError(err))
    } finally {
      setInvoiceSubmitting(false)
    }
  }

  function handleInvoiceReset() {
    setResolvedInvoiceData(null)
    setInvoiceScannerError(null)
    setInvoiceForm({ rawInput: '', invoice_number: '', purchase_amount: '', issued_at: '' })
    setInvoiceScannerActivated(false)
    setInvoiceEntryMode('scan')
  }

  async function handleInvoiceSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    await handleQrScanRegister(invoiceForm.rawInput)
  }

  function renderCancha() {
    const activeGroupTitle = selectedGroupSummary?.displayLabel ?? activePredictionPhase?.name ?? 'la fase activa'
    const isSelectedGroupComplete = predictionMode === 'pending' && Boolean(selectedGroupSummary?.isComplete)
    const emptyTitle =
      predictionMode === 'mine'
        ? `Todavia no has enviado pronosticos en ${activeGroupTitle}.`
        : isSelectedGroupComplete
          ? `Ya completaste todos tus pronosticos de ${activeGroupTitle}.`
          : 'No hay partidos pendientes en esta fase.'
    const emptyDescription =
      predictionMode === 'mine'
        ? `Cuando envies tus resultados para ${activeGroupTitle}, los veras aqui.`
        : isSelectedGroupComplete
          ? `En este grupo ya no te queda ningun partido por llenar. Puedes revisar otro grupo o entrar en Mis pronosticos para ver lo que ya enviaste.`
          : `Cuando haya partidos habilitados para ${activeGroupTitle} los veras aqui.`

    return (
      <>
        <section className="marea-phase-section cancha-phase-section">
          <div className="marea-phase-header cancha-phase-header">
            <div className="marea-headline-wrap cancha-headline-wrap">
              <span className="marea-kicker">SUPER CARNES 2026</span>
              <h1 className="cancha-headline-title auth-reference-title" aria-label="Panama juega y tu ganas">
                <span className="auth-reference-title-line is-light">PANAMÁ</span>
                <span className="auth-reference-title-line is-light">JUEGA Y TÚ</span>
                <span className="auth-reference-title-line is-gold">GANAS!</span>
              </h1>
              <p>{activePredictionPhase ? `Fase activa: ${activePredictionPhase.name}` : 'Pronósticos oficiales del torneo'}</p>
            </div>

            <div className="cancha-phase-hero-art" aria-hidden="true">
              <img className="cancha-phase-confetti" alt="" src={AUTH_REFERENCE_ASSETS.confetti} />
              <img className="cancha-phase-player" alt="" src={AUTH_REFERENCE_ASSETS.player} />
              <img className="cancha-phase-mascot" alt="" src={AUTH_REFERENCE_ASSETS.mascot} />
              <img className="cancha-phase-ball" alt="" src={AUTH_REFERENCE_ASSETS.ball} />
            </div>

            <div className="marea-phase-meta cancha-phase-meta">
              <div className="marea-progress-card cancha-progress-card">
                <div className="marea-progress-head">
                  <span>PROGRESO DE PRONOSTICOS</span>
                  <strong>
                    {progress.completed}/{progress.total} PARTIDOS
                  </strong>
                </div>
                <div className="marea-progress-track">
                  <div className="marea-progress-fill" style={{ width: `${progress.percentage}%` }} />
                </div>
                <p className="cancha-progress-copy">Sigue participando y suma más puntos en cada fase.</p>
                <span className="cancha-progress-link">¿Cómo se ganan puntos?</span>
              </div>
            </div>
          </div>

          <div className="marea-alert-banner cancha-alert-banner">
            <div className="marea-alert-icon">
              <span className="material-symbols-outlined">campaign</span>
            </div>
            <div className="marea-alert-copy">
              <p className="marea-alert-message">
                <strong>Atención, seleccionado.</strong>{' '}
                {nextDeadlineMatch
                  ? `Tienes hasta el ${formatDateTime(nextDeadlineMatch.kickoff_at)} para enviar tus resultados de ${activeGroupTitle}.`
                  : `Registro legal hasta el ${REGISTRATION_DEADLINE}. Los ganadores por fase se anuncian el ${WINNERS_ANNOUNCEMENT}.`}
              </p>
              {(nextDeadlineMatch || countdownParts) ? (
                <div className="marea-alert-meta">
                  {nextDeadlineMatch ? (
                    <div className="marea-deadline-global">
                      <span className="material-symbols-outlined">warning</span>
                      <strong>Cierre</strong>
                      <span>{formatUpperDate(nextDeadlineMatch.kickoff_at).toUpperCase()} - {formatTime(nextDeadlineMatch.kickoff_at)}</span>
                    </div>
                  ) : null}
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
              ) : null}
            </div>
            <div className="cancha-alert-side-icon" aria-hidden="true">
              <img alt="" src="/redesign/cancha-board-icon.svg" />
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
            {groupSummaries.map((summary) => (
              <button
                key={summary.groupLabel}
                className={[
                  'round-tab',
                  summary.groupLabel === selectedGroupLabel ? 'active' : '',
                  summary.isComplete ? 'is-complete' : '',
                ].filter(Boolean).join(' ')}
                type="button"
                onClick={() => setSelectedGroupLabel(summary.groupLabel)}
                aria-label={`${summary.displayLabel}. ${summary.isComplete ? 'Pronosticos completados' : `${summary.pending} partidos pendientes`}.`}
              >
                <span className="round-tab-label">{summary.displayLabel}</span>
                <span className="round-tab-status">
                  {summary.isComplete ? 'COMPLETADO' : `${summary.pending} PENDIENTE${summary.pending === 1 ? '' : 'S'}`}
                </span>
                <img className="round-tab-watermark" alt="" src="/redesign/cancha-ball-mark.svg" />
              </button>
            ))}
          </div>

          <label className="marea-desktop-group-select">
            <span>{bucketSelectorLabel}</span>
            <div className="marea-desktop-group-select-control">
              <select value={selectedGroupLabel ?? ''} onChange={(event) => setSelectedGroupLabel(event.target.value)}>
                <option value={ALL_GROUPS_KEY}>
                  {`Todos${groupSummaries.length ? ` - ${groupSummaries.length} grupos` : ''}`}
                </option>
                {groupSummaries.map((summary) => (
                  <option key={summary.groupLabel} value={summary.groupLabel}>
                    {`${summary.displayLabel}${summary.isComplete ? ' - Completado' : ` - ${summary.pending} pendiente${summary.pending === 1 ? '' : 's'}`}`}
                  </option>
                ))}
              </select>
              <div className="marea-desktop-group-select-meta">
                <strong>{selectedGroupSummary?.displayLabel ?? activeGroupTitle}</strong>
                <small>
                  {selectedGroupSummary?.isComplete
                    ? 'Pronosticos completados'
                    : `${selectedGroupSummary?.pending ?? 0} pendiente${selectedGroupSummary?.pending === 1 ? '' : 's'}`}
                </small>
              </div>
            </div>
          </label>

          <label className="marea-mobile-group-select">
            <span>{bucketSelectorLabel}</span>
            <select value={selectedGroupLabel ?? ''} onChange={(event) => setSelectedGroupLabel(event.target.value)}>
              <option value={ALL_GROUPS_KEY}>
                {`Todos${groupSummaries.length ? ` - ${groupSummaries.length} grupos` : ''}`}
              </option>
              {groupSummaries.map((summary) => (
                <option key={summary.groupLabel} value={summary.groupLabel}>
                  {`${summary.displayLabel}${summary.isComplete ? ' - Completado' : ` - ${summary.pending} pendiente${summary.pending === 1 ? '' : 's'}`}`}
                </option>
              ))}
            </select>
          </label>
        </section>

        <section className="marea-group-stack">
          {visibleMatches.length ? (
            <div className="marea-match-grid">
              {visibleMatches.map((match) => {
                const homeTeam = match.homeTeam ?? match.home_team
                const awayTeam = match.awayTeam ?? match.away_team
                const favoriteTeam = getFavoriteTeam(match)
                const draft = predictionDrafts[match.id] ?? { home: '', away: '' }
                const prediction = predictionMap.get(match.id)
                const isSaving = savingPredictionIds.includes(match.id)
                const isReadonlyPrediction = predictionMode === 'mine' && Boolean(prediction)
                const isPredictionClosed = match.status !== 'scheduled' || matchTimeValue(match.kickoff_at) <= Date.now()
                const homeDisplayScore = isReadonlyPrediction ? String(prediction?.predicted_home_score ?? 0) : draft.home
                const awayDisplayScore = isReadonlyPrediction ? String(prediction?.predicted_away_score ?? 0) : draft.away

                return (
                  <article key={match.id} className={favoriteTeam ? 'marea-match-card featured cancha-match-card' : 'marea-match-card cancha-match-card'}>
                    <div className="marea-match-topline">
                      <div className="marea-match-banner">
                        <span>{matchBucket(match, activePredictionPhase?.name).label}</span>
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
                          {typeof favoriteTeam.ranking_fifa === 'number' ? ` Â· Ranking FIFA #${favoriteTeam.ranking_fifa}` : ''}
                        </span>
                      </div>
                    ) : null}

                    <div className="marea-teams-row">
                      <div className="marea-teams-badges">
                        <TeamBadge team={homeTeam} featured={favoriteTeam?.id === homeTeam?.id} />
                        <TeamBadge team={awayTeam} featured={favoriteTeam?.id === awayTeam?.id} />
                      </div>

                      <div className="marea-teams-names">
                        <span className="team-name">{homeTeam?.name ?? 'Local'}</span>
                        <span className="team-name">{awayTeam?.name ?? 'Visitante'}</span>
                      </div>

                      {isReadonlyPrediction ? (
                        <div className="marea-score-readonly" aria-label="Pronostico enviado">
                          <strong>{homeDisplayScore}</strong>
                          <span>-</span>
                          <strong>{awayDisplayScore}</strong>
                        </div>
                      ) : (
                        <div className="marea-score-inputs">
                          <div className="marea-score-stepper">
                            <button
                              type="button"
                              className="marea-score-stepper-button"
                              aria-label={`Restar marcador local ${homeTeam?.name ?? 'Local'}`}
                              onClick={() => adjustScore(match.id, 'home', -1)}
                            >
                              <span className="material-symbols-outlined">remove</span>
                            </button>
                            <input
                              aria-label={`Marcador local ${homeTeam?.name ?? 'Local'}`}
                              type="text"
                              inputMode="numeric"
                              placeholder="0"
                              value={homeDisplayScore}
                              onChange={(event) => updateScore(match.id, 'home', event.target.value)}
                            />
                            <button
                              type="button"
                              className="marea-score-stepper-button"
                              aria-label={`Sumar marcador local ${homeTeam?.name ?? 'Local'}`}
                              onClick={() => adjustScore(match.id, 'home', 1)}
                            >
                              <span className="material-symbols-outlined">add</span>
                            </button>
                          </div>
                          <span>-</span>
                          <div className="marea-score-stepper">
                            <button
                              type="button"
                              className="marea-score-stepper-button"
                              aria-label={`Restar marcador visitante ${awayTeam?.name ?? 'Visitante'}`}
                              onClick={() => adjustScore(match.id, 'away', -1)}
                            >
                              <span className="material-symbols-outlined">remove</span>
                            </button>
                            <input
                              aria-label={`Marcador visitante ${awayTeam?.name ?? 'Visitante'}`}
                              type="text"
                              inputMode="numeric"
                              placeholder="0"
                              value={awayDisplayScore}
                              onChange={(event) => updateScore(match.id, 'away', event.target.value)}
                            />
                            <button
                              type="button"
                              className="marea-score-stepper-button"
                              aria-label={`Sumar marcador visitante ${awayTeam?.name ?? 'Visitante'}`}
                              onClick={() => adjustScore(match.id, 'away', 1)}
                            >
                              <span className="material-symbols-outlined">add</span>
                            </button>
                          </div>
                        </div>
                      )}
                    </div>

                    <div className="marea-card-action">
                      <button className="marea-card-send-button" disabled={isSaving || Boolean(prediction) || isPredictionClosed} type="button" onClick={() => void handlePredictionSubmit(match)}>
                        <span>{prediction ? 'Pronostico enviado' : isPredictionClosed ? 'Pronostico cerrado' : isSaving ? 'Enviando...' : 'Enviar pronostico'}</span>
                        <span className="material-symbols-outlined cancha-button-arrow">arrow_forward</span>
                      </button>
                    </div>
                  </article>
                )
              })}
            </div>
          ) : (
            <article className="marea-match-card cancha-match-card empty">
              <div className="marea-empty-copy">
                <span className="material-symbols-outlined">{isSelectedGroupComplete ? 'task_alt' : 'sports_soccer'}</span>
                {isSelectedGroupComplete ? <span className="marea-empty-badge">Grupo completado</span> : null}
                <h3>{emptyTitle}</h3>
                <p>{emptyDescription}</p>
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
        invoiceScannerActivated={invoiceScannerActivated}
        invoiceScannerError={invoiceScannerError}
        invoiceScannerDebug={invoiceScannerDebug}
        invoiceSubmitting={invoiceSubmitting}
        invoices={invoices}
        onRegister={(rawCufe) => void handleQrScanRegister(rawCufe)}
        onActivateScan={async () => {
          setInvoiceScannerError(null)
          if (typeof navigator !== 'undefined' && 'mediaDevices' in navigator) {
            try {
              const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
              stream.getTracks().forEach((t) => t.stop())
            } catch {
              setInvoiceScannerError('Permiso de camara denegado. ActÃ­valo en la configuracion de tu navegador e intenta de nuevo.')
              return
            }
          }
          setInvoiceScannerActivated(true)
        }}
        onFieldChange={updateInvoiceForm}
        onGalleryUpload={handleInvoiceGalleryUpload}
        onModeChange={setInvoiceEntryMode}
        onReset={handleInvoiceReset}
        onSubmit={handleInvoiceSubmit}
        resolvedInvoiceData={resolvedInvoiceData}
        showScannerDebug={showScannerDebug}
      />
    )

    const approvedInvoices = invoices.filter((invoice) => invoice.validation_status === 'approved')
    const activeInvoicePhase = clientOverview?.active_phase ?? null
    const phaseInvoicePoints = approvedInvoices
      .filter((invoice) => {
        if (!activeInvoicePhase || !invoice.issued_at) return false

        const issuedAt = new Date(invoice.issued_at).getTime()
        return issuedAt >= new Date(activeInvoicePhase.starts_at).getTime() && issuedAt <= new Date(activeInvoicePhase.ends_at).getTime()
      })
      .reduce((total, invoice) => total + Number(invoice.points_awarded ?? 0), 0)
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
          const labelText = status.tone === 'approved' ? 'GOL VÃLIDO' : status.tone === 'pending' ? 'REVISIÃ“N VAR' : 'FUERA DE JUEGO'
          const iconName = status.tone === 'approved' ? 'verified' : status.tone === 'pending' ? 'update' : 'dangerous'
          const title = invoice.invoice_number ? `#${invoice.invoice_number}` : `#PAN-${String(invoice.id).padStart(6, '0')}`
          const referenceDate = invoice.issued_at ?? invoice.created_at
          const subtitle = `${referenceDate ? formatUpperDate(referenceDate) : 'Fecha pendiente'} â¬¢ ${formatCurrency(invoice.purchase_amount)}`

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
                  alt="Estadio Rommel FernÃ¡ndez"
                  className="w-full h-full object-cover opacity-50 scale-105"
                  src="https://lh3.googleusercontent.com/aida-public/AB6AXuBEx-hRFUMZ710fF7EatYLLO_SftyRg0ww2GvBNKWHSjPObe2Hu17fXzKDy8LOFbxMv93SOa0IWNTCINLfrcTI4Gv7Fb8T-KRHOU6iyLxekm6vci5QI1h6h-jtqFVtscsl4aPJJld2V-TOyhBaZNKlPweuhcxfvNwlUxFNiz07sFuBIttiDysG-4NIdDsaDGIygvIgQn-m1chePGiwL3D2k8IOl-CypudZp6J8U6ve38WWsbNyTIdWbQWlJlq2K7BKdk_nqv4a5KH8"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-background via-background/60 to-transparent"></div>
              </div>
              <div className="relative z-10">
                <h1 className="font-display-lg text-headline-lg md:text-display-lg text-on-surface leading-none mb-2">EL ENTRENAMIENTO NACIONAL</h1>
                <p className="text-secondary font-title-md text-title-md uppercase tracking-widest">Sube tus facturas y anota goles por PanamÃ¡</p>
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
                      <label className="font-label-caps text-label-caps text-on-surface-variant ml-1">NÃšMERO DE FACTURA</label>
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
                      <p className="text-center text-body-sm text-on-surface-variant italic">Cada factura vÃ¡lida te acerca a la Copa.</p>
                    </div>
                  </form>
                </div>
                <div className="bg-surface-container rounded-[1.5rem] p-6 border-l-4 border-secondary flex items-start gap-4">
                  <span className="material-symbols-outlined text-secondary text-3xl">lightbulb</span>
                  <div>
                    <h3 className="font-title-md text-title-md text-secondary uppercase">TIP DEL CAPITÃN</h3>
                    <p className="text-body-sm text-on-surface-variant">
                      "No permitas que el VAR anule tu gol. AsegÃºrate de que el monto coincida exactamente con tu factura."
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
                        {phaseInvoicePoints.toFixed(1)} Goles de Fase
                      </span>
                    </div>
                  </div>
                  <div className="flex-1 overflow-y-auto p-4 space-y-3 max-h-[600px]">
                    {invoiceCards ? invoiceCards : (
                      <div className="bg-surface-container-lowest p-8 rounded-2xl border border-outline-variant text-center">
                        <div className="font-title-md text-on-surface mb-2">Sin facturas registradas todavÃ­a</div>
                        <div className="text-body-sm text-on-surface-variant">Cuando existan registros reales del cliente, aparecerÃ¡n aquÃ­ sin datos de ejemplo.</div>
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
          invoices={invoices}
          invoiceTotals={invoiceTotals}
          overview={clientOverview}
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
      return user ? <CuentaView user={user} saving={profileSaving} branches={branches} termsText={TERMS_TEXT} section={accountSection} onSectionChange={openAccountSection} onSave={handleProfileSave} /> : null
    }

    return renderCancha()
  }

  const playerName = user?.full_name ?? 'Participante'
  const playerBadge = userInitials(user?.full_name)
  const playerAvatarUrl = user?.avatar_url ?? null
  const sidebarIdentityLabel = user?.cedula?.trim() || 'Participante activo'
  const effectiveHeaderLogo = headerLogoUrl || authLogoUrl || '/redesign/auth-logo-super-carnes.png'
  const currentViewDemoTarget = VIEW_DEMO_TARGETS[currentView]
  const isDemoTargetActive = (targetId: string) => demoTourStep?.targetId === targetId
  const headerBrand = <img alt="Super Carnes" src={effectiveHeaderLogo} />
  const usesCanchaShell = currentView === 'cancha' || currentView === 'facturas'
  const topNavButton = (target: MainView) =>
    `marea-header-nav-button ${currentView === target ? 'is-active' : ''}`

  const sideNavButton = (target: MainView) =>
    currentView === target
      ? 'flex items-center gap-4 bg-primary-container text-white rounded-xl p-3 mx-2 transition-all'
      : 'flex items-center gap-4 text-on-surface-variant p-3 mx-2 hover:bg-surface-variant hover:text-on-surface transition-all hover:translate-x-1 duration-300'

  if (location.pathname === '/terminos') return <TerminosPage termsText={termsText} />
  if (location.pathname === '/privacidad') return <PrivacidadPage />
  if (location.pathname === '/contacto') return <ContactoPage contactInfo={contactInfo} />

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

  const TERMS_TEXT = termsText.trim() ? termsText : (OFFICIAL_TERMS_TEXT_V2 || OFFICIAL_TERMS_TEXT)

  return (
    <div className="marea-app-shell">
      <div className="cursor-trail" aria-hidden="true" />
      {message ? <div className="feedback success" role="status" aria-live="polite">{message}</div> : null}
      {error ? <div className="feedback error" role="alert">{error}</div> : null}
      {predictionCelebration ? (
        <div className="prediction-celebration" role="status" aria-live="polite">
          <div className="prediction-celebration-card">
            <img
              className="prediction-celebration-gaby"
              src="/redesign/gaby-celebration.gif"
              alt=""
              aria-hidden="true"
            />
            <p>Pronostico enviado</p>
            <strong>Panama celebra tu jugada</strong>
          </div>
        </div>
      ) : null}

      {/* Modal de TÃ©rminos y Condiciones */}
      {termsModalOpen ? (
        <div className="terms-overlay" onClick={() => setTermsModalOpen(false)}>
          <div className="terms-modal" onClick={(e) => e.stopPropagation()}>
            <div className="terms-modal-header">
              <span className="material-symbols-outlined">gavel</span>
              <span>TÃ©rminos y Condiciones</span>
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
                  DesplÃ¡zate hasta el final para habilitar la aceptaciÃ³n
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
                {termsScrolledEnd ? 'Acepto los tÃ©rminos y condiciones' : 'Lee el documento completo primero'}
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {!user || isCompletingGoogleRegistration ? (
        <section className="auth-shell">
          <div className="auth-reference-bg" aria-hidden="true">
            <img alt="" className="auth-reference-bg-stadium" src={AUTH_REFERENCE_ASSETS.stadium} />
            <img alt="" className="auth-reference-bg-crowd" src={AUTH_REFERENCE_ASSETS.crowd} />
            <img alt="" className="auth-reference-bg-confetti" src={AUTH_REFERENCE_ASSETS.confetti} />
          </div>
          <div className="auth-top-logo auth-top-logo-reference">
            <img alt="Logo de Super Carnes" src={authLogoUrl || AUTH_REFERENCE_ASSETS.logo} />
          </div>
          <div className="auth-reference-layout">
            <div className="auth-hero auth-hero-reference" ref={authHeroRef}>
              <div className="auth-hero-content auth-hero-content-reference">
                <p className="auth-kicker auth-reference-pill">PROMOCION SUPER CARNES 2026</p>
                <div className="auth-reference-title" aria-label="¡Panamá juega y tú ganas!">
                  <span className="auth-reference-title-line is-light">¡PANAMÁ</span>
                  <span className="auth-reference-title-line is-light">JUEGA Y TÚ</span>
                  <span className="auth-reference-title-line is-gold">GANAS!</span>
                </div>
                <p className="auth-reference-copy">Registra tus facturas, pronostica partidos y participa por premios durante la ruta mundialista.</p>
                <div className="auth-reference-benefits" aria-label="Beneficios de la promocion">
                  <article>
                    <span className="material-symbols-outlined">receipt_long</span>
                    <strong>Registra tus facturas</strong>
                  </article>
                  <article>
                    <span className="material-symbols-outlined">sports_soccer</span>
                    <strong>Pronostica los partidos</strong>
                  </article>
                  <article>
                    <span className="material-symbols-outlined">emoji_events</span>
                    <strong>Acumula puntos</strong>
                  </article>
                  <article>
                    <span className="material-symbols-outlined">redeem</span>
                    <strong>Gana premios increibles</strong>
                  </article>
                </div>
              </div>
              <div className="auth-reference-characters" aria-hidden="true">
                <img alt="" className="auth-reference-player" data-auth-parallax="14" src={AUTH_REFERENCE_ASSETS.player} />
                <div className="auth-reference-mascot-stack" data-auth-parallax="20">
                  <img alt="" className="auth-reference-ball" data-auth-parallax="28" src={AUTH_REFERENCE_ASSETS.ball} />
                  <img alt="" className="auth-reference-mascot" src={AUTH_REFERENCE_ASSETS.mascot} />
                </div>
                <div className="auth-reference-badge" data-auth-parallax="10">
                  <span>¡ACIERTA</span>
                  <span>Y GANA!</span>
                </div>
              </div>
            </div>

          <form className="auth-panel auth-panel-reference" onSubmit={handleAuthSubmit}>
            <div aria-hidden="true" ref={recaptchaContainerRef} style={{ height: 0, overflow: 'hidden' }} />
            {isCompletingGoogleRegistration ? null : <div className="auth-tabs auth-tabs-reference">
              <button className={authMode === 'login' ? 'active' : ''} type="button" onClick={() => { setAuthMode('login'); setRegisterStep(1) }}>
                Iniciar sesión
              </button>
              <button className={authMode === 'register' ? 'active' : ''} type="button" onClick={() => { setAuthMode('register'); setRegisterStep(1) }}>
                Registrarse
              </button>
            </div>}

            {!isCompletingGoogleRegistration && (
              <div style={{
                background: 'rgba(0,122,255,0.15)',
                border: '1px solid rgba(0,122,255,0.4)',
                borderRadius: '12px',
                padding: '12px 16px',
                display: 'flex',
                alignItems: 'center',
                gap: '10px',
                fontSize: '13px',
                color: '#d9ecff',
                margin: '0 0 4px',
              }}>
                <span className="material-symbols-outlined" style={{ fontSize: '18px', flexShrink: 0 }}>lock</span>
                <span>El registro sigue disponible. Si ya participas, inicia sesiÃ³n para ver tu posición y tus pronósticos.</span>
              </div>
            )}

            <div className={`auth-panel-header${authMode === 'register' || isCompletingGoogleRegistration ? ' is-register' : ''}`}>
              <p>
                {isCompletingGoogleRegistration
                  ? 'Registro pendiente'
                  : authMode === 'register'
                    ? 'Crea tu cuenta'
                    : authMode === 'forgot-password'
                      ? 'Recuperación segura'
                      : authMode === 'reset-password'
                        ? 'Nueva contraseña'
                        : ''}
              </p>
              <h2>
                {isCompletingGoogleRegistration
                  ? 'Completa tus datos legales'
                  : authMode === 'register'
                    ? 'Regístrate'
                    : authMode === 'forgot-password'
                      ? 'Recupera tu acceso'
                      : authMode === 'reset-password'
                        ? 'Define tu nueva clave'
                        : '¡Bienvenido de nuevo!'}
              </h2>
              <span>
                {isCompletingGoogleRegistration
                  ? `Tu correo de Google ya fue verificado: ${user?.email ?? authForm.email}. Solo falta completar los datos obligatorios para participar.`
                  : authMode === 'register'
                    ? 'Completa tus datos para participar por premios de Super Carnes.'
                    : authMode === 'forgot-password'
                      ? 'Te enviaremos un enlace si el correo existe en nuestra base de datos.'
                      : authMode === 'reset-password'
                        ? 'Usa el enlace que recibiste por correo para crear una clave nueva.'
                        : 'Ingresa tus datos para continuar'}
              </span>
            </div>

            {authMode === 'login' ? (
              <div className="auth-helper-links">
                <button type="button" className="auth-helper-link" onClick={() => setAuthMode('forgot-password')}>
                  Olvidé mi contraseña
                </button>
                <button type="button" className="auth-helper-link" onClick={() => setNewsletterEmail(authForm.email)}>
                  Recibir newsletter
                </button>
              </div>
            ) : null}

            {authMode === 'forgot-password' ? (
              <div className="auth-mini-card">
                <p>Te enviaremos un correo con un enlace de un solo uso.</p>
                <label className="auth-reference-field">
                  <span>Correo electrónico</span>
                  <div className="auth-reference-input">
                    <span className="material-symbols-outlined">mail</span>
                    <input
                      required
                      placeholder="ejemplo@correo.com"
                      type="email"
                      value={resetPasswordEmail || authForm.email}
                      onChange={(event) => {
                        setResetPasswordEmail(event.target.value)
                        setAuthForm((current) => ({ ...current, email: event.target.value }))
                      }}
                    />
                  </div>
                </label>
                <button className="auth-submit" type="submit" disabled={loading}>
                  Enviar enlace
                </button>
                <button type="button" className="auth-helper-link" onClick={() => setAuthMode('login')}>
                  Volver al inicio de sesión
                </button>
              </div>
            ) : null}

            {authMode === 'reset-password' ? (
              <div className="auth-mini-card">
                <p>Vas a cambiar tu contraseña usando el enlace recibido por correo.</p>
                <input type="hidden" value={resetPasswordToken} readOnly />
                <label className="auth-reference-field">
                  <span>Correo electrónico</span>
                  <div className="auth-reference-input">
                    <span className="material-symbols-outlined">mail</span>
                    <input
                      required
                      placeholder="ejemplo@correo.com"
                      type="email"
                      value={resetPasswordEmail}
                      onChange={(event) => setResetPasswordEmail(event.target.value)}
                    />
                  </div>
                </label>
                <label className="auth-reference-field">
                  <span>Nueva contraseña</span>
                  <div className="auth-reference-input">
                    <span className="material-symbols-outlined">lock</span>
                    <input
                      required
                      type="password"
                      value={resetPassword}
                      onChange={(event) => setResetPassword(event.target.value)}
                    />
                  </div>
                </label>
                <label className="auth-reference-field">
                  <span>Confirmar contraseña</span>
                  <div className="auth-reference-input">
                    <span className="material-symbols-outlined">verified_user</span>
                    <input
                      required
                      type="password"
                      value={resetPasswordConfirmation}
                      onChange={(event) => setResetPasswordConfirmation(event.target.value)}
                    />
                  </div>
                </label>
                <button className="auth-submit" type="submit" disabled={loading}>
                  Actualizar contraseña
                </button>
              </div>
            ) : null}

            {isCompletingGoogleRegistration || authMode === 'register' ? (
              <>
                {/* Indicador de pasos */}
                <div className="auth-steps-indicator">
                  {Array.from({ length: totalRegisterSteps }, (_, index) => index + 1).map((s) => (
                    <div key={s} className={`auth-step-dot${registerStep === s ? ' is-active' : ''}${registerStep > s ? ' is-done' : ''}`}>
                      {registerStep > s
                        ? <span className="material-symbols-outlined">check</span>
                        : s}
                    </div>
                  ))}
                </div>

                {/* Paso 1: Tu perfil */}
                {registerStep === 1 ? (
                  <>
                    <div className="auth-section-label">
                      <span className="material-symbols-outlined">person</span>
                      <span>Tu perfil</span>
                    </div>
                    <button
                      type="button"
                      className={`auth-avatar-zone${registrationStepErrors.avatar ? ' is-invalid' : ''}`}
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
                    {registrationStepErrors.avatar ? <p className="auth-field-error">{registrationStepErrors.avatar}</p> : null}
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
                          try {
                            const optimized = await optimizeAvatarFile(file, 'avatar-registration')
                            setRegistrationAvatarFile(optimized)
                          } catch {
                            setRegistrationAvatarFile(file)
                          }
                        }
                      }}
                    />
                    <div className="auth-field-grid">
                      <label className={registrationStepErrors.full_name ? 'is-invalid' : ''}>
                        Nombre completo *
                        <input
                          required
                          placeholder="Tu nombre completo"
                          value={authForm.full_name}
                          onChange={(event) => setAuthForm({ ...authForm, full_name: event.target.value })}
                        />
                        {registrationStepErrors.full_name ? <span className="auth-field-error">{registrationStepErrors.full_name}</span> : null}
                      </label>
                      <label className={registrationStepErrors.phone ? 'is-invalid' : ''}>
                        Teléfono *
                        <div className="auth-phone-field">
                          <button className="auth-phone-prefix" type="button">
                            <span className="auth-phone-prefix-badge" aria-hidden="true">PA</span>
                            <span>+507</span>
                            <span className="material-symbols-outlined" aria-hidden="true">arrow_drop_down</span>
                          </button>
                          <input
                            required
                            inputMode="numeric"
                            maxLength={8}
                            placeholder="61234567"
                            value={getPanamaLocalPhone(authForm.phone)}
                            onChange={(event) => setAuthForm({ ...authForm, phone: normalizePanamaPhone(event.target.value) })}
                          />
                        </div>
                        {registrationStepErrors.phone ? <span className="auth-field-error">{registrationStepErrors.phone}</span> : null}
                      </label>
                    </div>
                  </>
                ) : null}

                {/* Paso 2: Documento */}
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
                          {dt === 'cedula' ? 'CÃ©dula' : dt === 'passport' ? 'Pasaporte' : 'Residente'}
                        </button>
                      ))}
                    </div>
                    <div className="auth-field-grid">
                      <label className={registrationStepErrors.cedula ? 'is-invalid' : ''}>
                        {documentNumberLabel(authForm.document_type)} *
                        <input
                          required
                          placeholder={documentNumberPlaceholder(authForm.document_type)}
                          value={authForm.cedula}
                          onChange={(event) => setAuthForm({ ...authForm, cedula: normalizeIdentityNumber(authForm.document_type, event.target.value) })}
                        />
                        {registrationStepErrors.cedula ? <span className="auth-field-error">{registrationStepErrors.cedula}</span> : null}
                      </label>
                      <label className={registrationStepErrors.birthdate ? 'is-invalid' : ''}>
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
                              setError('Debes ser mayor de 18 aÃ±os para participar.')
                            } else {
                              setError(null)
                            }
                          }}
                        />
                        {registrationStepErrors.birthdate ? <span className="auth-field-error">{registrationStepErrors.birthdate}</span> : null}
                      </label>
                    </div>
                  </>
                ) : null}

                {/* Paso 3: PronÃ³stico + sucursal */}
                {registerStep === 3 ? (
                  <>
                    <div className="auth-section-label">
                      <span className="material-symbols-outlined">sports_soccer</span>
                      <span>PronÃ³stico de desempate</span>
                    </div>
                    <p className="auth-goal-hint">Â¿CuÃ¡ntos goles totales habrÃ¡ en la fase de grupos del Mundial 2026?</p>
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
                        -
                      </button>
                      <input
                        className="auth-goal-input"
                        type="text"
                        inputMode="numeric"
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

                    <div className="auth-section-label">
                      <span className="material-symbols-outlined">store</span>
                      <span>Sucursal de preferencia</span>
                    </div>
                    <label className="auth-field-label">
                      <span>Sucursal</span>
                      <select
                        className={`auth-select${registrationStepErrors.branch_id ? ' is-invalid' : ''}`}
                        value={authForm.branch_id}
                        onChange={(event) => setAuthForm({ ...authForm, branch_id: event.target.value })}
                      >
                        <option value="">Selecciona una sucursal</option>
                        {branches.map((branch) => (
                          <option key={branch.id} value={String(branch.id)}>{branch.name}</option>
                        ))}
                      </select>
                      {registrationStepErrors.branch_id ? <span className="auth-field-error">{registrationStepErrors.branch_id}</span> : null}
                    </label>
                  </>
                ) : null}

                {/* â”€â”€ Paso 4: Confirmaciones â”€â”€ */}
                {registerStep === 4 ? (
                  <>
                    <div className="auth-section-label">
                      <span className="material-symbols-outlined">check_circle</span>
                      <span>Confirmaciones</span>
                    </div>
                    <button
                      type="button"
                      className={`auth-toggle-card${authForm.resides_in_panama ? ' is-on' : ''}${registrationStepErrors.resides_in_panama ? ' is-invalid' : ''}`}
                      onClick={() => setAuthForm((f) => ({ ...f, resides_in_panama: !f.resides_in_panama }))}
                    >
                      <span className="material-symbols-outlined auth-toggle-card-icon">location_on</span>
                      <div className="auth-toggle-card-text">
                        <strong>Resido en PanamÃ¡ y soy mayor de 18 aÃ±os</strong>
                        <span>Obligatorio para participar</span>
                      </div>
                      <div className="auth-toggle-switch" />
                    </button>
                    {registrationStepErrors.resides_in_panama ? <p className="auth-field-error">{registrationStepErrors.resides_in_panama}</p> : null}
                    <button
                      type="button"
                      className={`auth-toggle-card${authForm.accepted_terms ? ' is-on' : ''}${registrationStepErrors.accepted_terms ? ' is-invalid' : ''}`}
                      onClick={() => { setTermsScrolledEnd(false); setTermsModalOpen(true) }}
                    >
                      <span className="material-symbols-outlined auth-toggle-card-icon">gavel</span>
                      <div className="auth-toggle-card-text">
                        <strong>
                          {authForm.accepted_terms ? 'TÃ©rminos aceptados' : 'Leer y aceptar tÃ©rminos y condiciones'}
                        </strong>
                        <span>Obligatorio Â· Toca para leer el documento completo</span>
                      </div>
                      {authForm.accepted_terms
                        ? <span className="material-symbols-outlined" style={{ color: 'var(--secondary)', flexShrink: 0 }}>check_circle</span>
                        : <span className="material-symbols-outlined" style={{ color: 'rgba(225,226,236,0.4)', flexShrink: 0 }}>chevron_right</span>}
                    </button>
                    {registrationStepErrors.accepted_terms ? <p className="auth-field-error">{registrationStepErrors.accepted_terms}</p> : null}
                  </>
                ) : null}

                {/* â”€â”€ Paso 5: Cuenta â”€â”€ */}
                {!isCompletingGoogleRegistration && registerStep === 5 ? (
                  <div className="auth-section-label">
                    <span className="material-symbols-outlined">lock</span>
                    <span>Correo y contraseña</span>
                  </div>
                ) : null}
              </>
            ) : null}

            {/* Campos de email/contraseÃ±a: login siempre, registro solo en paso 5 */}
            {!isCompletingGoogleRegistration && (authMode === 'login' || registerStep === 5) ? (
              <>
                <label className={`auth-reference-field${authMode === 'register' && registrationStepErrors.email ? ' is-invalid' : ''}`}>
                  <span>Correo electronico</span>
                  <div className="auth-reference-input">
                    <span className="material-symbols-outlined">mail</span>
                    <input required placeholder="ejemplo@correo.com" type="email" value={authForm.email} onChange={(event) => setAuthForm({ ...authForm, email: event.target.value })} />
                  </div>
                  {authMode === 'register' && registrationStepErrors.email ? <span className="auth-field-error">{registrationStepErrors.email}</span> : null}
                </label>
                <label className={authMode === 'register' && registrationStepErrors.password ? 'is-invalid' : ''}>
                  Contraseña *
                  <input required type="password" value={authForm.password} onChange={(event) => setAuthForm({ ...authForm, password: event.target.value })} />
                  {authMode === 'register' && registrationStepErrors.password ? <span className="auth-field-error">{registrationStepErrors.password}</span> : null}
                </label>
                {authMode === 'register' ? (
                  <label className={registrationStepErrors.password_confirmation ? 'is-invalid' : ''}>
                    Confirmar contraseña *
                    <input
                      required
                      type="password"
                      value={authForm.password_confirmation}
                      onChange={(event) => setAuthForm({ ...authForm, password_confirmation: event.target.value })}
                    />
                    {registrationStepErrors.password_confirmation ? <span className="auth-field-error">{registrationStepErrors.password_confirmation}</span> : null}
                  </label>
                ) : null}
              </>
            ) : null}

            {/* NavegaciÃ³n de pasos / submit */}
            {isCompletingGoogleRegistration || authMode === 'register' ? (
              <div className="auth-step-nav">
                {registerStep > 1 ? (
                  <button type="button" className="auth-step-back" onClick={() => setRegisterStep((s) => s - 1)}>
                    <span className="material-symbols-outlined">arrow_back</span>
                    Anterior
                  </button>
                ) : <span />}
                {registerStep < totalRegisterSteps ? (
                  <button
                    type="button"
                    className="auth-step-next"
                    disabled={!registrationStepValid}
                    onClick={() => {
                      setError(null)
                      if (registerStep === 1) {
                        if (!registrationAvatarFile) { setError('Debes subir tu foto de perfil.'); return }
                        if (!authForm.full_name.trim()) { setError('Debes ingresar tu nombre completo.'); return }
                        if (!authForm.phone.trim()) { setError('Debes ingresar los 8 digitos de tu telefono.'); return }
                        if (!validatePanamaPhone(authForm.phone)) { setError('Ingresa 8 digitos validos despues del prefijo +507.'); return }
                      }
                      if (registerStep === 2) {
                        const docError = validateDocumentNumber(authForm.document_type, authForm.cedula)
                        if (docError) { setError(docError); return }
                        if (!authForm.birthdate) { setError('Debes ingresar tu fecha de nacimiento.'); return }
                        if (!isAtLeast18(authForm.birthdate)) { setError('Debes ser mayor de 18 aÃ±os para participar.'); return }
                      }
                      if (registerStep === 3) {
                        if (!authForm.group_stage_goal_prediction.trim()) { setError('Debes ingresar tu pronostico de desempate.'); return }
                        if (!authForm.branch_id) { setError('Debes seleccionar tu sucursal de preferencia.'); return }
                      }
                      if (registerStep === 4) {
                        if (!authForm.resides_in_panama) { setError('Debes confirmar que resides en PanamÃ¡.'); return }
                        if (!authForm.accepted_terms) { setError('Debes leer y aceptar los tÃ©rminos y condiciones.'); return }
                      }
                      setRegisterStep((s) => s + 1)
                    }}
                  >
                    Siguiente
                    <span className="material-symbols-outlined">arrow_forward</span>
                  </button>
                ) : (
                  <button className="auth-submit auth-submit-step" disabled={loading || !registrationStepValid} type="submit">
                    {loading ? 'Procesando...' : isCompletingGoogleRegistration ? 'Completar registro con Google' : 'Completar registro'}
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
              {authMode === 'register'
                ? `Registro hasta el ${REGISTRATION_DEADLINE}`
                : authMode === 'forgot-password' || authMode === 'reset-password'
                  ? 'Recuperación de acceso segura'
                  : 'Mundialista Â· Super Carnes 2026'}
            </p>
          </form>
          </div>
          <form className="auth-newsletter" onSubmit={handleNewsletterSubmit}>
            <div>
              <strong>Newsletter</strong>
              <p>Recibe novedades y fechas importantes sin salir de esta pantalla.</p>
            </div>
            <div className="auth-newsletter-row">
              <input
                type="email"
                placeholder="tu@correo.com"
                value={newsletterEmail || authForm.email}
                onChange={(event) => setNewsletterEmail(event.target.value)}
              />
              <button type="submit" disabled={loading}>
                Suscribirme
              </button>
            </div>
          </form>
          <div className="auth-reference-footer" aria-label="Como participar">
            <div className="auth-reference-footer-title">¿CÓMO PARTICIPAR?</div>
            <div className="auth-reference-footer-steps">
              {[
                'Registrate gratis',
                'Pronostica los resultados',
                'Acumula puntos si aciertas',
                'Gana los mejores premios',
                'Revisa tu ranking y sigue sumando',
              ].map((step, index) => (
                <article key={step}>
                  <span>{index + 1}</span>
                  <strong>{step}</strong>
                </article>
              ))}
            </div>
            <p>Promocion valida del 11 de junio al 19 de julio de 2026. Aplican terminos y condiciones.</p>
          </div>
        </section>
      ) : (
        <div className={`client-shell bg-background text-on-background font-body-lg min-h-screen ${usesCanchaShell ? 'client-shell--cancha-reference' : ''} ${demoTourStep ? 'demo-tour-mode' : ''}`}>
          <header className="marea-client-header marea-client-header--wide bg-background/80 backdrop-blur-xl border-b border-outline-variant bg-surface-container-lowest/90 docked full-width top-0 sticky z-50 shadow-md">
            <div className="marea-client-header-inner flex justify-between items-center px-4 md:px-margin-desktop w-full max-w-7xl mx-auto h-16">
              <div className="marea-client-header-start flex items-center gap-3">
                <button
                  className="marea-header-menu marea-header-icon material-symbols-outlined text-on-surface-variant hover:text-primary transition-all"
                  type="button"
                  onClick={() => setSidebarOpen((value) => !value)}
                  aria-label="Abrir menu"
                >
                  menu
                </button>
                <button className="marea-header-brand" type="button" onClick={() => navigateToView('cancha')} aria-label="Ir a La Cancha">
                  <span className="marea-header-brand-logo">{headerBrand}</span>
                  <span className="marea-header-brand-copy">
                    <span className="marea-header-brand-kicker">PANAMA JUEGA</span>
                    <strong>Y TU GANAS</strong>
                  </span>
                </button>
              </div>
              <nav
                className={`marea-header-nav hidden md:flex items-center gap-3 demo-tour-target ${isDemoTargetActive('demo-main-navigation') ? 'is-demo-tour-active-target' : ''}`}
                data-demo-target="demo-main-navigation"
              >
                <button className={topNavButton('cancha')} type="button" onClick={() => navigateToView('cancha')}>
                  La Cancha
                </button>
                <button className={topNavButton('facturas')} type="button" onClick={() => navigateToView('facturas')}>
                  Entrenamiento
                </button>
                <button className={topNavButton('perfil')} type="button" onClick={() => navigateToView('perfil')}>
                  Ranking
                </button>
                <button className={topNavButton('reglas')} type="button" onClick={() => navigateToView('reglas')}>
                  Vitrina
                </button>
              </nav>
              <div className="marea-client-header-end flex items-center gap-4">
                <button
                  className="demo-tour-reopen-button"
                  type="button"
                  onClick={startDemoTour}
                  aria-label="Ver tour guiado"
                >
                  <span className="material-symbols-outlined">assistant_navigation</span>
                  <span>Ver tour</span>
                </button>
                <button className="marea-header-icon marea-header-notifications text-on-surface-variant hover:text-primary transition-all" type="button" aria-label="Notificaciones">
                  <span className="material-symbols-outlined">notifications</span>
                  <span className="cancha-notification-dot" />
                </button>
                <button
                  className="marea-header-icon marea-header-invoice-mobile material-symbols-outlined text-on-surface-variant hover:text-primary transition-all md:hidden"
                  type="button"
                  onClick={() => navigateToView('facturas')}
                  aria-label="Registrar factura"
                >
                  receipt_long
                </button>
                <div className="marea-header-user-menu" ref={userMenuRef}>
                  <button
                    aria-expanded={userMenuOpen || mobileUserSidebarOpen}
                    aria-haspopup="menu"
                    className="marea-header-avatar"
                    type="button"
                    onClick={() => {
                      if (window.innerWidth < 640) {
                        setMobileUserSidebarOpen((v) => !v)
                      } else {
                        setUserMenuOpen((value) => !value)
                      }
                    }}
                    aria-label="Abrir menu de usuario"
                  >
                    {playerAvatarUrl ? <img alt={`Avatar de ${playerName}`} src={playerAvatarUrl} /> : <span>{playerBadge}</span>}
                  </button>
                  {userMenuOpen ? (
                    <div className="marea-header-user-dropdown" role="menu" aria-label="Menu de usuario">
                      <button className="marea-header-user-item" role="menuitem" type="button" onClick={() => openAccountSection('perfil')}>
                        <span className="material-symbols-outlined">person</span>
                        <span>Mi cuenta</span>
                      </button>
                      <button className="marea-header-user-item" role="menuitem" type="button" onClick={() => openAccountSection('perfil')}>
                        <span className="material-symbols-outlined">settings</span>
                        <span>Ajustes</span>
                      </button>
                      <button className="marea-header-user-item danger" role="menuitem" type="button" onClick={() => void handleLogout()}>
                        <span className="material-symbols-outlined">logout</span>
                        <span>Cerrar sesión</span>
                      </button>
                    </div>
                  ) : null}
                </div>
                <button className="marea-header-cta bg-primary-container text-on-tertiary-container font-display-lg px-4 py-1 rounded-lg text-sm hover:opacity-80 active:scale-95 transition-all" type="button" onClick={() => navigateToView('facturas')}>
                  <span className="material-symbols-outlined">receipt_long</span>
                  Registrar factura
                </button>
              </div>
            </div>
          </header>

          {sidebarOpen ? <button className="marea-sidebar-overlay fixed inset-0 z-30 bg-black/45 md:hidden" type="button" onClick={() => setSidebarOpen(false)} /> : null}

          {mobileUserSidebarOpen ? (
            <>
              <button
                className="fixed inset-0 z-[60] bg-black/55 md:hidden"
                type="button"
                onClick={() => setMobileUserSidebarOpen(false)}
                aria-label="Cerrar menu de usuario"
              />
              <div ref={mobileUserSidebarRef} className="marea-mobile-user-sidebar md:hidden">
                <div className="marea-mobile-user-sidebar-header">
                  <span>Mi cuenta</span>
                  <button className="marea-mobile-user-sidebar-close material-symbols-outlined" type="button" onClick={() => setMobileUserSidebarOpen(false)}>
                    close
                  </button>
                </div>
                <div className="marea-mobile-user-sidebar-profile">
                  <div className="marea-mobile-user-sidebar-avatar">
                    {playerAvatarUrl ? <img alt={`Avatar de ${playerName}`} src={playerAvatarUrl} /> : <span>{playerBadge}</span>}
                  </div>
                  <div>
                    <p className="marea-mobile-user-sidebar-name">{playerName}</p>
                  </div>
                </div>
                <nav className="marea-mobile-user-sidebar-nav">
                  <button className="marea-mobile-user-sidebar-item" type="button" onClick={() => { openAccountSection('perfil'); setMobileUserSidebarOpen(false); }}>
                    <span className="material-symbols-outlined">person</span>
                    <span>Mi cuenta</span>
                  </button>
                  <button className="marea-mobile-user-sidebar-item" type="button" onClick={() => { openAccountSection('perfil'); setMobileUserSidebarOpen(false); }}>
                    <span className="material-symbols-outlined">settings</span>
                    <span>Ajustes</span>
                  </button>
                  <div className="marea-mobile-user-sidebar-divider" />
                  <button className="marea-mobile-user-sidebar-item danger" type="button" onClick={() => { void handleLogout(); setMobileUserSidebarOpen(false); }}>
                    <span className="material-symbols-outlined">logout</span>
                    <span>Cerrar sesión</span>
                  </button>
                </nav>
              </div>
            </>
          ) : null}

          <div className="flex w-full">
            <aside
              className={
                sidebarOpen
                  ? 'marea-client-sidebar fixed inset-y-16 left-0 z-40 flex w-64 flex-col bg-surface-container-low border-r border-outline-variant py-unit md:sticky md:top-16 md:h-[calc(100vh-64px)]'
                  : 'marea-client-sidebar hidden'
              }
            >
              <div className="px-6 py-4 mb-4">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 rounded-full bg-secondary border-2 border-primary-container flex items-center justify-center font-display-lg text-sm text-on-secondary overflow-hidden">
                    {playerAvatarUrl ? <img alt={`Avatar de ${playerName}`} className="user-avatar-image" src={playerAvatarUrl} /> : playerBadge}
                  </div>
                  <div>
                    <div className="font-title-md text-title-md text-on-surface leading-tight">{playerName}</div>
                    <div className="font-label-caps text-label-caps text-primary-container">
                      {sidebarIdentityLabel}
                    </div>
                  </div>
                </div>
                <div className="marea-sidebar-user-stats">
                  <span className="material-symbols-outlined">confirmation_number</span>
                  <span>Facturas registradas: {invoices.length}</span>
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
                  <span className="material-symbols-outlined">leaderboard</span>
                  <span className="font-label-caps text-label-caps">Ranking</span>
                </button>
              </nav>
              <div className="mt-auto border-t border-outline-variant pt-4 flex flex-col gap-1">
                <button className="demo-tour-side-button" type="button" onClick={startDemoTour}>
                  <span className="material-symbols-outlined">assistant_navigation</span>
                  <span className="font-label-caps text-label-caps">Ver tour</span>
                </button>
                <button className={sideNavButton('cuenta')} type="button" onClick={() => openAccountSection('perfil')}>
                  <span className="material-symbols-outlined">settings</span>
                  <span className="font-label-caps text-label-caps">Ajustes</span>
                </button>
                <button className="flex items-center gap-4 text-primary-container p-3 mx-2 hover:bg-primary-container/10 transition-all" type="button" onClick={() => void handleLogout()}>
                  <span className="material-symbols-outlined">logout</span>
                  <span className="font-label-caps text-label-caps">Cerrar sesión</span>
                </button>
              </div>
            </aside>

            <main className="marea-main flex-1 min-w-0">
              <div className="marea-hero-background">
                {heroVideoUrl ? (
                  <video aria-hidden="true" autoPlay loop muted playsInline poster={STADIUM_IMAGE_URL}>
                    <source src={heroVideoUrl} />
                  </video>
                ) : (
                  <img alt="Ambiente del estadio para PRONOSTICA EL MUNDIAL Y GANA" src={STADIUM_IMAGE_URL} />
                )}
                <div className="marea-hero-overlay" />
              </div>
              <div className={`marea-content ${usesCanchaShell ? 'marea-content--cancha-reference' : ''} ${currentView === 'facturas' ? 'marea-content--facturas-reference' : ''}`}>
                <ClientDemoTour
                  showBanner={showDemoTourBanner}
                  step={demoTourStep}
                  stepIndex={demoTourState.stepIndex}
                  totalSteps={DEMO_TOUR_STEPS.length}
                  onStart={startDemoTour}
                  onDismiss={dismissDemoTourBanner}
                  onNext={advanceDemoTour}
                  onPrevious={goBackDemoTour}
                  onClose={closeDemoTour}
                  onComplete={completeDemoTour}
                />
                <div
                  className={`demo-tour-target ${isDemoTargetActive(currentViewDemoTarget) ? 'is-demo-tour-active-target' : ''}`}
                  data-demo-target={currentViewDemoTarget}
                >
                  {renderMainView()}
                </div>
              </div>
            </main>
          </div>

          <nav
            className={`marea-mobile-bottom-nav md:hidden fixed bottom-0 left-0 right-0 h-16 bg-surface-container-lowest/90 backdrop-blur-xl border-t border-outline-variant flex justify-around items-center px-4 z-50 demo-tour-target ${isDemoTargetActive('demo-main-navigation') ? 'is-demo-tour-active-target' : ''}`}
            data-demo-target="demo-main-navigation"
          >
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
              <span className="material-symbols-outlined">leaderboard</span>
              <span className="text-[10px] font-bold">Ranking</span>
            </button>
            <button className={`flex flex-col items-center gap-1 ${currentView === 'reglas' ? 'text-primary-container' : 'text-on-surface-variant'}`} type="button" onClick={() => navigateToView('reglas')}>
              <span className="material-symbols-outlined">storefront</span>
              <span className="text-[10px] font-bold">Vitrina</span>
            </button>
          </nav>

          <button className="marea-mobile-menu-fab fixed bottom-20 right-6 md:bottom-8 md:right-8 w-14 h-14 bg-primary-container text-white rounded-full shadow-2xl flex items-center justify-center hover:scale-110 active:scale-95 transition-all md:hidden z-40" type="button" onClick={() => setSidebarOpen((value) => !value)}>
            <span className="material-symbols-outlined text-3xl">{sidebarOpen ? 'close' : 'menu'}</span>
          </button>
        </div>
      )}
    </div>
  )
}



