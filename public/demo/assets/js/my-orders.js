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
const pageTitle = document.getElementById("pageTitle");
const pageSubtitle = document.getElementById("pageSubtitle");
const statusTitle = document.getElementById("statusTitle");
const ordersList = document.getElementById("ordersList");

function applyTranslations(lang) {
    badgeText.textContent = t(lang, "badge");
    pageTitle.textContent = t(lang, "my_orders_title");
    pageSubtitle.textContent = t(lang, "my_orders_subtitle");
    statusTitle.textContent = t(lang, "status");
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
        loadOrders();
    });
}

function money(value) {
    const num = Number(value) || 0;
    return (num / 100).toFixed(2);
}

function formatDateTime(value) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value || "—";
    const lang = getLanguage();
    const locale =
        lang === "ar" ? "ar-EG" : lang === "de" ? "de-DE" : "en-US";
    return date.toLocaleString(locale, {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
}

function setEmpty() {
    ordersList.innerHTML = "";
    const msg = document.createElement("div");
    msg.style.color = "rgba(255,255,255,0.7)";
    msg.style.fontSize = "13px";
    msg.textContent = t(getLanguage(), "my_orders_empty");
    ordersList.appendChild(msg);
}

function renderOrders(items) {
    ordersList.innerHTML = "";
    if (!items.length) {
        setEmpty();
        return;
    }

    items.forEach((order) => {
        const card = document.createElement("div");
        card.style.border = "1px solid rgba(255,255,255,0.12)";
        card.style.borderRadius = "12px";
        card.style.background = "rgba(0,0,0,0.12)";
        card.style.padding = "10px 12px";
        card.style.display = "grid";
        card.style.gap = "6px";

        const title = document.createElement("div");
        title.style.fontWeight = "700";
        title.textContent = `${t(getLanguage(), "my_orders_order")} #${order.id}`;

        const meta = document.createElement("div");
        meta.style.fontSize = "13px";
        meta.style.color = "rgba(255,255,255,0.75)";
        const total = money(order.total_amount);
        meta.textContent = `${t(getLanguage(), "my_orders_status")}: ${
            order.status
        } • ${t(getLanguage(), "my_orders_total")}: ${total} ${
            order.currency_iso || "EUR"
        }`;

        const dates = document.createElement("div");
        dates.style.fontSize = "12px";
        dates.style.color = "rgba(255,255,255,0.6)";
        const placed = order.placed_at
            ? formatDateTime(order.placed_at)
            : "—";
        dates.textContent = `${t(getLanguage(), "my_orders_placed")}: ${placed}`;

        const itemsWrap = document.createElement("div");
        itemsWrap.style.display = "grid";
        itemsWrap.style.gap = "6px";

        const itemsTitle = document.createElement("div");
        itemsTitle.style.fontWeight = "600";
        itemsTitle.style.fontSize = "13px";
        itemsTitle.textContent = t(getLanguage(), "my_orders_items");
        itemsWrap.appendChild(itemsTitle);

        const itemList = document.createElement("div");
        itemList.style.display = "grid";
        itemList.style.gap = "6px";

        (order.items || []).forEach((item) => {
            const row = document.createElement("div");
            row.style.fontSize = "13px";
            row.style.color = "rgba(255,255,255,0.75)";
            const qty = item.quantity || 1;
            const unit = money(item.unit_amount);
            const totalLine = money(item.gross_amount);
            row.textContent = `${item.title_snapshot || "Item"} • ${t(
                getLanguage(),
                "cart_qty",
            )}: ${qty} • ${t(getLanguage(), "cart_unit_price")}: ${unit} • ${t(
                getLanguage(),
                "cart_total_price",
            )}: ${totalLine}`;
            itemList.appendChild(row);
        });

        if (!(order.items || []).length) {
            const empty = document.createElement("div");
            empty.style.fontSize = "13px";
            empty.style.color = "rgba(255,255,255,0.6)";
            empty.textContent = t(getLanguage(), "my_orders_items_empty");
            itemList.appendChild(empty);
        }

        itemsWrap.appendChild(itemList);

        card.appendChild(title);
        card.appendChild(meta);
        card.appendChild(dates);
        card.appendChild(itemsWrap);
        ordersList.appendChild(card);
    });
}

async function loadOrders() {
    try {
        statusUI.setRequestMeta("GET", "/api/orders/my-orders");
        statusUI.setStatus(t(getLanguage(), "loading"), "neutral", null);

        const res = await apiRequest("/orders/my-orders", { method: "GET" });
        statusUI.setDebug(res);

        const list = res?.data || res?.result || [];
        renderOrders(list);
        statusUI.setStatus(t(getLanguage(), "ready"), "neutral", null);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(err.message || t(getLanguage(), "error"), "bad", err.status || 0);
        setEmpty();
    }
}

initLang();
loadOrders();
