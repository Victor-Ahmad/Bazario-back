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
const listTitle = document.getElementById("listTitle");
const payoutList = document.getElementById("payoutList");
const payAllBtn = document.getElementById("payAllBtn");

function applyTranslations(lang) {
    badgeText.textContent = t(lang, "badge");
    pageTitle.textContent = t(lang, "admin_payouts_title");
    pageSubtitle.textContent = t(lang, "admin_payouts_subtitle");
    statusTitle.textContent = t(lang, "status");
    listTitle.textContent = t(lang, "admin_payouts_accounts");
    payAllBtn.textContent = t(lang, "admin_payouts_pay_all");
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
        loadPayouts();
    });
}

function formatAmount(cents, currency) {
    const amount = Number(cents || 0) / 100;
    const code = currency || "EUR";
    return `${amount.toFixed(2)} ${code}`;
}

function setEmpty(text) {
    payoutList.innerHTML = "";
    const msg = document.createElement("div");
    msg.style.color = "rgba(255,255,255,0.7)";
    msg.style.fontSize = "13px";
    msg.textContent = text;
    payoutList.appendChild(msg);
}

function detailRow(label, value) {
    const row = document.createElement("div");
    row.style.display = "grid";
    row.style.gap = "4px";
    row.style.fontSize = "13px";
    row.style.color = "rgba(255,255,255,0.75)";
    row.innerHTML = `<strong style="color: rgba(255,255,255,0.9)">${label}</strong>`;
    const span = document.createElement("div");
    span.textContent = value;
    row.appendChild(span);
    return row;
}

function buildCard(item) {
    const card = document.createElement("div");
    card.style.border = "1px solid rgba(255,255,255,0.12)";
    card.style.borderRadius = "12px";
    card.style.background = "rgba(0,0,0,0.12)";
    card.style.padding = "12px";
    card.style.display = "grid";
    card.style.gap = "10px";

    const title = document.createElement("div");
    title.style.fontWeight = "700";
    title.textContent = `${item.name || "User"} (#${item.id})`;

    const subtitle = document.createElement("div");
    subtitle.style.color = "rgba(255,255,255,0.68)";
    subtitle.style.fontSize = "13px";
    const roles = Array.isArray(item.roles) ? item.roles.join(", ") : "";
    subtitle.textContent = `${item.email || "—"}${roles ? ` • ${roles}` : ""}`;

    const connect = item.connect_account;
    const connectLabel = connect
        ? connect.payouts_enabled
            ? t(getLanguage(), "admin_payouts_connect_ready")
            : t(getLanguage(), "admin_payouts_connect_incomplete")
        : t(getLanguage(), "admin_payouts_connect_missing");

    const fields = document.createElement("div");
    fields.style.display = "grid";
    fields.style.gap = "8px";
    fields.appendChild(detailRow(t(getLanguage(), "admin_payouts_connect"), connectLabel));

    if (item.balances?.length) {
        const balancesText = item.balances
            .map((b) => formatAmount(b.amount, b.currency_iso))
            .join(" • ");
        fields.appendChild(
            detailRow(t(getLanguage(), "admin_payouts_balance"), balancesText),
        );
    } else {
        fields.appendChild(
            detailRow(
                t(getLanguage(), "admin_payouts_balance"),
                t(getLanguage(), "admin_payouts_no_balances"),
            ),
        );
    }

    const actions = document.createElement("div");
    actions.style.display = "flex";
    actions.style.gap = "10px";
    actions.style.flexWrap = "wrap";

    const btnPay = document.createElement("button");
    btnPay.type = "button";
    btnPay.textContent = t(getLanguage(), "admin_payouts_pay");
    btnPay.style.width = "auto";
    btnPay.style.marginTop = "0";

    if (!item.balances?.length) {
        btnPay.disabled = true;
    }

    actions.appendChild(btnPay);

    card.appendChild(title);
    card.appendChild(subtitle);
    card.appendChild(fields);
    card.appendChild(actions);

    btnPay.addEventListener("click", async () => {
        await payoutUser(item.id, btnPay, card);
    });

    return card;
}

async function payoutUser(userId, btn, card) {
    try {
        btn.disabled = true;
        statusUI.setRequestMeta("POST", `/api/admin/payouts/${userId}/pay`);
        statusUI.setStatus(t(getLanguage(), "admin_payouts_loading"), "neutral", null);

        const res = await apiRequest(`/admin/payouts/${userId}/pay`, {
            method: "POST",
        });
        statusUI.setDebug(res);
        statusUI.setStatus(t(getLanguage(), "admin_payouts_success"), "ok", 200);
        await loadPayouts();
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(err.message || "Payout failed", "bad", err.status || 0);
        btn.disabled = false;
    }
}

async function payoutAll() {
    try {
        payAllBtn.disabled = true;
        statusUI.setRequestMeta("POST", "/api/admin/payouts/pay-all");
        statusUI.setStatus(t(getLanguage(), "admin_payouts_loading"), "neutral", null);

        const res = await apiRequest("/admin/payouts/pay-all", { method: "POST" });
        statusUI.setDebug(res);
        statusUI.setStatus(t(getLanguage(), "admin_payouts_success"), "ok", 200);
        await loadPayouts();
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(err.message || "Payout failed", "bad", err.status || 0);
    } finally {
        payAllBtn.disabled = false;
    }
}

async function loadPayouts() {
    try {
        statusUI.setRequestMeta("GET", "/api/admin/payouts");
        statusUI.setStatus(t(getLanguage(), "loading"), "neutral", null);

        const res = await apiRequest("/admin/payouts", { method: "GET" });
        statusUI.setDebug(res);

        const list = res?.result || [];
        payoutList.innerHTML = "";
        if (!list.length) {
            setEmpty(t(getLanguage(), "admin_payouts_empty"));
            statusUI.setStatus(t(getLanguage(), "ready"), "neutral", null);
            return;
        }

        list.forEach((item) => {
            payoutList.appendChild(buildCard(item));
        });

        statusUI.setStatus(t(getLanguage(), "ready"), "neutral", null);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(err.message || t(getLanguage(), "error"), "bad", err.status || 0);
        setEmpty(t(getLanguage(), "admin_payouts_empty"));
    }
}

function initPage() {
    initLang();
    payAllBtn.addEventListener("click", payoutAll);
    loadPayouts();
}

initPage();
