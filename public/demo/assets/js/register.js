import { apiRequest } from "./api.js";
import { setAuthSession } from "./auth.js";
import { getLanguage, setLanguage } from "./lang.js";
import { t } from "./i18n/index.js";

import {
    createStatusUI,
    clearErrors,
    setFieldError,
    showFieldErrors,
    markInvalid,
    markValidAllFilled,
    formToObject,
} from "./ui.js";

const form = document.getElementById("registerForm");
const submitBtn = document.getElementById("submitBtn");

const statusBox = document.getElementById("statusBox");
const debugBox = document.getElementById("debugBox");
const statusPill = document.getElementById("statusPill");
const statusMeta = document.getElementById("statusMeta");

const langSelect = document.getElementById("langSelect");

const toLoginLink = document.getElementById("toLoginLink");

// Translatable elements
const badgeText = document.getElementById("badgeText");
const pageTitle = document.getElementById("pageTitle");
const pageSubtitle = document.getElementById("pageSubtitle");
const statusTitle = document.getElementById("statusTitle");

// Labels
const lblName = document.getElementById("lblName");
const lblAge = document.getElementById("lblAge");
const lblEmail = document.getElementById("lblEmail");
const lblPhone = document.getElementById("lblPhone");
const lblPassword = document.getElementById("lblPassword");
const lblPasswordConfirm = document.getElementById("lblPasswordConfirm");

// Status controller (reusable)
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
    pageTitle.textContent = t(lang, "title");
    pageSubtitle.textContent = t(lang, "subtitle");
    statusTitle.textContent = t(lang, "status");

    lblName.textContent = t(lang, "name");
    lblAge.textContent = t(lang, "age");
    lblEmail.textContent = t(lang, "email");
    lblPhone.textContent = t(lang, "phone");
    lblPassword.textContent = t(lang, "password");
    lblPasswordConfirm.textContent = t(lang, "password_confirmation");

    submitBtn.textContent = t(lang, "submit");

    toLoginLink.textContent = t(lang, "login_link");

    // Keep initial text consistent
    statusUI.setStatus(t(lang, "ready"), "neutral", null);
}

function initLanguageSelector() {
    const lang = currentLang();
    langSelect.value = lang;
    setLanguage(lang); // sets html lang + dir (RTL for Arabic)
    applyTranslations(lang);

    langSelect.addEventListener("change", () => {
        const next = langSelect.value;
        setLanguage(next);
        applyTranslations(next);
        statusUI.setStatus(t(next, "lang_set"), "neutral", null);
    });
}

initLanguageSelector();

// Show request meta once
statusUI.setRequestMeta("POST", "/api/register");

function validateClient(payload) {
    let ok = true;

    if ((payload.password || "") !== (payload.password_confirmation || "")) {
        setFieldError(
            "password_confirmation",
            t(currentLang(), "password_mismatch")
        );
        markInvalid("password_confirmation");
        ok = false;
    }

    return ok;
}

function buildPayload() {
    const payload = formToObject(form);

    // normalize age
    if (payload.age !== undefined) payload.age = Number(payload.age);

    // if password_confirmation was removed by formToObject only if empty, OK.
    return payload;
}

form.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearErrors(form);

    submitBtn.disabled = true;

    statusUI.setRequestMeta("POST", "/api/register");
    statusUI.setStatus(t(currentLang(), "registering"), "neutral", null);

    const payload = buildPayload();

    if (!validateClient(payload)) {
        statusUI.setStatus(t(currentLang(), "fix_fields"), "bad", 0);
        submitBtn.disabled = false;
        return;
    }

    try {
        const res = await apiRequest("/register", {
            method: "POST",
            body: JSON.stringify(payload),
        });

        statusUI.setDebug(res);

        const result = res?.result ?? res;
        if (!result?.token) {
            statusUI.setStatus(
                "Registered, but token not found in response.",
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

        statusUI.setStatus(t(currentLang(), "success"), "ok", 200);
        markValidAllFilled(form);
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
            const normalized = showFieldErrors(errors);

            // if your backend sends custom "contact" error
            if (normalized?.contact) {
                const msg = Array.isArray(normalized.contact)
                    ? normalized.contact[0]
                    : String(normalized.contact);
                statusUI.setStatus(msg, "bad", 422);
            }
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

// Live password match feedback (optional)
const pass = document.getElementById("password");
const pass2 = document.getElementById("password_confirmation");

function liveCheck() {
    if (!pass || !pass2) return;

    const a = pass.value;
    const b = pass2.value;

    if (!b) {
        setFieldError("password_confirmation", "");
        return;
    }

    if (a !== b) {
        setFieldError(
            "password_confirmation",
            t(currentLang(), "password_mismatch")
        );
        markInvalid("password_confirmation");
    } else {
        setFieldError("password_confirmation", "");
    }
}

pass?.addEventListener("input", liveCheck);
pass2?.addEventListener("input", liveCheck);
