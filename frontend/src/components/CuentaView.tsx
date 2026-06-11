import { useEffect, useState } from 'react'
import type { ChangeEvent, FormEvent } from 'react'
import type { Branch, User } from '../types'
import { optimizeAvatarFile } from '../utils/avatarUpload'
import { isPushSupported, registerPushSubscription, unregisterPushSubscription, getPushState } from '../utils/pushNotifications'

type CuentaSection = 'perfil' | 'terminos'

function documentTypeLabel(documentType: User['document_type']) {
  if (documentType === 'cedula') return 'Cedula'
  if (documentType === 'residente') return 'Residente'
  return 'Pasaporte'
}

function formatBirthdate(dateValue: string | null | undefined) {
  if (!dateValue) return 'No registrada'
  const date = new Date(dateValue)
  if (Number.isNaN(date.getTime())) return dateValue
  return date.toLocaleDateString('es-PA', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
    timeZone: 'America/Panama',
  })
}

export function CuentaView({
  user,
  saving,
  branches,
  termsText,
  section,
  onSectionChange,
  onSave,
}: {
  user: User
  saving: boolean
  branches: Branch[]
  termsText: string
  section: CuentaSection
  onSectionChange: (section: CuentaSection) => void
  onSave: (payload: {
    email: string
    phone: string
    avatarFile: File | null
    branchId: string
    currentPassword: string
    newPassword: string
    newPasswordConfirmation: string
  }) => Promise<void>
}) {
  const [email, setEmail] = useState(user.email)
  const [phone, setPhone] = useState(user.phone ?? '')
  const [branchId, setBranchId] = useState(String(user.branch?.id ?? ''))
  const [avatarFile, setAvatarFile] = useState<File | null>(null)
  const [avatarPreview, setAvatarPreview] = useState<string | null>(user.avatar_url ?? null)
  const [currentPassword, setCurrentPassword] = useState('')
  const [newPassword, setNewPassword] = useState('')
  const [newPasswordConfirmation, setNewPasswordConfirmation] = useState('')
  const [pushStatus, setPushStatus] = useState<'idle' | 'checking' | 'enabled' | 'disabled' | 'error' | 'blocked'>('idle')
  const [pushMessage, setPushMessage] = useState<string | null>(null)

  useEffect(() => {
    setEmail(user.email)
    setPhone(user.phone ?? '')
    setBranchId(String(user.branch?.id ?? ''))
    setAvatarFile(null)
    setAvatarPreview(user.avatar_url ?? null)
    setCurrentPassword('')
    setNewPassword('')
    setNewPasswordConfirmation('')
  }, [user.email, user.phone, user.avatar_url, user.branch])

  useEffect(() => {
    let alive = true

    async function loadPushState() {
      if (!isPushSupported()) {
        if (alive) setPushStatus('disabled')
        return
      }

      setPushStatus('checking')

      try {
        const state = await getPushState()
        if (!alive) return
        setPushStatus(state.enabled ? 'enabled' : 'disabled')
      } catch {
        if (alive) setPushStatus('error')
      }
    }

    void loadPushState()

    return () => {
      alive = false
    }
  }, [])

  useEffect(() => {
    if (!avatarFile) return
    const objectUrl = URL.createObjectURL(avatarFile)
    setAvatarPreview(objectUrl)
    return () => URL.revokeObjectURL(objectUrl)
  }, [avatarFile])

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    await onSave({ email, phone, avatarFile, branchId, currentPassword, newPassword, newPasswordConfirmation })
  }

  async function handleAvatarChange(event: ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0] ?? null
    if (!file) {
      setAvatarFile(null)
      return
    }

    try {
      setAvatarFile(await optimizeAvatarFile(file, 'avatar-profile'))
    } catch {
      setAvatarFile(file)
    }
  }

  async function handleEnablePush() {
    setPushMessage(null)

    if (!isPushSupported()) {
      setPushStatus('error')
      setPushMessage('Tu navegador no soporta notificaciones push.')
      return
    }

    if (Notification.permission === 'denied') {
      setPushStatus('blocked')
      setPushMessage(null)
      return
    }

    try {
      await registerPushSubscription()
      setPushStatus('enabled')
      setPushMessage('Notificaciones activadas.')
    } catch (error) {
      setPushStatus('error')
      setPushMessage(error instanceof Error ? error.message : 'No fue posible activar las notificaciones.')
    }
  }

  async function handleDisablePush() {
    setPushMessage(null)

    try {
      await unregisterPushSubscription()
      setPushStatus('disabled')
      setPushMessage('Notificaciones desactivadas.')
    } catch (error) {
      setPushStatus('error')
      setPushMessage(error instanceof Error ? error.message : 'No fue posible desactivar las notificaciones.')
    }
  }

  return (
    <section className="cuenta-view client-shell--cancha-reference">
      <header className="cuenta-hero cuenta-hero--cancha">
        <div className="cuenta-hero-copy">
          <span className="cuenta-kicker">SUPER CARNES</span>
          <h1>{section === 'terminos' ? 'Terminos y condiciones' : 'Mi cuenta'}</h1>
          <p>
            {section === 'terminos'
              ? 'Consulta el documento legal completo de la promocion dentro de tu cuenta.'
              : 'Gestiona tus datos, tu foto y tu contrasena en una vista mas limpia y sobria.'}
          </p>
        </div>
        {section === 'perfil' ? (
          <div className="cuenta-hero-summary">
            <div>
              <span>Correo</span>
              <strong>{user.email}</strong>
            </div>
            <div>
              <span>Telefono</span>
              <strong>{user.phone ?? 'No registrado'}</strong>
            </div>
            <div>
              <span>Sucursal</span>
              <strong>{user.branch?.name ?? 'Sin preferencia'}</strong>
            </div>
          </div>
        ) : null}
      </header>

      <nav className="cuenta-section-tabs" aria-label="Secciones de mi cuenta">
        <button className={section === 'perfil' ? 'cuenta-section-tab active' : 'cuenta-section-tab'} type="button" onClick={() => onSectionChange('perfil')}>
          Perfil
        </button>
        <button className={section === 'terminos' ? 'cuenta-section-tab active' : 'cuenta-section-tab'} type="button" onClick={() => onSectionChange('terminos')}>
          Terminos y condiciones
        </button>
      </nav>

      {section === 'terminos' ? (
        <section className="cuenta-panel cuenta-panel-terms">
          <div className="cuenta-panel-head">
            <span className="cuenta-kicker">Documento legal</span>
            <h2>Terminos y condiciones</h2>
          </div>
          <pre className="cuenta-terms-text">{termsText}</pre>
        </section>
      ) : (
        <div className="cuenta-layout cuenta-layout--flat">
          <section className="cuenta-panel cuenta-panel-profile">
            <div className="cuenta-profile-top cuenta-profile-top--flat">
              <div className="cuenta-avatar-block">
                <div className="cuenta-avatar-frame">
                  {avatarPreview ? (
                    <img alt={`Avatar de ${user.full_name}`} className="cuenta-avatar-image" src={avatarPreview} />
                  ) : (
                    <span>{user.full_name.slice(0, 1).toUpperCase()}</span>
                  )}
                </div>
                <div className="cuenta-avatar-copy">
                  <span className="cuenta-kicker">Participante</span>
                  <strong>{user.full_name}</strong>
                  <span>{user.branch?.name ?? 'Cuenta participante'}</span>
                </div>
              </div>

              <dl className="cuenta-meta-list">
                <div>
                  <dt>Nombre</dt>
                  <dd>{user.full_name}</dd>
                </div>
                <div>
                  <dt>Fecha de nacimiento</dt>
                  <dd>{formatBirthdate(user.birthdate)}</dd>
                </div>
                <div>
                  <dt>Documento</dt>
                  <dd>{user.cedula}</dd>
                </div>
                <div>
                  <dt>Tipo</dt>
                  <dd>{documentTypeLabel(user.document_type)}</dd>
                </div>
              </dl>
            </div>

            <form className="cuenta-form cuenta-form--inline" onSubmit={handleSubmit}>
              <div className="cuenta-form-intro">
                <span className="cuenta-kicker">Perfil</span>
                <h2>Datos de contacto</h2>
                <p>Actualiza correo, telefono, sucursal y fotografia desde un formulario simple.</p>
              </div>

              <div className="cuenta-form-grid cuenta-form-grid--flat">
                <label className="cuenta-field">
                  <span>Correo electronico</span>
                  <input type="email" value={email} onChange={(event) => setEmail(event.target.value)} />
                </label>

                <label className="cuenta-field">
                  <span>Numero de telefono</span>
                  <input type="text" value={phone} onChange={(event) => setPhone(event.target.value)} />
                </label>

                <label className="cuenta-field">
                  <span>Sucursal de preferencia</span>
                  <select value={branchId} onChange={(event) => setBranchId(event.target.value)} className="cuenta-select">
                    <option value="">- Selecciona una sucursal -</option>
                    {branches.map((branch) => (
                      <option key={branch.id} value={String(branch.id)}>
                        {branch.name}
                      </option>
                    ))}
                  </select>
                </label>

                <label className="cuenta-field cuenta-field-upload">
                  <span>Fotografia</span>
                  <input accept="image/png,image/jpeg,image/webp" type="file" onChange={handleAvatarChange} />
                </label>
              </div>

              <div className="cuenta-form-actions">
                <button className="cuenta-save-button" disabled={saving} type="submit">
                  {saving ? 'Guardando...' : 'Guardar cambios'}
                </button>
              </div>
            </form>
          </section>

          <div className="cuenta-bottom-grid">
            <section className="cuenta-panel cuenta-panel-password">
              <div className="cuenta-panel-head">
                <span className="cuenta-kicker">Seguridad</span>
                <h2>Cambiar contrasena</h2>
                <p>Completa estos campos solamente si quieres actualizar tu clave de acceso.</p>
              </div>

              <form className="cuenta-form cuenta-form--compact" onSubmit={handleSubmit}>
                <div className="cuenta-form-grid cuenta-form-grid--flat">
                  <label className="cuenta-field">
                    <span>Contrasena actual</span>
                    <input type="password" value={currentPassword} onChange={(event) => setCurrentPassword(event.target.value)} />
                  </label>

                  <label className="cuenta-field">
                    <span>Nueva contrasena</span>
                    <input type="password" value={newPassword} onChange={(event) => setNewPassword(event.target.value)} />
                  </label>

                  <label className="cuenta-field cuenta-field-wide">
                    <span>Confirmar nueva contrasena</span>
                    <input type="password" value={newPasswordConfirmation} onChange={(event) => setNewPasswordConfirmation(event.target.value)} />
                  </label>
                </div>

                <div className="cuenta-form-actions">
                  <button className="cuenta-save-button cuenta-save-button--secondary" disabled={saving} type="submit">
                    {saving ? 'Guardando...' : 'Guardar contrasena'}
                  </button>
                </div>
              </form>
            </section>

            <section className="cuenta-panel cuenta-panel-push">
              <div className="cuenta-panel-head">
                <span className="cuenta-kicker">Avisos</span>
                <h2>Notificaciones push web</h2>
              </div>
              <p className="cuenta-push-copy">Recibe promociones, recordatorios y anuncios del concurso directamente en tu navegador.</p>
              <div className="cuenta-push-actions">
                <button className="cuenta-save-button" type="button" onClick={() => void handleEnablePush()} disabled={pushStatus === 'checking'}>
                  Activar notificaciones
                </button>
                <button className="cuenta-section-tab" type="button" onClick={() => void handleDisablePush()} disabled={pushStatus === 'checking'}>
                  Desactivar
                </button>
              </div>
              <div className="cuenta-push-status">
                <span>Estado:</span>
                <strong>{pushStatus === 'enabled' ? 'Activas' : pushStatus === 'checking' ? 'Verificando...' : pushStatus === 'error' ? 'Error' : pushStatus === 'blocked' ? 'Bloqueadas' : 'Inactivas'}</strong>
              </div>
              {pushStatus === 'blocked' && (
                <div className="cuenta-push-blocked">
                  <p>Tu navegador tiene las notificaciones <strong>bloqueadas</strong> para este sitio. Para activarlas:</p>
                  <ol>
                    <li>Haz clic en el <strong>candado 🔒</strong> en la barra de direccion del navegador</li>
                    <li>Busca <strong>Notificaciones</strong> y cambia a <strong>Permitir</strong></li>
                    <li>Recarga la pagina y vuelve a intentarlo</li>
                  </ol>
                </div>
              )}
              {pushMessage ? <p className="cuenta-push-message">{pushMessage}</p> : null}
            </section>
          </div>
        </div>
      )}
    </section>
  )
}
