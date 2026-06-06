import { PublicBrandHeader } from './PublicBrandHeader'

interface ContactInfo {
  contact_email?: string
  contact_phone?: string
  contact_address?: string
  contact_hours?: string
}

interface Props {
  contactInfo: ContactInfo
}

export function ContactoPage({ contactInfo }: Props) {
  const items = [
    contactInfo.contact_email && {
      icon: 'mail',
      label: 'Correo electronico',
      value: contactInfo.contact_email,
      href: `mailto:${contactInfo.contact_email}`,
    },
    contactInfo.contact_phone && {
      icon: 'phone',
      label: 'Telefono',
      value: contactInfo.contact_phone,
      href: `tel:${contactInfo.contact_phone.replace(/\s|-/g, '')}`,
    },
    contactInfo.contact_address && {
      icon: 'location_on',
      label: 'Direccion',
      value: contactInfo.contact_address,
      href: null,
    },
    contactInfo.contact_hours && {
      icon: 'schedule',
      label: 'Horario de atencion',
      value: contactInfo.contact_hours,
      href: null,
    },
  ].filter(Boolean) as { icon: string; label: string; value: string; href: string | null }[]

  return (
    <div className="min-h-screen bg-background text-on-background" style={{ fontFamily: 'Segoe UI, Arial, sans-serif' }}>
      <PublicBrandHeader />

      <main style={{ maxWidth: '600px', margin: '0 auto', padding: '40px 24px 80px' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '32px' }}>
          <span className="material-symbols-outlined" style={{ fontSize: '36px', color: '#007aff' }}>support_agent</span>
          <div>
            <h1 style={{ margin: 0, fontSize: '28px', fontWeight: 700 }}>Contacto</h1>
            <p style={{ margin: '4px 0 0', color: '#9fb4b2', fontSize: '14px' }}>Tienes preguntas sobre PRONOSTICA EL MUNDIAL Y GANA?</p>
          </div>
        </div>

        {items.length > 0 ? (
          <div style={{ display: 'grid', gap: '12px' }}>
            {items.map((item) => (
              <div
                key={item.label}
                style={{
                  background: 'rgba(20,32,40,0.92)',
                  border: '1px solid rgba(255,255,255,0.08)',
                  borderRadius: '14px',
                  padding: '20px 24px',
                  display: 'flex',
                  alignItems: 'flex-start',
                  gap: '16px',
                }}
              >
                <span className="material-symbols-outlined" style={{ fontSize: '24px', color: '#007aff', marginTop: '2px', flexShrink: 0 }}>
                  {item.icon}
                </span>
                <div>
                  <div style={{ fontSize: '12px', color: '#9fb4b2', marginBottom: '4px' }}>{item.label}</div>
                  {item.href ? (
                    <a
                      href={item.href}
                      style={{ color: '#ffd27a', fontSize: '16px', fontWeight: 600, textDecoration: 'none' }}
                    >
                      {item.value}
                    </a>
                  ) : (
                    <div style={{ color: '#eef4ef', fontSize: '15px', fontWeight: 500, whiteSpace: 'pre-line' }}>{item.value}</div>
                  )}
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div style={{
            background: 'rgba(20,32,40,0.92)',
            border: '1px solid rgba(255,255,255,0.08)',
            borderRadius: '14px',
            padding: '40px 24px',
            textAlign: 'center',
            color: '#9fb4b2',
          }}>
            <span className="material-symbols-outlined" style={{ fontSize: '48px', marginBottom: '12px', display: 'block' }}>info</span>
            La informacion de contacto estara disponible proximamente.
          </div>
        )}

        <div style={{
          marginTop: '32px',
          background: 'rgba(20,32,40,0.6)',
          border: '1px solid rgba(255,255,255,0.06)',
          borderRadius: '14px',
          padding: '20px 24px',
          fontSize: '13px',
          color: '#9fb4b2',
          lineHeight: '1.6',
        }}>
          <strong style={{ color: '#d4e8e0' }}>Super Carnes</strong><br />
          Para consultas sobre la promocion "PRONOSTICA EL MUNDIAL Y GANA", puedes acercarte a cualquiera de nuestras sucursales
          o escribirnos por los medios indicados. Atendemos con gusto.
        </div>
      </main>
    </div>
  )
}
