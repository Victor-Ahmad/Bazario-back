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
    pageTitle.textContent = t(lang, "admin_announcements_title");
    pageSubtitle.textContent = t(lang, "admin_announcements_subtitle");
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
    return `/${path}`;
}

function statusTone(status) {
    if (status === "approved") return "ok";
    if (status === "rejected") return "bad";
    return "warn";
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

function buildCard(item) {
    const lang = getLanguage();
    const card = document.createElement("div");
    card.style.border = "1px solid rgba(255,255,255,0.12)";
    card.style.borderRadius = "12px";
    card.style.background = "rgba(0,0,0,0.12)";
    card.style.padding = "12px";
    card.style.display = "grid";
    card.style.gap = "10px";

    const title = document.createElement("div");
    title.style.fontWeight = "700";
    title.textContent = `${safeText(item.title, "Announcement")} (#${item.id})`;

    const meta = document.createElement("div");
    meta.style.display = "flex";
    meta.style.alignItems = "center";
    meta.style.justifyContent = "space-between";
    meta.style.gap = "10px";
    meta.style.flexWrap = "wrap";
    meta.style.color = "rgba(255,255,255,0.68)";
    meta.style.fontSize = "13px";

    const metaDate = document.createElement("span");
    metaDate.textContent = `${t(lang, "admin_announcements_created")}: ${safeText(item.created_at)}`;

    const normalizedStatus = safeText(item.status, "pending").toLowerCase();
    const statusChip = buildStatusChip(
        t(lang, `admin_status_${normalizedStatus}`),
        statusTone(normalizedStatus)
    );

    meta.appendChild(metaDate);
    meta.appendChild(statusChip);

    const imageUrl = fileUrl(item.cover_image?.path || item.coverImage?.path || item.images?.[0]?.path);
    if (imageUrl) {
        const img = document.createElement("img");
        img.src = imageUrl;
        img.alt = item.title || "Announcement image";
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
    fields.appendChild(detailRow(t(lang, "admin_announcements_owner"), safeText(item.user?.name)));
    fields.appendChild(detailRow("Email", safeText(item.user?.email)));
    fields.appendChild(detailRow("Description", safeText(item.description)));
    if (item.price !== null && item.price !== undefined && item.price !== "") {
        fields.appendChild(detailRow(t(lang, "admin_announcements_price"), safeText(item.price)));
    }

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

    card.appendChild(title);
    card.appendChild(meta);
    card.appendChild(fields);
    card.appendChild(actions);

    const setBusy = (busy) => {
        btnApprove.disabled = busy;
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
        statusUI.setRequestMeta("POST", `/api/admin/listings/${id}/status`);
        statusUI.setStatus(t(getLanguage(), "admin_announcements_loading"), "neutral", null);

        const res = await apiRequest(`/admin/listings/${id}/status`, {
            method: "POST",
            body: JSON.stringify({ status }),
        });
        statusUI.setDebug(res);
        cardEl.remove();

        if (!listEl.children.length) {
            setEmpty(t(getLanguage(), "admin_announcements_empty"));
        }

        statusUI.setStatus(t(getLanguage(), "admin_announcements_updated"), "ok", 200);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(err.message || "Something went wrong.", "bad", err.status || 0);
    } finally {
        setBusy(false);
    }
}

async function load() {
    try {
        statusUI.setRequestMeta("GET", "/api/admin/listings/pending");
        statusUI.setStatus(t(getLanguage(), "admin_announcements_loading"), "neutral", null);

        const res = await apiRequest("/admin/listings/pending", { method: "GET" });
        statusUI.setDebug(res);

        const result = res?.result ?? res;
        const items = result?.data ?? [];

        listEl.innerHTML = "";
        if (!items.length) {
            setEmpty(t(getLanguage(), "admin_announcements_empty"));
        } else {
            items.forEach((item) => listEl.appendChild(buildCard(item)));
        }

        statusUI.setStatus(`Loaded ${items.length} pending announcements.`, "ok", 200);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(err.message || "Failed to load announcements.", "bad", err.status || 0);
    }
}

load();
