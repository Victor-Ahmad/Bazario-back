import { apiRequest } from "./api.js";
import { getLanguage, setLanguage } from "./lang.js";
import { t } from "./i18n/index.js";
import { getTimezones } from "./timezones.js";
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

function getLocale() {
    const lang = getLanguage();
    return lang === "ar" ? "ar-EG" : lang === "de" ? "de-DE" : "en-US";
}

function applyTranslations(lang) {
    badgeText.textContent = t(lang, "badge");
    pageTitle.textContent = t(lang, "my_bookings_title");
    pageSubtitle.textContent = t(lang, "my_bookings_subtitle");
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
    msg.textContent = t(getLanguage(), "my_bookings_empty");
    bookingsList.appendChild(msg);
}

function formatDateTime(value) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value || "—";
    return date.toLocaleString(getLocale(), {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
}

function formatTimeInTimezone(value, timeZone) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value || "—";
    return new Intl.DateTimeFormat(getLocale(), {
        timeZone,
        hour: "2-digit",
        minute: "2-digit",
    }).format(date);
}

function formatDateForTimezone(dateLike, timeZone) {
    const date = new Date(dateLike);
    if (Number.isNaN(date.getTime())) return "";
    return new Intl.DateTimeFormat("en-CA", {
        timeZone,
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
    }).format(date);
}

function createField(labelText, control) {
    const field = document.createElement("div");
    field.className = "field";
    field.style.margin = "0";

    const label = document.createElement("label");
    label.textContent = labelText;

    field.appendChild(label);
    field.appendChild(control);
    return field;
}

function buildTimezoneSelect(selectedTimezone) {
    const select = document.createElement("select");
    const allTz = getTimezones();

    allTz.forEach((tz) => {
        const option = document.createElement("option");
        option.value = tz;
        option.textContent = tz;
        select.appendChild(option);
    });

    select.value = allTz.includes(selectedTimezone)
        ? selectedTimezone
        : allTz[0] || "UTC";

    return select;
}

function setSelectedSlotSummary(element, slot, timezone) {
    element.textContent = slot
        ? `${t(getLanguage(), "booking_selected")}: ${formatTimeInTimezone(
              slot.starts_at,
              timezone,
          )} - ${formatTimeInTimezone(slot.ends_at, timezone)}`
        : t(getLanguage(), "booking_select_slot");
}

function setEmptySlots(container) {
    container.innerHTML = "";
    const msg = document.createElement("div");
    msg.style.color = "rgba(255,255,255,0.7)";
    msg.style.fontSize = "13px";
    msg.textContent = t(getLanguage(), "booking_no_slots");
    container.appendChild(msg);
}

function renderSlotGrid(container, booking, state, timezone, onPick) {
    container.innerHTML = "";
    if (!state.currentSlots.length) {
        setEmptySlots(container);
        return;
    }

    const grid = document.createElement("div");
    grid.style.display = "grid";
    grid.style.gridTemplateColumns = "repeat(auto-fit, minmax(160px, 1fr))";
    grid.style.gap = "8px";

    state.currentSlots.forEach((slot) => {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.style.width = "100%";
        btn.style.marginTop = "0";
        btn.className =
            state.selectedSlot?.starts_at === slot.starts_at ? "topbarBtn ok" : "";

        const durationLabel = booking.service?.duration_minutes
            ? ` (${booking.service.duration_minutes} min)`
            : "";
        btn.textContent = `${formatTimeInTimezone(
            slot.starts_at,
            timezone,
        )} - ${formatTimeInTimezone(slot.ends_at, timezone)}${durationLabel}`;

        btn.addEventListener("click", () => onPick(slot));
        grid.appendChild(btn);
    });

    container.appendChild(grid);
}

function createStatusBadge(status) {
    const badge = document.createElement("span");
    const normalized = String(status || "").toLowerCase();

    badge.textContent = status;
    badge.style.display = "inline-flex";
    badge.style.alignItems = "center";
    badge.style.width = "fit-content";
    badge.style.padding = "4px 8px";
    badge.style.borderRadius = "999px";
    badge.style.fontSize = "12px";
    badge.style.fontWeight = "700";
    badge.style.border = "1px solid rgba(255,255,255,0.18)";

    if (normalized.includes("cancelled")) {
        badge.style.color = "#fecaca";
        badge.style.background = "rgba(185, 28, 28, 0.35)";
    } else if (normalized === "completed") {
        badge.style.color = "#bbf7d0";
        badge.style.background = "rgba(21, 128, 61, 0.3)";
    } else {
        badge.style.color = "#bfdbfe";
        badge.style.background = "rgba(30, 64, 175, 0.28)";
    }

    return badge;
}

async function loadAvailabilityForBooking(booking, state, elements) {
    if (!elements.dateInput.value) {
        statusUI.setStatus(t(getLanguage(), "booking_pick_date"), "bad", 0);
        return;
    }

    try {
        const qs = new URLSearchParams({
            date: elements.dateInput.value,
            timezone: elements.timezoneInput.value || "",
            ignore_booking_id: String(booking.id),
        });

        statusUI.setRequestMeta(
            "GET",
            `/api/services/${booking.service_id}/availability?${qs.toString()}`,
        );
        statusUI.setStatus(t(getLanguage(), "availability_loading"), "neutral", null);

        const res = await apiRequest(`/services/${booking.service_id}/availability?${qs}`, {
            method: "GET",
        });
        statusUI.setDebug(res);

        state.currentSlots = res?.slots || [];
        state.selectedSlot = null;
        if (!state.currentSlots.length) {
            setEmptySlots(elements.slotsGrid);
        }
        setSelectedSlotSummary(
            elements.selectedSlotBox,
            state.selectedSlot,
            elements.timezoneInput.value || "UTC",
        );
        elements.rescheduleBtn.disabled = true;
        statusUI.setStatus(t(getLanguage(), "availability_loaded"), "ok", 200);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(
            err.message || t(getLanguage(), "availability_failed"),
            "bad",
            err.status || 0,
        );
        state.currentSlots = [];
        state.selectedSlot = null;
        setEmptySlots(elements.slotsGrid);
        setSelectedSlotSummary(
            elements.selectedSlotBox,
            null,
            elements.timezoneInput.value || "UTC",
        );
        elements.rescheduleBtn.disabled = true;
    }
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
    title.textContent = `${booking.service?.title || "Service"} (#${booking.id})`;

    const meta = document.createElement("div");
    meta.style.fontSize = "13px";
    meta.style.color = "rgba(255,255,255,0.75)";
    meta.textContent = `${formatDateTime(booking.starts_at)} → ${formatDateTime(
        booking.ends_at,
    )}`;

    const provider = document.createElement("div");
    provider.style.fontSize = "12px";
    provider.style.color = "rgba(255,255,255,0.6)";
    provider.textContent = `${t(getLanguage(), "my_bookings_provider")}: ${
        booking.provider_user?.name || "—"
    }`;

    card.appendChild(title);
    card.appendChild(meta);
    card.appendChild(createStatusBadge(booking.status));
    card.appendChild(provider);

    if (isFinal) {
        return card;
    }

    const state = {
        currentSlots: [],
        selectedSlot: null,
    };

    const panel = document.createElement("div");
    panel.style.display = "grid";
    panel.style.gap = "10px";
    panel.style.marginTop = "4px";
    panel.style.paddingTop = "10px";
    panel.style.borderTop = "1px solid rgba(255,255,255,0.08)";

    const controls = document.createElement("div");
    controls.style.display = "grid";
    controls.style.gridTemplateColumns = "repeat(auto-fit, minmax(220px, 1fr))";
    controls.style.gap = "10px";

    const timezoneInput = buildTimezoneSelect(booking.timezone || "UTC");
    const dateInput = document.createElement("input");
    dateInput.type = "date";
    dateInput.value = formatDateForTimezone(
        booking.starts_at,
        timezoneInput.value || "UTC",
    );

    controls.appendChild(createField(t(getLanguage(), "booking_date"), dateInput));
    controls.appendChild(
        createField(t(getLanguage(), "booking_timezone"), timezoneInput),
    );

    const hint = document.createElement("div");
    hint.style.fontSize = "12px";
    hint.style.color = "rgba(255,255,255,0.6)";
    hint.textContent = t(getLanguage(), "my_bookings_reschedule_hint");

    const actionRow = document.createElement("div");
    actionRow.style.display = "flex";
    actionRow.style.gap = "8px";
    actionRow.style.flexWrap = "wrap";

    const checkBtn = document.createElement("button");
    checkBtn.type = "button";
    checkBtn.textContent = t(getLanguage(), "booking_check");
    checkBtn.style.width = "auto";
    checkBtn.style.marginTop = "0";

    const rescheduleBtn = document.createElement("button");
    rescheduleBtn.type = "button";
    rescheduleBtn.textContent = t(getLanguage(), "my_bookings_reschedule");
    rescheduleBtn.style.width = "auto";
    rescheduleBtn.style.marginTop = "0";
    rescheduleBtn.disabled = true;

    const cancelBtn = document.createElement("button");
    cancelBtn.type = "button";
    cancelBtn.className = "topbarBtn secondary";
    cancelBtn.textContent = t(getLanguage(), "my_bookings_cancel");
    cancelBtn.style.width = "auto";
    cancelBtn.style.marginTop = "0";

    actionRow.appendChild(checkBtn);
    actionRow.appendChild(rescheduleBtn);
    actionRow.appendChild(cancelBtn);

    const selectedSlotBox = document.createElement("div");
    selectedSlotBox.className = "statusBox";
    setSelectedSlotSummary(selectedSlotBox, null, timezoneInput.value || "UTC");

    const slotsGrid = document.createElement("div");
    slotsGrid.style.display = "grid";
    slotsGrid.style.gap = "8px";
    setEmptySlots(slotsGrid);

    const renderSlots = () =>
        renderSlotGrid(
            slotsGrid,
            booking,
            state,
            timezoneInput.value || "UTC",
            (slot) => {
                state.selectedSlot = slot;
                renderSlots();
                setSelectedSlotSummary(
                    selectedSlotBox,
                    state.selectedSlot,
                    timezoneInput.value || "UTC",
                );
                rescheduleBtn.disabled = false;
            },
        );

    const clearRescheduleSelection = () => {
        state.currentSlots = [];
        state.selectedSlot = null;
        setEmptySlots(slotsGrid);
        setSelectedSlotSummary(selectedSlotBox, null, timezoneInput.value || "UTC");
        rescheduleBtn.disabled = true;
    };

    dateInput.addEventListener("change", clearRescheduleSelection);
    timezoneInput.addEventListener("change", clearRescheduleSelection);

    checkBtn.addEventListener("click", async () => {
        await loadAvailabilityForBooking(booking, state, {
            dateInput,
            timezoneInput,
            slotsGrid,
            selectedSlotBox,
            rescheduleBtn,
        });
        if (state.currentSlots.length) renderSlots();
    });

    rescheduleBtn.addEventListener("click", async () => {
        if (!state.selectedSlot) {
            statusUI.setStatus(t(getLanguage(), "booking_select_slot"), "bad", 0);
            return;
        }

        await updateBooking(booking.id, "reschedule", {
            starts_at: state.selectedSlot.starts_at,
            ends_at: state.selectedSlot.ends_at,
            timezone: timezoneInput.value || "UTC",
        });
        await loadBookings();
    });

    cancelBtn.addEventListener("click", async () => {
        await updateBooking(booking.id, "cancel", {});
        await loadBookings();
    });

    panel.appendChild(controls);
    panel.appendChild(hint);
    panel.appendChild(actionRow);
    panel.appendChild(selectedSlotBox);
    panel.appendChild(slotsGrid);
    card.appendChild(panel);

    return card;
}

async function updateBooking(id, action, payload) {
    try {
        statusUI.setRequestMeta("PATCH", `/api/bookings/${id}/${action}`);
        statusUI.setStatus(t(getLanguage(), "my_bookings_loading"), "neutral", null);

        const res = await apiRequest(`/bookings/${id}/${action}`, {
            method: "PATCH",
            body: JSON.stringify(payload),
        });
        statusUI.setDebug(res);
        statusUI.setStatus(t(getLanguage(), "my_bookings_updated"), "ok", 200);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(
            err.message || t(getLanguage(), "error"),
            "bad",
            err.status || 0,
        );
    }
}

async function loadBookings() {
    try {
        statusUI.setRequestMeta("GET", "/api/me/bookings");
        statusUI.setStatus(t(getLanguage(), "loading"), "neutral", null);

        const res = await apiRequest("/me/bookings", { method: "GET" });
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
        statusUI.setStatus(
            err.message || t(getLanguage(), "error"),
            "bad",
            err.status || 0,
        );
        setEmpty();
    }
}

initLang();
loadBookings();
