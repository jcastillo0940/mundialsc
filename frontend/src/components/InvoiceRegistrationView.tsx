import type { ChangeEvent, FormEvent } from 'react'
import { useMemo } from 'react'
import type { RegisteredInvoice, ResolvedInvoiceData } from '../types'

type InvoiceEntryMode = 'scan' | 'manual'

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
}

interface InvoiceRegistrationViewProps {
  invoices: RegisteredInvoice[]
  invoiceEntryMode: InvoiceEntryMode
  invoiceForm: InvoiceFormState
  invoiceGalleryProcessing: boolean
  invoiceResolving: boolean
  invoiceScannerError: string | null
  invoiceScannerDebug: InvoiceScannerDebugInfo
  invoiceSubmitting: boolean
  resolvedInvoiceData: ResolvedInvoiceData | null
  stadiumImageUrl: string
  detectedCufe: string
  onSubmit: (event: FormEvent<HTMLFormElement>) => void | Promise<void>
  onGalleryUpload: (event: ChangeEvent<HTMLInputElement>) => void | Promise<void>
  onModeChange: (mode: InvoiceEntryMode) => void
  onFieldChange: <K extends keyof InvoiceFormState>(field: K, value: InvoiceFormState[K]) => void
}

function formatCurrency(value: number | string | null | undefined) {
  const amount = Number(value ?? 0)
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number.isFinite(amount) ? amount : 0)
}

function formatUpperDate(dateValue: string) {
  const date = new Date(dateValue)
  if (Number.isNaN(date.getTime())) return dateValue

  const day = date.toLocaleDateString('es-PA', { day: 'numeric', timeZone: 'America/Panama' })
  const month = date.toLocaleDateString('es-PA', { month: 'long', timeZone: 'America/Panama' })
  return `${day} ${month.charAt(0).toUpperCase()}${month.slice(1)}`
}

function invoiceStatusMeta(status: string) {
  if (status === 'approved') return { tone: 'approved' as const }
  if (status === 'pending') return { tone: 'pending' as const }
  return { tone: 'rejected' as const }
}

function getCufeSegments(value: string) {
  const segments = value.split('-').filter(Boolean)
  const numericTail = segments.at(-1) ?? ''
  const prefix = segments.length > 1 ? `${segments.slice(0, -1).join('-')}-` : ''

  return { prefix, numericTail }
}

export function InvoiceRegistrationView({
  invoices,
  invoiceEntryMode,
  invoiceForm,
  invoiceGalleryProcessing,
  invoiceResolving,
  invoiceScannerError,
  invoiceScannerDebug,
  invoiceSubmitting,
  resolvedInvoiceData,
  stadiumImageUrl,
  detectedCufe,
  onGalleryUpload,
  onSubmit,
  onModeChange,
  onFieldChange,
}: InvoiceRegistrationViewProps) {
  const totalInvoicePoints = useMemo(
    () =>
      invoices
        .filter((invoice) => invoice.validation_status === 'approved')
        .reduce((total, invoice) => total + Number(invoice.points_awarded ?? 0), 0),
    [invoices],
  )

  const invoiceCards = useMemo(
    () =>
      invoices.length
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
            const badgeText =
              status.tone === 'approved' ? `+${Number(invoice.points_awarded ?? 0).toFixed(1)}` : status.tone === 'pending' ? '+0.0' : '0.0'
            const labelText = status.tone === 'approved' ? 'GOL VALIDO' : status.tone === 'pending' ? 'REVISION VAR' : 'FUERA DE JUEGO'
            const iconName = status.tone === 'approved' ? 'verified' : status.tone === 'pending' ? 'update' : 'dangerous'
            const title = invoice.invoice_number ? `#${invoice.invoice_number}` : `#PAN-${String(invoice.id).padStart(6, '0')}`
            const referenceDate = invoice.issued_at ?? invoice.created_at
            const subtitle = `${referenceDate ? formatUpperDate(referenceDate) : 'Fecha pendiente'} | ${formatCurrency(invoice.purchase_amount)}`

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
        : null,
    [invoices],
  )

  const cufeSegments = useMemo(() => getCufeSegments(detectedCufe), [detectedCufe])
  const hasDetectedCufe = Boolean(detectedCufe)
  const hasResolvedInvoice = Boolean(resolvedInvoiceData)

  return (
    <div className="facturas-view">
      <div className="relative w-full rounded-3xl overflow-hidden mb-gutter h-48 md:h-64 flex items-end p-6 md:p-10 border border-outline-variant">
        <div className="absolute inset-0 z-0">
          <img alt="Estadio Rommel Fernandez" className="w-full h-full object-cover opacity-50 scale-105" src={stadiumImageUrl} />
          <div className="absolute inset-0 bg-gradient-to-t from-background via-background/60 to-transparent"></div>
        </div>
        <div className="relative z-10">
          <h1 className="font-display-lg text-headline-lg md:text-display-lg text-on-surface leading-none mb-2">EL ENTRENAMIENTO NACIONAL</h1>
          <p className="text-secondary font-title-md text-title-md uppercase tracking-widest">Sube una foto de la factura y te ayudamos a escribir el CUFE</p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
        <section className="lg:col-span-5 flex flex-col gap-gutter">
          <div className="bg-surface-container-low p-6 rounded-[1.5rem] border border-outline-variant pitch-glow">
            <div className="flex items-center gap-4 mb-6">
              <div className="bg-primary-container/20 p-3 rounded-xl border border-primary-container/30">
                <span className="material-symbols-outlined text-primary-container text-3xl" data-weight="fill">
                  document_scanner
                </span>
              </div>
              <div>
                <h2 className="font-headline-lg text-headline-lg leading-none">REGISTRO</h2>
                <p className="text-on-surface-variant text-body-sm">La forma mas rapida es tomar una foto. Intentaremos leer el CUFE y escribirlo por ti.</p>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-3 mb-6">
              <button
                className={invoiceEntryMode === 'manual' ? 'bg-primary-container text-white font-label-caps py-3 rounded-xl border border-primary-container' : 'bg-surface-container-lowest text-on-surface font-label-caps py-3 rounded-xl border border-outline-variant'}
                type="button"
                onClick={() => onModeChange('manual')}
              >
                Foto y CUFE
              </button>
              <button
                className={invoiceEntryMode === 'scan' ? 'bg-primary-container text-white font-label-caps py-3 rounded-xl border border-primary-container' : 'bg-surface-container-lowest text-on-surface font-label-caps py-3 rounded-xl border border-outline-variant'}
                type="button"
                onClick={() => onModeChange('scan')}
              >
                QR en vivo
              </button>
            </div>

            <form className="flex flex-col gap-6" onSubmit={onSubmit}>
              <div className="flex flex-col gap-3">
                <label className="font-label-caps text-label-caps text-on-surface-variant ml-1">FOTO DE LA FACTURA</label>
                <label className="bg-surface-container-lowest border border-dashed border-outline-variant rounded-2xl p-5 cursor-pointer hover:bg-surface-container transition-colors">
                  <div className="flex items-start gap-4">
                    <div className="bg-primary-container/10 text-primary-container rounded-2xl p-3 border border-primary-container/20">
                      <span className="material-symbols-outlined" data-weight="fill">
                        photo_camera
                      </span>
                    </div>
                    <div className="flex-1">
                      <div className="font-title-md text-on-surface">
                        {invoiceGalleryProcessing ? 'Leyendo la imagen y buscando el CUFE...' : 'Tomar foto o subir imagen'}
                      </div>
                      <p className="text-body-sm text-on-surface-variant mt-1">
                        Abre la camara del telefono o elige una imagen de la galeria. Si detectamos el CUFE, lo escribimos automaticamente abajo.
                      </p>
                    </div>
                  </div>
                  <input accept="image/*" capture="environment" className="hidden" disabled={invoiceGalleryProcessing} type="file" onChange={onGalleryUpload} />
                </label>
                <p className="text-body-sm text-on-surface-variant">
                  Consejo: procura que se vea completa la linea del CUFE o la zona inferior de la factura.
                </p>
              </div>

              {invoiceEntryMode === 'scan' ? (
                <div className="flex flex-col gap-3">
                  <label className="font-label-caps text-label-caps text-on-surface-variant ml-1">ESCANEAR QR DGI EN VIVO</label>
                  <div className="bg-surface-container-lowest border border-outline-variant rounded-2xl p-3">
                    <div id="dgi-qr-reader" className="overflow-hidden rounded-xl min-h-[260px]" />
                  </div>
                  <p className="text-body-sm text-on-surface-variant">
                    Esta opcion depende de permisos de camara y HTTPS. Si falla, usa la foto de arriba: tambien puede extraer el CUFE por OCR.
                  </p>
                  <details className="bg-surface-container rounded-xl border border-outline-variant p-4 text-body-sm text-on-surface-variant">
                    <summary className="font-title-md text-on-surface cursor-pointer">Ver diagnostico del escaner</summary>
                    <div className="space-y-2 mt-3">
                      <p>
                        Estado: <strong className="text-on-surface">{invoiceScannerDebug.lastStage}</strong>
                      </p>
                      <p>
                        Contexto seguro: <strong className="text-on-surface">{invoiceScannerDebug.isSecureContext ? 'si' : 'no'}</strong>
                      </p>
                      <p>
                        Origen actual: <strong className="text-on-surface break-all">{invoiceScannerDebug.origin}</strong>
                      </p>
                      <p>
                        Camara por SSL/HTTPS: <strong className="text-on-surface">{invoiceScannerDebug.likelyCameraBlockedBySecurity ? 'probablemente bloqueada' : 'sin bloqueo evidente'}</strong>
                      </p>
                      <p>
                        `mediaDevices`: <strong className="text-on-surface">{invoiceScannerDebug.hasMediaDevices ? 'disponible' : 'no disponible'}</strong>
                      </p>
                      <p>
                        `getUserMedia`: <strong className="text-on-surface">{invoiceScannerDebug.hasGetUserMedia ? 'disponible' : 'no disponible'}</strong>
                      </p>
                      <p>
                        Permiso de camara: <strong className="text-on-surface">{invoiceScannerDebug.cameraPermission}</strong>
                      </p>
                      <p>
                        Lectura desde galeria: <strong className="text-on-surface">{invoiceScannerDebug.fileReaderSupported ? 'disponible' : 'no disponible'}</strong>
                      </p>
                      {invoiceScannerDebug.lastError ? (
                        <p className="break-all">
                          Ultimo error: <strong className="text-error">{invoiceScannerDebug.lastError}</strong>
                        </p>
                      ) : null}
                    </div>
                  </details>
                </div>
              ) : null}

              {invoiceScannerError ? (
                <div className="bg-error/10 border border-error/20 rounded-xl p-3 text-body-sm text-error">
                  {invoiceScannerError}
                </div>
              ) : null}

              <div className="flex flex-col gap-2">
                <label className="font-label-caps text-label-caps text-on-surface-variant ml-1">CUFE DE LA FACTURA</label>
                <textarea
                  className="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 text-on-surface font-title-md transition-all"
                  placeholder="Escribe o pega el CUFE completo. Si subes una foto, intentaremos llenarlo por ti."
                  required
                  rows={3}
                  value={invoiceForm.rawInput}
                  onChange={(event) => onFieldChange('rawInput', event.target.value)}
                />
                <p className="text-body-sm text-on-surface-variant">
                  Puedes corregir el texto manualmente si el OCR confundio algun caracter.
                </p>
                <div className="bg-surface-container rounded-xl border border-outline-variant p-4 space-y-3">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <p className="font-title-md text-on-surface">Lectura del CUFE</p>
                      <p className="text-body-sm text-on-surface-variant">
                        {hasDetectedCufe ? 'Detectamos una estructura valida. Revisa solo si hace falta.' : 'Todavia no detectamos un CUFE completo.'}
                      </p>
                    </div>
                    <span
                      className={`px-3 py-1 rounded-full text-[10px] font-bold uppercase border ${
                        hasDetectedCufe
                          ? 'bg-primary-container/10 text-primary-container border-primary-container/20'
                          : 'bg-surface-container-lowest text-on-surface-variant border-outline-variant'
                      }`}
                    >
                      {hasDetectedCufe ? 'CUFE detectado' : 'Pendiente'}
                    </span>
                  </div>
                  {cufeSegments.prefix ? (
                    <div className="text-body-sm text-on-surface-variant break-all">
                      <strong className="text-on-surface">Prefijo:</strong> {cufeSegments.prefix}
                    </div>
                  ) : null}
                  <div className="flex items-center justify-between gap-3 text-body-sm">
                    <span className="text-on-surface-variant">Bloque numerico final</span>
                    <strong className={cufeSegments.numericTail.length >= 40 ? 'text-primary-container' : 'text-on-surface'}>
                      {cufeSegments.numericTail.length}/40
                    </strong>
                  </div>
                  {hasDetectedCufe ? (
                    <div className="text-body-sm text-on-surface-variant break-all">
                      <strong className="text-on-surface">CUFE detectado:</strong> {detectedCufe}
                    </div>
                  ) : null}
                </div>
                {invoiceResolving ? (
                  <div className="bg-surface-container rounded-xl border border-outline-variant p-3 text-body-sm text-on-surface-variant">
                    Consultando datos oficiales de la factura...
                  </div>
                ) : null}
              </div>

              <div className="flex flex-col gap-2">
                <label className="font-label-caps text-label-caps text-on-surface-variant ml-1">NUMERO DE FACTURA</label>
                <input
                  className="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 text-on-surface font-title-md transition-all"
                  placeholder="Autollenado por DGI"
                  readOnly
                  type="text"
                  value={invoiceForm.invoice_number}
                />
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="flex flex-col gap-2">
                  <label className="font-label-caps text-label-caps text-on-surface-variant ml-1">MONTO TOTAL ($)</label>
                  <input
                    className="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 text-on-surface font-title-md transition-all"
                    placeholder="Autollenado por DGI"
                    required
                    min="0.01"
                    readOnly
                    step="0.01"
                    type="number"
                    value={invoiceForm.purchase_amount}
                  />
                </div>
                <div className="flex flex-col gap-2">
                  <label className="font-label-caps text-label-caps text-on-surface-variant ml-1">FECHA DE FACTURA</label>
                  <input
                    className="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 text-on-surface font-title-md transition-all"
                    required
                    readOnly
                    type="date"
                    value={invoiceForm.issued_at}
                  />
                </div>
              </div>
              {resolvedInvoiceData?.issuer_name ? (
                <div className="bg-surface-container rounded-xl border border-outline-variant p-3 text-body-sm text-on-surface-variant">
                  <strong className="text-on-surface">Emisor:</strong> {resolvedInvoiceData.issuer_name}
                </div>
              ) : null}

              <div className="mt-4 flex flex-col gap-3">
                <button
                  className="bg-primary-container text-white font-display-lg text-headline-lg py-4 rounded-xl flex items-center justify-center gap-3 hover:opacity-90 active:scale-[0.98] transition-all group disabled:opacity-60 disabled:cursor-not-allowed"
                  disabled={invoiceSubmitting || invoiceResolving || !hasResolvedInvoice}
                  type="submit"
                >
                  <span>{invoiceSubmitting ? 'REGISTRANDO FACTURA...' : 'REGISTRAR FACTURA DGI'}</span>
                  <span className="material-symbols-outlined group-hover:translate-x-1 transition-transform">sports_soccer</span>
                </button>
                {!hasResolvedInvoice ? (
                  <p className="text-center text-body-sm text-on-surface-variant">El boton se activa cuando DGI confirma el CUFE y trae los datos oficiales de la factura.</p>
                ) : null}
                <p className="text-center text-body-sm text-on-surface-variant italic">Cada factura valida te acerca a la Copa.</p>
              </div>
            </form>
          </div>

          <div className="bg-surface-container rounded-[1.5rem] p-6 border-l-4 border-secondary flex items-start gap-4">
            <span className="material-symbols-outlined text-secondary text-3xl">lightbulb</span>
              <div>
                <h3 className="font-title-md text-title-md text-secondary uppercase">TIP DEL CAPITAN</h3>
                <p className="text-body-sm text-on-surface-variant">
                  "Si el QR no abre, toma una foto nitida de la parte baja de la factura. El sistema intentara leer el CUFE y dejarlo listo para validar."
                </p>
              </div>
            </div>
        </section>

        <section className="lg:col-span-7">
          <div className="bg-surface-container-low rounded-[1.5rem] border border-outline-variant h-full flex flex-col overflow-hidden">
            <div className="p-6 border-b border-outline-variant flex justify-between items-center">
              <h2 className="font-headline-lg text-headline-lg">HISTORIAL DE FACTURAS</h2>
              <div className="flex gap-2">
                <span className="bg-primary-container/10 text-primary-container px-3 py-1 rounded-full text-[10px] font-bold border border-primary-container/20 uppercase">
                  {totalInvoicePoints.toFixed(1)} Goles Totales
                </span>
              </div>
            </div>
            <div className="flex-1 overflow-y-auto p-4 space-y-3 max-h-[600px]">
              {invoiceCards ?? (
                <div className="bg-surface-container-lowest p-8 rounded-2xl border border-outline-variant text-center">
                  <div className="font-title-md text-on-surface mb-2">Sin facturas registradas todavia</div>
                  <div className="text-body-sm text-on-surface-variant">Tus facturas validadas, pendientes o rechazadas apareceran aqui.</div>
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
