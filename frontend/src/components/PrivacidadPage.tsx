import { useNavigate } from 'react-router-dom'

export function PrivacidadPage() {
  const navigate = useNavigate()

  return (
    <div className="min-h-screen bg-background text-on-background" style={{ fontFamily: 'Segoe UI, Arial, sans-serif' }}>
      <header style={{
        background: 'rgba(10,18,22,0.97)',
        borderBottom: '1px solid rgba(255,255,255,0.08)',
        padding: '16px 24px',
        display: 'flex',
        alignItems: 'center',
        gap: '16px',
        position: 'sticky',
        top: 0,
        zIndex: 50,
      }}>
        <button
          type="button"
          onClick={() => navigate(-1)}
          style={{
            background: 'rgba(255,255,255,0.06)',
            border: '1px solid rgba(255,255,255,0.12)',
            borderRadius: '10px',
            color: '#eef4ef',
            padding: '8px 14px',
            cursor: 'pointer',
            display: 'flex',
            alignItems: 'center',
            gap: '6px',
            fontSize: '14px',
          }}
        >
          <span className="material-symbols-outlined" style={{ fontSize: '18px' }}>arrow_back</span>
          Volver
        </button>
        <strong style={{ color: '#ffd27a', fontSize: '16px' }}>Super Carnes · Polla Mundialista 2026</strong>
      </header>

      <main style={{ maxWidth: '800px', margin: '0 auto', padding: '40px 24px 80px' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '32px' }}>
          <span className="material-symbols-outlined" style={{ fontSize: '36px', color: '#007aff' }}>shield</span>
          <div>
            <h1 style={{ margin: 0, fontSize: '28px', fontWeight: 700 }}>Política de Privacidad</h1>
            <p style={{ margin: '4px 0 0', color: '#9fb4b2', fontSize: '14px' }}>Tratamiento de datos personales — Ley 81 de 2019</p>
          </div>
        </div>

        {[
          {
            title: '1. Responsable del tratamiento',
            body: 'Super Carnes, empresa constituida y operando en la República de Panamá, es responsable del tratamiento de los datos personales recopilados a través de la plataforma "Polla Mundialista Super Carnes 2026".',
          },
          {
            title: '2. Datos que recopilamos',
            body: 'Recopilamos únicamente los datos necesarios para la administración de la promoción:\n• Nombre completo\n• Número de cédula de identidad personal o pasaporte\n• Correo electrónico\n• Número de teléfono\n• Sucursal de preferencia\n• Historial de facturas registradas (CUFE, monto, fecha)\n• Pronósticos deportivos realizados en la plataforma',
          },
          {
            title: '3. Finalidad del tratamiento',
            body: 'Los datos personales se utilizan exclusivamente para:\n• Administrar, desarrollar y ejecutar la promoción "Polla Mundialista Super Carnes 2026"\n• Validar la identidad del participante y su elegibilidad\n• Verificar las facturas registradas ante la DGI\n• Contactar a los ganadores y gestionar la entrega de premios\n• Cumplir obligaciones legales y regulatorias aplicables en Panamá',
          },
          {
            title: '4. Base legal del tratamiento',
            body: 'El tratamiento de sus datos se basa en el consentimiento expreso otorgado al momento del registro y en el cumplimiento de las obligaciones derivadas de la participación en la promoción, de conformidad con la Ley 81 de 2019 sobre Protección de Datos Personales de la República de Panamá.',
          },
          {
            title: '5. Compartición de datos',
            body: 'Super Carnes no vende, alquila ni comercializa sus datos personales a terceros. Los datos únicamente podrán ser compartidos con:\n• Autoridades públicas panameñas cuando sea requerido por ley\n• Proveedores de servicios tecnológicos estrictamente necesarios para la operación de la plataforma, bajo acuerdos de confidencialidad\n• La Dirección General de Ingresos (DGI) para la verificación de facturas',
          },
          {
            title: '6. Plazo de conservación',
            body: 'Los datos personales se conservarán durante la vigencia de la promoción y por un período adicional de hasta cinco (5) años, para atender posibles reclamaciones legales o requerimientos de autoridades competentes. Transcurrido dicho plazo, los datos serán eliminados de manera segura.',
          },
          {
            title: '7. Derechos del titular',
            body: 'De conformidad con la Ley 81 de 2019, usted tiene derecho a:\n• Acceder a sus datos personales\n• Rectificar datos inexactos o incompletos\n• Cancelar o suprimir sus datos cuando ya no sean necesarios\n• Oponerse al tratamiento en los casos previstos por la ley\n• Revocar el consentimiento otorgado\n\nPara ejercer estos derechos, puede contactarnos en la dirección indicada en la sección de Contacto de esta plataforma.',
          },
          {
            title: '8. Seguridad de los datos',
            body: 'Super Carnes implementa medidas técnicas y organizativas adecuadas para proteger sus datos personales contra pérdida, acceso no autorizado, divulgación o destrucción, incluyendo cifrado de comunicaciones (HTTPS), control de acceso restringido y auditoría de operaciones sobre datos sensibles.',
          },
          {
            title: '9. Modificaciones',
            body: 'Super Carnes se reserva el derecho de actualizar esta política en cualquier momento. Cualquier cambio relevante será comunicado a través de la plataforma.',
          },
        ].map((section) => (
          <div
            key={section.title}
            style={{
              background: 'rgba(20,32,40,0.92)',
              border: '1px solid rgba(255,255,255,0.08)',
              borderRadius: '14px',
              padding: '24px',
              marginBottom: '12px',
            }}
          >
            <h2 style={{ margin: '0 0 12px', fontSize: '16px', color: '#ffd27a', fontWeight: 600 }}>{section.title}</h2>
            <p style={{ margin: 0, fontSize: '14px', lineHeight: '1.8', color: '#d4e8e0', whiteSpace: 'pre-line' }}>{section.body}</p>
          </div>
        ))}
      </main>
    </div>
  )
}
