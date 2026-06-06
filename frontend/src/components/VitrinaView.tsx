import type { ClientBootstrap, RegisteredInvoice, TournamentPhase, User, WalletMovement, WalletSnapshot } from '../types'
import { InfoTooltip } from './InfoTooltip'

function formatCompactNumber(value: number | string | null | undefined) {
  const amount = Number(value ?? 0)
  return new Intl.NumberFormat('es-PA').format(Number.isFinite(amount) ? amount : 0)
}

function formatCurrency(value: number | string | null | undefined) {
  const amount = Number(value ?? 0)
  return new Intl.NumberFormat('es-PA', { style: 'currency', currency: 'USD' }).format(
    Number.isFinite(amount) ? amount : 0,
  )
}

function formatUpperDate(dateValue: string | null | undefined) {
  if (!dateValue) return 'Fecha pendiente'

  const date = new Date(dateValue)
  if (Number.isNaN(date.getTime())) return dateValue

  const day = date.toLocaleDateString('es-PA', { day: 'numeric', timeZone: 'America/Panama' })
  const month = date.toLocaleDateString('es-PA', { month: 'long', timeZone: 'America/Panama' })
  const time = date.toLocaleTimeString('es-PA', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
    timeZone: 'America/Panama',
  })

  return `${day} ${month.charAt(0).toUpperCase()}${month.slice(1)} · ${time}`
}

function movementLabel(movement: WalletMovement) {
  const notes = movement.notes?.trim()
  if (notes) return notes

  const labels: Record<string, string> = {
    invoice_goal_awarded: 'Gol ganado por factura validada',
    prediction_points_awarded: 'Goles ganados por pronóstico',
    coupon_redeemed: 'Movimiento registrado en vitrina',
    game_shot_spent: 'Tiro usado en dinámica',
    game_prize_won: 'Premio asignado',
  }

  return labels[movement.type] ?? 'Movimiento registrado'
}

function movementTone(movement: WalletMovement) {
  if (movement.goals_delta > 0 || movement.shots_delta > 0) return 'positive'
  if (movement.goals_delta < 0 || movement.shots_delta < 0) return 'negative'
  return 'neutral'
}

function movementIcon(movement: WalletMovement) {
  if (movement.type === 'invoice_goal_awarded') return 'receipt_long'
  if (movement.type === 'prediction_points_awarded') return 'sports_soccer'
  if (movement.type === 'coupon_redeemed') return 'redeem'
  if (movement.type === 'game_shot_spent') return 'ads_click'
  if (movement.type === 'game_prize_won') return 'workspace_premium'
  return 'monitoring'
}

function dateInPhase(dateValue: string | null | undefined, phase: TournamentPhase | null | undefined) {
  if (!dateValue || !phase) return false

  const time = new Date(dateValue).getTime()
  const startsAt = new Date(phase.starts_at).getTime()
  const endsAt = new Date(phase.ends_at).getTime()

  return Number.isFinite(time) && time >= startsAt && time <= endsAt
}

export function VitrinaView({
  user,
  walletSnapshot,
  invoices,
  invoiceTotals,
  overview,
}: {
  user: User
  walletSnapshot: WalletSnapshot | null
  invoices: RegisteredInvoice[]
  invoiceTotals: {
    goals?: number
    amount?: number
    phase_goals?: number
    phase_amount?: number
  } | null
  overview: ClientBootstrap | null
}) {
  const wallet = walletSnapshot?.wallet ?? user.wallet ?? null
  const movements = walletSnapshot?.movements ?? []
  const activePhase = overview?.active_phase ?? null
  const approvedInvoices = invoices.filter((invoice) => invoice.validation_status === 'approved')
  const activePhaseInvoices = approvedInvoices.filter((invoice) => dateInPhase(invoice.issued_at, activePhase))
  const approvedInvoiceTotal = Number(
    invoiceTotals?.phase_amount
      ?? activePhaseInvoices.reduce((total, invoice) => total + Number(invoice.purchase_amount ?? 0), 0),
  )
  const historicalInvoiceTotal = Number(
    invoiceTotals?.amount
      ?? approvedInvoices.reduce((total, invoice) => total + Number(invoice.purchase_amount ?? 0), 0),
  )
  const invoiceById = new Map(approvedInvoices.map((invoice) => [invoice.id, invoice]))
  const activePhaseMovements = movements.filter((movement) => {
    const movementPhaseId = Number(movement.meta?.phase_id ?? 0)
    if (activePhase && movementPhaseId > 0) return movementPhaseId === activePhase.id
    if (movement.resource_type === 'registered_invoice' && movement.resource_id) {
      return dateInPhase(invoiceById.get(movement.resource_id)?.issued_at, activePhase)
    }
    return false
  })
  const totalGoalsWon = movements.reduce((total, movement) => total + Math.max(Number(movement.goals_delta ?? 0), 0), 0)
  const phaseGoalsWon = Number(overview?.phase_goals ?? invoiceTotals?.phase_goals ?? 0)
  const phaseGoalsSpent = activePhaseMovements.reduce((total, movement) => total + Math.abs(Math.min(Number(movement.goals_delta ?? 0), 0)), 0)
  const activePhaseInvoiceCount = activePhaseInvoices.length

  return (
    <section className="vitrina-view marea-vitrina-page">
      <header className="marea-vitrina-hero">
        <div className="marea-vitrina-hero-copy">
          <span className="marea-kicker">SUPER CARNES 2026</span>
          <h1 className="cancha-headline-title auth-reference-title marea-vitrina-hero-title" aria-label="Tu vitrina de premios">
            <span className="auth-reference-title-line is-light">TU VITRINA</span>
            <span className="auth-reference-title-line is-gold">DE PREMIOS</span>
          </h1>
          <p className="marea-vitrina-hero-description">
            Sube en el ranking, registra facturas válidas y compite por los premios oficiales de cada fase.
          </p>
        </div>

        <div className="marea-vitrina-hero-art" aria-hidden="true">
          <img className="marea-vitrina-hero-confetti" alt="" src="/redesign/auth-confetti-layer.svg" />
          <div className="marea-vitrina-prize-chip tv">
            <span className="material-symbols-outlined">tv</span>
            <strong>TOP 10</strong>
            <small>TV 50"</small>
          </div>
          <div className="marea-vitrina-prize-chip ball">
            <span className="material-symbols-outlined">sports_soccer</span>
            <strong>11-110</strong>
            <small>Balón</small>
          </div>
          <div className="marea-vitrina-prize-chip certificate">
            <span className="material-symbols-outlined">card_giftcard</span>
            <strong>TOP 20</strong>
            <small>USD 200</small>
          </div>
          <img className="marea-vitrina-hero-mascot" alt="" src="/redesign/auth-mascot-center.png" />
          <img className="marea-vitrina-hero-ball" alt="" src="/redesign/auth-ball-center.png" />
        </div>

        <aside className="marea-vitrina-hero-stats">
          <article className="marea-vitrina-stat-card is-primary">
            <span>
              Goles de fase
              <InfoTooltip compact content="Goles que cuentan para la fase actual. Los goles de fases anteriores quedan guardados en el historial, pero no se suman aquí." />
            </span>
            <strong>{formatCompactNumber(phaseGoalsWon)} G</strong>
          </article>
          <article className="marea-vitrina-stat-card">
            <span>Facturas aprobadas</span>
            <strong>{formatCompactNumber(activePhaseInvoiceCount)}</strong>
          </article>
          <article className="marea-vitrina-stat-card">
            <span>
              Valor aprobado
              <InfoTooltip compact content="Monto acumulado de tus facturas aprobadas emitidas dentro de la ventana de la fase actual." />
            </span>
            <strong>{formatCurrency(approvedInvoiceTotal)}</strong>
          </article>
        </aside>
      </header>

      <section className="marea-vitrina-prizes-grid">
        <article className="marea-vitrina-prize-card">
          <div className="marea-vitrina-prize-card-head">
            <span className="marea-vitrina-prize-label">Fase 1</span>
            <h2>Premios por posición</h2>
          </div>
          <div className="marea-vitrina-prize-lanes">
            <div className="marea-vitrina-prize-lane">
              <div className="marea-vitrina-prize-lane-rank">Puestos 1 al 10</div>
              <div className="marea-vitrina-prize-lane-copy">
                <strong>1 televisor de 50 pulgadas cada uno</strong>
                <p>Los 10 mejores puntajes de la primera fase ganan un televisor nuevo.</p>
              </div>
            </div>
            <div className="marea-vitrina-prize-lane">
              <div className="marea-vitrina-prize-lane-rank">Puestos 11 al 110</div>
              <div className="marea-vitrina-prize-lane-copy">
                <strong>1 balón original cada uno</strong>
                <p>Los siguientes 100 lugares de la fase reciben un balón oficial.</p>
              </div>
            </div>
          </div>
        </article>

        <article className="marea-vitrina-prize-card is-highlight">
          <div className="marea-vitrina-prize-card-head">
            <span className="marea-vitrina-prize-label">Fase 2</span>
            <h2>Remate competitivo</h2>
          </div>
          <div className="marea-vitrina-phase-banner">
            <strong>Top 20 del ranking final</strong>
            <span>20 certificados de regalo de USD 200 cada uno</span>
          </div>
          <p className="marea-vitrina-prize-note">
            Las facturas válidas también fortalecen tu posición en los criterios oficiales de desempate.
          </p>
        </article>
      </section>

      <section className="marea-vitrina-history-shell">
        <div className="marea-vitrina-history-head">
          <div>
            <span className="marea-kicker">Actividad oficial</span>
            <h2>
              Historial de cuenta
              <InfoTooltip compact content={`Histórico completo de movimientos. Acumulado general: ${formatCompactNumber(wallet?.lifetime_goals_earned ?? totalGoalsWon)} goles y ${formatCurrency(historicalInvoiceTotal)} en facturas aprobadas.`} />
            </h2>
          </div>

          <div className="marea-vitrina-chip-row">
            <span className="marea-vitrina-chip">{formatCompactNumber(movements.length)} movimientos</span>
            <span className="marea-vitrina-chip positive">+{formatCompactNumber(phaseGoalsWon)} G fase</span>
            <span className="marea-vitrina-chip negative">-{formatCompactNumber(phaseGoalsSpent)} G fase</span>
          </div>
        </div>

        {movements.length ? (
          <div className="marea-vitrina-history-list">
            {movements.map((movement) => {
              const tone = movementTone(movement)

              return (
                <article key={movement.id} className={`marea-vitrina-history-card ${tone}`}>
                  <div className="marea-vitrina-history-icon">
                    <span className="material-symbols-outlined">{movementIcon(movement)}</span>
                  </div>

                  <div className="marea-vitrina-history-copy">
                    <strong>{movementLabel(movement)}</strong>
                    <p>{formatUpperDate(movement.created_at)}</p>
                    <small>
                      Tipo: {movement.type}
                      {movement.resource_id ? ` · Ref ${movement.resource_id}` : ''}
                    </small>
                  </div>

                  <div className="marea-vitrina-history-score">
                    <strong>
                      {movement.goals_delta > 0 ? '+' : ''}
                      {formatCompactNumber(movement.goals_delta)} G
                    </strong>
                    <span>
                      {movement.shots_delta > 0 ? '+' : ''}
                      {formatCompactNumber(movement.shots_delta)} T
                    </span>
                  </div>
                </article>
              )
            })}
          </div>
        ) : (
          <div className="marea-vitrina-empty-state">
            <span className="material-symbols-outlined">monitoring</span>
            <h3>Sin movimientos registrados</h3>
            <p>Cuando sumes goles o se validen nuevas facturas, el historial oficial aparecerÃ¡ aquí.</p>
          </div>
        )}
      </section>
    </section>
  )
}

