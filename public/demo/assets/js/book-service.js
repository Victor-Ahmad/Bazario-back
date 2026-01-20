import { apiRequest } from "./api.js";
import { addServiceBookingToCart } from "./cart.js";
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
const slotsTitle = document.getElementById("slotsTitle");
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
    lblDate.textContent = t(lang, "booking_date");
    lblTimezone.textContent = t(lang, "booking_timezone");
    checkAvailabilityBtn.textContent = t(lang, "booking_check");
    addBookingBtn.textContent = t(lang, "booking_add");
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

    const timezones = [
        "UTC",
        "Europe/Berlin",
        "Europe/London",
        "Europe/Istanbul",
        "Asia/Dubai",
        "Asia/Riyadh",
        "Asia/Amman",
        "Asia/Beirut",
        "Asia/Damascus",
        "Africa/Cairo",
        "America/New_York",
        "America/Los_Angeles",
    ];
    const browserTz = Intl.DateTimeFormat().resolvedOptions().timeZone || "UTC";
    const allTz = Array.from(new Set([browserTz, ...timezones]));
    timezoneInput.innerHTML = "";
    allTz.forEach((tz) => {
        const opt = document.createElement("option");
        opt.value = tz;
        opt.textContent = tz;
        if (tz === browserTz) opt.selected = true;
        timezoneInput.appendChild(opt);
    });

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

    addBookingBtn.addEventListener("click", () => {
        if (!service || !selectedSlot) return;
        addServiceBookingToCart(service, selectedSlot, timezoneInput.value);
        statusUI.setStatus(t(getLanguage(), "cart_add_success"), "ok", 200);
    });
}

initPage();
