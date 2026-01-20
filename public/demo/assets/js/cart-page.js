import { getCart } from "./cart.js";
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
const productsTitle = document.getElementById("productsTitle");
const servicesTitle = document.getElementById("servicesTitle");
const totalsBox = document.getElementById("totalsBox");
const checkoutBtn = document.getElementById("checkoutBtn");

const productsList = document.getElementById("productsList");
const servicesList = document.getElementById("servicesList");

function applyTranslations(lang) {
    badgeText.textContent = t(lang, "badge");
    pageTitle.textContent = t(lang, "cart_title");
    pageSubtitle.textContent = t(lang, "cart_subtitle");
    statusTitle.textContent = t(lang, "status");
    productsTitle.textContent = t(lang, "cart_products_title");
    servicesTitle.textContent = t(lang, "cart_services_title");
    checkoutBtn.textContent = t(lang, "cart_checkout");
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
    return num.toFixed(2);
}

function renderProducts(items) {
    productsList.innerHTML = "";
    if (!items.length) {
        const msg = document.createElement("div");
        msg.style.color = "rgba(255,255,255,0.7)";
        msg.style.fontSize = "13px";
        msg.textContent = t(getLanguage(), "cart_empty_products");
        productsList.appendChild(msg);
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
        title.textContent = `${item.name} (#${item.id})`;

        const meta = document.createElement("div");
        meta.style.fontSize = "13px";
        meta.style.color = "rgba(255,255,255,0.75)";
        const unit = money(item.price);
        const total = money(item.price * item.qty);
        meta.textContent = `${t(getLanguage(), "cart_qty")}: ${item.qty} • ${t(getLanguage(), "cart_unit_price")}: ${unit} • ${t(getLanguage(), "cart_total_price")}: ${total}`;

        if (item.image) {
            const img = document.createElement("img");
            img.src = item.image;
            img.alt = item.name || "Product";
            img.style.width = "100%";
            img.style.maxHeight = "160px";
            img.style.objectFit = "cover";
            img.style.borderRadius = "10px";
            img.style.border = "1px solid rgba(255,255,255,0.1)";
            card.appendChild(img);
        }

        card.appendChild(title);
        card.appendChild(meta);
        productsList.appendChild(card);
    });
}

function renderServices(items) {
    servicesList.innerHTML = "";
    if (!items.length) {
        const msg = document.createElement("div");
        msg.style.color = "rgba(255,255,255,0.7)";
        msg.style.fontSize = "13px";
        msg.textContent = t(getLanguage(), "cart_empty_services");
        servicesList.appendChild(msg);
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
        title.textContent = `${item.title} (#${item.service_id})`;

        const meta = document.createElement("div");
        meta.style.fontSize = "13px";
        meta.style.color = "rgba(255,255,255,0.75)";
        const unit = money(item.price);
        meta.textContent = `${item.starts_at} → ${item.ends_at} • ${t(getLanguage(), "cart_unit_price")}: ${unit}`;

        card.appendChild(title);
        card.appendChild(meta);
        servicesList.appendChild(card);
    });
}

function renderTotals(cart) {
    const productsTotal = cart.products.reduce(
        (sum, p) => sum + p.price * p.qty,
        0,
    );
    const servicesTotal = cart.services.reduce(
        (sum, s) => sum + s.price,
        0,
    );
    const total = productsTotal + servicesTotal;

    totalsBox.textContent = `${t(getLanguage(), "cart_grand_total")}: ${money(
        total,
    )}`;
}

function loadCart() {
    const cart = getCart();
    renderProducts(cart.products);
    renderServices(cart.services);
    renderTotals(cart);
}

initLang();
loadCart();

checkoutBtn.addEventListener("click", () => {
    recheckBookings();
});

async function recheckBookings() {
    const cart = getCart();
    if (!cart.services.length) {
        statusUI.setStatus(t(getLanguage(), "cart_checkout_pending"), "neutral", 0);
        return;
    }

    statusUI.setStatus(t(getLanguage(), "cart_recheck_loading"), "neutral", null);

    const failures = [];
    for (const booking of cart.services) {
        const date = booking.starts_at?.slice(0, 10);
        if (!date) continue;
        try {
            const qs = new URLSearchParams({
                date,
                timezone: booking.timezone || "",
            });
            const res = await apiRequest(
                `/services/${booking.service_id}/availability?${qs}`,
                { method: "GET" },
            );
            const slots = res?.slots || [];
            const found = slots.some(
                (s) =>
                    s.starts_at === booking.starts_at &&
                    s.ends_at === booking.ends_at,
            );
            if (!found) failures.push(booking);
        } catch (err) {
            failures.push(booking);
        }
    }

    if (failures.length) {
        statusUI.setStatus(t(getLanguage(), "cart_recheck_failed"), "bad", 409);
        return;
    }

    statusUI.setStatus(t(getLanguage(), "cart_checkout_pending"), "neutral", 0);
}
