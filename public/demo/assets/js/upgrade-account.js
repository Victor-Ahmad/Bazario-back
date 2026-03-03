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
const connectSectionTitle = document.getElementById("connectSectionTitle");
const connectSectionSubtitle = document.getElementById("connectSectionSubtitle");
const connectEligibilityBox = document.getElementById("connectEligibilityBox");
const connectStatusBox = document.getElementById("connectStatusBox");
const connectRefreshBtn = document.getElementById("connectRefreshBtn");
const connectStartBtn = document.getElementById("connectStartBtn");

const optSeller = document.getElementById("optSeller");
const optServiceProvider = document.getElementById("optServiceProvider");

const sellerGroup = document.getElementById("sellerFields");
const providerGroup = document.getElementById("providerFields");

const storeOwnerInput = document.getElementById("store_owner_name");
const storeNameInput = document.getElementById("store_name");
const providerNameInput = document.getElementById("provider_name");

let lastConnectStatus = null;

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
    connectSectionTitle.textContent = t(lang, "upgrade_connect_title");
    connectSectionSubtitle.textContent = t(lang, "upgrade_connect_subtitle");
    connectRefreshBtn.textContent = t(lang, "upgrade_connect_refresh");
    connectStartBtn.textContent = t(lang, "upgrade_connect_start");

    optSeller.textContent = t(lang, "upgrade_type_seller");
    optServiceProvider.textContent = t(lang, "upgrade_type_service_provider");

    statusUI.setStatus(t(lang, "ready"), "neutral", null);
    renderConnectState(lastConnectStatus);
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
    loadConnectStatus();
});

if (!getToken()) {
    statusUI.setStatus(t(getLanguage(), "upgrade_login_required"), "bad", 401);
    submitBtn.disabled = true;
    connectRefreshBtn.disabled = true;
    connectStartBtn.disabled = true;
}

function connectStatusLabel(data) {
    const lang = getLanguage();

    if (!data) return t(lang, "upgrade_connect_locked");
    if (!data.eligible) return t(lang, "upgrade_connect_locked");
    if (!data.connected) return t(lang, "upgrade_connect_not_connected");
    if (data.account?.details_submitted) return t(lang, "upgrade_connect_connected");
    if (data.account?.payouts_enabled) return t(lang, "upgrade_connect_ready");
    return t(lang, "upgrade_connect_incomplete");
}

function renderConnectState(data) {
    lastConnectStatus = data;
    const lang = getLanguage();

    if (!getToken()) {
        connectEligibilityBox.textContent = t(lang, "upgrade_login_required");
        connectStatusBox.textContent = t(lang, "upgrade_connect_locked");
        connectRefreshBtn.disabled = true;
        connectStartBtn.disabled = true;
        return;
    }

    const type = accountType.value || "seller";
    const typeLabel =
        type === "service_provider"
            ? t(lang, "upgrade_type_service_provider")
            : t(lang, "upgrade_type_seller");

    if (!data) {
        connectEligibilityBox.textContent = t(lang, "upgrade_connect_checking");
        connectStatusBox.textContent = `${t(lang, "upgrade_connect_status_label")}: ${t(lang, "loading")}`;
        connectRefreshBtn.disabled = true;
        connectStartBtn.disabled = true;
        return;
    }

    if (!data.eligible) {
        connectEligibilityBox.textContent = t(lang, "upgrade_connect_locked");
        connectStatusBox.textContent = `${t(lang, "upgrade_connect_status_label")}: ${t(lang, "upgrade_connect_not_available")}`;
        connectRefreshBtn.disabled = false;
        connectStartBtn.disabled = true;
        return;
    }

    connectEligibilityBox.textContent = t(lang, "upgrade_connect_eligible").replace(":type", typeLabel);
    connectStatusBox.textContent = `${t(lang, "upgrade_connect_status_label")}: ${connectStatusLabel(data)}`;
    connectRefreshBtn.disabled = false;
    connectStartBtn.disabled = false;
}

async function loadConnectStatus() {
    if (!getToken()) {
        renderConnectState(null);
        return;
    }

    renderConnectState(null);

    try {
        const type = accountType.value || "seller";
        const res = await apiRequest(
            `/connect/status?account_type=${encodeURIComponent(type)}`,
            { method: "GET" },
        );

        renderConnectState(res);
    } catch (err) {
        if (err.status === 403) {
            renderConnectState({ eligible: false, connected: false, account: null });
            return;
        }

        connectEligibilityBox.textContent = err.message || t(getLanguage(), "error");
        connectStatusBox.textContent = `${t(getLanguage(), "upgrade_connect_status_label")}: ${t(getLanguage(), "upgrade_connect_not_available")}`;
        connectRefreshBtn.disabled = false;
        connectStartBtn.disabled = true;
    }
}

async function startConnectOnboarding() {
    if (!getToken()) {
        statusUI.setStatus(t(getLanguage(), "upgrade_login_required"), "bad", 401);
        return;
    }

    const type = accountType.value || "seller";
    connectStartBtn.disabled = true;
    connectRefreshBtn.disabled = true;

    try {
        const res = await apiRequest("/connect/onboard", {
            method: "POST",
            body: JSON.stringify({ account_type: type }),
        });

        if (res?.onboarding_url) {
            window.location.href = res.onboarding_url;
            return;
        }

        throw new Error(t(getLanguage(), "upgrade_connect_not_available"));
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(err.message || t(getLanguage(), "error"), "bad", err.status || 0);
    } finally {
        connectRefreshBtn.disabled = false;
        connectStartBtn.disabled = false;
    }
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
        await loadConnectStatus();
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

connectRefreshBtn.addEventListener("click", loadConnectStatus);
connectStartBtn.addEventListener("click", startConnectOnboarding);

loadConnectStatus();
