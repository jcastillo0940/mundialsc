import { useMemo, useState } from 'react'
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

function roleLabel(value: string | null | undefined) {
  if (!value) return 'Participante'
  return value.replace(/_/g, ' ')
}

function positionLabel(position: number) {
  return String(position).padStart(2, '0')
}

function leaderboardSlice(entries: LeaderboardEntry[], userId: number) {
  if (entries.length <= 8) return entries

  const userIndex = entries.findIndex((entry) => entry.user_id === userId)
  if (userIndex >= 0 && userIndex > 2) {
    const start = Math.max(0, userIndex - 2)
    return entries.slice(start, Math.min(entries.length, start + 6))
  }

  return entries.slice(0, 8)
}

function calculatePercent(value: number, total: number) {
  if (total <= 0) return 0
  return Math.max(0, Math.min(100, (value / total) * 100))
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
  const [searchTerm, setSearchTerm] = useState('')
  const [rankingMode, setRankingMode] = useState<'all' | 'top' | 'nearby'>('all')

  const avatarUrl = user.avatar_url ?? null
  const leaderboard = overview?.leaderboard ?? []
  const topThree = leaderboard.slice(0, 3)
  const userEntry = leaderboard.find((entry) => entry.user_id === user.id) ?? null
  const generalGoals = Number(overview?.general_goals ?? leaderboard.reduce((total, entry) => total + Number(entry.goals ?? 0), 0))
  const userGoals = Number(userEntry?.goals ?? 0)
  const nextMilestone = generalGoals > 0 ? Math.ceil((generalGoals + 1) / 500) * 500 : 500
  const milestoneProgress = calculatePercent(generalGoals, nextMilestone)
  const milestoneRemaining = Math.max(nextMilestone - generalGoals, 0)
  const maxGoals = leaderboard.reduce((currentMax, entry) => Math.max(currentMax, entry.goals), 0)
  const podiumEntries = topThree.length === 3 ? [topThree[1], topThree[0], topThree[2]] : topThree
  const spotlightLeader = topThree[0] ?? userEntry
  const userContribution = calculatePercent(userGoals, generalGoals)

  const rankingRows = useMemo(() => {
    const query = searchTerm.trim().toLowerCase()
    const baseRows =
      rankingMode === 'top' ? leaderboard.slice(0, 10) : rankingMode === 'nearby' ? leaderboardSlice(leaderboard, user.id) : leaderboard

    if (!query) return baseRows

    return baseRows.filter((entry) => {
      const haystack = `${entry.full_name} ${roleLabel(entry.football_role)} ${entry.position}`.toLowerCase()
      return haystack.includes(query)
    })
  }, [leaderboard, rankingMode, searchTerm, user.id])

  const summaryCards = [
    { label: 'Participantes', value: formatCompactNumber(leaderboard.length) },
    { label: 'Top 3', value: formatCompactNumber(topThree.length) },
    { label: 'Tu posicion', value: userEntry ? `#${positionLabel(userEntry.position)}` : 'N/D' },
    { label: 'Tus goles', value: formatCompactNumber(userGoals) },
  ]

  return (
    <section className="vestuario-view">
      <header className="vestuario-stage">
        <div className="vestuario-stage-copy">
          <span className="vestuario-kicker">SUPER CARNES 2026</span>
          <h1>Tabla de goleadores</h1>
          <p>Consulta tu posicion, los lideres actuales y el avance de participantes durante la promocion.</p>
        </div>

        <div className="vestuario-stage-player">
          <div className="vestuario-player-badge">
            {avatarUrl ? <img alt={`Avatar de ${user.full_name}`} className="vestuario-avatar-image" src={avatarUrl} /> : userInitials(user.full_name)}
          </div>
          <div className="vestuario-stage-player-copy">
            <strong>{user.full_name}</strong>
            <span>{user.branch?.name ?? 'Participante registrado'}</span>
            <small>
              {userEntry ? `Posicion #${positionLabel(userEntry.position)} en el ranking oficial` : 'Esperando tu aparicion en el ranking'}
            </small>
          </div>
          <div className="vestuario-stage-score">
            <span>Tus goles</span>
            <strong>{formatCompactNumber(userGoals)} G</strong>
          </div>
        </div>
      </header>

      <section className="vestuario-podium-shell">
        {podiumEntries.length ? (
          <div className="vestuario-podium-grid">
            {podiumEntries.map((entry, index) => {
              const isChampion = topThree[0]?.user_id === entry.user_id
              const place = topThree.findIndex((candidate) => candidate.user_id === entry.user_id) + 1

              return (
                <article key={entry.user_id} className={isChampion ? 'vestuario-podium-card champion' : 'vestuario-podium-card support'}>
                  <div className="vestuario-podium-avatar">{userInitials(entry.full_name)}</div>
                  <div className="vestuario-podium-place">{place}</div>
                  <span className="vestuario-podium-icon material-symbols-outlined">
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

      <section className="vestuario-ranking-shell">
        <div className="vestuario-ranking-head">
          <div>
            <span className="vestuario-panel-kicker">Ranking oficial</span>
            <h2>Goleadores</h2>
          </div>

          <div className="vestuario-ranking-controls">
            <label className="vestuario-search">
              <span className="material-symbols-outlined">search</span>
              <input
                type="text"
                value={searchTerm}
                onChange={(event) => setSearchTerm(event.target.value)}
                placeholder="Buscar participante..."
              />
            </label>

            <div className="vestuario-filter-group" role="tablist" aria-label="Filtro de ranking">
              <button
                className={rankingMode === 'all' ? 'vestuario-filter-chip active' : 'vestuario-filter-chip'}
                type="button"
                onClick={() => setRankingMode('all')}
              >
                Todo
              </button>
              <button
                className={rankingMode === 'top' ? 'vestuario-filter-chip active' : 'vestuario-filter-chip'}
                type="button"
                onClick={() => setRankingMode('top')}
              >
                Top 10
              </button>
              <button
                className={rankingMode === 'nearby' ? 'vestuario-filter-chip active' : 'vestuario-filter-chip'}
                type="button"
                onClick={() => setRankingMode('nearby')}
              >
                Cerca de mi
              </button>
            </div>
          </div>
        </div>

        {rankingRows.length ? (
          <>
            <div className="vestuario-table-wrap">
              <table className="vestuario-table">
                <thead>
                  <tr>
                    <th>Pos</th>
                    <th>Participante</th>
                    <th>Rol</th>
                    <th>Rendimiento</th>
                    <th className="is-right">Goles</th>
                  </tr>
                </thead>
                <tbody>
                  {rankingRows.map((entry) => {
                    const progress = performancePercent(entry, maxGoals)

                    return (
                      <tr key={entry.user_id} className={entry.user_id === user.id ? 'is-current-user' : ''}>
                        <td className="vestuario-rank-cell">{positionLabel(entry.position)}</td>
                        <td>
                          <div className="vestuario-row-player">
                            <div className="vestuario-row-player-badge">{userInitials(entry.full_name)}</div>
                            <div>
                              <strong>{entry.full_name}</strong>
                            </div>
                          </div>
                        </td>
                        <td>
                          <span className="vestuario-role-chip">{roleLabel(entry.football_role)}</span>
                        </td>
                        <td>
                          <div className="vestuario-performance">
                            <div className="vestuario-performance-track">
                              <span className="vestuario-performance-fill" style={{ width: `${progress}%` }} />
                            </div>
                          </div>
                        </td>
                        <td className="vestuario-goals-cell">{formatCompactNumber(entry.goals)}</td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>

            <div className="vestuario-ranking-footer">
              <span>
                {userEntry ? `Tu posicion actual es #${positionLabel(userEntry.position)} con ${formatCompactNumber(userEntry.goals)} goles.` : 'Todavia no apareces en el ranking oficial.'}
              </span>
              <strong>{formatCompactNumber(leaderboard.length)} participantes en competencia</strong>
            </div>
          </>
        ) : (
          <div className="vestuario-empty-state">
            <span className="material-symbols-outlined">search_off</span>
            <h3>Sin resultados para esa busqueda</h3>
            <p>Ajusta el nombre o cambia el filtro para volver a ver la tabla.</p>
          </div>
        )}
      </section>

      <section className="vestuario-insight-strip">
        <article className="vestuario-insight-card wide dark">
          <span>Meta global</span>
          <strong>{formatCompactNumber(generalGoals)} goles</strong>
          <div className="vestuario-progress-block">
            <div className="vestuario-progress-track">
              <span className="vestuario-progress-fill" style={{ width: `${milestoneProgress}%` }} />
            </div>
          </div>
          <p>Faltan {formatCompactNumber(milestoneRemaining)} goles para alcanzar el siguiente corte oficial de {formatCompactNumber(nextMilestone)}.</p>
        </article>

        <article className="vestuario-insight-card gold">
          <span>Lider actual</span>
          <strong>{spotlightLeader?.full_name ?? 'Sin lider visible'}</strong>
          <p>{spotlightLeader ? `${roleLabel(spotlightLeader.football_role)} con ${formatCompactNumber(spotlightLeader.goals)} goles.` : 'Esperando lider del ranking.'}</p>
        </article>

        <article className="vestuario-insight-card red">
          <span>Estado de tu cuenta</span>
          <strong>{userEntry ? `#${positionLabel(userEntry.position)}` : 'Pendiente'}</strong>
          <p>
            Aportas {formatCompactNumber(userGoals)} goles al torneo y representas {userContribution.toFixed(1)}% del acumulado actual.
          </p>
        </article>
      </section>

      <div className="vestuario-dashboard-grid">
        <section className="vestuario-panel">
          <div className="vestuario-panel-head compact">
            <div>
              <span className="vestuario-panel-kicker">Resumen</span>
              <h2>Resumen del ranking</h2>
            </div>
          </div>

          <div className="vestuario-summary-grid">
            {summaryCards.map((card) => (
              <article key={card.label}>
                <span>{card.label}</span>
                <strong>{card.value}</strong>
              </article>
            ))}
          </div>
        </section>

        <section className="vestuario-panel">
          <div className="vestuario-panel-head compact">
            <div>
              <span className="vestuario-panel-kicker">Actividad</span>
              <h2>Estado general</h2>
            </div>
          </div>

          <div className="vestuario-mini-strip">
            <article>
              <span>Goles totales</span>
              <strong>{formatCompactNumber(generalGoals)}</strong>
            </article>
            <article>
              <span>Lider actual</span>
              <strong>{spotlightLeader ? `#${positionLabel(spotlightLeader.position)}` : 'N/D'}</strong>
            </article>
          </div>
        </section>
      </div>
    </section>
  )
}
