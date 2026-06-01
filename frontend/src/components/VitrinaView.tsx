import type { RegisteredInvoice, User, WalletMovement, WalletSnapshot } from '../types'

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

export function VitrinaView({
  user,
  walletSnapshot,
  invoices,
}: {
  user: User
  walletSnapshot: WalletSnapshot | null
  invoices: RegisteredInvoice[]
}) {
  const wallet = walletSnapshot?.wallet ?? user.wallet ?? null
  const movements = walletSnapshot?.movements ?? []
  const approvedInvoices = invoices.filter((invoice) => invoice.validation_status === 'approved')
  const approvedInvoiceTotal = approvedInvoices.reduce((total, invoice) => total + Number(invoice.purchase_amount ?? 0), 0)
  const totalGoalsWon = movements.reduce((total, movement) => total + Math.max(Number(movement.goals_delta ?? 0), 0), 0)
  const totalGoalsSpent = movements.reduce((total, movement) => total + Math.abs(Math.min(Number(movement.goals_delta ?? 0), 0)), 0)

  return (
    <section className="vitrina-view">
      <header className="vitrina-hero">
        <div className="vitrina-hero-copy">
          <span className="vitrina-kicker">SUPER CARNES 2026</span>
          <h1>Vitrina de goles</h1>
          <p>Consulta tus goles acumulados, el total de tus facturas aprobadas y cada movimiento registrado durante la promocion.</p>
        </div>

        <div className="vitrina-scoreboard">
          <article className="vitrina-scoreboard-primary">
            <span>Goles acumulados</span>
            <strong>{formatCompactNumber(wallet?.lifetime_goals_earned ?? totalGoalsWon)} G</strong>
          </article>
          <article>
            <span>Total facturas</span>
            <strong>{formatCurrency(approvedInvoiceTotal)}</strong>
          </article>
          <article>
            <span>Saldo disponible</span>
            <strong>{formatCompactNumber(wallet?.goals_balance ?? 0)} G</strong>
          </article>
        </div>
      </header>

      <section className="vitrina-history-shell">
        <div className="vitrina-history-head">
          <div>
            <span className="vitrina-kicker">Actividad oficial</span>
            <h2>Historial de cuenta</h2>
          </div>

          <div className="vitrina-chip-row">
            <span className="vitrina-chip">{formatCompactNumber(movements.length)} movimientos</span>
            <span className="vitrina-chip positive">+{formatCompactNumber(totalGoalsWon)} G</span>
            <span className="vitrina-chip negative">-{formatCompactNumber(totalGoalsSpent)} G</span>
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
