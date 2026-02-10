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
const itemsTitle = document.getElementById("itemsTitle");
const summaryBox = document.getElementById("summaryBox");
const itemsList = document.getElementById("itemsList");
const continueBtn = document.getElementById("continueBtn");
const ordersBtn = document.getElementById("ordersBtn");

function applyTranslations(lang) {
    badgeText.textContent = t(lang, "badge");
    pageTitle.textContent = t(lang, "order_success_title");
    pageSubtitle.textContent = t(lang, "order_success_subtitle");
    statusTitle.textContent = t(lang, "status");
    itemsTitle.textContent = t(lang, "order_success_items");
    continueBtn.textContent = t(lang, "order_success_continue");
    ordersBtn.textContent = t(lang, "order_success_back");
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

function money(value) {
    const num = Number(value) || 0;
    return (num / 100).toFixed(2);
}

function setEmpty() {
    itemsList.innerHTML = "";
    const msg = document.createElement("div");
    msg.style.color = "rgba(255,255,255,0.7)";
    msg.style.fontSize = "13px";
    msg.textContent = t(getLanguage(), "order_success_empty");
    itemsList.appendChild(msg);
}

function renderItems(items) {
    itemsList.innerHTML = "";
    if (!items?.length) {
        setEmpty();
        return;
    }

    items.forEach((item) => {
        const card = document.createElement("div");
        card.style.border = "1px solid rgba(255,255,255,0.12)";
        card.style.borderRadius = "12px";
        card.style.background = "rgba(0,0,0,0.12)";
        card.style.padding = "10px 12px";
        card.style.display = "grid";
        card.style.gap = "6px";

        const title = document.createElement("div");
        title.style.fontWeight = "700";
        title.textContent = item.title_snapshot || `#${item.id}`;

        const meta = document.createElement("div");
        meta.style.fontSize = "13px";
        meta.style.color = "rgba(255,255,255,0.75)";
        const qty = item.quantity || 1;
        const unit = money(item.unit_amount);
        const total = money(item.gross_amount);
        meta.textContent = `${t(getLanguage(), "cart_qty")}: ${qty} • ${t(
            getLanguage(),
            "cart_unit_price",
        )}: ${unit} • ${t(getLanguage(), "cart_total_price")}: ${total}`;

        card.appendChild(title);
        card.appendChild(meta);
        itemsList.appendChild(card);
    });
}

function renderSummary(order) {
    const currency = order?.currency_iso || "EUR";
    const subtotal = money(order?.subtotal_amount);
    const total = money(order?.total_amount);
    const status = order?.status || "—";

    summaryBox.textContent = `${t(getLanguage(), "order_success_order")}: #${
        order?.id || "—"
    } • ${t(getLanguage(), "order_success_status")}: ${status} • ${t(
        getLanguage(),
        "order_success_total",
    )}: ${total} ${currency} (Subtotal: ${subtotal} ${currency})`;
}

async function loadOrder() {
    const params = new URLSearchParams(window.location.search);
    const orderId = params.get("order_id");
    if (!orderId) {
        statusUI.setStatus(t(getLanguage(), "order_success_missing"), "bad", 400);
        summaryBox.textContent = t(getLanguage(), "order_success_missing");
        setEmpty();
        return;
    }

    try {
        statusUI.setRequestMeta("GET", `/api/orders/${orderId}`);
        statusUI.setStatus(t(getLanguage(), "loading"), "neutral", null);

        const res = await apiRequest(`/orders/${orderId}`, { method: "GET" });
        statusUI.setDebug(res);

        renderSummary(res);
        renderItems(res?.items || []);

        statusUI.setStatus(t(getLanguage(), "ready"), "neutral", null);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(err.message || t(getLanguage(), "error"), "bad", err.status || 0);
        summaryBox.textContent = t(getLanguage(), "order_success_failed");
        setEmpty();
    }
}

initLang();
loadOrder();
