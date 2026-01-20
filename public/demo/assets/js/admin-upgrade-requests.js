import { apiRequest } from "./api.js";
import { getAuthSession, getToken } from "./auth.js";
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
const sellerTitle = document.getElementById("sellerTitle");
const providerTitle = document.getElementById("providerTitle");

const sellerList = document.getElementById("sellerList");
const providerList = document.getElementById("providerList");

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

function hasRole(roles, name) {
    return normalizeRoles(roles).includes(name);
}

function safeText(value, fallback = "—") {
    return value === null || value === undefined || value === ""
        ? fallback
        : String(value);
}

function fileUrl(path) {
    if (!path) return null;
    if (path.startsWith("http://") || path.startsWith("https://")) return path;
    return `/${path}`;
}

function setEmpty(listEl, text) {
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

function attachmentsBlock(items) {
    const wrap = document.createElement("div");
    wrap.style.display = "grid";
    wrap.style.gap = "6px";
    wrap.style.fontSize = "13px";
    wrap.style.color = "rgba(255,255,255,0.75)";

    const title = document.createElement("div");
    title.style.fontWeight = "700";
    title.style.color = "rgba(255,255,255,0.9)";
    title.textContent = t(getLanguage(), "upgrade_attachments");
    wrap.appendChild(title);

    if (!items?.length) {
        const empty = document.createElement("div");
        empty.textContent = "—";
        wrap.appendChild(empty);
        return wrap;
    }

    const list = document.createElement("div");
    list.style.display = "grid";
    list.style.gap = "4px";
    items.forEach((item) => {
        const link = document.createElement("a");
        const url = fileUrl(item?.file);
        link.href = url || "#";
        link.textContent = item?.name || url || "Attachment";
        link.target = "_blank";
        link.rel = "noopener";
        link.style.color = "rgba(255,255,255,0.9)";
        list.appendChild(link);
    });
    wrap.appendChild(list);
    return wrap;
}

function buildCard(type, item) {
    const card = document.createElement("div");
    card.style.border = "1px solid rgba(255,255,255,0.12)";
    card.style.borderRadius = "12px";
    card.style.background = "rgba(0,0,0,0.12)";
    card.style.padding = "12px";
    card.style.display = "grid";
    card.style.gap = "10px";

    const title = document.createElement("div");
    title.style.fontWeight = "700";
    title.textContent = `${
        type === "seller"
            ? safeText(item.store_name, "Seller")
            : safeText(item.name, "Service Provider")
    } (#${item.id})`;

    const meta = document.createElement("div");
    meta.style.color = "rgba(255,255,255,0.68)";
    meta.style.fontSize = "13px";
    meta.textContent = `Status: ${safeText(item.status)} • Created: ${safeText(item.created_at)}`;

    const user = item.user || {};
    const userInfo = [
        safeText(user.name),
        safeText(user.email),
        safeText(user.phone),
    ]
        .filter((v) => v && v !== "—")
        .join(" • ");

    const fields = document.createElement("div");
    fields.style.display = "grid";
    fields.style.gap = "8px";

    fields.appendChild(detailRow("User", userInfo || "—"));
    if (type === "seller") {
        fields.appendChild(
            detailRow(
                "Store owner",
                safeText(item.store_owner_name),
            ),
        );
        fields.appendChild(detailRow("Store name", safeText(item.store_name)));
    } else {
        fields.appendChild(detailRow("Provider name", safeText(item.name)));
    }
    fields.appendChild(detailRow("Address", safeText(item.address)));
    fields.appendChild(detailRow("Description", safeText(item.description)));

    const logoUrl = fileUrl(item.logo);
    if (logoUrl) {
        const img = document.createElement("img");
        img.src = logoUrl;
        img.alt = "Logo";
        img.style.width = "100%";
        img.style.maxHeight = "180px";
        img.style.objectFit = "cover";
        img.style.borderRadius = "10px";
        img.style.border = "1px solid rgba(255,255,255,0.1)";
        card.appendChild(img);
    }

    const actions = document.createElement("div");
    actions.style.display = "flex";
    actions.style.gap = "10px";
    actions.style.flexWrap = "wrap";

    const btnApprove = document.createElement("button");
    btnApprove.type = "button";
    btnApprove.textContent = t(getLanguage(), "admin_upgrade_accept");
    btnApprove.style.width = "auto";
    btnApprove.style.marginTop = "0";

    const btnReject = document.createElement("button");
    btnReject.type = "button";
    btnReject.className = "topbarBtn secondary";
    btnReject.textContent = t(getLanguage(), "admin_upgrade_reject");
    btnReject.style.width = "auto";
    btnReject.style.marginTop = "0";

    actions.appendChild(btnApprove);
    actions.appendChild(btnReject);

    card.appendChild(title);
    card.appendChild(meta);
    card.appendChild(fields);
    card.appendChild(attachmentsBlock(item.attachments));
    card.appendChild(actions);

    const setBusy = (busy) => {
        btnApprove.disabled = busy;
        btnReject.disabled = busy;
    };

    const listEl = type === "seller" ? sellerList : providerList;

    btnApprove.addEventListener("click", async () => {
        await updateStatus(type, item.id, "approve", card, listEl, setBusy);
    });
    btnReject.addEventListener("click", async () => {
        await updateStatus(type, item.id, "reject", card, listEl, setBusy);
    });

    return card;
}

async function updateStatus(type, id, action, cardEl, listEl, setBusy) {
    const path =
        type === "seller"
            ? `/admin/upgrade-requests/seller/${id}/${action}`
            : `/admin/upgrade-requests/service-provider/${id}/${action}`;

    try {
        setBusy(true);
        statusUI.setRequestMeta("POST", `/api${path}`);
        statusUI.setStatus(t(getLanguage(), "admin_upgrade_loading"), "neutral", null);

        const res = await apiRequest(path, { method: "POST" });
        statusUI.setDebug(res);

        cardEl.remove();
        if (!listEl.children.length) {
            setEmpty(listEl, t(getLanguage(), "admin_upgrade_empty"));
        }

        statusUI.setStatus(t(getLanguage(), "admin_upgrade_updated"), "ok", 200);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(
            err.message || "Something went wrong.",
            "bad",
            err.status || 0,
        );
    } finally {
        setBusy(false);
    }
}

function applyTranslations(lang) {
    badgeText.textContent = t(lang, "badge");
    pageTitle.textContent = t(lang, "admin_upgrade_title");
    pageSubtitle.textContent = t(lang, "admin_upgrade_subtitle");
    statusTitle.textContent = t(lang, "status");
    sellerTitle.textContent = t(lang, "admin_upgrade_sellers");
    providerTitle.textContent = t(lang, "admin_upgrade_service_providers");
    statusUI.setStatus(t(lang, "ready"), "neutral", null);
}

function initLanguageSelector() {
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

initLanguageSelector();
statusUI.setRequestMeta("GET", "/api/admin/upgrade-requests");

async function loadRequests() {
    const session = getAuthSession();
    const roles = session?.roles;
    const isAdmin = hasRole(roles, "admin");

    if (!getToken() || !isAdmin) {
        statusUI.setStatus(t(getLanguage(), "admin_upgrade_admin_only"), "bad", 403);
        setEmpty(sellerList, t(getLanguage(), "admin_upgrade_empty"));
        setEmpty(providerList, t(getLanguage(), "admin_upgrade_empty"));
        return;
    }

    try {
        statusUI.setStatus(t(getLanguage(), "admin_upgrade_loading"), "neutral", null);

        const res = await apiRequest("/admin/upgrade-requests", {
            method: "GET",
        });
        statusUI.setDebug(res);

        const result = res?.result ?? res;
        const sellers = result?.sellers ?? [];
        const providers = result?.service_providers ?? [];

        sellerList.innerHTML = "";
        providerList.innerHTML = "";

        if (!sellers.length) {
            setEmpty(sellerList, t(getLanguage(), "admin_upgrade_empty"));
        } else {
            sellers.forEach((item) => sellerList.appendChild(buildCard("seller", item)));
        }

        if (!providers.length) {
            setEmpty(providerList, t(getLanguage(), "admin_upgrade_empty"));
        } else {
            providers.forEach((item) =>
                providerList.appendChild(buildCard("service-provider", item)),
            );
        }

        statusUI.setStatus(t(getLanguage(), "admin_upgrade_updated"), "ok", 200);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(
            err.message || "Something went wrong.",
            "bad",
            err.status || 0,
        );
    }
}

loadRequests();
