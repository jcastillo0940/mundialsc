export type DemoTourStatus = 'not_started' | 'dismissed' | 'in_progress' | 'completed'

export interface DemoTourState {
  status: DemoTourStatus
  stepIndex: number
}

export interface DemoTourStep {
  id: string
  title: string
  description: string
  detail: string
  targetId?: string
  view?: 'cancha' | 'facturas' | 'perfil' | 'reglas' | 'cuenta'
}

export const DEMO_TOUR_STORAGE_KEY = 'super-carnes-demo-tour'

export const DEMO_TOUR_STEPS: DemoTourStep[] = [
  {
    id: 'welcome',
    title: 'Bienvenido al recorrido',
    description: 'Este tour te muestra como usar la plataforma antes de participar.',
    detail: 'Puedes avanzar paso a paso, salir cuando quieras y volver a abrirlo luego desde el boton Ver tour.',
  },
  {
    id: 'navigation',
    title: 'Asi te mueves por la plataforma',
    description: 'Aqui tienes las pestanas principales para entrar a cada seccion.',
    detail: 'La Cancha es para pronosticos, Entrenamiento para facturas, Ranking para tu posicion, Vitrina para tus goles y Mi Cuenta para tus datos.',
    targetId: 'demo-main-navigation',
    view: 'cancha',
  },
  {
    id: 'cancha',
    title: 'La Cancha',
    description: 'Aqui ves los partidos activos y envias tus pronosticos.',
    detail: 'Revisa la fase, ajusta los marcadores y usa el boton de envio para registrar tu prediccion.',
    targetId: 'demo-view-cancha',
    view: 'cancha',
  },
  {
    id: 'facturas',
    title: 'Entrenamiento',
    description: 'En esta seccion registras o escaneas facturas para participar.',
    detail: 'Puedes elegir entre escaneo o captura manual y luego enviar la informacion desde los campos principales.',
    targetId: 'demo-view-facturas',
    view: 'facturas',
  },
  {
    id: 'ranking',
    title: 'Ranking',
    description: 'Aqui revisas tu posicion y el avance frente a otros participantes.',
    detail: 'Mira tu tabla, el podio y los indicadores para entender como vas dentro de la promocion.',
    targetId: 'demo-view-perfil',
    view: 'perfil',
  },
  {
    id: 'vitrina',
    title: 'Vitrina',
    description: 'Esta vista te deja ver tus goles, movimientos y el historial de la promocion.',
    detail: 'Revisa los bloques principales para entender cuanto llevas acumulado y que acciones ya fueron registradas.',
    targetId: 'demo-view-reglas',
    view: 'reglas',
  },
  {
    id: 'cuenta',
    title: 'Mi Cuenta',
    description: 'Desde aqui administras tus datos personales y consultas informacion de tu perfil.',
    detail: 'Puedes revisar tu informacion, actualizar contacto, foto y acceder a terminos y condiciones.',
    targetId: 'demo-view-cuenta',
    view: 'cuenta',
  },
  {
    id: 'complete',
    title: 'Listo para participar',
    description: 'Ya conoces las secciones principales de la plataforma.',
    detail: 'Cuando quieras repasar el recorrido, usa el boton Ver tour y comenzara otra vez desde el inicio.',
  },
]

export function loadDemoTourState(): DemoTourState {
  if (typeof window === 'undefined') {
    return { status: 'not_started', stepIndex: 0 }
  }

  try {
    const raw = window.localStorage.getItem(DEMO_TOUR_STORAGE_KEY)
    if (!raw) return { status: 'not_started', stepIndex: 0 }

    const parsed = JSON.parse(raw) as Partial<DemoTourState>
    const allowedStatuses: DemoTourStatus[] = ['not_started', 'dismissed', 'in_progress', 'completed']
    const status = allowedStatuses.includes(parsed.status as DemoTourStatus) ? (parsed.status as DemoTourStatus) : 'not_started'
    const stepIndex = Number.isInteger(parsed.stepIndex) ? Math.max(0, Number(parsed.stepIndex)) : 0

    return { status, stepIndex }
  } catch {
    return { status: 'not_started', stepIndex: 0 }
  }
}

export function saveDemoTourState(state: DemoTourState) {
  if (typeof window === 'undefined') return
  window.localStorage.setItem(DEMO_TOUR_STORAGE_KEY, JSON.stringify(state))
}
