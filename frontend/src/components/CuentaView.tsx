import { useEffect, useState } from 'react'
import type { ChangeEvent, FormEvent } from 'react'
import type { User } from '../types'

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
  onSave,
}: {
  user: User
  saving: boolean
  onSave: (payload: { email: string; phone: string; avatarFile: File | null }) => Promise<void>
}) {
  const [email, setEmail] = useState(user.email)
  const [phone, setPhone] = useState(user.phone ?? '')
  const [avatarFile, setAvatarFile] = useState<File | null>(null)
  const [avatarPreview, setAvatarPreview] = useState<string | null>(user.avatar_url ?? null)

  useEffect(() => {
    setEmail(user.email)
    setPhone(user.phone ?? '')
    setAvatarFile(null)
    setAvatarPreview(user.avatar_url ?? null)
  }, [user.email, user.phone, user.avatar_url])

  useEffect(() => {
    if (!avatarFile) return

    const objectUrl = URL.createObjectURL(avatarFile)
    setAvatarPreview(objectUrl)

    return () => URL.revokeObjectURL(objectUrl)
  }, [avatarFile])

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    await onSave({ email, phone, avatarFile })
  }

  function handleAvatarChange(event: ChangeEvent<HTMLInputElement>) {
    setAvatarFile(event.target.files?.[0] ?? null)
  }

  return (
    <section className="cuenta-view">
      <header className="cuenta-hero">
        <div className="cuenta-hero-copy">
          <span className="cuenta-kicker">Mi cuenta</span>
          <h1>Datos personales</h1>
          <p>Administra tu información de cuenta y mantén tus datos al día.</p>
        </div>
      </header>

      <div className="cuenta-layout">
        <section className="cuenta-panel cuenta-panel-profile">
          <div className="cuenta-avatar-block">
            <div className="cuenta-avatar-frame">
              {avatarPreview ? <img alt={`Avatar de ${user.full_name}`} className="cuenta-avatar-image" src={avatarPreview} /> : <span>{user.full_name.slice(0, 1).toUpperCase()}</span>}
            </div>
            <div className="cuenta-avatar-copy">
              <strong>{user.full_name}</strong>
              <span>{user.branch?.name ?? 'Cuenta participante'}</span>
            </div>
          </div>

          <div className="cuenta-readonly-grid">
            <article>
              <span>Nombre</span>
              <strong>{user.full_name}</strong>
            </article>
            <article>
              <span>Fecha de nacimiento</span>
              <strong>{formatBirthdate(user.birthdate)}</strong>
            </article>
            <article>
              <span>{documentTypeLabel(user.document_type)}</span>
              <strong>{user.cedula}</strong>
            </article>
            <article>
              <span>Documento</span>
              <strong>{documentTypeLabel(user.document_type)}</strong>
            </article>
          </div>
        </section>

        <section className="cuenta-panel">
          <div className="cuenta-panel-head">
            <span className="cuenta-kicker">Campos editables</span>
            <h2>Contacto y foto</h2>
          </div>

          <form className="cuenta-form" onSubmit={handleSubmit}>
            <label className="cuenta-field">
              <span>Correo electronico</span>
              <input type="email" value={email} onChange={(event) => setEmail(event.target.value)} />
            </label>

            <label className="cuenta-field">
              <span>Numero de telefono</span>
              <input type="text" value={phone} onChange={(event) => setPhone(event.target.value)} />
            </label>

            <label className="cuenta-field">
              <span>Fotografia</span>
              <input accept="image/png,image/jpeg,image/webp" type="file" onChange={handleAvatarChange} />
            </label>

            <div className="cuenta-form-actions">
              <button className="cuenta-save-button" disabled={saving} type="submit">
                {saving ? 'Guardando...' : 'Guardar cambios'}
              </button>
            </div>
          </form>
        </section>
      </div>
    </section>
  )
}
