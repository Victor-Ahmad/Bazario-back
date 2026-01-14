import { apiRequest } from "./api.js";
import { getLanguage, setLanguage } from "./lang.js";
import { t } from "./i18n/index.js";
import {
    createStatusUI,
    clearErrors,
    showFieldErrors,
    formToObject,
} from "./ui.js";

const form = document.getElementById("verifyForm");
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
const lblOtp = document.getElementById("lblOtp");

function applyTranslations(lang) {
    badgeText.textContent = t(lang, "badge");
    pageTitle.textContent = t(lang, "verify_title");
    pageSubtitle.textContent = t(lang, "verify_subtitle");
    statusTitle.textContent = t(lang, "status");

    lblEmail.textContent = t(lang, "verify_email");
    lblOtp.textContent = t(lang, "verify_otp");
    submitBtn.textContent = t(lang, "verify_submit");

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
statusUI.setRequestMeta("POST", "/api/password/verify-otp");

// Prefill email from step 1
const emailInput = document.getElementById("email");
const savedEmail = sessionStorage.getItem("reset_email");
if (savedEmail) emailInput.value = savedEmail;

form.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearErrors(form);

    submitBtn.disabled = true;
    statusUI.setStatus(t(getLanguage(), "verify_verifying"), "neutral", null);

    const payload = formToObject(form);

    try {
        const res = await apiRequest("/password/verify-otp", {
            method: "POST",
            body: JSON.stringify(payload),
        });

        statusUI.setDebug(res);

        const result = res?.result ?? res;
        const token = result?.token;

        if (!token) {
            statusUI.setStatus("Token not found in response.", "bad", 200);
            return;
        }

        sessionStorage.setItem("reset_email", payload.email);
        sessionStorage.setItem("reset_token", token);

        statusUI.setStatus(t(getLanguage(), "verify_success"), "ok", 200);

        window.location.href = "/demo/reset-password.html";
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });

        const code = err.status ?? 0;

        // invalid_otp likely returns 400
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
