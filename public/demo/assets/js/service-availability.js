import { apiRequest } from "./api.js";
import { getLanguage, setLanguage } from "./lang.js";
import { t } from "./i18n/index.js";
import { createStatusUI, clearErrors } from "./ui.js";
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
const timeOffTitle = document.getElementById("timeOffTitle");

const lblTimezone = document.getElementById("lblTimezone");
const lblWorkingHours = document.getElementById("lblWorkingHours");
const lblTimeOffStart = document.getElementById("lblTimeOffStart");
const lblTimeOffEnd = document.getElementById("lblTimeOffEnd");
const lblTimeOffReason = document.getElementById("lblTimeOffReason");
const lblTimeOffHoliday = document.getElementById("lblTimeOffHoliday");

const timezoneInput = document.getElementById("timezone");
const workingHoursGrid = document.getElementById("workingHoursGrid");
const timeOffList = document.getElementById("timeOffList");

const hoursForm = document.getElementById("hoursForm");
const timeOffForm = document.getElementById("timeOffForm");

const saveHoursBtn = document.getElementById("saveHoursBtn");
const addTimeOffBtn = document.getElementById("addTimeOffBtn");

function applyTranslations(lang) {
    badgeText.textContent = t(lang, "badge");
    pageTitle.textContent = t(lang, "availability_title");
    pageSubtitle.textContent = t(lang, "availability_subtitle");
    statusTitle.textContent = t(lang, "status");
    timeOffTitle.textContent = t(lang, "availability_timeoff_title");

    lblTimezone.textContent = t(lang, "availability_timezone");
    lblWorkingHours.textContent = t(lang, "availability_working_hours");
    lblTimeOffStart.textContent = t(lang, "availability_timeoff_start");
    lblTimeOffEnd.textContent = t(lang, "availability_timeoff_end");
    lblTimeOffReason.textContent = t(lang, "availability_timeoff_reason");
    lblTimeOffHoliday.textContent = t(lang, "availability_timeoff_holiday");

    saveHoursBtn.textContent = t(lang, "availability_save_hours");
    addTimeOffBtn.textContent = t(lang, "availability_add_timeoff");

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

function populateTimezoneOptions() {
    if (!timezoneInput) return;
    timezoneInput.innerHTML = "";
    getTimezones().forEach((tz) => {
        const opt = document.createElement("option");
        opt.value = tz;
        opt.textContent = tz;
        timezoneInput.appendChild(opt);
    });
}
function setEmpty() {
    timeOffList.innerHTML = "";
    const el = document.createElement("div");
    el.style.color = "rgba(255,255,255,0.7)";
    el.style.fontSize = "13px";
    el.textContent = t(getLanguage(), "availability_timeoff_empty");
    timeOffList.appendChild(el);
}

function renderTimeOff(items) {
    timeOffList.innerHTML = "";
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
        card.style.fontSize = "13px";
        card.style.color = "rgba(255,255,255,0.75)";

        const title = document.createElement("div");
        title.style.fontWeight = "700";
        title.style.color = "rgba(255,255,255,0.95)";
        title.textContent = `#${item.id}`;

        const range = document.createElement("div");
        range.textContent = `${item.starts_at} → ${item.ends_at}`;

        const reason = document.createElement("div");
        reason.textContent = item.reason || "—";

        const holiday = document.createElement("div");
        holiday.textContent = item.is_holiday
            ? t(getLanguage(), "availability_holiday_yes")
            : t(getLanguage(), "availability_holiday_no");

        card.appendChild(title);
        card.appendChild(range);
        card.appendChild(reason);
        card.appendChild(holiday);
        timeOffList.appendChild(card);
    });
}

const dayLabels = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

function createIntervalRow(dayIndex, interval = {}) {
    const row = document.createElement("div");
    row.className = "intervalRow";
    row.dataset.day = String(dayIndex);

    const start = document.createElement("input");
    start.type = "time";
    start.value = interval.start_time || "";
    start.placeholder = "09:00";

    const end = document.createElement("input");
    end.type = "time";
    end.value = interval.end_time || "";
    end.placeholder = "17:00";

    const removeBtn = document.createElement("button");
    removeBtn.type = "button";
    removeBtn.className = "miniBtn secondary";
    removeBtn.textContent = "Remove";
    removeBtn.addEventListener("click", () => row.remove());

    row.appendChild(start);
    row.appendChild(end);
    row.appendChild(removeBtn);

    return row;
}

function renderWorkingHours(workingHours = []) {
    workingHoursGrid.innerHTML = "";

    const dayMap = new Map();
    workingHours.forEach((wh) => {
        if (!dayMap.has(wh.day_of_week)) dayMap.set(wh.day_of_week, []);
        dayMap.get(wh.day_of_week).push({
            start_time: wh.start_time,
            end_time: wh.end_time,
        });
    });

    for (let i = 0; i < 7; i += 1) {
        const card = document.createElement("div");
        card.className = "dayCard";
        card.dataset.day = String(i);

        const header = document.createElement("div");
        header.className = "dayHeader";

        const title = document.createElement("div");
        title.className = "dayTitle";
        title.textContent = dayLabels[i];

        const addBtn = document.createElement("button");
        addBtn.type = "button";
        addBtn.className = "miniBtn";
        addBtn.textContent = "Add interval";

        const list = document.createElement("div");
        list.className = "intervalList";

        addBtn.addEventListener("click", () => {
            list.appendChild(createIntervalRow(i));
        });

        header.appendChild(title);
        header.appendChild(addBtn);
        card.appendChild(header);
        card.appendChild(list);

        const intervals = dayMap.get(i) || [];
        intervals.forEach((interval) => {
            list.appendChild(createIntervalRow(i, interval));
        });

        workingHoursGrid.appendChild(card);
    }
}

function collectWorkingHours() {
    const days = [];
    const dayCards = workingHoursGrid.querySelectorAll(".dayCard");
    let hasError = false;

    dayCards.forEach((card) => {
        const day = Number(card.dataset.day);
        const intervals = [];
        card.querySelectorAll(".intervalRow").forEach((row) => {
            const inputs = row.querySelectorAll("input");
            const start = inputs[0]?.value || "";
            const end = inputs[1]?.value || "";

            if (!start || !end) {
                hasError = true;
                return;
            }

            intervals.push({ start_time: start, end_time: end });
        });

        if (intervals.length) {
            days.push({ day_of_week: day, intervals });
        }
    });

    if (hasError) {
        statusUI.setStatus(
            t(getLanguage(), "availability_hours_invalid"),
            "bad",
            422,
        );
        return null;
    }

    return days;
}

async function loadAvailability() {
    try {
        statusUI.setRequestMeta("GET", "/api/service_provider/availability");
        statusUI.setStatus(t(getLanguage(), "availability_loading"), "neutral", null);

        const res = await apiRequest("/service_provider/availability", {
            method: "GET",
        });
        statusUI.setDebug(res);

        timezoneInput.value = res?.timezone || "";
        const workingHours = res?.workingHours ?? res?.working_hours ?? [];
        renderWorkingHours(workingHours);

        const timeOffs = res?.timeOffs ?? res?.time_offs ?? [];
        renderTimeOff(timeOffs);

        statusUI.setStatus(t(getLanguage(), "availability_loaded"), "ok", 200);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(
            err.message || t(getLanguage(), "availability_failed"),
            "bad",
            err.status || 0,
        );
        renderWorkingHours([]);
        setEmpty();
    }
}

initLang();
populateTimezoneOptions();
loadAvailability();

hoursForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearErrors(hoursForm);

    const days = collectWorkingHours();
    if (!days) return;

    saveHoursBtn.disabled = true;
    try {
        statusUI.setRequestMeta("PUT", "/api/service_provider/working-hours");
        statusUI.setStatus(t(getLanguage(), "availability_saving"), "neutral", null);

        const res = await apiRequest("/service_provider/working-hours", {
            method: "PUT",
            body: JSON.stringify({
                timezone: timezoneInput.value || undefined,
                days,
            }),
        });

        statusUI.setDebug(res);
        statusUI.setStatus(t(getLanguage(), "availability_saved"), "ok", 200);
        loadAvailability();
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(
            err.message || t(getLanguage(), "availability_failed"),
            "bad",
            err.status || 0,
        );
    } finally {
        saveHoursBtn.disabled = false;
    }
});

timeOffForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearErrors(timeOffForm);

    addTimeOffBtn.disabled = true;
    try {
        statusUI.setRequestMeta("POST", "/api/service_provider/time-off");
        statusUI.setStatus(t(getLanguage(), "availability_saving"), "neutral", null);

        const payload = new FormData(timeOffForm);
        const res = await apiRequest("/service_provider/time-off", {
            method: "POST",
            body: JSON.stringify(Object.fromEntries(payload.entries())),
        });

        statusUI.setDebug(res);
        statusUI.setStatus(t(getLanguage(), "availability_saved"), "ok", 200);
        timeOffForm.reset();
        loadAvailability();
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(
            err.message || t(getLanguage(), "availability_failed"),
            "bad",
            err.status || 0,
        );
    } finally {
        addTimeOffBtn.disabled = false;
    }
});
