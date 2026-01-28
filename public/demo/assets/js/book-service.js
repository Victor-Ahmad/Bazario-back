import { apiRequest } from "./api.js";
import { addServiceBookingToCart } from "./cart.js";
import { getLanguage, setLanguage } from "./lang.js";
import { t } from "./i18n/index.js";
import { createStatusUI } from "./ui.js";
import { getTimezones } from "./timezones.js";

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
const slotsTitle = document.getElementById("slotsTitle");
const availabilityOverviewTitle = document.getElementById(
    "availabilityOverviewTitle",
);
const availabilityOverviewNote = document.getElementById(
    "availabilityOverviewNote",
);
const availabilitySummary = document.getElementById("availabilitySummary");
const selectedSlotEl = document.getElementById("selectedSlot");
const addBookingBtn = document.getElementById("addBookingBtn");
const checkAvailabilityBtn = document.getElementById("checkAvailabilityBtn");

const lblDate = document.getElementById("lblDate");
const lblTimezone = document.getElementById("lblTimezone");
const timezoneInput = document.getElementById("timezoneInput");
const dateInput = document.getElementById("dateInput");
const slotsGrid = document.getElementById("slotsGrid");

let service = null;
let selectedSlot = null;
let currentSlots = [];
let slotInterval = 15;

function applyTranslations(lang) {
    badgeText.textContent = t(lang, "badge");
    pageTitle.textContent = t(lang, "booking_title");
    pageSubtitle.textContent = t(lang, "booking_subtitle");
    statusTitle.textContent = t(lang, "status");
    slotsTitle.textContent = t(lang, "booking_slots");
    availabilityOverviewTitle.textContent = t(
        lang,
        "booking_availability_overview_title",
    );
    lblDate.textContent = t(lang, "booking_date");
    lblTimezone.textContent = t(lang, "booking_timezone");
    checkAvailabilityBtn.textContent = t(lang, "booking_check");
    addBookingBtn.textContent = t(lang, "booking_add");
    updateAvailabilityNote();
    updateSelectedSlot();
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

function formatTime(value) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
}

function formatDateLabel(dateString) {
    const [year, month, day] = dateString.split("-").map(Number);
    if (!year || !month || !day) return dateString;
    const date = new Date(Date.UTC(year, month - 1, day));
    const lang = getLanguage();
    const locale =
        lang === "ar" ? "ar-EG" : lang === "de" ? "de-DE" : "en-US";
    return date.toLocaleDateString(locale, {
        weekday: "short",
        month: "short",
        day: "numeric",
    });
}

function updateAvailabilityNote() {
    const tz = timezoneInput?.value || "UTC";
    availabilityOverviewNote.textContent = `${t(
        getLanguage(),
        "booking_availability_overview_note",
    )} (${tz})`;
}

function updateSelectedSlot() {
    if (!selectedSlot) {
        selectedSlotEl.textContent = t(getLanguage(), "booking_select_slot");
        addBookingBtn.disabled = true;
        return;
    }
    const text = `${t(getLanguage(), "booking_selected")}: ${formatTime(
        selectedSlot.starts_at,
    )} - ${formatTime(selectedSlot.ends_at)}`;
    selectedSlotEl.textContent = text;
    addBookingBtn.disabled = false;
}

function setEmptySlots() {
    slotsGrid.innerHTML = "";
    const msg = document.createElement("div");
    msg.style.color = "rgba(255,255,255,0.7)";
    msg.style.fontSize = "13px";
    msg.textContent = t(getLanguage(), "booking_no_slots");
    slotsGrid.appendChild(msg);
}

function renderSlots() {
    slotsGrid.innerHTML = "";
    if (!currentSlots.length) {
        setEmptySlots();
        return;
    }

    const times = new Map(
        currentSlots.map((slot) => [slot.starts_at, slot]),
    );
    const starts = currentSlots.map((slot) => new Date(slot.starts_at));
    const ends = currentSlots.map((slot) => new Date(slot.ends_at));

    const minStart = new Date(Math.min(...starts.map((d) => d.getTime())));
    const maxEnd = new Date(Math.max(...ends.map((d) => d.getTime())));

    const step = Math.max(5, Number(slotInterval) || 15);
    const slots = [];
    for (
        let cursor = new Date(minStart.getTime());
        cursor < maxEnd;
        cursor = new Date(cursor.getTime() + step * 60000)
    ) {
        slots.push(cursor);
    }

    const grid = document.createElement("div");
    grid.style.display = "grid";
    grid.style.gridTemplateColumns = "repeat(auto-fit, minmax(140px, 1fr))";
    grid.style.gap = "8px";

    slots.forEach((slotTime) => {
        const iso = slotTime.toISOString();
        const dataKey = currentSlots.find((s) => {
            const dt = new Date(s.starts_at);
            return Math.abs(dt.getTime() - slotTime.getTime()) < 60000;
        })?.starts_at;

        const isAvailable = Boolean(dataKey && times.get(dataKey));
        const btn = document.createElement("button");
        btn.type = "button";
        const durationLabel = service?.duration_minutes
            ? ` (${service.duration_minutes} min)`
            : "";
        btn.textContent = `${formatTime(slotTime)}${durationLabel}`;
        btn.style.width = "100%";
        btn.style.marginTop = "0";
        btn.style.opacity = isAvailable ? "1" : "0.45";
        if (!isAvailable) {
            btn.disabled = true;
        }

        btn.addEventListener("click", () => {
            selectedSlot = times.get(dataKey);
            updateSelectedSlot();
        });

        grid.appendChild(btn);
    });

    slotsGrid.appendChild(grid);
}

function renderAvailabilitySummary(days) {
    availabilitySummary.innerHTML = "";
    if (!days.length) {
        const empty = document.createElement("div");
        empty.style.color = "rgba(255,255,255,0.7)";
        empty.style.fontSize = "13px";
        empty.textContent = t(getLanguage(), "booking_availability_empty");
        availabilitySummary.appendChild(empty);
        return;
    }

    const grid = document.createElement("div");
    grid.style.display = "grid";
    grid.style.gridTemplateColumns = "repeat(auto-fit, minmax(170px, 1fr))";
    grid.style.gap = "8px";

    days.forEach((day) => {
        const card = document.createElement("div");
        card.style.border = "1px solid rgba(255,255,255,0.08)";
        card.style.borderRadius = "12px";
        card.style.padding = "10px";
        card.style.background = "rgba(255,255,255,0.03)";
        card.style.display = "grid";
        card.style.gap = "6px";

        const title = document.createElement("div");
        title.style.fontWeight = "600";
        title.textContent = formatDateLabel(day.date);

        const count = document.createElement("div");
        count.style.fontSize = "13px";
        count.style.color = "rgba(255,255,255,0.7)";
        count.textContent = `${t(
            getLanguage(),
            "booking_availability_slots",
        )}: ${day.count}`;

        const range = document.createElement("div");
        range.style.fontSize = "13px";
        range.style.color = "rgba(255,255,255,0.7)";
        if (day.first && day.last) {
            range.textContent = `${formatTime(day.first)} - ${formatTime(
                day.last,
            )}`;
        } else {
            range.textContent = t(
                getLanguage(),
                "booking_availability_no_times",
            );
        }

        card.appendChild(title);
        card.appendChild(count);
        card.appendChild(range);
        grid.appendChild(card);
    });

    availabilitySummary.appendChild(grid);
}

function formatDateForTimezone(date, timeZone) {
    const formatter = new Intl.DateTimeFormat("en-CA", {
        timeZone,
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
    });
    return formatter.format(date);
}

async function loadAvailabilitySummary() {
    if (!service?.id) return;

    availabilitySummary.innerHTML = "";
    const loading = document.createElement("div");
    loading.style.color = "rgba(255,255,255,0.7)";
    loading.style.fontSize = "13px";
    loading.textContent = t(getLanguage(), "booking_availability_loading");
    availabilitySummary.appendChild(loading);

    const tz = timezoneInput.value || "UTC";
    const base = new Date();
    const days = Array.from({ length: 7 }, (_, idx) => {
        const next = new Date(base);
        next.setDate(base.getDate() + idx);
        return formatDateForTimezone(next, tz);
    });

    try {
        const results = await Promise.allSettled(
            days.map((date) =>
                apiRequest(
                    `/services/${service.id}/availability?${new URLSearchParams({
                        date,
                        timezone: tz,
                    })}`,
                    { method: "GET" },
                ),
            ),
        );

        const summary = results.map((result, idx) => {
            if (result.status !== "fulfilled") {
                return { date: days[idx], count: 0, first: null, last: null };
            }
            const slots = result.value?.slots ?? [];
            return {
                date: days[idx],
                count: slots.length,
                first: slots[0]?.starts_at || null,
                last: slots[slots.length - 1]?.ends_at || null,
            };
        });

        renderAvailabilitySummary(summary);
    } catch (err) {
        renderAvailabilitySummary([]);
    }
}

async function loadAvailability() {
    if (!service?.id) return;
    if (!dateInput.value) {
        statusUI.setStatus(t(getLanguage(), "booking_pick_date"), "bad", 0);
        return;
    }
    try {
        statusUI.setRequestMeta("GET", `/api/services/${service.id}/availability`);
        statusUI.setStatus(t(getLanguage(), "availability_loading"), "neutral", null);

        const qs = new URLSearchParams({
            date: dateInput.value,
            timezone: timezoneInput.value || "",
        });
        const res = await apiRequest(`/services/${service.id}/availability?${qs}`, {
            method: "GET",
        });
        statusUI.setDebug(res);

        currentSlots = res?.slots || [];
        slotInterval = res?.slot_interval_minutes || 15;
        selectedSlot = null;
        renderSlots();
        updateSelectedSlot();

        statusUI.setStatus(t(getLanguage(), "availability_loaded"), "ok", 200);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(
            err.message || t(getLanguage(), "availability_failed"),
            "bad",
            err.status || 0,
        );
        currentSlots = [];
        selectedSlot = null;
        renderSlots();
        updateSelectedSlot();
    }
}

async function initPage() {
    initLang();

    const allTz = getTimezones();
    timezoneInput.innerHTML = "";
    allTz.forEach((tz) => {
        const opt = document.createElement("option");
        opt.value = tz;
        opt.textContent = tz;
        timezoneInput.appendChild(opt);
    });
    timezoneInput.value = allTz[0] || "UTC";
    updateAvailabilityNote();

    service = JSON.parse(sessionStorage.getItem("pending_service") || "null");
    if (!service) {
        const params = new URLSearchParams(window.location.search);
        const serviceId = params.get("service_id");
        if (serviceId) {
            try {
                const res = await apiRequest(`/services?per_page=200`, {
                    method: "GET",
                });
                const result = res?.result ?? res;
                const items = result?.data ?? [];
                service =
                    items.find((s) => String(s.id) === String(serviceId)) || null;
            } catch (err) {
                statusUI.setDebug(err.data || { error: err.message });
            }
        }
    }

    if (!service) {
        statusUI.setStatus(t(getLanguage(), "booking_missing_service"), "bad", 400);
        return;
    }

    checkAvailabilityBtn.addEventListener("click", loadAvailability);
    timezoneInput.addEventListener("change", () => {
        updateAvailabilityNote();
        loadAvailabilitySummary();
    });

    addBookingBtn.addEventListener("click", () => {
        if (!service || !selectedSlot) return;
        addServiceBookingToCart(service, selectedSlot, timezoneInput.value);
        statusUI.setStatus(t(getLanguage(), "cart_add_success"), "ok", 200);
    });

    loadAvailabilitySummary();
}

initPage();
