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
const bookingsList = document.getElementById("bookingsList");

function applyTranslations(lang) {
    badgeText.textContent = t(lang, "badge");
    pageTitle.textContent = t(lang, "provider_bookings_title");
    pageSubtitle.textContent = t(lang, "provider_bookings_subtitle");
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
        loadBookings();
    });
}

function setEmpty() {
    bookingsList.innerHTML = "";
    const msg = document.createElement("div");
    msg.style.color = "rgba(255,255,255,0.7)";
    msg.style.fontSize = "13px";
    msg.textContent = t(getLanguage(), "provider_bookings_empty");
    bookingsList.appendChild(msg);
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

function toIso(value) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    return date.toISOString();
}

function toInputValue(value) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return "";
    const pad = (n) => String(n).padStart(2, "0");
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(
        date.getDate(),
    )}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function bookingCard(booking) {
    const status = String(booking.status || "").toLowerCase();
    const isFinal = [
        "completed",
        "cancelled_by_customer",
        "cancelled_by_provider",
        "no_show",
    ].includes(status);

    const card = document.createElement("div");
    card.style.border = "1px solid rgba(255,255,255,0.12)";
    card.style.borderRadius = "12px";
    card.style.background = "rgba(0,0,0,0.12)";
    card.style.padding = "10px 12px";
    card.style.display = "grid";
    card.style.gap = "8px";

    const title = document.createElement("div");
    title.style.fontWeight = "700";
    title.textContent = `${booking.service?.title || "Service"} (#${
        booking.id
    })`;

    const meta = document.createElement("div");
    meta.style.fontSize = "13px";
    meta.style.color = "rgba(255,255,255,0.75)";
    meta.textContent = `${formatDateTime(booking.starts_at)} → ${formatDateTime(
        booking.ends_at,
    )}`;

    const statusBadge = document.createElement("span");
    statusBadge.textContent = booking.status;
    statusBadge.style.display = "inline-flex";
    statusBadge.style.alignItems = "center";
    statusBadge.style.width = "fit-content";
    statusBadge.style.padding = "4px 8px";
    statusBadge.style.borderRadius = "999px";
    statusBadge.style.fontSize = "12px";
    statusBadge.style.fontWeight = "700";
    statusBadge.style.border = "1px solid rgba(255,255,255,0.18)";
    if (status.includes("cancelled")) {
        statusBadge.style.color = "#fecaca";
        statusBadge.style.background = "rgba(185, 28, 28, 0.35)";
    } else if (status === "completed") {
        statusBadge.style.color = "#bbf7d0";
        statusBadge.style.background = "rgba(21, 128, 61, 0.3)";
    } else {
        statusBadge.style.color = "#bfdbfe";
        statusBadge.style.background = "rgba(30, 64, 175, 0.28)";
    }

    const customer = document.createElement("div");
    customer.style.fontSize = "12px";
    customer.style.color = "rgba(255,255,255,0.6)";
    customer.textContent = `${t(getLanguage(), "provider_bookings_customer")}: ${
        booking.customer_user?.name || "—"
    }`;

    const actions = document.createElement("div");
    actions.style.display = "grid";
    actions.style.gap = "8px";

    const row = document.createElement("div");
    row.style.display = "flex";
    row.style.gap = "8px";
    row.style.flexWrap = "wrap";

    const dateInput = document.createElement("input");
    dateInput.type = "datetime-local";
    dateInput.style.minWidth = "220px";
    dateInput.value = toInputValue(booking.starts_at);

    const rescheduleHint = document.createElement("div");
    rescheduleHint.style.fontSize = "12px";
    rescheduleHint.style.color = "rgba(255,255,255,0.6)";
    rescheduleHint.textContent = t(
        getLanguage(),
        "provider_bookings_reschedule_hint",
    );

    const btnReschedule = document.createElement("button");
    btnReschedule.type = "button";
    btnReschedule.textContent = t(getLanguage(), "provider_bookings_reschedule");
    btnReschedule.style.width = "auto";
    btnReschedule.style.marginTop = "0";

    const btnCancel = document.createElement("button");
    btnCancel.type = "button";
    btnCancel.className = "topbarBtn secondary";
    btnCancel.textContent = t(getLanguage(), "provider_bookings_cancel");
    btnCancel.style.width = "auto";
    btnCancel.style.marginTop = "0";

    btnReschedule.addEventListener("click", async () => {
        const iso = toIso(dateInput.value);
        if (!iso) return;
        await updateBooking(booking.id, "reschedule", {
            starts_at: iso,
            timezone: booking.timezone || "UTC",
        });
        await loadBookings();
    });

    btnCancel.addEventListener("click", async () => {
        await updateBooking(booking.id, "cancel", {});
        await loadBookings();
    });

    row.appendChild(dateInput);
    row.appendChild(btnReschedule);
    row.appendChild(btnCancel);

    if (!isFinal) {
        actions.appendChild(row);
        actions.appendChild(rescheduleHint);
    }

    card.appendChild(title);
    card.appendChild(meta);
    card.appendChild(statusBadge);
    card.appendChild(customer);
    if (!isFinal) {
        card.appendChild(actions);
    }
    return card;
}

async function updateBooking(id, action, payload) {
    try {
        statusUI.setRequestMeta("PATCH", `/api/bookings/${id}/${action}`);
        statusUI.setStatus(t(getLanguage(), "provider_bookings_loading"), "neutral", null);

        const res = await apiRequest(`/bookings/${id}/${action}`, {
            method: "PATCH",
            body: JSON.stringify(payload),
        });
        statusUI.setDebug(res);
        statusUI.setStatus(t(getLanguage(), "provider_bookings_updated"), "ok", 200);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(err.message || t(getLanguage(), "error"), "bad", err.status || 0);
    }
}

async function loadBookings() {
    try {
        statusUI.setRequestMeta("GET", "/api/provider/bookings");
        statusUI.setStatus(t(getLanguage(), "loading"), "neutral", null);

        const res = await apiRequest("/provider/bookings", { method: "GET" });
        statusUI.setDebug(res);

        const list = res?.data || res?.result || res || [];
        bookingsList.innerHTML = "";
        if (!Array.isArray(list) || !list.length) {
            setEmpty();
            statusUI.setStatus(t(getLanguage(), "ready"), "neutral", null);
            return;
        }

        list.forEach((booking) => bookingsList.appendChild(bookingCard(booking)));
        statusUI.setStatus(t(getLanguage(), "ready"), "neutral", null);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(err.message || t(getLanguage(), "error"), "bad", err.status || 0);
        setEmpty();
    }
}

initLang();
loadBookings();
