import { apiRequest } from "./api.js";
import { setAuthSession } from "./auth.js";
import { getLanguage, setLanguage } from "./lang.js";
import { t } from "./i18n/index.js";

import {
    createStatusUI,
    clearErrors,
    showFieldErrors,
    formToObject,
} from "./ui.js";

const form = document.getElementById("loginForm");
const submitBtn = document.getElementById("submitBtn");

const statusBox = document.getElementById("statusBox");
const debugBox = document.getElementById("debugBox");
const statusPill = document.getElementById("statusPill");
const statusMeta = document.getElementById("statusMeta");

const langSelect = document.getElementById("langSelect");

// Translatable elements
const badgeText = document.getElementById("badgeText");
const pageTitle = document.getElementById("pageTitle");
const pageSubtitle = document.getElementById("pageSubtitle");
const statusTitle = document.getElementById("statusTitle");

const lblLoginEmail = document.getElementById("lblLoginEmail");
const lblLoginPassword = document.getElementById("lblLoginPassword");

const toRegisterLink = document.getElementById("toRegisterLink");

const statusUI = createStatusUI({
    statusBox,
    statusPill,
    statusMeta,
    debugBox,
});

function currentLang() {
    return getLanguage();
}

function applyTranslations(lang) {
    badgeText.textContent = t(lang, "badge");
    pageTitle.textContent = t(lang, "login_title");
    pageSubtitle.textContent = t(lang, "login_subtitle");
    statusTitle.textContent = t(lang, "status");

    lblLoginEmail.textContent = t(lang, "login_email");
    lblLoginPassword.textContent = t(lang, "login_password");
    submitBtn.textContent = t(lang, "login_submit");

    toRegisterLink.textContent = t(lang, "register_link");

    statusUI.setStatus(t(lang, "ready"), "neutral", null);
}

function initLanguageSelector() {
    const lang = currentLang();
    langSelect.value = lang;
    setLanguage(lang);
    applyTranslations(lang);

    langSelect.addEventListener("change", () => {
        const next = langSelect.value;
        setLanguage(next);
        applyTranslations(next);
        statusUI.setStatus(t(next, "lang_set"), "neutral", null);
    });
}

initLanguageSelector();
statusUI.setRequestMeta("POST", "/api/login");

form.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearErrors(form);

    submitBtn.disabled = true;
    statusUI.setRequestMeta("POST", "/api/login");
    statusUI.setStatus(t(currentLang(), "login_logging_in"), "neutral", null);

    const payload = formToObject(form);

    try {
        const res = await apiRequest("/login", {
            method: "POST",
            body: JSON.stringify(payload),
        });

        statusUI.setDebug(res);

        // Adjust depending on your backend response shape:
        // expected: { result: { token, token_type, user, roles } } or direct token
        const result = res?.result ?? res;

        if (!result?.token) {
            statusUI.setStatus(
                "Logged in, but token not found in response.",
                "bad",
                200
            );
            return;
        }

        setAuthSession({
            token: result.token,
            user: result.user,
            roles: result.roles,
            token_type: result.token_type,
        });

        statusUI.setStatus(t(currentLang(), "login_success"), "ok", 200);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });

        const code = err.status ?? 0;

        if (code === 422) {
            statusUI.setStatus(
                t(currentLang(), "validation_failed"),
                "bad",
                422
            );
            const errors = err?.data?.result?.errors;
            showFieldErrors(errors);
        } else {
            statusUI.setStatus(
                err.message || "Something went wrong.",
                "bad",
                code
            );
        }
    } finally {
        submitBtn.disabled = false;
    }
});
