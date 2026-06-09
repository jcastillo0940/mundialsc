import { api } from '../api'

export interface PushSubscriptionPayload {
  endpoint: string
  keys: {
    p256dh: string
    auth: string
  }
  content_encoding?: string
}

export interface PushSubscriptionRecord {
  enabled: boolean
  push_supported: boolean
  vapid_public_key: string | null
  subscriptions: Array<{
    id: number
    endpoint: string
    is_enabled: boolean
    last_seen_at?: string | null
    created_at?: string | null
    updated_at?: string | null
  }>
}

function base64UrlToUint8Array(base64UrlString: string): Uint8Array {
  const padding = '='.repeat((4 - (base64UrlString.length % 4)) % 4)
  const base64 = (base64UrlString + padding).replace(/-/g, '+').replace(/_/g, '/')
  const rawData = window.atob(base64)
  const outputArray = new Uint8Array(rawData.length)

  for (let index = 0; index < rawData.length; ++index) {
    outputArray[index] = rawData.charCodeAt(index)
  }

  return outputArray
}

export function isPushSupported(): boolean {
  return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window
}

export async function getPushState() {
  const { data } = await api.get<PushSubscriptionRecord>('/push/subscriptions')
  return data
}

export async function registerPushSubscription(): Promise<void> {
  const pushState = await getPushState()
  const vapidPublicKey = pushState.vapid_public_key?.trim()

  if (!vapidPublicKey || !isPushSupported()) {
    throw new Error('Push no disponible en este navegador o no configurado en el servidor.')
  }

  const permission = await Notification.requestPermission()
  if (permission !== 'granted') {
    throw new Error('El usuario no autorizo las notificaciones.')
  }

  const serviceWorkerRegistration = await navigator.serviceWorker.register('/sw-push.js', { scope: '/' })
  const existingSubscription = await serviceWorkerRegistration.pushManager.getSubscription()

  if (existingSubscription) {
    await api.post('/push/subscriptions', existingSubscription.toJSON() as PushSubscriptionPayload)
    return
  }

  const subscription = await serviceWorkerRegistration.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: base64UrlToUint8Array(vapidPublicKey) as unknown as BufferSource,
  })

  await api.post('/push/subscriptions', subscription.toJSON() as PushSubscriptionPayload)
}

export async function unregisterPushSubscription(): Promise<void> {
  if (!('serviceWorker' in navigator)) return

  const registration = await navigator.serviceWorker.getRegistration()
  const subscription = await registration?.pushManager.getSubscription()

  if (subscription) {
    await api.delete('/push/subscriptions', {
      data: {
        endpoint: subscription.endpoint,
      },
    })
    await subscription.unsubscribe()
  }
}
