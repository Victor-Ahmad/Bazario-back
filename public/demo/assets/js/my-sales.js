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
const salesList = document.getElementById("salesList");

function applyTranslations(lang) {
    badgeText.textContent = t(lang, "badge");
    pageTitle.textContent = t(lang, "my_sales_title");
    pageSubtitle.textContent = t(lang, "my_sales_subtitle");
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
        loadSales();
    });
}

function money(value) {
    const num = Number(value) || 0;
    return (num / 100).toFixed(2);
}

function setEmpty() {
    salesList.innerHTML = "";
    const msg = document.createElement("div");
    msg.style.color = "rgba(255,255,255,0.7)";
    msg.style.fontSize = "13px";
    msg.textContent = t(getLanguage(), "my_sales_empty");
    salesList.appendChild(msg);
}

function renderList(items) {
    salesList.innerHTML = "";
    if (!items.length) {
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
        title.textContent = `${item.title_snapshot || "Item"} (#${item.id})`;

        const meta = document.createElement("div");
        meta.style.fontSize = "13px";
        meta.style.color = "rgba(255,255,255,0.75)";
        const qty = item.quantity || 1;
        const unit = money(item.unit_amount);
        const total = money(item.gross_amount);
        const status = item.order?.status || "—";
        meta.textContent = `${t(getLanguage(), "my_sales_order")} #${
            item.order?.id || "—"
        } • ${t(getLanguage(), "my_sales_status")}: ${status} • ${t(
            getLanguage(),
            "cart_qty",
        )}: ${qty} • ${t(getLanguage(), "cart_unit_price")}: ${unit} • ${t(
            getLanguage(),
            "cart_total_price",
        )}: ${total}`;

        const customer = document.createElement("div");
        customer.style.fontSize = "12px";
        customer.style.color = "rgba(255,255,255,0.6)";
        const buyer = item.order?.buyer;
        customer.textContent = `${t(getLanguage(), "my_sales_customer")}: ${
            buyer?.name || "—"
        }`;

        card.appendChild(title);
        card.appendChild(meta);
        card.appendChild(customer);
        salesList.appendChild(card);
    });
}

async function loadSales() {
    try {
        statusUI.setRequestMeta("GET", "/api/orders/my-sales");
        statusUI.setStatus(t(getLanguage(), "loading"), "neutral", null);

        const res = await apiRequest("/orders/my-sales", { method: "GET" });
        statusUI.setDebug(res);

        const items = res?.items || [];
        renderList(items);

        statusUI.setStatus(t(getLanguage(), "ready"), "neutral", null);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(err.message || t(getLanguage(), "error"), "bad", err.status || 0);
        setEmpty();
    }
}

initLang();
loadSales();
