import { initializeApp, getApp, getApps } from 'firebase/app'
import { getAnalytics, isSupported as isAnalyticsSupported, type Analytics } from 'firebase/analytics'
import { getMessaging, isSupported as isMessagingSupported, type Messaging } from 'firebase/messaging'

const firebaseConfig = {
  apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
  authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
  projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
  storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
  messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
  appId: import.meta.env.VITE_FIREBASE_APP_ID,
  measurementId: import.meta.env.VITE_FIREBASE_MEASUREMENT_ID,
}

export const firebaseApp = getApps().length ? getApp() : initializeApp(firebaseConfig)

export const firebaseAnalyticsPromise: Promise<Analytics | null> = (async () => {
  if (!(await isAnalyticsSupported())) {
    return null
  }

  return getAnalytics(firebaseApp)
})()

export const firebaseMessagingPromise: Promise<Messaging | null> = (async () => {
  if (!(await isMessagingSupported())) {
    return null
  }

  return getMessaging(firebaseApp)
})()
