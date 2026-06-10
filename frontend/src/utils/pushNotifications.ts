import { api } from '../api'
import { firebaseMessagingPromise } from '../firebase'
import { getToken, deleteToken } from 'firebase/messaging'

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

export function isPushSupported(): boolean {
  return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window
}

export async function getPushState() {
  const { data } = await api.get<PushSubscriptionRecord>('/push/subscriptions')
  return data
}

export async function registerPushSubscription(): Promise<void> {
  const messaging = await firebaseMessagingPromise
  const vapidPublicKey = import.meta.env.VITE_FIREBASE_VAPID_KEY?.trim()

  if (!messaging || !vapidPublicKey || !isPushSupported()) {
    throw new Error('Firebase Messaging no está disponible en este navegador o no está configurado.')
  }

  const permission = await Notification.requestPermission()
  if (permission !== 'granted') {
    throw new Error('El usuario no autorizo las notificaciones.')
  }

  const serviceWorkerRegistration = await navigator.serviceWorker.register('/firebase-messaging-sw.js', { scope: '/' })
  const token = await getToken(messaging, {
    vapidKey: vapidPublicKey,
    serviceWorkerRegistration,
  })

  if (!token) {
    throw new Error('No fue posible obtener el token de Firebase Messaging.')
  }

  await api.post('/push/fcm-token', {
    token,
    device_name: navigator.platform || navigator.userAgent,
    platform: 'web',
  })
}

export async function unregisterPushSubscription(): Promise<void> {
  if (!('serviceWorker' in navigator)) return

  const messaging = await firebaseMessagingPromise
  const registration = await navigator.serviceWorker.getRegistration()

  if (messaging && registration) {
    const token = await getToken(messaging, {
      vapidKey: import.meta.env.VITE_FIREBASE_VAPID_KEY?.trim(),
      serviceWorkerRegistration: registration,
    }).catch(() => null)

    if (token) {
      await api.delete('/push/fcm-token', { data: { token } })
      await deleteToken(messaging).catch(() => undefined)
    }
  }
}
