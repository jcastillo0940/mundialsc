import { useMemo } from 'react'
import type {
  ClientBootstrap,
  DashboardSnapshot,
  LeaderboardEntry,
  Prediction,
  Prize,
  User,
  WalletSnapshot,
} from '../types'

function formatCompactNumber(value: number | string | null | undefined) {
  const amount = Number(value ?? 0)
  return new Intl.NumberFormat('es-PA').format(Number.isFinite(amount) ? amount : 0)
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

function maskName(name: string | null | undefined) {
  if (!name) return 'Participante'
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) return `${parts[0][0].toUpperCase()}***`
  return `${parts[0]} ${parts[1][0].toUpperCase()}.`
}

function roleLabel(value: string | null | undefined) {
  if (!value) return 'Participante'
  return value.replace(/_/g, ' ')
}

function positionLabel(position: number) {
  return String(position).padStart(2, '0')
}

function podiumLabel(place: number) {
  if (place === 1) return '1er lugar'
  if (place === 2) return '2do lugar'
  if (place === 3) return '3er lugar'
  return `${place}to lugar`
}

function performancePercent(entry: LeaderboardEntry, maxGoals: number) {
  if (maxGoals <= 0) return 10
  return Math.max(14, Math.round((entry.goals / maxGoals) * 100))
}

export function VestuarioView({
  user,
  overview,
}: {
  user: User
  overview: ClientBootstrap | null
  dashboard: DashboardSnapshot | null
  walletSnapshot: WalletSnapshot | null
  prizes: Prize[]
  predictions: Prediction[]
  invoices: unknown[]
}) {
  const avatarUrl = user.avatar_url ?? null
  const leaderboard = overview?.leaderboard ?? []
  const topThree = leaderboard.slice(0, 3)
  const userEntry = leaderboard.find((entry) => entry.user_id === user.id) ?? null
  const userGoals = Number(userEntry?.goals ?? 0)
  const maxGoals = leaderboard.reduce((currentMax, entry) => Math.max(currentMax, entry.goals), 0)
  const podiumEntries = topThree.length === 3 ? [topThree[1], topThree[0], topThree[2]] : topThree
  const userIndex = leaderboard.findIndex((entry) => entry.user_id === user.id)
  const leader = leaderboard[0] ?? null
  const goalsToFirst = leader && userEntry && userEntry.position > 1 ? Math.max(Number(leader.goals) - userGoals, 0) : 0

  const contextRows = useMemo(() => {
    if (userIndex < 0) return []
    const rows: Array<LeaderboardEntry & { isCurrentUser: boolean }> = []
    if (userIndex > 0) rows.push({ ...leaderboard[userIndex - 1], isCurrentUser: false })
    rows.push({ ...leaderboard[userIndex], isCurrentUser: true })
    if (userIndex < leaderboard.length - 1) rows.push({ ...leaderboard[userIndex + 1], isCurrentUser: false })
    return rows
  }, [leaderboard, userIndex])

  return (
    <section className="vestuario-view marea-ranking-page">
      <header className="marea-ranking-hero">
        <div className="marea-ranking-hero-copy">
          <span className="marea-kicker">SUPER CARNES 2026</span>
          <h1 className="cancha-headline-title auth-reference-title marea-ranking-hero-title" aria-label="Tabla de goleadores">
            <span className="auth-reference-title-line is-light">TABLA DE</span>
            <span className="auth-reference-title-line is-gold">GOLEADORES</span>
          </h1>
          <p className="marea-ranking-hero-description">Consulta tu posicion, tus goles y el avance oficial de la promocion.</p>
        </div>

        <div className="marea-ranking-hero-art" aria-hidden="true">
          <img className="marea-ranking-hero-confetti" alt="" src="/redesign/auth-confetti-layer.svg" />
          <img className="marea-ranking-hero-player" alt="" src="/redesign/auth-player-left.png" />
          <img className="marea-ranking-hero-ball" alt="" src="/redesign/auth-ball-center.png" />
        </div>

        <aside className="marea-ranking-user-card">
          <div className="marea-ranking-user-head">
            <div className="marea-ranking-user-avatar">
              {avatarUrl ? <img alt={`Avatar de ${user.full_name}`} className="vestuario-avatar-image" src={avatarUrl} /> : userInitials(user.full_name)}
            </div>
            <div className="marea-ranking-user-copy">
              <strong>{user.full_name}</strong>
              <span>{user.branch?.name ?? 'Participante registrado'}</span>
              <small>
                {userEntry ? `Posicion #${positionLabel(userEntry.position)} en el ranking oficial` : 'Esperando tu aparicion en el ranking'}
              </small>
            </div>
          </div>
          <div className="marea-ranking-user-score">
            <span>Tus goles</span>
            <strong>{formatCompactNumber(userGoals)}</strong>
          </div>
        </aside>
      </header>

      <section className="marea-ranking-podium-shell">
        {podiumEntries.length ? (
          <div className="marea-ranking-podium-grid">
            {podiumEntries.map((entry, index) => {
              const isChampion = topThree[0]?.user_id === entry.user_id
              const place = topThree.findIndex((candidate) => candidate.user_id === entry.user_id) + 1

              return (
                <article key={entry.user_id} className={`${isChampion ? 'marea-ranking-podium-card champion' : 'marea-ranking-podium-card support'} place-${place}`}>
                  <div className="marea-ranking-podium-badge">{place}</div>
                  <div className="marea-ranking-podium-avatar">{userInitials(entry.full_name)}</div>
                  <span className="marea-ranking-podium-label">{podiumLabel(place)}</span>
                  <span className="marea-ranking-podium-icon material-symbols-outlined">
                    {isChampion ? 'emoji_events' : index === 0 ? 'military_tech' : 'workspace_premium'}
                  </span>
                  <h3>{entry.full_name}</h3>
                  <p>{roleLabel(entry.football_role)}</p>
                  <strong>{formatCompactNumber(entry.goals)} goles</strong>
                </article>
              )
            })}
          </div>
        ) : (
          <div className="vestuario-empty-state large">
            <span className="material-symbols-outlined">leaderboard</span>
            <h3>Sin podio publicado</h3>
            <p>Cuando haya posiciones oficiales, aqui se mostraran los tres primeros participantes.</p>
          </div>
        )}
      </section>

      <section className="marea-ranking-table-shell">
        <div className="marea-ranking-table-head">
          <div>
            <span className="marea-kicker">Ranking oficial</span>
            <h2>Tu posicion</h2>
          </div>
        </div>

        {userEntry ? (
          <>
            {userEntry.position > 2 && (
              <div className="vestuario-ranking-gap">
                <span className="material-symbols-outlined">more_horiz</span>
                <span>{userEntry.position - 2} participante{userEntry.position - 2 !== 1 ? 's' : ''} arriba</span>
              </div>
            )}

            <div className="vestuario-table-wrap">
              <table className="vestuario-table">
                <thead>
                  <tr>
                    <th>Pos</th>
                    <th>Participante</th>
                    <th>Rendimiento</th>
                    <th className="is-right">Goles</th>
                  </tr>
                </thead>
                <tbody>
                  {contextRows.map((entry) => {
                    const progress = performancePercent(entry, maxGoals)
                    const isMe = entry.isCurrentUser
                    const displayName = isMe ? entry.full_name : maskName(entry.full_name)

                    return (
                      <tr key={entry.user_id} className={isMe ? 'is-current-user' : 'is-masked'}>
                        <td className="vestuario-rank-cell">{positionLabel(entry.position)}</td>
                        <td>
                          <div className="vestuario-row-player">
                            <div className="vestuario-row-player-badge">{userInitials(entry.full_name)}</div>
                            <div>
                              <strong>{displayName}</strong>
                            </div>
                          </div>
                        </td>
                        <td>
                          <div className="vestuario-performance">
                            <div className="vestuario-performance-track">
                              <span className="vestuario-performance-fill" style={{ width: `${progress}%` }} />
                            </div>
                          </div>
                        </td>
                        <td className="vestuario-goals-cell">{isMe ? formatCompactNumber(entry.goals) : '—'}</td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>

            {userEntry.position > 1 && (
              <div className="vestuario-ranking-footer">
                <span className="material-symbols-outlined">trending_up</span>
                <span>Te faltan <strong>{formatCompactNumber(goalsToFirst)} goles</strong> para alcanzar el 1er lugar.</span>
              </div>
            )}

            {userEntry.position === 1 && (
              <div className="vestuario-ranking-footer vestuario-ranking-footer--leader">
                <span className="material-symbols-outlined">emoji_events</span>
                <span>Eres el lider del ranking con <strong>{formatCompactNumber(userGoals)} goles</strong>.</span>
              </div>
            )}
          </>
        ) : (
          <div className="vestuario-empty-state">
            <span className="material-symbols-outlined">leaderboard</span>
            <h3>Todavia no apareces en el ranking</h3>
            <p>Registra tus facturas para acumular goles y entrar en la tabla oficial.</p>
          </div>
        )}
      </section>
    </section>
  )
}
