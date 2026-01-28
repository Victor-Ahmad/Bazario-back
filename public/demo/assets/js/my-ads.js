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
    pageTitle.textContent = t(lang, "my_ads_title");
    pageSubtitle.textContent = t(lang, "my_ads_subtitle");
    statusTitle.textContent = t(lang, "status");
}

applyLang();

langSelect.addEventListener("change", () => {
    setLanguage(langSelect.value);
    applyLang();
});

function safeText(v, fallback = "—") {
    return v === null || v === undefined || v === "" ? fallback : String(v);
}

function resolveLocalized(value) {
    if (!value) return "";
    if (typeof value === "string") return value;
    if (typeof value === "object") {
        const lang = getLanguage();
        return value[lang] || value.en || value.ar || "";
    }
    return String(value);
}

function snippet(text, max = 140) {
    const s = safeText(resolveLocalized(text), "");
    if (!s) return "—";
    return s.length > max ? s.slice(0, max).trim() + "…" : s;
}

function imgUrl(img) {
    if (!img) return null;
    return img.image_url ? `/${img.image_url}` : img.image ? `/${img.image}` : null;
}

function adTargetName(adable) {
    if (!adable) return "—";
    return (
        resolveLocalized(adable.title) ||
        resolveLocalized(adable.name) ||
        adable.store_name ||
        adable.store_owner_name ||
        adable.email ||
        `#${adable.id}`
    );
}

function cardAd(ad) {
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
    title.textContent = `${safeText(ad.title)} (#${ad.id})`;

    const status = document.createElement("div");
    status.style.color = "rgba(255,255,255,0.75)";
    status.textContent = `Status: ${safeText(ad.status)}`;

    top.appendChild(title);
    top.appendChild(status);

    const meta = document.createElement("div");
    meta.style.color = "rgba(255,255,255,0.68)";
    meta.style.fontSize = "13px";
    const position = ad.position?.name || "—";
    const target = adTargetName(ad.adable);
    meta.textContent = `Position: ${position} • Target: ${target}`;

    const desc = document.createElement("div");
    desc.style.color = "rgba(255,255,255,0.70)";
    desc.style.fontSize = "13px";
    desc.textContent = `Subtitle: ${snippet(ad.subtitle)}`;

    const firstImg = ad.images?.[0] ? imgUrl(ad.images[0]) : null;
    if (firstImg) {
        const img = document.createElement("img");
        img.src = firstImg;
        img.alt = ad.title || "Ad image";
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
        statusUI.setRequestMeta("GET", "/api/my-ads");
        statusUI.setStatus("Loading ads...", "neutral", null);

        const res = await apiRequest("/my-ads", { method: "GET" });
        statusUI.setDebug(res);

        const result = res?.result ?? res;
        const items = result?.data ?? [];

        listEl.innerHTML = "";
        if (!items.length) {
            const empty = document.createElement("div");
            empty.style.color = "rgba(255,255,255,0.7)";
            empty.style.fontSize = "13px";
            empty.textContent = t(getLanguage(), "my_ads_empty");
            listEl.appendChild(empty);
        } else {
            items.forEach((ad) => listEl.appendChild(cardAd(ad)));
        }

        statusUI.setStatus(`Loaded ${items.length} ads.`, "ok", 200);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(
            err.message || "Failed to load ads.",
            "bad",
            err.status || 0,
        );
    }
}

load();
