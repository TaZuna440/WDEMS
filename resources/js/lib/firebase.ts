import { initializeApp, type FirebaseApp } from 'firebase/app';
import { getAuth, type Auth } from 'firebase/auth';

let cachedApp: FirebaseApp | null = null;
let cachedAuth: Auth | null = null;

/**
 * Lazily initialize Firebase and return the Auth instance.
 *
 * Initialization is cached so React strict mode's double-mount does not
 * create two Firebase apps (which would throw "already exists"). The
 * config comes from VITE_ env vars — the public web app identifier,
 * not a secret.
 */
export function getFirebaseAuth(): Auth {
    if (cachedAuth !== null) {
        return cachedAuth;
    }

    cachedApp = initializeApp({
        apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
        authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
        projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
        appId: import.meta.env.VITE_FIREBASE_APP_ID,
    });

    cachedAuth = getAuth(cachedApp);

    return cachedAuth;
}
