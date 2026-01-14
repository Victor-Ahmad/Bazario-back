import { config } from "./config.js";

/**
 * Creates a status controller for a page.
 * Expects:
 * - statusBox: element showing message
 * - statusPill: element with children .code and .label
 * - statusMeta (optional): element to show request method/path
 * - debugBox (optional): pre/code element for debug JSON
 */
export function createStatusUI({
    statusBox,
    statusPill,
    statusMeta,
    debugBox,
}) {
    function statusTextFromCode(code) {
        if (!code && code !== 0) return "Not sent";
        if (code >= 200 && code < 300) return "OK";
        if (code === 400) return "Bad request";
        if (code === 401) return "Unauthorized";
        if (code === 403) return "Forbidden";
        if (code === 404) return "Not found";
        if (code === 409) return "Conflict";
        if (code === 422) return "Validation";
        if (code >= 500) return "Server error";
        return "Error";
    }

    function setPill(type, code) {
        if (!statusPill) return;

        const codeEl = statusPill.querySelector(".code");
        const labelEl = statusPill.querySelector(".label");

        statusPill.classList.remove("ok", "bad");
        if (type === "ok") statusPill.classList.add("ok");
        if (type === "bad") statusPill.classList.add("bad");

        if (codeEl)
            codeEl.textContent = code || code === 0 ? String(code) : "—";
        if (labelEl) labelEl.textContent = statusTextFromCode(code);
    }

    function setStatus(msg, type = "neutral", httpCode = null) {
        if (statusBox) statusBox.textContent = msg || "";
        if (statusBox) {
            statusBox.classList.remove("ok", "bad");
            if (type === "ok") statusBox.classList.add("ok");
            if (type === "bad") statusBox.classList.add("bad");
        }
        setPill(type, httpCode);
    }

    function setRequestMeta(method, path) {
        if (!statusMeta) return;
        statusMeta.innerHTML = `<code>${method}</code> <code>${path}</code>`;
    }

    function setDebug(obj) {
        if (!config.debug || !debugBox) return;
        debugBox.style.display = "block";
        debugBox.textContent = JSON.stringify(obj, null, 2);
    }

    return { setStatus, setRequestMeta, setDebug };
}

/**
 * Field errors + styles
 */
export function clearErrors(formEl) {
    document
        .querySelectorAll("[data-error-for]")
        .forEach((el) => (el.textContent = ""));
    if (formEl) {
        formEl.querySelectorAll("input, select, textarea").forEach((el) => {
            el.classList.remove("isInvalid", "isValid");
        });
    }
}

export function setFieldError(field, message) {
    const el = document.querySelector(`[data-error-for="${field}"]`);
    if (el) el.textContent = message || "";
}

export function markInvalid(field) {
    const input = document.querySelector(`[name="${field}"]`);
    if (input) input.classList.add("isInvalid");
}

export function showFieldErrors(errorsObj) {
    const errors = errorsObj || {};
    Object.entries(errors).forEach(([field, messages]) => {
        const el = document.querySelector(`[data-error-for="${field}"]`);
        if (el)
            el.textContent = Array.isArray(messages)
                ? messages[0]
                : String(messages);
        markInvalid(field);
    });
    return errors;
}

export function markValidAllFilled(formEl) {
    if (!formEl) return;
    formEl.querySelectorAll("input, select, textarea").forEach((el) => {
        if (el.value && el.type !== "password") el.classList.add("isValid");
    });
}

/**
 * Convert form to a JS object.
 * - Strips empty strings by default
 */
export function formToObject(formEl) {
    const fd = new FormData(formEl);
    const obj = Object.fromEntries(fd.entries());

    // remove empty strings
    Object.keys(obj).forEach((k) => {
        if (obj[k] === "") delete obj[k];
    });

    return obj;
}
