import { PublicBrandHeader } from './PublicBrandHeader'

export function PrivacidadPage() {
  return (
    <div className="min-h-screen bg-background text-on-background" style={{ fontFamily: 'Segoe UI, Arial, sans-serif' }}>
      <PublicBrandHeader />

      <main style={{ maxWidth: '800px', margin: '0 auto', padding: '40px 24px 80px' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '32px' }}>
          <span className="material-symbols-outlined" style={{ fontSize: '36px', color: '#007aff' }}>shield</span>
          <div>
            <h1 style={{ margin: 0, fontSize: '28px', fontWeight: 700 }}>Politica de Privacidad</h1>
            <p style={{ margin: '4px 0 0', color: '#9fb4b2', fontSize: '14px' }}>Tratamiento de datos personales - Ley 81 de 2019</p>
          </div>
        </div>

        {[
          {
            title: '1. Responsable del tratamiento',
            body: 'Super Carnes, empresa constituida y operando en la Republica de Panama, es responsable del tratamiento de los datos personales recopilados a traves de la plataforma "PRONOSTICA EL MUNDIAL Y GANA".',
          },
          {
            title: '2. Datos que recopilamos',
            body: 'Recopilamos unicamente los datos necesarios para la administracion de la promocion:\n• Nombre completo\n• Numero de cedula de identidad personal o pasaporte\n• Correo electronico\n• Numero de telefono\n• Sucursal de preferencia\n• Historial de facturas registradas (CUFE, monto, fecha)\n• Pronosticos deportivos realizados en la plataforma',
          },
          {
            title: '3. Finalidad del tratamiento',
            body: 'Los datos personales se utilizan exclusivamente para:\n• Administrar, desarrollar y ejecutar la promocion "PRONOSTICA EL MUNDIAL Y GANA"\n• Validar la identidad del participante y su elegibilidad\n• Verificar las facturas registradas ante la DGI\n• Contactar a los ganadores y gestionar la entrega de premios\n• Cumplir obligaciones legales y regulatorias aplicables en Panama',
          },
          {
            title: '4. Base legal del tratamiento',
            body: 'El tratamiento de sus datos se basa en el consentimiento expreso otorgado al momento del registro y en el cumplimiento de las obligaciones derivadas de la participacion en la promocion, de conformidad con la Ley 81 de 2019 sobre Proteccion de Datos Personales de la Republica de Panama.',
          },
          {
            title: '5. Comparticion de datos',
            body: 'Super Carnes no vende, alquila ni comercializa sus datos personales a terceros. Los datos unicamente podran ser compartidos con:\n• Autoridades publicas panameñas cuando sea requerido por ley\n• Proveedores de servicios tecnologicos estrictamente necesarios para la operacion de la plataforma, bajo acuerdos de confidencialidad\n• La Direccion General de Ingresos (DGI) para la verificacion de facturas',
          },
          {
            title: '6. Plazo de conservacion',
            body: 'Los datos personales se conservaran durante la vigencia de la promocion y por un periodo adicional de hasta cinco (5) años, para atender posibles reclamaciones legales o requerimientos de autoridades competentes. Transcurrido dicho plazo, los datos seran eliminados de manera segura.',
          },
          {
            title: '7. Derechos del titular',
            body: 'De conformidad con la Ley 81 de 2019, usted tiene derecho a:\n• Acceder a sus datos personales\n• Rectificar datos inexactos o incompletos\n• Cancelar o suprimir sus datos cuando ya no sean necesarios\n• Oponerse al tratamiento en los casos previstos por la ley\n• Revocar el consentimiento otorgado\n\nPara ejercer estos derechos, puede contactarnos en la direccion indicada en la seccion de Contacto de esta plataforma.',
          },
          {
            title: '8. Seguridad de los datos',
            body: 'Super Carnes implementa medidas tecnicas y organizativas adecuadas para proteger sus datos personales contra perdida, acceso no autorizado, divulgacion o destruccion, incluyendo cifrado de comunicaciones (HTTPS), control de acceso restringido y auditoria de operaciones sobre datos sensibles.',
          },
          {
            title: '9. Modificaciones',
            body: 'Super Carnes se reserva el derecho de actualizar esta politica en cualquier momento. Cualquier cambio relevante sera comunicado a traves de la plataforma.',
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
