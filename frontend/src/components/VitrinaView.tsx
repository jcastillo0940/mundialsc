import { useState } from 'react'
import type { ClientBootstrap, Prediction, RegisteredInvoice, TournamentPhase, User, WalletMovement, WalletSnapshot } from '../types'
import { InfoTooltip } from './InfoTooltip'

type HistoryFilter = 'todos' | 'facturas' | 'acertados' | 'no_acertados'

type UnifiedItem =
  | { kind: 'movement'; data: WalletMovement; sortDate: number }
  | { kind: 'prediction'; data: Prediction; sortDate: number }

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

function predictionIcon(resultType: string) {
  if (resultType === 'exact') return 'workspace_premium'
  return 'sports_soccer'
}

function predictionTone(resultType: string) {
  if (resultType === 'exact' || resultType === 'outcome') return 'positive'
  return 'neutral'
}

function predictionLabel(resultType: string) {
  if (resultType === 'exact') return 'Pronóstico exacto acertado'
  if (resultType === 'outcome') return 'Resultado correcto'
  return 'Pronóstico no acertado'
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
  predictions,
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
  predictions: Prediction[]
}) {
  const [activeFilter, setActiveFilter] = useState<HistoryFilter>('todos')

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
  const totalGoalsWon = movements.reduce((total, movement) => total + Math.max(Number(movement.goals_delta ?? 0), 0), 0)
  const phaseGoalsWon = Number(overview?.phase_goals ?? invoiceTotals?.phase_goals ?? 0)
  const groupStageContest = overview?.group_stage_contest ?? null
  const knockoutContest = overview?.knockout_contest ?? null
  const groupStagePoints = Number(groupStageContest?.user_points ?? 0)
  const knockoutPoints = Number(knockoutContest?.user_points ?? phaseGoalsWon)
  const activePhaseInvoiceCount = activePhaseInvoices.length

  // Build unified history: non-prediction wallet movements + all scored predictions
  const nonPredictionMovements = movements.filter((m) => m.type !== 'prediction_points_awarded')
  const scoredPredictions = predictions.filter((p) => p.result_type !== 'pending')

  const unifiedItems: UnifiedItem[] = [
    ...nonPredictionMovements.map((m) => ({
      kind: 'movement' as const,
      data: m,
      sortDate: m.created_at ? new Date(m.created_at).getTime() : 0,
    })),
    ...scoredPredictions.map((p) => ({
      kind: 'prediction' as const,
      data: p,
      sortDate: p.match?.kickoff_at ? new Date(p.match.kickoff_at).getTime() : 0,
    })),
  ].sort((a, b) => b.sortDate - a.sortDate)

  const hitCount = scoredPredictions.filter((p) => p.result_type === 'exact' || p.result_type === 'outcome').length
  const missCount = scoredPredictions.filter((p) => p.result_type === 'miss').length
  const invoiceMovementCount = nonPredictionMovements.filter((m) => m.type === 'invoice_goal_awarded').length

  const filteredItems = unifiedItems.filter((item) => {
    if (activeFilter === 'facturas') return item.kind === 'movement' && item.data.type === 'invoice_goal_awarded'
    if (activeFilter === 'acertados') return item.kind === 'prediction' && (item.data.result_type === 'exact' || item.data.result_type === 'outcome')
    if (activeFilter === 'no_acertados') return item.kind === 'prediction' && item.data.result_type === 'miss'
    return true
  })

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
              Fase de Grupos
              <InfoTooltip compact content="Puntos oficiales de la primera competencia. No se suman a las Fases Finales." />
            </span>
            <strong>{formatCompactNumber(groupStagePoints)} G</strong>
            <small>{groupStageContest?.user_rank ? `Pos. #${groupStageContest.user_rank}` : 'Ranking cerrado'}</small>
          </article>
          <article className="marea-vitrina-stat-card is-primary">
            <span>
              Fases Finales
              <InfoTooltip compact content="Puntos acumulados de la segunda competencia, desde dieciseisavos hasta la final. No incluye Fase de Grupos ni facturas." />
            </span>
            <strong>{formatCompactNumber(knockoutPoints)} G</strong>
            <small>{knockoutContest?.user_rank ? `Pos. #${knockoutContest.user_rank}` : 'En juego'}</small>
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
            Esta segunda competencia suma solo pronosticos de Fases Finales. La Fase de Grupos queda separada.
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
            <button
              type="button"
              className={`marea-vitrina-chip is-filter${activeFilter === 'todos' ? ' is-active' : ''}`}
              onClick={() => setActiveFilter('todos')}
            >
              Todos ({formatCompactNumber(unifiedItems.length)})
            </button>
            <button
              type="button"
              className={`marea-vitrina-chip is-filter${activeFilter === 'acertados' ? ' is-active positive' : ''}`}
              onClick={() => setActiveFilter('acertados')}
            >
              Acertados ({formatCompactNumber(hitCount)})
            </button>
            <button
              type="button"
              className={`marea-vitrina-chip is-filter${activeFilter === 'no_acertados' ? ' is-active' : ''}`}
              onClick={() => setActiveFilter('no_acertados')}
            >
              No acertados ({formatCompactNumber(missCount)})
            </button>
            <button
              type="button"
              className={`marea-vitrina-chip is-filter${activeFilter === 'facturas' ? ' is-active positive' : ''}`}
              onClick={() => setActiveFilter('facturas')}
            >
              Facturas ({formatCompactNumber(invoiceMovementCount)})
            </button>
          </div>
        </div>

        {filteredItems.length ? (
          <div className="marea-vitrina-history-list">
            {filteredItems.map((item) => {
              if (item.kind === 'prediction') {
                const prediction = item.data
                const match = prediction.match
                const tone = predictionTone(prediction.result_type)
                const homeCode = match?.home_team?.code ?? match?.homeTeam?.code ?? match?.home_team?.name ?? 'Local'
                const awayCode = match?.away_team?.code ?? match?.awayTeam?.code ?? match?.away_team?.name ?? 'Visitante'

                return (
                  <article key={`pred-${prediction.id}`} className={`marea-vitrina-history-card ${tone}`}>
                    <div className="marea-vitrina-history-icon">
                      <span className="material-symbols-outlined">{predictionIcon(prediction.result_type)}</span>
                    </div>

                    <div className="marea-vitrina-history-copy">
                      <strong>{predictionLabel(prediction.result_type)}</strong>
                      <p>{formatUpperDate(match?.kickoff_at)}</p>
                      <small>
                        {homeCode} vs {awayCode}
                        {match?.home_score != null && match?.away_score != null
                          ? ` · Pronós. ${prediction.predicted_home_score}-${prediction.predicted_away_score} · Real ${match.home_score}-${match.away_score}`
                          : ''}
                      </small>
                    </div>

                    <div className="marea-vitrina-history-score">
                      <strong>
                        {prediction.points_awarded > 0 ? '+' : ''}
                        {formatCompactNumber(prediction.points_awarded)} G
                      </strong>
                      <span>0 T</span>
                    </div>
                  </article>
                )
              }

              const movement = item.data
              const tone = movementTone(movement)

              return (
                <article key={`mov-${movement.id}`} className={`marea-vitrina-history-card ${tone}`}>
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
            <p>Cuando sumes goles o se validen nuevas facturas, el historial oficial aparecerá aquí.</p>
          </div>
        )}
      </section>
    </section>
  )
}
