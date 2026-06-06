import type { ChangeEvent, FormEvent } from 'react'
import { useEffect, useMemo, useRef, useState } from 'react'
import type { RegisteredInvoice, ResolvedInvoiceData } from '../types'

const CUFE_PREFIX = 'FE01200000032812-2-249262-'

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
  cameraResolution: string
}

interface InvoiceRegistrationViewProps {
  invoices: RegisteredInvoice[]
  invoiceEntryMode: InvoiceEntryMode
  invoiceForm: InvoiceFormState
  invoiceGalleryProcessing: boolean
  invoiceScannerActivated: boolean
  invoiceScannerError: string | null
  invoiceScannerDebug: InvoiceScannerDebugInfo
  invoiceSubmitting: boolean
  resolvedInvoiceData: ResolvedInvoiceData | null
  onActivateScan: () => void
  onRegister: (rawCufe: string) => void
  onReset: () => void
  onSubmit: (event: FormEvent<HTMLFormElement>) => void | Promise<void>
  onGalleryUpload: (event: ChangeEvent<HTMLInputElement>) => void | Promise<void>
  onModeChange: (mode: InvoiceEntryMode) => void
  onFieldChange: <K extends keyof InvoiceFormState>(field: K, value: InvoiceFormState[K]) => void
  showScannerDebug?: boolean
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
  return date.toLocaleDateString('es-PA', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'America/Panama',
  })
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
  if (status === 'approved') return { tone: 'approved' as const, label: 'GOL VALIDO', icon: 'verified', points: true }
  if (status === 'pending') return { tone: 'pending' as const, label: 'EN REVISION', icon: 'update', points: false }
  return { tone: 'rejected' as const, label: 'RECHAZADA', icon: 'dangerous', points: false }
}

export function InvoiceRegistrationView({
  invoices,
  invoiceEntryMode,
  invoiceForm,
  invoiceScannerActivated,
  invoiceScannerError,
  invoiceScannerDebug,
  invoiceSubmitting,
  resolvedInvoiceData,
  onActivateScan,
  onRegister,
  onReset,
  onModeChange,
  onFieldChange,
  showScannerDebug = false,
}: InvoiceRegistrationViewProps) {
  const hasResolved = Boolean(resolvedInvoiceData)
  const [debugOpen, setDebugOpen] = useState(false)
  const loaderRef = useRef<HTMLDivElement | null>(null)

  useEffect(() => {
    if (invoiceSubmitting && loaderRef.current) {
      loaderRef.current.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }
  }, [invoiceSubmitting])

  const deriveShortCode = (raw: string) => {
    if (!raw) return ''
    if (raw.startsWith(CUFE_PREFIX)) return raw.slice(CUFE_PREFIX.length)
    const chFeMatch = raw.match(/[?&]chFE=[^&]*-([A-Z0-9]{10,})(?:&|$)/i)
    if (chFeMatch?.[1]) return chFeMatch[1]
    const idx = raw.lastIndexOf('-')
    return idx >= 0 ? raw.slice(idx + 1) : raw
  }

  const [shortCode, setShortCode] = useState(() => deriveShortCode(invoiceForm.rawInput))

  useEffect(() => {
    setShortCode(deriveShortCode(invoiceForm.rawInput))
  }, [invoiceForm.rawInput])

  function handleShortCodeChange(value: string, autoPaste = false) {
    const trimmed = value.replace(/\s/g, '')
    setShortCode(trimmed)
    onFieldChange('rawInput', trimmed ? CUFE_PREFIX + trimmed : '')
    if (autoPaste && trimmed.length >= 30) {
      setTimeout(() => onRegister(CUFE_PREFIX + trimmed), 0)
    }
  }

  const totalPoints = useMemo(
    () =>
      invoices
        .filter((invoice) => invoice.validation_status === 'approved')
        .reduce((sum, invoice) => sum + Number(invoice.points_awarded ?? 0), 0),
    [invoices],
  )

  const approvedCount = useMemo(
    () => invoices.filter((invoice) => invoice.validation_status === 'approved').length,
    [invoices],
  )

  const rejectedCount = useMemo(
    () => invoices.filter((invoice) => invoice.validation_status === 'rejected').length,
    [invoices],
  )

  const heroStatusCopy =
    invoices.length > 0
      ? approvedCount > 0
        ? 'Tus facturas validadas ya estan sumando goles dentro de la promocion.'
        : 'Tu proxima factura valida puede convertir este entrenamiento en puntos.'
      : 'Escanea tu primera factura DGI para empezar a sumar goles dentro de la promocion.'

  const invoiceCards = useMemo(
    () =>
      invoices.slice(0, 5).map((invoice) => {
        const meta = invoiceStatusMeta(invoice.validation_status)
        const colorMap = {
          approved: {
            badge: 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
          },
          pending: {
            badge: 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30',
          },
          rejected: {
            badge: 'bg-red-500/20 text-red-400 border-red-500/30',
          },
        }
        const c = colorMap[meta.tone]
        const title = invoice.invoice_number ? `#${invoice.invoice_number}` : `#${String(invoice.id).padStart(6, '0')}`
        const date = invoice.issued_at ?? invoice.created_at

        return (
          <div key={invoice.id} className={`marea-invoice-history-item ${meta.tone}`}>
            <div className="marea-invoice-history-item-icon">
              <span className="material-symbols-outlined" data-weight="fill">
                {meta.icon}
              </span>
            </div>
            <div className="marea-invoice-history-item-copy">
              <div className="marea-invoice-history-item-title">{title}</div>
              <div className="marea-invoice-history-item-meta">
                {date ? formatDate(date) : ''} · {formatCurrency(invoice.purchase_amount)}
              </div>
            </div>
            <span className={`marea-invoice-history-item-badge ${c.badge}`}>
              {meta.points ? `+${Number(invoice.points_awarded ?? 0).toFixed(1)} GOL` : meta.label}
            </span>
          </div>
        )
      }),
    [invoices],
  )

  return (
    <div className="facturas-view marea-invoice-page">
      <section className="marea-invoice-hero-shell">
        <div className="marea-invoice-hero-copy">
          <span className="marea-kicker">SUPER CARNES 2026</span>
          <h1 className="cancha-headline-title auth-reference-title marea-invoice-hero-title" aria-label="Registra tu factura">
            <span className="auth-reference-title-line is-light">REGISTRA TU</span>
            <span className="auth-reference-title-line is-gold">FACTURA</span>
          </h1>
          <p className="marea-invoice-hero-description">
            Escanea el QR o ingresa el CUFE de tu factura para registrar compras validas y seguir sumando oportunidades dentro de la promocion.
          </p>
        </div>

        <div className="marea-invoice-hero-art" aria-hidden="true">
          <img className="marea-invoice-hero-confetti" alt="" src="/redesign/auth-confetti-layer.svg" />
          <div className="marea-invoice-hero-ticket">
            <div className="marea-invoice-hero-ticket-top">
              <span>Factura DGI</span>
              <span className="material-symbols-outlined">receipt_long</span>
            </div>
            <div className="marea-invoice-hero-ticket-qr" />
            <div className="marea-invoice-hero-ticket-lines">
              <span />
              <span />
              <span />
            </div>
          </div>
          <img className="marea-invoice-hero-mascot" alt="" src="/redesign/auth-mascot-center.png" />
          <img className="marea-invoice-hero-ball" alt="" src="/redesign/auth-ball-center.png" />
        </div>

        <aside className="marea-invoice-hero-stats">
          <div className="marea-invoice-hero-stats-head">
            <span>Marcador de facturas</span>
            <strong>Entrenamiento activo</strong>
          </div>
          <div className="marea-invoice-hero-metrics">
            <article>
              <span>Registradas</span>
              <strong>{invoices.length}</strong>
            </article>
          </div>
          <p className="marea-invoice-hero-status-copy">{heroStatusCopy}</p>
          {rejectedCount > 0 ? (
            <span className="marea-invoice-hero-status-flag">
              {rejectedCount} factura{rejectedCount === 1 ? '' : 's'} requieren correccion
            </span>
          ) : null}
        </aside>
      </section>

      <div className="marea-invoice-mode-switch">
        <button
          type="button"
          className={`marea-invoice-mode-button ${invoiceEntryMode === 'scan' ? 'is-active' : ''}`}
          onClick={() => onModeChange('scan')}
        >
          <span className="material-symbols-outlined">qr_code_scanner</span>
          Escanear QR
        </button>
        <button
          type="button"
          className={`marea-invoice-mode-button ${invoiceEntryMode === 'manual' ? 'is-active' : ''}`}
          onClick={() => onModeChange('manual')}
        >
          <span className="material-symbols-outlined">edit</span>
          Ingresar CUFE
        </button>
      </div>

      <div className="marea-invoice-content-grid">
        <section className="marea-invoice-entry-column">
          {invoiceEntryMode === 'scan' && !invoiceSubmitting && (
            <div className="marea-invoice-work-card">
              {!invoiceScannerActivated ? (
                <div className="marea-invoice-work-card-empty">
                  <span className="material-symbols-outlined">qr_code_scanner</span>
                  <h2>Escaneo rapido</h2>
                  <p>
                    Apunta la camara al codigo QR de tu factura DGI para registrarla automaticamente.
                  </p>
                  <button type="button" className="marea-invoice-action-button primary" onClick={onActivateScan}>
                    <span className="material-symbols-outlined">videocam</span>
                    Activar escaneo de QR
                  </button>
                </div>
              ) : (
                <>
                  <div className="marea-invoice-work-card-head">
                    <div>
                      <span className="marea-invoice-panel-kicker">Camara activa</span>
                      <h2>Apunta al codigo QR</h2>
                    </div>
                    <p>Apunta la camara al codigo QR de tu factura DGI.</p>
                  </div>
                  <div id="dgi-qr-reader" className="marea-invoice-scanner-frame" />
                  {invoiceScannerError && (
                    <div className="marea-invoice-inline-alert is-error">
                      <span className="material-symbols-outlined">error</span>
                      {invoiceScannerError}
                    </div>
                  )}
                  <div className="marea-invoice-work-card-footer">
                    <p>
                      Si no funciona el escaner,{' '}
                      <button type="button" className="marea-invoice-inline-link" onClick={() => onModeChange('manual')}>
                        ingresa el CUFE manualmente
                      </button>
                    </p>
                  </div>

                  {showScannerDebug ? (
                    <div className="border-t border-outline-variant">
                      <button type="button" className="marea-invoice-debug-toggle" onClick={() => setDebugOpen((open) => !open)}>
                        <span className="marea-invoice-debug-toggle-label">
                          <span className="material-symbols-outlined">bug_report</span>
                          Info tecnica del escaner
                        </span>
                        <span className="material-symbols-outlined">{debugOpen ? 'expand_less' : 'expand_more'}</span>
                      </button>

                      {debugOpen ? (
                        <div className="marea-invoice-debug-panel">
                          <DebugRow
                            label="Escaner"
                            value={
                              invoiceScannerDebug.scannerType === 'native'
                                ? 'BarcodeDetector nativo'
                                : invoiceScannerDebug.scannerType === 'html5-qrcode'
                                  ? 'html5-qrcode'
                                  : 'sin iniciar'
                            }
                            ok={invoiceScannerDebug.scannerType !== 'none'}
                          />
                          <DebugRow
                            label="Etapa"
                            value={invoiceScannerDebug.lastStage}
                            ok={!['idle', 'camera-start-failed', 'media-devices-missing', 'insecure-context'].includes(invoiceScannerDebug.lastStage)}
                          />
                          {invoiceScannerDebug.lastError ? (
                            <DebugRow label="Ultimo error" value={invoiceScannerDebug.lastError} ok={false} />
                          ) : null}
                          <DebugRow
                            label="BarcodeDetector"
                            value={invoiceScannerDebug.barcodeDetectorAvailable ? 'disponible' : 'no disponible'}
                            ok={invoiceScannerDebug.barcodeDetectorAvailable}
                          />
                          {invoiceScannerDebug.activeFormats.length > 0 ? (
                            <DebugRow
                              label="Formatos"
                              value={invoiceScannerDebug.activeFormats.join(', ')}
                              ok={invoiceScannerDebug.activeFormats.includes('qr_code')}
                            />
                          ) : null}
                          <DebugRow
                            label="Permiso camara"
                            value={invoiceScannerDebug.cameraPermission}
                            ok={invoiceScannerDebug.cameraPermission === 'granted'}
                          />
                          <DebugRow
                            label="Secure context"
                            value={invoiceScannerDebug.isSecureContext ? 'si' : 'no'}
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
                          {invoiceScannerDebug.cameraResolution ? (
                            <DebugRow label="Resolucion" value={invoiceScannerDebug.cameraResolution} ok={true} />
                          ) : null}
                          <DebugRow label="Origen" value={invoiceScannerDebug.origin} ok={true} />
                          <div className="pt-1 text-on-surface-variant/50 break-all leading-relaxed">
                            UA: {invoiceScannerDebug.userAgent}
                          </div>
                        </div>
                      ) : null}
                    </div>
                  ) : null}
                </>
              )}
            </div>
          )}

          {invoiceEntryMode === 'manual' && !invoiceSubmitting && (
            <div className="marea-invoice-work-card marea-invoice-work-card-manual">
              <div className="marea-invoice-work-card-head">
                <div>
                  <span className="marea-invoice-panel-kicker">Registro manual</span>
                  <h2>Ingresa el CUFE</h2>
                </div>
                <p>Usa el bloque final del CUFE para registrar tu factura con rapidez.</p>
              </div>

              <div className="marea-invoice-info-block">
                <p className="marea-invoice-info-block-title">
                  <span className="material-symbols-outlined">info</span>
                  Que numero debo escribir?
                </p>
                <p className="marea-invoice-info-block-copy">
                  El CUFE de tu factura Super Carnes tiene varias partes separadas por guiones. Solo necesitas escribir el bloque final, los numeros despues del ultimo guion.
                </p>
                <div className="marea-invoice-code-sample">
                  <span className="text-on-surface-variant/40">FE01200000032812-2-249262-</span>
                  <span className="text-emerald-400 font-bold">6300032026051830904933463090311813389704</span>
                </div>
                <p className="marea-invoice-info-block-copy is-muted">
                  Escribe solo la parte en verde. La encontraras al final de la factura, debajo del codigo QR, etiquetada como CUFE.
                </p>
              </div>

              <div className="marea-invoice-field-group">
                <label className="marea-invoice-field-label">Codigo final del CUFE</label>
                <div className="marea-invoice-field-stack">
                  <div className="marea-invoice-short-code-field">
                    <span className="marea-invoice-short-code-prefix">FE...-249262-</span>
                    <input
                      type="text"
                      inputMode="numeric"
                      className="marea-invoice-short-code-input"
                      placeholder="6300032026051830..."
                      value={shortCode}
                      onChange={(event) => handleShortCodeChange(event.target.value)}
                      onPaste={(event) => {
                        const pasted = event.clipboardData.getData('text')
                        event.preventDefault()
                        handleShortCodeChange(pasted, true)
                      }}
                    />
                  </div>
                  <p className="marea-invoice-field-help">Solo los numeros finales, sin espacios ni guiones.</p>
                </div>
              </div>

              {invoiceScannerError ? (
                <div className="marea-invoice-inline-alert is-error">
                  <span className="material-symbols-outlined">error</span>
                  {invoiceScannerError}
                </div>
              ) : null}

              <button
                type="button"
                disabled={shortCode.length < 10 || invoiceSubmitting}
                onClick={() => onRegister(CUFE_PREFIX + shortCode)}
                className="marea-invoice-action-button primary"
              >
                <span className="material-symbols-outlined">sports_soccer</span>
                {invoiceSubmitting ? 'Registrando...' : 'Registrar factura'}
              </button>
            </div>
          )}

          {invoiceSubmitting ? (
            <div ref={loaderRef} className="marea-invoice-loader-card">
              <div className="marea-invoice-loader-visual">
                <div className="marea-invoice-loader-ring" />
                <span className="material-symbols-outlined" data-weight="fill">
                  receipt_long
                </span>
              </div>
              <div>
                <p className="marea-invoice-loader-title">Verificando factura...</p>
                <p className="marea-invoice-loader-copy">
                  Estamos consultando tu factura en la DGI.
                  <br />
                  Por favor espera, puede tardar hasta 45 segundos.
                </p>
              </div>
              <div className="marea-invoice-loader-dots">
                <span className="w-2 h-2 rounded-full bg-primary-container animate-bounce [animation-delay:0ms]" />
                <span className="w-2 h-2 rounded-full bg-primary-container animate-bounce [animation-delay:150ms]" />
                <span className="w-2 h-2 rounded-full bg-primary-container animate-bounce [animation-delay:300ms]" />
              </div>
            </div>
          ) : null}

          {hasResolved && !invoiceSubmitting ? (
            <div className="marea-invoice-success-stack">
              <div className="marea-invoice-success-card">
                <div className="marea-invoice-success-head">
                  <div className="marea-invoice-success-icon">
                    <span className="material-symbols-outlined" data-weight="fill">
                      check_circle
                    </span>
                  </div>
                  <div>
                    <div className="marea-invoice-success-title">Factura registrada</div>
                    {resolvedInvoiceData?.issuer_name ? (
                      <div className="marea-invoice-success-subtitle">{resolvedInvoiceData.issuer_name}</div>
                    ) : null}
                  </div>
                </div>
                <div className="marea-invoice-success-grid">
                  <div>
                    <div className="marea-invoice-success-label">Monto</div>
                    <div className="marea-invoice-success-value is-gold">{formatCurrency(resolvedInvoiceData?.purchase_amount)}</div>
                  </div>
                  <div>
                    <div className="marea-invoice-success-label">Fecha</div>
                    <div className="marea-invoice-success-value">{formatDate(resolvedInvoiceData?.issued_at ?? '')}</div>
                  </div>
                  <div>
                    <div className="marea-invoice-success-label">CUFE</div>
                    <div className="marea-invoice-success-value is-small">{resolvedInvoiceData?.cufe?.slice(-8)}</div>
                  </div>
                </div>
              </div>
              <button type="button" onClick={onReset} className="marea-invoice-action-button secondary">
                <span className="material-symbols-outlined">qr_code_scanner</span>
                Registrar otra factura
              </button>
            </div>
          ) : null}
        </section>

        <section className="marea-invoice-history-column">
          <div className="marea-invoice-history-card">
            <div className="marea-invoice-history-card-head">
              <div>
                <span className="marea-invoice-panel-kicker">Historial real</span>
                <h2>Mis facturas</h2>
              </div>
              {totalPoints > 0 ? (
                <span className="marea-invoice-history-card-badge">{totalPoints.toFixed(1)} goles totales</span>
              ) : null}
            </div>

            <div className="marea-invoice-history-list">
              {invoiceCards.length > 0 ? (
                invoiceCards
              ) : (
                <div className="marea-invoice-history-empty">
                  <span className="material-symbols-outlined">receipt_long</span>
                  <div>
                    <p className="marea-invoice-history-empty-title">Sin facturas aun</p>
                    <p className="marea-invoice-history-empty-copy">Escanea el QR de tu primera factura DGI para comenzar</p>
                  </div>
                </div>
              )}
            </div>

            {invoices.length > 5 ? (
              <div className="marea-invoice-history-card-foot">Mostrando 5 de {invoices.length} facturas</div>
            ) : null}
          </div>
        </section>
      </div>
    </div>
  )
}
