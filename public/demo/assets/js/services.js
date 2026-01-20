import { apiRequest } from "./api.js";
import { getLanguage, setLanguage } from "./lang.js";
import { t } from "./i18n/index.js";
import { createStatusUI } from "./ui.js";
import { getAuthSession } from "./auth.js";

const listEl = document.getElementById("list");
const langSelect = document.getElementById("langSelect");

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
    document.getElementById("badgeText").textContent = t(lang, "badge");
    document.getElementById("statusTitle").textContent = t(lang, "status");
}
applyLang();

langSelect.addEventListener("change", () => {
    setLanguage(langSelect.value);
    applyLang();
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
    // services images use: { image: "storage/..." }
    return img.image ? `/${img.image}` : null;
}

function normalizeRoles(roles) {
    if (!roles) return [];
    if (Array.isArray(roles)) {
        return roles
            .map((role) =>
                typeof role === "string"
                    ? role
                    : role?.name || role?.slug || role?.role || "",
            )
            .filter(Boolean);
    }
    if (Array.isArray(roles?.data)) return normalizeRoles(roles.data);
    if (Array.isArray(roles?.roles)) return normalizeRoles(roles.roles);
    return [];
}

function isCustomer() {
    const roles = getAuthSession()?.roles || getAuthSession()?.user?.roles;
    return normalizeRoles(roles).includes("customer");
}

function cardService(s) {
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
    title.textContent = `${safeText(s.title)} (#${s.id})`;

    const price = document.createElement("div");
    price.style.color = "rgba(255,255,255,0.75)";
    price.textContent = `Price: ${safeText(s.price)}`;

    top.appendChild(title);
    top.appendChild(price);

    const meta = document.createElement("div");
    meta.style.color = "rgba(255,255,255,0.68)";
    meta.style.fontSize = "13px";

    const category = s.category?.name ? s.category.name : "—";
    const provider =
        s.service_provider?.name ||
        s.serviceProvider?.name ||
        s.serviceProvider?.user?.name ||
        "—";
    meta.textContent = `Category: ${category} • Provider: ${provider} • Created: ${safeText(s.created_at)}`;

    const desc = document.createElement("div");
    desc.style.color = "rgba(255,255,255,0.70)";
    desc.style.fontSize = "13px";
    desc.textContent = `Description: ${snippet(s.description)}`;

    const firstImg = s.images?.[0] ? imgUrl(s.images[0]) : null;
    if (firstImg) {
        const img = document.createElement("img");
        img.src = firstImg;
        img.alt = s.title || "Service image";
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

    if (isCustomer()) {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.textContent = t(getLanguage(), "cart_book_btn");
        btn.style.width = "auto";
        btn.style.marginTop = "6px";
        btn.addEventListener("click", () => {
            sessionStorage.setItem("pending_service", JSON.stringify(s));
            window.location.href = `/demo/book-service.html?service_id=${s.id}`;
        });
        wrap.appendChild(btn);
    }

    return wrap;
}

async function load() {
    try {
        statusUI.setRequestMeta("GET", "/api/services");
        statusUI.setStatus("Loading services...", "neutral", null);

        const res = await apiRequest("/services", { method: "GET" });
        statusUI.setDebug(res);

        const result = res?.result ?? res;
        const items = result?.data ?? [];

        listEl.innerHTML = "";
        items.forEach((s) => listEl.appendChild(cardService(s)));

        statusUI.setStatus(`Loaded ${items.length} services.`, "ok", 200);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(
            err.message || "Failed to load services.",
            "bad",
            err.status || 0,
        );
    }
}

load();
