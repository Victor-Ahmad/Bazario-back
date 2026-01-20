import { apiRequest } from "./api.js";
import { getToken } from "./auth.js";
import { getLanguage, setLanguage } from "./lang.js";
import { t } from "./i18n/index.js";
import { createStatusUI, clearErrors, showFieldErrors } from "./ui.js";

const form = document.getElementById("upgradeForm");
const submitBtn = document.getElementById("submitBtn");
const accountType = document.getElementById("accountType");

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

const lblAccountType = document.getElementById("lblAccountType");
const lblStoreOwnerName = document.getElementById("lblStoreOwnerName");
const lblStoreName = document.getElementById("lblStoreName");
const lblProviderName = document.getElementById("lblProviderName");
const lblAddress = document.getElementById("lblAddress");
const lblEmail = document.getElementById("lblEmail");
const lblPhone = document.getElementById("lblPhone");
const lblLogo = document.getElementById("lblLogo");
const lblDescription = document.getElementById("lblDescription");
const lblAttachments = document.getElementById("lblAttachments");

const optSeller = document.getElementById("optSeller");
const optServiceProvider = document.getElementById("optServiceProvider");

const sellerGroup = document.getElementById("sellerFields");
const providerGroup = document.getElementById("providerFields");

const storeOwnerInput = document.getElementById("store_owner_name");
const storeNameInput = document.getElementById("store_name");
const providerNameInput = document.getElementById("provider_name");

function endpointFor(type) {
    return type === "service_provider"
        ? "/customer/upgrade-to-service_provider"
        : "/customer/upgrade-to-seller";
}

function endpointLabel(type) {
    return type === "service_provider"
        ? "/api/customer/upgrade-to-service_provider"
        : "/api/customer/upgrade-to-seller";
}

function setGroupEnabled(group, enabled) {
    if (!group) return;
    group.style.display = enabled ? "grid" : "none";
    group.querySelectorAll("input, select, textarea").forEach((el) => {
        el.disabled = !enabled;
        if (!enabled) el.classList.remove("isInvalid", "isValid");
    });
}

function syncType(type) {
    const isSeller = type === "seller";

    setGroupEnabled(sellerGroup, isSeller);
    setGroupEnabled(providerGroup, !isSeller);

    storeOwnerInput.required = isSeller;
    storeNameInput.required = isSeller;
    providerNameInput.required = !isSeller;

    statusUI.setRequestMeta("POST", endpointLabel(type));
}

function normalizeErrors(errors) {
    if (!errors) return {};
    const normalized = { ...errors };
    if (!normalized.attachments) {
        const key = Object.keys(normalized).find((k) =>
            k.startsWith("attachments."),
        );
        if (key) normalized.attachments = normalized[key];
    }
    return normalized;
}

function applyTranslations(lang) {
    badgeText.textContent = t(lang, "badge");
    pageTitle.textContent = t(lang, "upgrade_title");
    pageSubtitle.textContent = t(lang, "upgrade_subtitle");
    statusTitle.textContent = t(lang, "status");

    lblAccountType.textContent = t(lang, "upgrade_type_label");
    lblStoreOwnerName.textContent = t(lang, "upgrade_store_owner_name");
    lblStoreName.textContent = t(lang, "upgrade_store_name");
    lblProviderName.textContent = t(lang, "upgrade_provider_name");
    lblAddress.textContent = t(lang, "upgrade_address");
    lblEmail.textContent = t(lang, "upgrade_email");
    lblPhone.textContent = t(lang, "upgrade_phone");
    lblLogo.textContent = t(lang, "upgrade_logo");
    lblDescription.textContent = t(lang, "upgrade_description");
    lblAttachments.textContent = t(lang, "upgrade_attachments");
    submitBtn.textContent = t(lang, "upgrade_submit");

    optSeller.textContent = t(lang, "upgrade_type_seller");
    optServiceProvider.textContent = t(lang, "upgrade_type_service_provider");

    statusUI.setStatus(t(lang, "ready"), "neutral", null);
}

function initLanguageSelector() {
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

initLanguageSelector();
syncType(accountType.value || "seller");

accountType.addEventListener("change", () => {
    syncType(accountType.value);
});

if (!getToken()) {
    statusUI.setStatus(t(getLanguage(), "upgrade_login_required"), "bad", 401);
    submitBtn.disabled = true;
}

form.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearErrors(form);

    if (!getToken()) {
        statusUI.setStatus(
            t(getLanguage(), "upgrade_login_required"),
            "bad",
            401,
        );
        return;
    }

    submitBtn.disabled = true;
    const type = accountType.value || "seller";

    statusUI.setRequestMeta("POST", endpointLabel(type));
    statusUI.setStatus(t(getLanguage(), "upgrade_submitting"), "neutral", null);

    const payload = new FormData(form);

    try {
        const res = await apiRequest(endpointFor(type), {
            method: "POST",
            body: payload,
        });

        statusUI.setDebug(res);
        statusUI.setStatus(t(getLanguage(), "upgrade_success"), "ok", 200);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });

        const code = err.status ?? 0;
        if (code === 422) {
            statusUI.setStatus(
                t(getLanguage(), "validation_failed"),
                "bad",
                422,
            );
            const errors = err?.data?.result?.errors || err?.data?.errors;
            showFieldErrors(normalizeErrors(errors));
        } else {
            statusUI.setStatus(
                err.message || "Something went wrong.",
                "bad",
                code,
            );
        }
    } finally {
        submitBtn.disabled = false;
    }
});
