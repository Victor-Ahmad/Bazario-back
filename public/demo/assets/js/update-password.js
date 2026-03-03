import { apiRequest } from "./api.js";
import { getLanguage, setLanguage } from "./lang.js";
import { t } from "./i18n/index.js";
import {
    createStatusUI,
    clearErrors,
    setFieldError,
    formToObject,
    markInvalid,
    showFieldErrors,
} from "./ui.js";

const form = document.getElementById("updatePasswordForm");
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

const lblOldPassword = document.getElementById("lblOldPassword");
const lblNewPassword = document.getElementById("lblNewPassword");
const lblPasswordConfirm = document.getElementById("lblPasswordConfirm");

function applyTranslations(lang) {
    badgeText.textContent = t(lang, "badge");
    pageTitle.textContent = t(lang, "update_password_title");
    pageSubtitle.textContent = t(lang, "update_password_subtitle");
    statusTitle.textContent = t(lang, "status");
    lblOldPassword.textContent = t(lang, "update_password_old");
    lblNewPassword.textContent = t(lang, "update_password_new");
    lblPasswordConfirm.textContent = t(lang, "update_password_confirmation");
    submitBtn.textContent = t(lang, "update_password_submit");
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

function validateClient(payload) {
    let ok = true;

    if ((payload.password || "") !== (payload.password_confirmation || "")) {
        setFieldError("password_confirmation", t(getLanguage(), "password_mismatch"));
        markInvalid("password_confirmation");
        ok = false;
    }

    if ((payload.password || "").length < 6) {
        setFieldError("password", t(getLanguage(), "update_password_min"));
        markInvalid("password");
        ok = false;
    }

    return ok;
}

initLang();
statusUI.setRequestMeta("POST", "/api/update-password");

form.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearErrors(form);

    submitBtn.disabled = true;
    statusUI.setStatus(t(getLanguage(), "update_password_submitting"), "neutral", null);

    const payload = formToObject(form);

    if (!validateClient(payload)) {
        statusUI.setStatus(t(getLanguage(), "fix_fields"), "bad", 0);
        submitBtn.disabled = false;
        return;
    }

    try {
        const res = await apiRequest("/update-password", {
            method: "POST",
            body: JSON.stringify({
                old_password: payload.old_password,
                password: payload.password,
            }),
        });

        statusUI.setDebug(res);
        statusUI.setStatus(t(getLanguage(), "update_password_success"), "ok", 200);
        form.reset();
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });

        const code = err.status ?? 0;
        if (code === 422) {
            statusUI.setStatus(t(getLanguage(), "validation_failed"), "bad", 422);
            showFieldErrors(err?.data?.result?.errors || err?.data?.errors);
        } else {
            statusUI.setStatus(
                err.message || t(getLanguage(), "error"),
                "bad",
                code,
            );
        }
    } finally {
        submitBtn.disabled = false;
    }
});
