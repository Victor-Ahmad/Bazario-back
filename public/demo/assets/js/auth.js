const TOKEN_KEY = "demo_token";
const USER_KEY = "demo_user";
const CONNECT_STATUS_KEY = "demo_connect_status";

function emitAuthChanged() {
    window.dispatchEvent(new CustomEvent("demo:auth-changed"));
}

export function setAuthSession({ token, user, roles, token_type }) {
    sessionStorage.setItem(TOKEN_KEY, token);
    sessionStorage.setItem(
        USER_KEY,
        JSON.stringify({ user, roles, token_type })
    );
    sessionStorage.removeItem(CONNECT_STATUS_KEY);
    emitAuthChanged();
}

export function getToken() {
    return sessionStorage.getItem(TOKEN_KEY);
}

export function getAuthSession() {
    const raw = sessionStorage.getItem(USER_KEY);
    return raw ? JSON.parse(raw) : null;
}

export function clearAuthSession() {
    sessionStorage.removeItem(TOKEN_KEY);
    sessionStorage.removeItem(USER_KEY);
    sessionStorage.removeItem(CONNECT_STATUS_KEY);
    emitAuthChanged();
}

export function getCachedConnectStatus() {
    const raw = sessionStorage.getItem(CONNECT_STATUS_KEY);
    return raw ? JSON.parse(raw) : null;
}

export function setCachedConnectStatus(payload) {
    sessionStorage.setItem(CONNECT_STATUS_KEY, JSON.stringify(payload));
}

export function clearCachedConnectStatus() {
    sessionStorage.removeItem(CONNECT_STATUS_KEY);
}
