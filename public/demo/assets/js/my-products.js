import { apiRequest } from "./api.js";
import { getLanguage, setLanguage } from "./lang.js";
import { t } from "./i18n/index.js";
import { createStatusUI } from "./ui.js";

const listEl = document.getElementById("list");
const langSelect = document.getElementById("langSelect");
const badgeText = document.getElementById("badgeText");
const pageTitle = document.getElementById("pageTitle");
const pageSubtitle = document.getElementById("pageSubtitle");
const statusTitle = document.getElementById("statusTitle");
const addBtn = document.getElementById("addBtn");

const statusUI = createStatusUI({
    statusBox: document.getElementById("statusBox"),
    statusPill: document.getElementById("statusPill"),
    statusMeta: document.getElementById("statusMeta"),
    debugBox: document.getElementById("debugBox"),
});

function applyLang() {
    const lang = getLanguage();
    langSelect.value = lang;
    setLanguage(lang);
    badgeText.textContent = t(lang, "badge");
    pageTitle.textContent = t(lang, "my_products_title");
    pageSubtitle.textContent = t(lang, "my_products_subtitle");
    statusTitle.textContent = t(lang, "status");
    addBtn.textContent = t(lang, "my_products_add_button");
}
applyLang();

langSelect.addEventListener("change", () => {
    setLanguage(langSelect.value);
    applyLang();
});

addBtn.addEventListener("click", () => {
    window.location.href = "/demo/add-product.html";
});

function safeText(v, fallback = "—") {
    return v === null || v === undefined || v === "" ? fallback : String(v);
}

function snippet(text, max = 140) {
    const s = safeText(text, "");
    if (!s) return "—";
    return s.length > max ? s.slice(0, max).trim() + "…" : s;
}

function imgUrl(img) {
    if (!img) return null;
    return img.image ? `/${img.image}` : null;
}

function cardProduct(p) {
    const wrap = document.createElement("div");
    wrap.style.border = "1px solid rgba(255,255,255,0.12)";
    wrap.style.borderRadius = "12px";
    wrap.style.background = "rgba(0,0,0,0.12)";
    wrap.style.padding = "12px";
    wrap.style.display = "grid";
    wrap.style.gap = "8px";

    const top = document.createElement("div");
    top.style.display = "flex";
    top.style.justifyContent = "space-between";
    top.style.gap = "10px";
    top.style.flexWrap = "wrap";

    const title = document.createElement("div");
    title.style.fontWeight = "700";
    title.textContent = `${safeText(p.name)} (#${p.id})`;

    const price = document.createElement("div");
    price.style.color = "rgba(255,255,255,0.75)";
    price.textContent = `Price: ${safeText(p.price)}`;

    top.appendChild(title);
    top.appendChild(price);

    const meta = document.createElement("div");
    meta.style.color = "rgba(255,255,255,0.68)";
    meta.style.fontSize = "13px";

    const category = p.category?.name ? p.category.name : "—";
    meta.textContent = `Category: ${category} • Created: ${safeText(p.created_at)}`;

    const desc = document.createElement("div");
    desc.style.color = "rgba(255,255,255,0.70)";
    desc.style.fontSize = "13px";
    desc.textContent = `Description: ${snippet(p.description)}`;

    const firstImg = p.images?.[0] ? imgUrl(p.images[0]) : null;
    if (firstImg) {
        const img = document.createElement("img");
        img.src = firstImg;
        img.alt = p.name || "Product image";
        img.style.width = "100%";
        img.style.maxHeight = "180px";
        img.style.objectFit = "cover";
        img.style.borderRadius = "10px";
        img.style.border = "1px solid rgba(255,255,255,0.10)";
        wrap.appendChild(img);
    }

    wrap.appendChild(top);
    wrap.appendChild(meta);
    wrap.appendChild(desc);

    return wrap;
}

async function load() {
    try {
        statusUI.setRequestMeta("GET", "/api/my-products");
        statusUI.setStatus("Loading products...", "neutral", null);

        const res = await apiRequest("/my-products", { method: "GET" });
        statusUI.setDebug(res);

        const result = res?.result ?? res;
        const items = result?.data ?? [];

        listEl.innerHTML = "";
        items.forEach((p) => listEl.appendChild(cardProduct(p)));

        statusUI.setStatus(`Loaded ${items.length} products.`, "ok", 200);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(
            err.message || "Failed to load products.",
            "bad",
            err.status || 0,
        );
    }
}

load();
