import { useNavigate } from 'react-router-dom'

export function PublicBrandHeader() {
  const navigate = useNavigate()

  return (
    <header className="marea-client-header public-brand-header bg-background/80 backdrop-blur-xl border-b border-outline-variant bg-surface-container-lowest/90 docked full-width top-0 sticky z-50 shadow-md">
      <div className="marea-client-header-inner public-brand-header-inner flex justify-between items-center px-4 md:px-margin-desktop w-full max-w-7xl mx-auto h-16">
        <div className="marea-client-header-start flex items-center gap-3">
          <button
            className="public-brand-header-back"
            type="button"
            onClick={() => navigate(-1)}
            aria-label="Volver a la pagina anterior"
          >
            <span className="material-symbols-outlined">arrow_back</span>
            <span>Volver</span>
          </button>
          <button className="marea-header-brand" type="button" onClick={() => navigate('/login')} aria-label="Ir al inicio">
            <span className="marea-header-brand-logo">
              <img alt="Super Carnes" src="/redesign/auth-logo-super-carnes.png" />
            </span>
            <span className="marea-header-brand-copy">
              <span className="marea-header-brand-kicker">PROMOCION</span>
              <strong>SUPER CARNES 2026</strong>
            </span>
          </button>
        </div>
        <div className="public-brand-header-end">
          <button className="public-brand-header-home" type="button" onClick={() => navigate('/login')}>
            Volver al inicio
          </button>
        </div>
      </div>
    </header>
  )
}
