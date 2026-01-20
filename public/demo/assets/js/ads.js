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

function typeShort(adableType) {
    if (!adableType) return "—";
    return String(adableType).split("\\").pop(); // App\Models\Product -> Product
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

function adImgUrl(img) {
    if (!img) return null;
    // ads images use: image_url
    return img.image_url ? `/${img.image_url}` : null;
}

function snippet(text, max = 140) {
    const s = safeText(text, "");
    if (!s) return "—";
    return s.length > max ? s.slice(0, max).trim() + "…" : s;
}

function cardAd(a) {
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
    title.textContent = `${safeText(a.title, "Ad")} (#${a.id})`;

    const price = document.createElement("div");
    price.style.color = "rgba(255,255,255,0.75)";
    price.textContent = `Price: ${safeText(a.price)}`;

    top.appendChild(title);
    top.appendChild(price);

    const meta = document.createElement("div");
    meta.style.color = "rgba(255,255,255,0.68)";
    meta.style.fontSize = "13px";
    meta.textContent = `Position: ${safeText(a.position?.name)} • Type: ${typeShort(a.adable_type)} • Status: ${safeText(a.status)} • Created: ${safeText(a.created_at)}`;

    const sub = document.createElement("div");
    sub.style.color = "rgba(255,255,255,0.70)";
    sub.style.fontSize = "13px";
    sub.textContent = `Subtitle: ${snippet(a.subtitle || "")}`;

    const firstImg = a.images?.[0] ? adImgUrl(a.images[0]) : null;
    if (firstImg) {
        const img = document.createElement("img");
        img.src = firstImg;
        img.alt = a.title || "Ad image";
        img.style.width = "100%";
        img.style.maxHeight = "180px";
        img.style.objectFit = "cover";
        img.style.borderRadius = "10px";
        img.style.border = "1px solid rgba(255,255,255,0.10)";
        wrap.appendChild(img);
    }

    // adable summary (if loaded)
    if (a.adable) {
        const adable = document.createElement("div");
        adable.style.color = "rgba(255,255,255,0.70)";
        adable.style.fontSize = "13px";

        const name =
            a.adable?.name ||
            a.adable?.title ||
            a.adable?.store_name ||
            a.adable?.name ||
            "—";

        adable.textContent = `Adable: ${safeText(name)}`;
        wrap.appendChild(adable);
    }

    wrap.appendChild(top);
    wrap.appendChild(meta);
    wrap.appendChild(sub);

    if (isCustomer()) {
        const actions = document.createElement("div");
        actions.style.display = "flex";
        actions.style.gap = "8px";
        actions.style.flexWrap = "wrap";

        const btnChat = document.createElement("button");
        btnChat.type = "button";
        btnChat.className = "topbarBtn secondary";
        btnChat.textContent = t(getLanguage(), "chat_contact_ad_owner");
        btnChat.style.width = "auto";
        btnChat.style.marginTop = "6px";
        btnChat.addEventListener("click", () => {
            const userId =
                a.adable?.user?.id ||
                a.adable?.seller?.user?.id ||
                a.adable?.serviceProvider?.user?.id ||
                null;
            if (!userId) return;
            sessionStorage.setItem(
                "chat_target_user",
                JSON.stringify({
                    id: userId,
                    name:
                        a.adable?.user?.name ||
                        a.adable?.store_name ||
                        a.adable?.name ||
                        "Owner",
                }),
            );
            window.location.href = "/demo/chat.html";
        });

        actions.appendChild(btnChat);
        wrap.appendChild(actions);
    }

    return wrap;
}

async function load() {
    try {
        statusUI.setRequestMeta("GET", "/api/ads");
        statusUI.setStatus("Loading ads...", "neutral", null);

        const res = await apiRequest("/ads", { method: "GET" });
        statusUI.setDebug(res);

        const result = res?.result ?? res;
        const items = result?.data ?? [];

        listEl.innerHTML = "";
        items.forEach((a) => listEl.appendChild(cardAd(a)));

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
