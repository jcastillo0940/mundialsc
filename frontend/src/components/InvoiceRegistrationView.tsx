import type { ChangeEvent, FormEvent } from 'react'
import { useMemo, useState } from 'react'
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
  barcodeDetectorAvailable: boolean
  scannerType: 'native' | 'html5-qrcode' | 'none'
  activeFormats: string[]
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
  onSubmit: (event: FormEvent<HTMLFormElement>) => void | Promise<void>
  onGalleryUpload: (event: ChangeEvent<HTMLInputElement>) => void | Promise<void>
  onModeChange: (mode: InvoiceEntryMode) => void
  onFieldChange: <K extends keyof InvoiceFormState>(field: K, value: InvoiceFormState[K]) => void
}

function formatCurrency(value: number | string | null | undefined) {
  const amount = Number(value ?? 0)
  return new Intl.NumberFormat('es-PA', { style: 'currency', currency: 'USD' }).format(
    Number.isFinite(amount) ? amount : 0,
  )
}

function formatDate(dateValue: string) {
  if (!dateValue) return ''
  const date = new Date(`${dateValue}T12:00:00`)
  if (Number.isNaN(date.getTime())) return dateValue
  return date.toLocaleDateString('es-PA', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'America/Panama' })
}

function DebugRow({ label, value, ok }: { label: string; value: string; ok: boolean }) {
  return (
    <div className="flex gap-2 items-start">
      <span className={`flex-shrink-0 w-2 h-2 rounded-full mt-0.5 ${ok ? 'bg-emerald-500' : 'bg-red-500'}`} />
      <span className="text-on-surface-variant/70 flex-shrink-0 min-w-[110px]">{label}:</span>
      <span className={ok ? 'text-on-surface' : 'text-red-400'}>{value}</span>
    </div>
  )
}

function invoiceStatusMeta(status: string) {
  if (status === 'approved') return { tone: 'approved' as const, label: 'GOL VÁLIDO', icon: 'verified', points: true }
  if (status === 'pending') return { tone: 'pending' as const, label: 'EN REVISIÓN', icon: 'update', points: false }
  return { tone: 'rejected' as const, label: 'RECHAZADA', icon: 'dangerous', points: false }
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
  onGalleryUpload,
  onSubmit,
  onModeChange,
  onFieldChange,
}: InvoiceRegistrationViewProps) {
  const hasResolved = Boolean(resolvedInvoiceData)
  const [debugOpen, setDebugOpen] = useState(false)

  const totalPoints = useMemo(
    () =>
      invoices
        .filter((i) => i.validation_status === 'approved')
        .reduce((sum, i) => sum + Number(i.points_awarded ?? 0), 0),
    [invoices],
  )

  const invoiceCards = useMemo(
    () =>
      invoices.slice(0, 5).map((invoice) => {
        const meta = invoiceStatusMeta(invoice.validation_status)
        const colorMap = {
          approved: { border: 'border-emerald-500', bg: 'bg-emerald-500/10', text: 'text-emerald-400', badge: 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' },
          pending:  { border: 'border-yellow-500',  bg: 'bg-yellow-500/10',  text: 'text-yellow-400',  badge: 'bg-yellow-500/20  text-yellow-400  border-yellow-500/30'  },
          rejected: { border: 'border-red-500',     bg: 'bg-red-500/10',     text: 'text-red-400',     badge: 'bg-red-500/20     text-red-400     border-red-500/30'     },
        }
        const c = colorMap[meta.tone]
        const title = invoice.invoice_number ? `#${invoice.invoice_number}` : `#${String(invoice.id).padStart(6, '0')}`
        const date = invoice.issued_at ?? invoice.created_at

        return (
          <div key={invoice.id} className={`flex items-center gap-3 p-3 rounded-xl border-l-4 ${c.border} bg-surface-container-lowest`}>
            <div className={`w-9 h-9 rounded-full ${c.bg} flex items-center justify-center flex-shrink-0`}>
              <span className={`material-symbols-outlined text-base ${c.text}`} data-weight="fill">{meta.icon}</span>
            </div>
            <div className="flex-1 min-w-0">
              <div className="font-title-md text-on-surface text-sm truncate">{title}</div>
              <div className="text-xs text-on-surface-variant">{date ? formatDate(date) : ''} · {formatCurrency(invoice.purchase_amount)}</div>
            </div>
            <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full border ${c.badge} whitespace-nowrap`}>
              {meta.points ? `+${Number(invoice.points_awarded ?? 0).toFixed(1)} GOL` : meta.label}
            </span>
          </div>
        )
      }),
    [invoices],
  )

  return (
    <div className="facturas-view flex flex-col gap-6 max-w-lg mx-auto lg:max-w-none">

      {/* Tabs: QR primero, Manual segundo */}
      <div className="flex rounded-2xl border border-outline-variant overflow-hidden">
        <button
          type="button"
          className={`flex-1 flex items-center justify-center gap-2 py-3.5 text-sm font-semibold transition-colors ${
            invoiceEntryMode === 'scan'
              ? 'bg-primary-container text-white'
              : 'bg-surface-container-lowest text-on-surface-variant hover:bg-surface-container'
          }`}
          onClick={() => onModeChange('scan')}
        >
          <span className="material-symbols-outlined text-lg">qr_code_scanner</span>
          Escanear QR
        </button>
        <button
          type="button"
          className={`flex-1 flex items-center justify-center gap-2 py-3.5 text-sm font-semibold transition-colors border-l border-outline-variant ${
            invoiceEntryMode === 'manual'
              ? 'bg-primary-container text-white'
              : 'bg-surface-container-lowest text-on-surface-variant hover:bg-surface-container'
          }`}
          onClick={() => onModeChange('manual')}
        >
          <span className="material-symbols-outlined text-lg">edit</span>
          Ingresar CUFE
        </button>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <section className="lg:col-span-5 flex flex-col gap-4">

          {/* Panel QR */}
          {invoiceEntryMode === 'scan' && (
            <div className="bg-surface-container-low rounded-2xl border border-outline-variant overflow-hidden">
              <div className="px-5 pt-5 pb-3">
                <p className="text-sm text-on-surface-variant text-center">
                  Apunta la cámara al código QR de tu factura DGI
                </p>
              </div>
              <div id="dgi-qr-reader" className="w-full min-h-[280px] bg-black" />
              {invoiceScannerError && (
                <div className="mx-4 mb-4 mt-2 flex items-start gap-2 bg-red-500/10 border border-red-500/20 rounded-xl p-3 text-sm text-red-400">
                  <span className="material-symbols-outlined text-base flex-shrink-0 mt-0.5">error</span>
                  {invoiceScannerError}
                </div>
              )}
              <div className="px-5 pb-3 pt-2 text-center">
                <p className="text-xs text-on-surface-variant">
                  Si no funciona el escáner,{' '}
                  <button type="button" className="underline text-primary-container" onClick={() => onModeChange('manual')}>
                    ingresa el CUFE manualmente
                  </button>
                </p>
              </div>

              {/* Panel de debug técnico */}
              <div className="border-t border-outline-variant">
                <button
                  type="button"
                  className="w-full flex items-center justify-between px-4 py-2.5 text-xs text-on-surface-variant hover:bg-surface-container transition-colors"
                  onClick={() => setDebugOpen((v) => !v)}
                >
                  <span className="flex items-center gap-1.5">
                    <span className="material-symbols-outlined text-sm">bug_report</span>
                    Info técnica del escáner
                  </span>
                  <span className="material-symbols-outlined text-sm">{debugOpen ? 'expand_less' : 'expand_more'}</span>
                </button>

                {debugOpen && (
                  <div className="px-4 pb-4 font-mono text-[11px] space-y-1">
                    <DebugRow
                      label="Escáner"
                      value={
                        invoiceScannerDebug.scannerType === 'native'
                          ? 'BarcodeDetector nativo ✓'
                          : invoiceScannerDebug.scannerType === 'html5-qrcode'
                            ? 'html5-qrcode (fallback)'
                            : 'sin iniciar'
                      }
                      ok={invoiceScannerDebug.scannerType !== 'none'}
                    />
                    <DebugRow
                      label="Etapa"
                      value={invoiceScannerDebug.lastStage}
                      ok={!['idle', 'camera-start-failed', 'media-devices-missing', 'insecure-context'].includes(invoiceScannerDebug.lastStage)}
                    />
                    {invoiceScannerDebug.lastError && (
                      <DebugRow label="Último error" value={invoiceScannerDebug.lastError} ok={false} />
                    )}
                    <DebugRow
                      label="BarcodeDetector"
                      value={invoiceScannerDebug.barcodeDetectorAvailable ? 'disponible' : 'no disponible'}
                      ok={invoiceScannerDebug.barcodeDetectorAvailable}
                    />
                    {invoiceScannerDebug.activeFormats.length > 0 && (
                      <DebugRow
                        label="Formatos activos"
                        value={invoiceScannerDebug.activeFormats.join(', ')}
                        ok={invoiceScannerDebug.activeFormats.includes('qr_code')}
                      />
                    )}
                    <DebugRow
                      label="Permiso cámara"
                      value={invoiceScannerDebug.cameraPermission}
                      ok={invoiceScannerDebug.cameraPermission === 'granted'}
                    />
                    <DebugRow
                      label="HTTPS / secure"
                      value={invoiceScannerDebug.isSecureContext ? 'sí' : 'NO — cámara bloqueada'}
                      ok={invoiceScannerDebug.isSecureContext}
                    />
                    <DebugRow
                      label="mediaDevices"
                      value={invoiceScannerDebug.hasMediaDevices ? 'disponible' : 'no disponible'}
                      ok={invoiceScannerDebug.hasMediaDevices}
                    />
                    <DebugRow
                      label="getUserMedia"
                      value={invoiceScannerDebug.hasGetUserMedia ? 'disponible' : 'no disponible'}
                      ok={invoiceScannerDebug.hasGetUserMedia}
                    />
                    <DebugRow label="Origen" value={invoiceScannerDebug.origin} ok={true} />
                    <div className="pt-1 text-on-surface-variant/50 break-all leading-relaxed">
                      UA: {invoiceScannerDebug.userAgent}
                    </div>
                  </div>
                )}
              </div>
            </div>
          )}

          {/* Panel Manual */}
          {invoiceEntryMode === 'manual' && (
            <div className="bg-surface-container-low rounded-2xl border border-outline-variant p-5 flex flex-col gap-4">
              <div>
                <label className="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-2">
                  CUFE de la factura
                </label>
                <textarea
                  className="w-full bg-surface-container-lowest border border-outline-variant rounded-xl p-4 text-on-surface text-sm resize-none focus:outline-none focus:border-primary-container transition-colors"
                  placeholder="Pega o escribe el CUFE completo aquí…"
                  rows={4}
                  value={invoiceForm.rawInput}
                  onChange={(e) => onFieldChange('rawInput', e.target.value)}
                />
                <p className="text-xs text-on-surface-variant mt-1.5">
                  El CUFE está en la parte inferior de tu factura electrónica DGI.
                </p>
              </div>

              {/* Subir foto como alternativa */}
              <label className="flex items-center gap-3 p-3 rounded-xl border border-dashed border-outline-variant bg-surface-container cursor-pointer hover:bg-surface-container-low transition-colors">
                <span className="material-symbols-outlined text-on-surface-variant">photo_camera</span>
                <span className="text-sm text-on-surface-variant">
                  {invoiceGalleryProcessing ? 'Leyendo imagen…' : 'O toma una foto de la factura para extraer el CUFE'}
                </span>
                <input
                  type="file"
                  accept="image/*"
                  capture="environment"
                  className="hidden"
                  disabled={invoiceGalleryProcessing}
                  onChange={onGalleryUpload}
                />
              </label>

              {invoiceScannerError && (
                <div className="flex items-start gap-2 bg-red-500/10 border border-red-500/20 rounded-xl p-3 text-sm text-red-400">
                  <span className="material-symbols-outlined text-base flex-shrink-0 mt-0.5">error</span>
                  {invoiceScannerError}
                </div>
              )}
            </div>
          )}

          {/* Estado de consulta DGI */}
          {invoiceResolving && (
            <div className="flex items-center gap-3 bg-surface-container rounded-xl border border-outline-variant p-4 text-sm text-on-surface-variant">
              <span className="material-symbols-outlined animate-spin text-primary-container">progress_activity</span>
              Consultando datos en DGI…
            </div>
          )}

          {/* Datos resueltos + botón de registro */}
          {hasResolved && !invoiceResolving && (
            <form onSubmit={onSubmit} className="flex flex-col gap-4">
              <div className="bg-surface-container-low rounded-2xl border border-outline-variant overflow-hidden">
                <div className="flex items-center gap-3 px-5 py-4 border-b border-outline-variant">
                  <div className="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center">
                    <span className="material-symbols-outlined text-emerald-400 text-base" data-weight="fill">check_circle</span>
                  </div>
                  <div>
                    <div className="font-semibold text-on-surface text-sm">Factura verificada en DGI</div>
                    {resolvedInvoiceData?.issuer_name && (
                      <div className="text-xs text-on-surface-variant truncate max-w-[220px]">{resolvedInvoiceData.issuer_name}</div>
                    )}
                  </div>
                </div>
                <div className="grid grid-cols-3 divide-x divide-outline-variant">
                  <div className="px-4 py-3 text-center">
                    <div className="text-xs text-on-surface-variant mb-0.5">Factura</div>
                    <div className="font-semibold text-on-surface text-sm truncate">
                      {invoiceForm.invoice_number ? `#${invoiceForm.invoice_number}` : '—'}
                    </div>
                  </div>
                  <div className="px-4 py-3 text-center">
                    <div className="text-xs text-on-surface-variant mb-0.5">Monto</div>
                    <div className="font-semibold text-primary-container text-sm">{formatCurrency(invoiceForm.purchase_amount)}</div>
                  </div>
                  <div className="px-4 py-3 text-center">
                    <div className="text-xs text-on-surface-variant mb-0.5">Fecha</div>
                    <div className="font-semibold text-on-surface text-sm">{formatDate(invoiceForm.issued_at)}</div>
                  </div>
                </div>
              </div>

              <button
                type="submit"
                disabled={invoiceSubmitting}
                className="w-full py-4 bg-primary-container text-white font-bold text-base rounded-2xl flex items-center justify-center gap-2 hover:opacity-90 active:scale-[0.98] transition-all disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {invoiceSubmitting ? (
                  <>
                    <span className="material-symbols-outlined animate-spin text-lg">progress_activity</span>
                    Registrando…
                  </>
                ) : (
                  <>
                    <span className="material-symbols-outlined text-lg">sports_soccer</span>
                    Registrar factura
                  </>
                )}
              </button>
            </form>
          )}

        </section>

        {/* Historial */}
        <section className="lg:col-span-7">
          <div className="bg-surface-container-low rounded-2xl border border-outline-variant flex flex-col overflow-hidden">
            <div className="flex items-center justify-between px-5 py-4 border-b border-outline-variant">
              <h2 className="font-semibold text-on-surface">Mis facturas</h2>
              {totalPoints > 0 && (
                <span className="text-xs font-bold px-3 py-1 rounded-full bg-primary-container/20 text-primary-container border border-primary-container/30">
                  {totalPoints.toFixed(1)} goles totales
                </span>
              )}
            </div>

            <div className="flex-1 overflow-y-auto p-4 space-y-2.5 max-h-[520px]">
              {invoiceCards.length > 0 ? invoiceCards : (
                <div className="flex flex-col items-center justify-center py-12 text-center gap-3">
                  <span className="material-symbols-outlined text-4xl text-on-surface-variant/40">receipt_long</span>
                  <div>
                    <p className="text-sm font-medium text-on-surface-variant">Sin facturas aún</p>
                    <p className="text-xs text-on-surface-variant/60 mt-1">Escanea el QR de tu primera factura DGI para comenzar</p>
                  </div>
                </div>
              )}
            </div>

            {invoices.length > 5 && (
              <div className="px-5 py-3 border-t border-outline-variant text-center text-xs text-on-surface-variant">
                Mostrando 5 de {invoices.length} facturas
              </div>
            )}
          </div>
        </section>
      </div>
    </div>
  )
}
