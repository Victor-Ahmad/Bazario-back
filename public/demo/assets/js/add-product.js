import { apiRequest } from "./api.js";
import { getLanguage, setLanguage } from "./lang.js";
import { t } from "./i18n/index.js";
import { createStatusUI, clearErrors, showFieldErrors } from "./ui.js";

const form = document.getElementById("productForm");
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

const lblNameEn = document.getElementById("lblNameEn");
const lblNameAr = document.getElementById("lblNameAr");
const lblDescEn = document.getElementById("lblDescEn");
const lblDescAr = document.getElementById("lblDescAr");
const lblCategory = document.getElementById("lblCategory");
const lblPrice = document.getElementById("lblPrice");
const lblImages = document.getElementById("lblImages");

function applyTranslations(lang) {
    badgeText.textContent = t(lang, "badge");
    pageTitle.textContent = t(lang, "add_product_title");
    pageSubtitle.textContent = t(lang, "add_product_subtitle");
    statusTitle.textContent = t(lang, "status");

    lblNameEn.textContent = t(lang, "add_product_name_en");
    lblNameAr.textContent = t(lang, "add_product_name_ar");
    lblDescEn.textContent = t(lang, "add_product_desc_en");
    lblDescAr.textContent = t(lang, "add_product_desc_ar");
    lblCategory.textContent = t(lang, "add_product_category");
    lblPrice.textContent = t(lang, "add_product_price");
    lblImages.textContent = t(lang, "add_product_images");
    submitBtn.textContent = t(lang, "add_product_submit");

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
    Object.keys(normalized).forEach((key) => {
        if (key.includes(".")) {
            normalized[key] = normalized[key];
        }
    });
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
statusUI.setRequestMeta("POST", "/api/product");
loadCategories();

form.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearErrors(form);

    submitBtn.disabled = true;
    statusUI.setStatus(t(getLanguage(), "add_product_submitting"), "neutral", null);

    const payload = new FormData(form);

    try {
        const res = await apiRequest("/product", {
            method: "POST",
            body: payload,
        });

        statusUI.setDebug(res);
        statusUI.setStatus(t(getLanguage(), "add_product_success"), "ok", 200);
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
