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
    pageTitle.textContent = t(lang, "admin_ads_title");
    pageSubtitle.textContent = t(lang, "admin_ads_subtitle");
    statusTitle.textContent = t(lang, "status");
}
applyLang();

langSelect.addEventListener("change", () => {
    setLanguage(langSelect.value);
    applyLang();
    load();
});

function safeText(v, fallback = "—") {
    return v === null || v === undefined || v === "" ? fallback : String(v);
}

function fileUrl(path) {
    if (!path) return null;
    if (path.startsWith("http://") || path.startsWith("https://")) return path;
    const normalized = path.startsWith("/") ? path.slice(1) : path;
    const assetPath = normalized.startsWith("storage/") ? normalized : `storage/${normalized}`;
    return `/${assetPath}`;
}

function buildStatusChip(text, tone = "neutral") {
    const chip = document.createElement("span");
    chip.textContent = text;
    chip.style.display = "inline-flex";
    chip.style.alignItems = "center";
    chip.style.padding = "4px 10px";
    chip.style.borderRadius = "999px";
    chip.style.fontSize = "12px";
    chip.style.fontWeight = "700";
    chip.style.letterSpacing = "0.01em";

    if (tone === "ok") {
        chip.style.background = "rgba(34,197,94,0.16)";
        chip.style.color = "#86efac";
        return chip;
    }

    if (tone === "bad") {
        chip.style.background = "rgba(248,113,113,0.16)";
        chip.style.color = "#fca5a5";
        return chip;
    }

    if (tone === "warn") {
        chip.style.background = "rgba(250,204,21,0.16)";
        chip.style.color = "#fde047";
        return chip;
    }

    chip.style.background = "rgba(255,255,255,0.08)";
    chip.style.color = "rgba(255,255,255,0.85)";
    return chip;
}

function setEmpty(text) {
    listEl.innerHTML = "";
    const msg = document.createElement("div");
    msg.style.color = "rgba(255,255,255,0.7)";
    msg.style.fontSize = "13px";
    msg.textContent = text;
    listEl.appendChild(msg);
}

function detailRow(label, value) {
    const row = document.createElement("div");
    row.style.display = "grid";
    row.style.gap = "4px";
    row.style.fontSize = "13px";
    row.style.color = "rgba(255,255,255,0.75)";
    row.innerHTML = `<strong style="color: rgba(255,255,255,0.9)">${label}</strong>`;
    const span = document.createElement("div");
    span.textContent = value;
    row.appendChild(span);
    return row;
}

function typeShort(adableType) {
    if (!adableType) return "—";
    return String(adableType).split("\\").pop();
}

function buildCard(item) {
    const lang = getLanguage();
    const card = document.createElement("div");
    card.style.border = "1px solid rgba(255,255,255,0.12)";
    card.style.borderRadius = "12px";
    card.style.background = "rgba(0,0,0,0.12)";
    card.style.padding = "12px";
    card.style.display = "grid";
    card.style.gap = "10px";

    const top = document.createElement("div");
    top.style.display = "flex";
    top.style.justifyContent = "space-between";
    top.style.alignItems = "flex-start";
    top.style.gap = "10px";
    top.style.flexWrap = "wrap";

    const title = document.createElement("div");
    title.style.fontWeight = "700";
    title.textContent = `${safeText(item.title, "Sponsored ad")} (#${item.id})`;

    const chips = document.createElement("div");
    chips.style.display = "flex";
    chips.style.gap = "8px";
    chips.style.flexWrap = "wrap";

    const payment = buildStatusChip(
        item.paid_at ? t(lang, "admin_ads_paid") : t(lang, "admin_ads_unpaid"),
        item.paid_at ? "ok" : "warn"
    );

    const normalizedStatus = safeText(item.status, "pending").toLowerCase();
    const moderation = buildStatusChip(
        t(lang, `admin_status_${normalizedStatus}`),
        normalizedStatus === "approved" ? "ok" : normalizedStatus === "rejected" ? "bad" : "warn"
    );

    top.appendChild(title);
    chips.appendChild(payment);
    chips.appendChild(moderation);
    top.appendChild(chips);

    const imageUrl = fileUrl(item.images?.[0]?.image_url || item.images?.[0]?.path);
    if (imageUrl) {
        const img = document.createElement("img");
        img.src = imageUrl;
        img.alt = item.title || "Ad image";
        img.style.width = "100%";
        img.style.maxHeight = "180px";
        img.style.objectFit = "cover";
        img.style.borderRadius = "10px";
        img.style.border = "1px solid rgba(255,255,255,0.1)";
        card.appendChild(img);
    }

    const fields = document.createElement("div");
    fields.style.display = "grid";
    fields.style.gap = "8px";
    fields.appendChild(detailRow(t(lang, "admin_ads_position"), safeText(item.position?.name)));
    fields.appendChild(detailRow(t(lang, "admin_ads_target_type"), typeShort(item.adable_type)));
    fields.appendChild(detailRow(t(lang, "admin_ads_created"), safeText(item.created_at)));
    fields.appendChild(detailRow("Subtitle", safeText(item.subtitle)));
    fields.appendChild(detailRow("Price", safeText(item.price)));

    const targetName =
        item.adable?.title ||
        item.adable?.name ||
        item.adable?.store_name ||
        item.adable?.store_owner_name ||
        "—";
    fields.appendChild(detailRow("Target", safeText(targetName)));

    const actions = document.createElement("div");
    actions.style.display = "flex";
    actions.style.gap = "10px";
    actions.style.flexWrap = "wrap";

    const btnApprove = document.createElement("button");
    btnApprove.type = "button";
    btnApprove.textContent = t(lang, "admin_upgrade_accept");
    btnApprove.style.width = "auto";
    btnApprove.style.marginTop = "0";

    const btnReject = document.createElement("button");
    btnReject.type = "button";
    btnReject.className = "topbarBtn secondary";
    btnReject.textContent = t(lang, "admin_upgrade_reject");
    btnReject.style.width = "auto";
    btnReject.style.marginTop = "0";

    actions.appendChild(btnApprove);
    actions.appendChild(btnReject);

    if (!item.paid_at) {
        btnApprove.disabled = true;
        const hint = document.createElement("div");
        hint.style.fontSize = "13px";
        hint.style.color = "rgba(253,224,71,0.9)";
        hint.textContent = t(lang, "admin_ads_payment_required");
        actions.appendChild(hint);
    }

    card.appendChild(top);
    card.appendChild(fields);
    card.appendChild(actions);

    const setBusy = (busy) => {
        btnApprove.disabled = busy || !item.paid_at;
        btnReject.disabled = busy;
    };

    btnApprove.addEventListener("click", async () => {
        await updateStatus(item.id, "approved", card, setBusy);
    });
    btnReject.addEventListener("click", async () => {
        await updateStatus(item.id, "rejected", card, setBusy);
    });

    return card;
}

async function updateStatus(id, status, cardEl, setBusy) {
    try {
        setBusy(true);
        statusUI.setRequestMeta("POST", `/api/admin/ad/${id}/status`);
        statusUI.setStatus(t(getLanguage(), "admin_ads_loading"), "neutral", null);

        const res = await apiRequest(`/admin/ad/${id}/status`, {
            method: "POST",
            body: JSON.stringify({ status }),
        });
        statusUI.setDebug(res);
        cardEl.remove();

        if (!listEl.children.length) {
            setEmpty(t(getLanguage(), "admin_ads_empty"));
        }

        statusUI.setStatus(t(getLanguage(), "admin_ads_updated"), "ok", 200);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(err.message || "Failed to update ad.", "bad", err.status || 0);
    } finally {
        setBusy(false);
    }
}

async function load() {
    try {
        statusUI.setRequestMeta("GET", "/api/ads/pending");
        statusUI.setStatus(t(getLanguage(), "admin_ads_loading"), "neutral", null);

        const res = await apiRequest("/ads/pending", { method: "GET" });
        statusUI.setDebug(res);

        const result = res?.result ?? res;
        const items = result?.data ?? [];

        listEl.innerHTML = "";
        if (!items.length) {
            setEmpty(t(getLanguage(), "admin_ads_empty"));
        } else {
            items.forEach((item) => listEl.appendChild(buildCard(item)));
        }

        statusUI.setStatus(`Loaded ${items.length} pending sponsored ads.`, "ok", 200);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(err.message || "Failed to load sponsored ads.", "bad", err.status || 0);
    }
}

load();
