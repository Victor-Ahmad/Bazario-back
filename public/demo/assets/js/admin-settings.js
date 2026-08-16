import { apiRequest } from "./api.js";
import { getLanguage, setLanguage } from "./lang.js";
import { t } from "./i18n/index.js";
import { createStatusUI } from "./ui.js";

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
const form = document.getElementById("settingsForm");
const saveBtn = document.getElementById("saveBtn");

const fields = {
    platform_fee_percent: document.getElementById("platform_fee_percent"),
    announcement_price_per_day: document.getElementById("announcement_price_per_day"),
    ad_price_per_day_golden_ad: document.getElementById("ad_price_per_day_golden_ad"),
    ad_price_per_day_silver_ad: document.getElementById("ad_price_per_day_silver_ad"),
    ad_price_per_day_normal_ad: document.getElementById("ad_price_per_day_normal_ad"),
};

function applyTranslations(lang) {
    badgeText.textContent = t(lang, "badge");
    pageTitle.textContent = t(lang, "admin_settings_title");
    pageSubtitle.textContent = t(lang, "admin_settings_subtitle");
    statusTitle.textContent = t(lang, "status");
    document.getElementById("labelPlatformFee").textContent = t(lang, "admin_settings_platform_fee");
    document.getElementById("labelAnnouncementPrice").textContent = t(lang, "admin_settings_announcement_price");
    document.getElementById("labelGoldPrice").textContent = t(lang, "admin_settings_gold_price");
    document.getElementById("labelSilverPrice").textContent = t(lang, "admin_settings_silver_price");
    document.getElementById("labelNormalPrice").textContent = t(lang, "admin_settings_normal_price");
    saveBtn.textContent = t(lang, "admin_settings_save");
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

function setFormValues(data) {
    Object.entries(fields).forEach(([key, input]) => {
        input.value = data?.[key] ?? "";
    });
}

function getPayload() {
    return Object.fromEntries(
        Object.entries(fields).map(([key, input]) => [key, Number(input.value || 0)]),
    );
}

async function loadSettings() {
    try {
        statusUI.setRequestMeta("GET", "/api/admin/settings");
        statusUI.setStatus(t(getLanguage(), "loading"), "neutral", null);

        const res = await apiRequest("/admin/settings", { method: "GET" });
        statusUI.setDebug(res);
        setFormValues(res);
        statusUI.setStatus(t(getLanguage(), "ready"), "neutral", 200);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(err.message || t(getLanguage(), "error"), "bad", err.status || 0);
    }
}

async function saveSettings(event) {
    event.preventDefault();

    try {
        saveBtn.disabled = true;
        const payload = getPayload();
        statusUI.setRequestMeta("PUT", "/api/admin/settings");
        statusUI.setStatus(t(getLanguage(), "admin_settings_saving"), "neutral", null);

        const res = await apiRequest("/admin/settings", {
            method: "PUT",
            body: JSON.stringify(payload),
        });

        statusUI.setDebug(res);
        setFormValues(res);
        statusUI.setStatus(t(getLanguage(), "admin_settings_success"), "ok", 200);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(err.message || t(getLanguage(), "error"), "bad", err.status || 0);
    } finally {
        saveBtn.disabled = false;
    }
}

function initPage() {
    initLang();
    form.addEventListener("submit", saveSettings);
    loadSettings();
}

initPage();
