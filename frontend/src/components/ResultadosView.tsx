import { useEffect, useRef, useState } from 'react'
import { api } from '../api'
import type { TournamentMatch, TournamentPhase } from '../types'

type ResultTab = 'hoy' | 'finalizados' | 'programados'

interface Props {
  initialMatches: TournamentMatch[]
  phases: TournamentPhase[]
}

interface MatchesResponse {
  data: TournamentMatch[]
}

function TeamBadgeResult({ team }: { team: TournamentMatch['homeTeam'] | undefined }) {
  return (
    <div className="team-badge">
      {team?.provider_logo_url ? (
        <img alt={`Escudo de ${team.name}`} className="team-logo-image" src={team.provider_logo_url} />
      ) : team?.flag_url ? (
        <img alt={`Bandera de ${team.name}`} className="team-flag-image" src={team.flag_url} />
      ) : team?.flag_emoji ? (
        <span className="team-emoji">{team.flag_emoji}</span>
      ) : (
        <span className="team-fallback">{(team?.name ?? 'SC').slice(0, 3).toUpperCase()}</span>
      )}
    </div>
  )
}

function formatTime(value: string) {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  return date.toLocaleTimeString('es-PA', { hour: '2-digit', minute: '2-digit', timeZone: 'America/Panama' })
}

function formatDateLabel(value: string) {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleDateString('es-PA', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'America/Panama' })
}

function dateKey(value: string) {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleDateString('en-CA', { timeZone: 'America/Panama' })
}

function todayKey() {
  return new Date().toLocaleDateString('en-CA', { timeZone: 'America/Panama' })
}

function groupByDate(matches: TournamentMatch[]): { date: string; label: string; matches: TournamentMatch[] }[] {
  const map = new Map<string, { label: string; matches: TournamentMatch[] }>()
  for (const match of matches) {
    const key = dateKey(match.kickoff_at)
    if (!map.has(key)) {
      map.set(key, { label: formatDateLabel(match.kickoff_at), matches: [] })
    }
    map.get(key)!.matches.push(match)
  }
  return Array.from(map.entries())
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([date, value]) => ({ date, ...value }))
}

function ResultCard({ match }: { match: TournamentMatch }) {
  const home = match.homeTeam ?? match.home_team
  const away = match.awayTeam ?? match.away_team
  const isLive = match.status === 'locked'
  const isFinal = match.status === 'final'
  const hasScore = match.home_score !== null && match.away_score !== null
  const groupLabel = match.group_label ? `Grupo ${match.group_label}` : (match.round_label ?? match.stage_label ?? null)

  return (
    <article className={[
      'marea-match-card cancha-match-card resultado-card',
      isLive ? 'resultado-card--live' : '',
      isFinal ? 'resultado-card--final' : '',
    ].filter(Boolean).join(' ')}>
      <div className="marea-match-topline">
        <div className="marea-match-banner">
          <span>{groupLabel ?? 'Partido'}</span>
        </div>
        {isLive ? (
          <span className="marea-time resultado-live-badge">
            <span className="marea-live-dot" />
            En vivo
          </span>
        ) : isFinal ? (
          <span className="marea-time resultado-final-badge">
            <span className="material-symbols-outlined" style={{ fontSize: 14 }}>task_alt</span>
            Final
          </span>
        ) : (
          <span className="marea-time">
            <span className="material-symbols-outlined" style={{ fontSize: 14 }}>schedule</span>
            {formatTime(match.kickoff_at)}
          </span>
        )}
      </div>

      {match.venue_name ? (
        <div className="marea-match-banner meta">
          <span>{match.venue_name}</span>
        </div>
      ) : null}

      <div className="resultado-teams-row">
        <div className="resultado-side">
          <TeamBadgeResult team={home} />
          <span className="resultado-team-name">{home?.name ?? 'Local'}</span>
        </div>

        <div className="resultado-score-block">
          {hasScore ? (
            <>
              <span className={`resultado-score${isLive ? ' resultado-score--live' : ''}`}>
                {match.home_score}
              </span>
              <span className="resultado-score-sep">-</span>
              <span className={`resultado-score${isLive ? ' resultado-score--live' : ''}`}>
                {match.away_score}
              </span>
            </>
          ) : (
            <span className="resultado-score-vs">vs</span>
          )}
        </div>

        <div className="resultado-side">
          <TeamBadgeResult team={away} />
          <span className="resultado-team-name">{away?.name ?? 'Visitante'}</span>
        </div>
      </div>
    </article>
  )
}

function EmptyTab({ icon, text }: { icon: string; text: string }) {
  return (
    <div className="resultado-empty">
      <span className="material-symbols-outlined">{icon}</span>
      <p>{text}</p>
    </div>
  )
}

export function ResultadosView({ initialMatches, phases: _phases }: Props) {
  const [matches, setMatches] = useState<TournamentMatch[]>(initialMatches)
  const [lastRefresh, setLastRefresh] = useState<Date>(new Date())
  const [tab, setTab] = useState<ResultTab>('hoy')
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null)

  const hasLive = matches.some((m) => m.status === 'locked')
  const today = todayKey()

  async function refresh() {
    try {
      const response = await api.get<MatchesResponse>('/client/matches')
      setMatches(response.data.data)
      setLastRefresh(new Date())
    } catch {
      // silent
    }
  }

  useEffect(() => {
    if (intervalRef.current) clearInterval(intervalRef.current)
    if (hasLive) {
      intervalRef.current = setInterval(() => { void refresh() }, 30_000)
    }
    return () => { if (intervalRef.current) clearInterval(intervalRef.current) }
  }, [hasLive])

  useEffect(() => { setMatches(initialMatches) }, [initialMatches])

  // auto-switch to hoy if there are live matches
  useEffect(() => { if (hasLive) setTab('hoy') }, [hasLive])

  const liveMatches = matches.filter((m) => m.status === 'locked')
  const todayMatches = matches.filter((m) => dateKey(m.kickoff_at) === today && m.status !== 'locked' && m.status !== 'final')
  const finalMatches = matches.filter((m) => m.status === 'final').sort((a, b) => new Date(b.kickoff_at).getTime() - new Date(a.kickoff_at).getTime())
  const scheduledMatches = matches.filter((m) => m.status === 'scheduled' && dateKey(m.kickoff_at) !== today)

  const hoyCount = liveMatches.length + todayMatches.length
  const finalGrouped = groupByDate(finalMatches)
  const scheduledGrouped = groupByDate(scheduledMatches)

  return (
    <div className="resultado-shell">
      <div className="resultado-header">
        <h2 className="resultado-title">
          <span className="material-symbols-outlined">scoreboard</span>
          Resultados
        </h2>
        <div className="resultado-refresh-info">
          {hasLive ? (
            <span className="resultado-auto-refresh">
              <span className="marea-live-dot" />
              Actualizando cada 30s
            </span>
          ) : (
            <button className="resultado-refresh-button" type="button" onClick={() => void refresh()}>
              <span className="material-symbols-outlined">refresh</span>
              Actualizar
            </button>
          )}
          <span className="resultado-last-refresh">
            {lastRefresh.toLocaleTimeString('es-PA', { hour: '2-digit', minute: '2-digit', second: '2-digit', timeZone: 'America/Panama' })}
          </span>
        </div>
      </div>

      <div className="resultado-tabs" role="tablist">
        <button
          role="tab"
          aria-selected={tab === 'hoy'}
          className={`resultado-tab${tab === 'hoy' ? ' resultado-tab--active' : ''}${hasLive ? ' resultado-tab--has-live' : ''}`}
          type="button"
          onClick={() => setTab('hoy')}
        >
          {hasLive ? <span className="marea-live-dot" /> : null}
          Hoy
          {hoyCount > 0 ? <span className="resultado-tab-badge">{hoyCount}</span> : null}
        </button>
        <button
          role="tab"
          aria-selected={tab === 'finalizados'}
          className={`resultado-tab${tab === 'finalizados' ? ' resultado-tab--active' : ''}`}
          type="button"
          onClick={() => setTab('finalizados')}
        >
          Finalizados
          {finalMatches.length > 0 ? <span className="resultado-tab-badge">{finalMatches.length}</span> : null}
        </button>
        <button
          role="tab"
          aria-selected={tab === 'programados'}
          className={`resultado-tab${tab === 'programados' ? ' resultado-tab--active' : ''}`}
          type="button"
          onClick={() => setTab('programados')}
        >
          Programados
          {scheduledMatches.length > 0 ? <span className="resultado-tab-badge">{scheduledMatches.length}</span> : null}
        </button>
      </div>

      {/* ── HOY ── */}
      {tab === 'hoy' && (
        <>
          {liveMatches.length > 0 && (
            <section className="resultado-section">
              <h3 className="resultado-section-title resultado-section-title--live">
                <span className="marea-live-dot" />
                En vivo ahora
              </h3>
              <div className="resultado-cards">
                {liveMatches.map((m) => <ResultCard key={m.id} match={m} />)}
              </div>
            </section>
          )}
          {todayMatches.length > 0 ? (
            <section className="resultado-section">
              {liveMatches.length > 0 && (
                <h3 className="resultado-section-title">Resto del día</h3>
              )}
              <div className="resultado-cards">
                {todayMatches
                  .sort((a, b) => new Date(a.kickoff_at).getTime() - new Date(b.kickoff_at).getTime())
                  .map((m) => <ResultCard key={m.id} match={m} />)}
              </div>
            </section>
          ) : null}
          {hoyCount === 0 && (
            <EmptyTab icon="today" text="No hay partidos programados para hoy." />
          )}
        </>
      )}

      {/* ── FINALIZADOS ── */}
      {tab === 'finalizados' && (
        <>
          {finalGrouped.length > 0 ? finalGrouped.map(({ date, label, matches: dayMatches }) => (
            <section key={date} className="resultado-section">
              <h3 className="resultado-section-title">{label}</h3>
              <div className="resultado-cards">
                {dayMatches.map((m) => <ResultCard key={m.id} match={m} />)}
              </div>
            </section>
          )) : (
            <EmptyTab icon="task_alt" text="Aún no hay partidos finalizados." />
          )}
        </>
      )}

      {/* ── PROGRAMADOS ── */}
      {tab === 'programados' && (
        <>
          {scheduledGrouped.length > 0 ? scheduledGrouped.map(({ date, label, matches: dayMatches }) => (
            <section key={date} className="resultado-section">
              <h3 className="resultado-section-title">{label}</h3>
              <div className="resultado-cards">
                {dayMatches
                  .sort((a, b) => new Date(a.kickoff_at).getTime() - new Date(b.kickoff_at).getTime())
                  .map((m) => <ResultCard key={m.id} match={m} />)}
              </div>
            </section>
          )) : (
            <EmptyTab icon="calendar_month" text="No hay partidos próximos programados." />
          )}
        </>
      )}
    </div>
  )
}
