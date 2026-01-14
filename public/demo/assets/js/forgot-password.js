import { apiRequest } from "./api.js";
import { getLanguage, setLanguage } from "./lang.js";
import { t } from "./i18n/index.js";
import {
    createStatusUI,
    clearErrors,
    showFieldErrors,
    formToObject,
} from "./ui.js";

const form = document.getElementById("forgotForm");
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
const toLoginLink = document.getElementById("toLoginLink");

function applyTranslations(lang) {
    badgeText.textContent = t(lang, "badge");
    pageTitle.textContent = t(lang, "forgot_title");
    pageSubtitle.textContent = t(lang, "forgot_subtitle");
    statusTitle.textContent = t(lang, "status");

    lblEmail.textContent = t(lang, "forgot_email");
    submitBtn.textContent = t(lang, "forgot_submit");
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
statusUI.setRequestMeta("POST", "/api/password/forgot");

// Prefill email if it exists from earlier attempts
const emailInput = document.getElementById("email");
const savedEmail = sessionStorage.getItem("reset_email");
if (savedEmail) emailInput.value = savedEmail;

form.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearErrors(form);

    submitBtn.disabled = true;
    statusUI.setStatus(t(getLanguage(), "forgot_sending"), "neutral", null);

    const payload = formToObject(form);

    try {
        const res = await apiRequest("/password/forgot", {
            method: "POST",
            body: JSON.stringify(payload),
        });

        statusUI.setDebug(res);
        sessionStorage.setItem("reset_email", payload.email);

        statusUI.setStatus(t(getLanguage(), "forgot_sent"), "ok", 200);

        // Go to OTP page
        window.location.href = "/demo/verify-otp.html";
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
