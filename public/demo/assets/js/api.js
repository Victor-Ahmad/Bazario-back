import { config } from "./config.js";
import { getToken } from "./auth.js";
import { getLanguage } from "./lang.js";

function buildUrl(path) {
    const clean = path.startsWith("/") ? path : `/${path}`;
    return `${config.apiBase}${clean}`;
}

export async function apiRequest(path, options = {}) {
    const url = buildUrl(path);

    const headers = new Headers(options.headers || {});
    headers.set("Accept", "application/json");
    headers.set("Accept-Language", getLanguage());

    if (options.body && !(options.body instanceof FormData)) {
        headers.set("Content-Type", "application/json");
    }

    // Bearer token
    const token = getToken();
    if (token) headers.set("Authorization", `Bearer ${token}`);

    const res = await fetch(url, {
        ...options,
        headers,
    });

    let data = null;
    const text = await res.text();
    try {
        data = text ? JSON.parse(text) : null;
    } catch {
        data = { raw: text };
    }

    if (!res.ok) {
        const err = new Error(data?.message || `Request failed: ${res.status}`);
        err.status = res.status;
        err.data = data;
        throw err;
    }

    return data;
}
