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
const statusTitle = document.getElementById("statusTitle");

const productsList = document.getElementById("productsList");
const servicesList = document.getElementById("servicesList");
const adsList = document.getElementById("adsList");

function applyLang() {
    const lang = getLanguage();
    langSelect.value = lang;
    setLanguage(lang);

    // Keep it minimal: only badge + status title for now
    badgeText.textContent = t(lang, "badge") || "Marketplace Demo";
    statusTitle.textContent = t(lang, "status") || "Status";
}

applyLang();

langSelect.addEventListener("change", () => {
    const lang = langSelect.value;
    setLanguage(lang);
    applyLang();
});

function itemRow(title, sub) {
    const div = document.createElement("div");
    div.style.border = "1px solid rgba(255,255,255,0.12)";
    div.style.borderRadius = "10px";
    div.style.background = "rgba(0,0,0,0.12)";
    div.style.padding = "10px 12px";
    div.style.display = "grid";
    div.style.gap = "4px";

    const h = document.createElement("div");
    h.style.fontWeight = "700";
    h.textContent = title;

    const p = document.createElement("div");
    p.style.color = "rgba(255,255,255,0.70)";
    p.style.fontSize = "13px";
    p.textContent = sub || "";

    div.appendChild(h);
    div.appendChild(p);
    return div;
}

function renderProducts(products) {
    productsList.innerHTML = "";
    (products?.data || []).forEach((p) => {
        const price = p.price != null ? `${p.price}` : "";
        const cat = p.category?.name ? `• ${p.category.name}` : "";
        productsList.appendChild(itemRow(p.name, `${price} ${cat}`.trim()));
    });
}

function renderServices(services) {
    servicesList.innerHTML = "";
    (services?.data || []).forEach((s) => {
        const price = s.price != null ? `${s.price}` : "";
        const cat = s.category?.name ? `• ${s.category.name}` : "";
        servicesList.appendChild(itemRow(s.title, `${price} ${cat}`.trim()));
    });
}

function renderAds(ads) {
    adsList.innerHTML = "";
    (ads?.data || []).forEach((a) => {
        const position = a.position?.name ? `• ${a.position.name}` : "";
        const type = a.adable_type
            ? `• ${String(a.adable_type).split("\\\\").pop()}`
            : "";
        adsList.appendChild(
            itemRow(a.title || `Ad #${a.id}`, `${position} ${type}`.trim()),
        );
    });
}

async function loadHome() {
    try {
        statusUI.setRequestMeta("GET", "/api/home");
        statusUI.setStatus("Loading home data...", "neutral", null);

        // 10 items for each section by default
        const res = await apiRequest("/home?per_page=10", { method: "GET" });
        statusUI.setDebug(res);

        const result = res?.result ?? res;

        renderProducts(result.products);
        renderServices(result.services);
        renderAds(result.ads);

        statusUI.setStatus("Loaded successfully.", "ok", 200);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(
            err.message || "Failed to load home.",
            "bad",
            err.status || 0,
        );
    }
}

loadHome();
