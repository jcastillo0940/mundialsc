import { PublicBrandHeader } from './PublicBrandHeader'

interface Props {
  termsText: string
}

export function TerminosPage({ termsText }: Props) {
  return (
    <div className="min-h-screen bg-background text-on-background" style={{ fontFamily: 'Segoe UI, Arial, sans-serif' }}>
      <PublicBrandHeader />

      <main style={{ maxWidth: '800px', margin: '0 auto', padding: '40px 24px 80px' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '32px' }}>
          <span className="material-symbols-outlined" style={{ fontSize: '36px', color: '#007aff' }}>gavel</span>
          <div>
            <h1 style={{ margin: 0, fontSize: '28px', fontWeight: 700 }}>Terminos y Condiciones</h1>
            <p style={{ margin: '4px 0 0', color: '#9fb4b2', fontSize: '14px' }}>PRONOSTICA EL MUNDIAL Y GANA</p>
          </div>
        </div>

        <div style={{
          background: 'rgba(20,32,40,0.92)',
          border: '1px solid rgba(255,255,255,0.08)',
          borderRadius: '18px',
          padding: '32px',
        }}>
          <pre style={{
            whiteSpace: 'pre-wrap',
            fontFamily: 'Segoe UI, Arial, sans-serif',
            fontSize: '14px',
            lineHeight: '1.8',
            color: '#d4e8e0',
            margin: 0,
          }}>
            {termsText}
          </pre>
        </div>
      </main>
    </div>
  )
}
