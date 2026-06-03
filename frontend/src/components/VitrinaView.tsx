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
    prediction_points_awarded: 'Goles ganados por pronostico',
    coupon_redeemed: 'Canje realizado en vitrina',
    game_shot_spent: 'Tiro usado en dinamica',
    game_prize_won: 'Premio ganado en dinamica',
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

  return (
    <section className="vitrina-view">
      <header className="vitrina-hero">
        <div className="vitrina-hero-copy">
          <span className="vitrina-kicker">SUPER CARNES 2026</span>
          <h1>Vitrina de goles</h1>
          <p>Consulta tus goles de la fase actual, el total de tus facturas aprobadas y cada movimiento registrado durante la promocion.</p>
        </div>

        <div className="vitrina-scoreboard">
          <article className="vitrina-scoreboard-primary">
            <span>
              Goles de fase
              <InfoTooltip compact content="Goles que cuentan para la fase actual. Los goles de fases anteriores quedan guardados en el historial, pero no se suman aqui." />
            </span>
            <strong>{formatCompactNumber(phaseGoalsWon)} G</strong>
          </article>
          <article className="vitrina-scoreboard-secondary">
            <span>
              Facturas de fase
              <InfoTooltip compact content="Monto acumulado de tus facturas aprobadas emitidas dentro de la ventana de la fase actual." />
            </span>
            <strong>{formatCurrency(approvedInvoiceTotal)}</strong>
          </article>
        </div>
      </header>

      <section className="vitrina-history-shell">
        <div className="vitrina-history-head">
          <div>
            <span className="vitrina-kicker">Actividad oficial</span>
            <h2>
              Historial de cuenta
              <InfoTooltip compact content={`Historico completo de movimientos. Acumulado general: ${formatCompactNumber(wallet?.lifetime_goals_earned ?? totalGoalsWon)} goles y ${formatCurrency(historicalInvoiceTotal)} en facturas aprobadas.`} />
            </h2>
          </div>

          <div className="vitrina-chip-row">
            <span className="vitrina-chip">{formatCompactNumber(movements.length)} movimientos</span>
            <span className="vitrina-chip positive">+{formatCompactNumber(phaseGoalsWon)} G fase</span>
            <span className="vitrina-chip negative">-{formatCompactNumber(phaseGoalsSpent)} G fase</span>
          </div>
        </div>

        {movements.length ? (
          <div className="vitrina-history-list">
            {movements.map((movement) => {
              const tone = movementTone(movement)

              return (
                <article key={movement.id} className={`vitrina-history-card ${tone}`}>
                  <div className="vitrina-history-icon">
                    <span className="material-symbols-outlined">{movementIcon(movement)}</span>
                  </div>

                  <div className="vitrina-history-copy">
                    <strong>{movementLabel(movement)}</strong>
                    <p>{formatUpperDate(movement.created_at)}</p>
                    <small>
                      Tipo: {movement.type}
                      {movement.resource_id ? ` · Ref ${movement.resource_id}` : ''}
                    </small>
                  </div>

                  <div className="vitrina-history-score">
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
          <div className="vitrina-empty-state">
            <span className="material-symbols-outlined">monitoring</span>
            <h3>Sin movimientos registrados</h3>
            <p>Cuando sumes goles, uses tiros o canjees premios, el historial aparecera aqui.</p>
          </div>
        )}
      </section>
    </section>
  )
}
