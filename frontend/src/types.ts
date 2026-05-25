export type Role = 'client' | 'cashier' | 'admin'
export type DocumentType = 'cedula' | 'passport' | 'residente'

export interface User {
  id: number
  role: Role
  full_name: string
  cedula: string
  document_type: DocumentType
  email: string
  phone: string | null
  avatar_url?: string | null
  birthdate?: string | null
  resides_in_panama?: boolean
  accepted_terms_at?: string | null
  group_stage_goal_prediction?: number | null
  registration_completed_at?: string | null
  predictions_completed_at?: string | null
  disqualified_at?: string | null
  branch?: UserBranch | null
  wallet?: WalletSummary | null
}

export interface UserBranch {
  id: number
  name: string
  code: string
}

export interface WalletSummary {
  goals_balance: number
  shots_balance: number
  lifetime_goals_earned: number
  lifetime_shots_earned: number
}

export interface WalletMovement {
  id: number
  type: string
  resource_type?: string | null
  resource_id?: number | null
  goals_delta: number
  shots_delta: number
  notes?: string | null
  meta?: Record<string, unknown> | null
  created_at?: string | null
}

export interface TournamentPhase {
  id: number
  name: string
  slug: string
  stage_order: number
  starts_at: string
  ends_at: string
  exact_score_points: number
  outcome_points: number
}

export interface Team {
  id: number
  name: string
  code: string
  group_label: string | null
  flag_emoji: string | null
  flag_url?: string | null
  provider_logo_url?: string | null
  ranking_fifa?: number | null
}

export interface TournamentMatch {
  id: number
  phase_id: number
  match_number: number | null
  group_label: string | null
  round_label?: string | null
  stage_label?: string | null
  venue_name?: string | null
  kickoff_timezone?: string | null
  kickoff_at: string
  home_score: number | null
  away_score: number | null
  status: 'scheduled' | 'locked' | 'final'
  favorite_side?: 'home' | 'away' | 'none'
  home_team: Team
  away_team: Team
  homeTeam: Team
  awayTeam: Team
}

export interface Prediction {
  id: number
  match_id: number
  predicted_home_score: number
  predicted_away_score: number
  points_awarded: number
  result_type: 'pending' | 'exact' | 'outcome' | 'miss'
  match: TournamentMatch
}

export interface LeaderboardEntry {
  position: number
  user_id: number
  full_name: string
  goals: number
  total_points: number
  exact_hits: number
  invoice_count: number
  invoice_total_amount: number
  goal_prediction_delta: number
  football_role: string
}

export interface RegisteredInvoice {
  id: number
  cufe: string
  qr_raw_text: string
  invoice_number?: string | null
  issued_at?: string | null
  purchase_amount: number | string
  points_awarded: number
  validation_status: 'approved' | 'pending' | 'rejected' | string
  validation_notes?: string | null
  daily_points_capped?: boolean
  daily_invoice_limit_hit?: boolean
  created_at?: string
}

export interface ResolvedInvoiceData {
  cufe: string
  invoice_number: string
  purchase_amount: string
  issued_at: string
  issuer_name?: string
}

export interface ClientBootstrap {
  active_phase: TournamentPhase | null
  phase_goals: number
  general_goals: number
  leaderboard: LeaderboardEntry[]
}

export interface DashboardStats {
  registered_invoices: number
  active_coupons: number
  delivered_coupons: number
}

export interface Prize {
  id: number
  name: string
  slug: string
  description?: string | null
  category: string
  redemption_type: string
  points_cost?: number | null
  shots_cost?: number | null
  total_stock: number
  reserved_stock: number
  delivered_stock: number
  available_stock: number
  image_url?: string | null
  is_active: boolean
}

export interface Coupon {
  id: number
  code: string
  source_type: string
  status: string
  qr_payload?: string
  expires_at?: string | null
  delivered_at?: string | null
  prize?: Prize | null
}

export interface DashboardSnapshot {
  wallet: WalletSummary | null
  stats: DashboardStats
  recent_invoices: RegisteredInvoice[]
  recent_coupons: Coupon[]
}

export interface WalletSnapshot {
  wallet: WalletSummary | null
  movements: WalletMovement[]
}
