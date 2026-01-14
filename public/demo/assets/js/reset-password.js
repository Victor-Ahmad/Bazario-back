import { apiRequest } from "./api.js";
import { getLanguage, setLanguage } from "./lang.js";
import { t } from "./i18n/index.js";
import {
    createStatusUI,
    clearErrors,
    setFieldError,
    showFieldErrors,
    formToObject,
    markInvalid,
} from "./ui.js";

const form = document.getElementById("resetForm");
const submitBtn = document.getElementById("submitBtn");

const statusUI = createStatusUI({
    statusBox: document.getElementById("statusBox"),
    statusPill: document.getElementById("statusPill"),
    statusMeta: document.getElementById("statusMeta"),
    debugBox: document.getElementById("debugBox"),
});

const langSelect = document.getElementById("langSelect");
const badgeText = document.getElementById("badgeText");
const pageTitle = document.getElementById("pageTitle");
const pageSubtitle = document.getElementById("pageSubtitle");
const statusTitle = document.getElementById("statusTitle");

const lblEmail = document.getElementById("lblEmail");
const lblPassword = document.getElementById("lblPassword");
const lblPasswordConfirm = document.getElementById("lblPasswordConfirm");
const toLoginLink = document.getElementById("toLoginLink");

function applyTranslations(lang) {
    badgeText.textContent = t(lang, "badge");
    pageTitle.textContent = t(lang, "reset_title");
    pageSubtitle.textContent = t(lang, "reset_subtitle");
    statusTitle.textContent = t(lang, "status");

    lblEmail.textContent = t(lang, "reset_email");
    lblPassword.textContent = t(lang, "reset_password");
    lblPasswordConfirm.textContent = t(lang, "reset_password_confirmation");
    submitBtn.textContent = t(lang, "reset_submit");
    toLoginLink.textContent = t(lang, "forgot_to_login");

    statusUI.setStatus(t(lang, "ready"), "neutral", null);
}

function initLang() {
    const lang = getLanguage();
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

initLang();
statusUI.setRequestMeta("POST", "/api/password/reset");

// Prefill email and ensure token exists
const emailInput = document.getElementById("email");
const savedEmail = sessionStorage.getItem("reset_email");
const savedToken = sessionStorage.getItem("reset_token");

if (savedEmail) emailInput.value = savedEmail;
if (!savedToken) {
    statusUI.setStatus(
        "Missing reset token. Please verify OTP first.",
        "bad",
        0
    );
}

function validateClient(payload) {
    let ok = true;
    if ((payload.password || "") !== (payload.password_confirmation || "")) {
        setFieldError(
            "password_confirmation",
            t(getLanguage(), "password_mismatch")
        );
        markInvalid("password_confirmation");
        ok = false;
    }
    return ok;
}

form.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearErrors(form);

    submitBtn.disabled = true;
    statusUI.setStatus(t(getLanguage(), "reset_resetting"), "neutral", null);

    const payload = formToObject(form);

    // attach token from storage
    payload.token = sessionStorage.getItem("reset_token") || "";

    if (!validateClient(payload)) {
        statusUI.setStatus(t(getLanguage(), "fix_fields"), "bad", 0);
        submitBtn.disabled = false;
        return;
    }

    try {
        const res = await apiRequest("/password/reset", {
            method: "POST",
            body: JSON.stringify(payload),
        });

        statusUI.setDebug(res);

        // clear flow data
        sessionStorage.removeItem("reset_email");
        sessionStorage.removeItem("reset_token");

        statusUI.setStatus(t(getLanguage(), "reset_success"), "ok", 200);

        // redirect to login after a short moment (optional)
        // window.location.href = "/demo/login.html";
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });

        const code = err.status ?? 0;
        if (code === 422) {
            statusUI.setStatus(
                t(getLanguage(), "validation_failed"),
                "bad",
                422
            );
            showFieldErrors(err?.data?.errors || err?.data?.result?.errors);
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
