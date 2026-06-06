import type { DemoTourStep } from '../demoTour'

export function ClientDemoTour({
  showBanner,
  step,
  stepIndex,
  totalSteps,
  onStart,
  onDismiss,
  onNext,
  onPrevious,
  onClose,
  onComplete,
}: {
  showBanner: boolean
  step: DemoTourStep | null
  stepIndex: number
  totalSteps: number
  onStart: () => void
  onDismiss: () => void
  onNext: () => void
  onPrevious: () => void
  onClose: () => void
  onComplete: () => void
}) {
  const isLastStep = stepIndex >= totalSteps - 1

  return (
    <>
      {showBanner ? (
        <section className="demo-tour-banner" aria-label="Bienvenida del tour guiado">
          <div className="demo-tour-banner-copy">
            <span className="demo-tour-banner-kicker">RECORRIDO GUIADO</span>
            <h2>Quieres hacer un tour o prefieres participar?</h2>
            <p>Te mostramos rapidamente para que sirve cada seccion, pestana y boton importante antes de comenzar.</p>
          </div>
          <div className="demo-tour-banner-actions">
            <button className="demo-tour-banner-primary" type="button" onClick={onStart}>
              Quiero hacer un tour
            </button>
            <button className="demo-tour-banner-secondary" type="button" onClick={onDismiss}>
              Prefiero participar
            </button>
          </div>
        </section>
      ) : null}

      {step ? (
        <>
          <div className="demo-tour-scrim" aria-hidden="true" />
          <aside className="demo-tour-card" aria-label="Tour guiado">
            <div className="demo-tour-card-head">
              <div>
                <span className="demo-tour-card-kicker">
                  Paso {Math.min(stepIndex + 1, totalSteps)} de {totalSteps}
                </span>
                <h3>{step.title}</h3>
              </div>
              <button className="demo-tour-close material-symbols-outlined" type="button" onClick={onClose} aria-label="Salir del tour">
                close
              </button>
            </div>

            <p className="demo-tour-card-description">{step.description}</p>
            <p className="demo-tour-card-detail">{step.detail}</p>

            <div className="demo-tour-card-actions">
              <button className="demo-tour-banner-secondary" type="button" onClick={onPrevious} disabled={stepIndex === 0}>
                Anterior
              </button>
              {isLastStep ? (
                <button className="demo-tour-banner-primary" type="button" onClick={onComplete}>
                  Empezar ahora
                </button>
              ) : (
                <button className="demo-tour-banner-primary" type="button" onClick={onNext}>
                  Siguiente
                </button>
              )}
            </div>
          </aside>
        </>
      ) : null}
    </>
  )
}
