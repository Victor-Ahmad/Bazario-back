import { apiRequest } from "./api.js";
import { getLanguage, setLanguage } from "./lang.js";
import { t } from "./i18n/index.js";
import { createStatusUI, clearErrors, showFieldErrors } from "./ui.js";

const form = document.getElementById("serviceForm");
const submitBtn = document.getElementById("submitBtn");
const categorySelect = document.getElementById("category_id");

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

const lblTitleEn = document.getElementById("lblTitleEn");
const lblTitleAr = document.getElementById("lblTitleAr");
const lblDescEn = document.getElementById("lblDescEn");
const lblDescAr = document.getElementById("lblDescAr");
const lblCategory = document.getElementById("lblCategory");
const lblPrice = document.getElementById("lblPrice");
const lblImages = document.getElementById("lblImages");
const lblDuration = document.getElementById("lblDuration");
const lblSlotInterval = document.getElementById("lblSlotInterval");
const lblMaxConcurrent = document.getElementById("lblMaxConcurrent");
const lblLocationType = document.getElementById("lblLocationType");
const lblIsActive = document.getElementById("lblIsActive");

function applyTranslations(lang) {
    badgeText.textContent = t(lang, "badge");
    pageTitle.textContent = t(lang, "add_service_title");
    pageSubtitle.textContent = t(lang, "add_service_subtitle");
    statusTitle.textContent = t(lang, "status");

    lblTitleEn.textContent = t(lang, "add_service_title_en");
    lblTitleAr.textContent = t(lang, "add_service_title_ar");
    lblDescEn.textContent = t(lang, "add_service_desc_en");
    lblDescAr.textContent = t(lang, "add_service_desc_ar");
    lblCategory.textContent = t(lang, "add_service_category");
    lblPrice.textContent = t(lang, "add_service_price");
    lblDuration.textContent = t(lang, "add_service_duration");
    lblSlotInterval.textContent = t(lang, "add_service_slot_interval");
    lblMaxConcurrent.textContent = t(lang, "add_service_max_concurrent");
    lblLocationType.textContent = t(lang, "add_service_location_type");
    lblIsActive.textContent = t(lang, "add_service_is_active");
    lblImages.textContent = t(lang, "add_service_images");
    submitBtn.textContent = t(lang, "add_service_submit");

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

function normalizeErrors(errors) {
    if (!errors) return {};
    const normalized = { ...errors };
    if (!normalized.images) {
        const key = Object.keys(normalized).find((k) => k.startsWith("images."));
        if (key) normalized.images = normalized[key];
    }
    return normalized;
}

async function loadCategories() {
    try {
        const res = await apiRequest("/categories", { method: "GET" });
        const result = res?.result ?? res;
        const items = result?.data ?? result ?? [];

        categorySelect.innerHTML = "";
        const placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = "--";
        placeholder.disabled = true;
        placeholder.selected = true;
        categorySelect.appendChild(placeholder);

        items.forEach((cat) => {
            const opt = document.createElement("option");
            opt.value = cat.id;
            opt.textContent = cat.name || `#${cat.id}`;
            categorySelect.appendChild(opt);
        });
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(
            err.message || "Failed to load categories.",
            "bad",
            err.status || 0,
        );
    }
}

initLang();
statusUI.setRequestMeta("POST", "/api/service");
loadCategories();

form.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearErrors(form);

    submitBtn.disabled = true;
    statusUI.setStatus(t(getLanguage(), "add_service_submitting"), "neutral", null);

    const payload = new FormData(form);

    try {
        const res = await apiRequest("/service", {
            method: "POST",
            body: payload,
        });

        statusUI.setDebug(res);
        statusUI.setStatus(t(getLanguage(), "add_service_success"), "ok", 200);
        form.reset();
        loadCategories();
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
