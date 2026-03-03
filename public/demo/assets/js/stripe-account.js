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
const balancesTitle = document.getElementById("balancesTitle");
const pendingTitle = document.getElementById("pendingTitle");
const transfersTitle = document.getElementById("transfersTitle");

const accountSummary = document.getElementById("accountSummary");
const stripeBalanceList = document.getElementById("stripeBalanceList");
const pendingBalanceList = document.getElementById("pendingBalanceList");
const transferList = document.getElementById("transferList");

function applyTranslations(lang) {
    badgeText.textContent = t(lang, "badge");
    pageTitle.textContent = t(lang, "stripe_account_title");
    pageSubtitle.textContent = t(lang, "stripe_account_subtitle");
    statusTitle.textContent = t(lang, "status");
    balancesTitle.textContent = t(lang, "stripe_account_balances");
    pendingTitle.textContent = t(lang, "stripe_account_pending");
    transfersTitle.textContent = t(lang, "stripe_account_transfers");
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
        loadSummary();
    });
}

function money(cents, currency) {
    return `${(Number(cents || 0) / 100).toFixed(2)} ${currency || "EUR"}`;
}

function createCard() {
    const card = document.createElement("div");
    card.style.border = "1px solid rgba(255,255,255,0.12)";
    card.style.borderRadius = "12px";
    card.style.background = "rgba(0,0,0,0.12)";
    card.style.padding = "10px 12px";
    card.style.display = "grid";
    card.style.gap = "6px";
    return card;
}

function setEmpty(target, text) {
    target.innerHTML = "";
    const msg = document.createElement("div");
    msg.style.color = "rgba(255,255,255,0.72)";
    msg.style.fontSize = "13px";
    msg.textContent = text;
    target.appendChild(msg);
}

function detail(title, value) {
    const row = document.createElement("div");
    row.style.fontSize = "13px";
    row.style.color = "rgba(255,255,255,0.76)";
    row.innerHTML = `<strong style="color: rgba(255,255,255,0.92)">${title}</strong>`;
    const body = document.createElement("div");
    body.textContent = value;
    row.appendChild(body);
    return row;
}

function renderAccountSummary(data) {
    accountSummary.innerHTML = "";

    const card = createCard();
    const connected = !!data?.connected;
    const account = data?.account;

    card.appendChild(
        detail(
            t(getLanguage(), "stripe_account_connect_status"),
            !connected
                ? t(getLanguage(), "upgrade_connect_not_connected")
                : account?.payouts_enabled
                  ? t(getLanguage(), "upgrade_connect_ready")
                  : account?.details_submitted
                    ? t(getLanguage(), "upgrade_connect_connected")
                    : t(getLanguage(), "upgrade_connect_incomplete"),
        ),
    );
    card.appendChild(
        detail(
            t(getLanguage(), "stripe_account_id_label"),
            account?.stripe_account_id || "—",
        ),
    );

    accountSummary.appendChild(card);
}

function renderBalances(target, availableRows, pendingRows) {
    target.innerHTML = "";
    const rows = [
        ...availableRows.map((row) => ({
            label: t(getLanguage(), "stripe_account_available"),
            ...row,
        })),
        ...pendingRows.map((row) => ({
            label: t(getLanguage(), "stripe_account_pending_stripe"),
            ...row,
        })),
    ];

    if (!rows.length) {
        setEmpty(target, t(getLanguage(), "stripe_account_empty_balances"));
        return;
    }

    rows.forEach((row) => {
        const card = createCard();
        card.appendChild(detail(row.label, money(row.amount, row.currency_iso)));
        target.appendChild(card);
    });
}

function renderPendingBalances(rows) {
    pendingBalanceList.innerHTML = "";
    if (!rows?.length) {
        setEmpty(pendingBalanceList, t(getLanguage(), "stripe_account_empty_pending"));
        return;
    }

    rows.forEach((row) => {
        const card = createCard();
        card.appendChild(
            detail(
                t(getLanguage(), "stripe_account_pending_platform"),
                money(row.amount, row.currency_iso),
            ),
        );
        pendingBalanceList.appendChild(card);
    });
}

function renderTransfers(rows) {
    transferList.innerHTML = "";
    if (!rows?.length) {
        setEmpty(transferList, t(getLanguage(), "stripe_account_empty_transfers"));
        return;
    }

    rows.forEach((row) => {
        const card = createCard();
        card.appendChild(
            detail(
                t(getLanguage(), "stripe_account_transfer_amount"),
                money(row.amount, row.currency_iso),
            ),
        );
        card.appendChild(
            detail(
                t(getLanguage(), "stripe_account_transfer_id"),
                row.transfer_id || "—",
            ),
        );
        card.appendChild(
            detail(
                t(getLanguage(), "stripe_account_transfer_order"),
                row.order_id ? `#${row.order_id}` : "—",
            ),
        );
        card.appendChild(
            detail(
                t(getLanguage(), "stripe_account_transfer_status"),
                row.status || "—",
            ),
        );
        card.appendChild(
            detail(
                t(getLanguage(), "stripe_account_transfer_date"),
                row.created_at
                    ? new Date(row.created_at).toLocaleString()
                    : "—",
            ),
        );
        transferList.appendChild(card);
    });
}

async function loadSummary() {
    try {
        statusUI.setRequestMeta("GET", "/api/connect/summary");
        statusUI.setStatus(t(getLanguage(), "loading"), "neutral", null);

        const res = await apiRequest("/connect/summary", { method: "GET" });
        statusUI.setDebug(res);

        renderAccountSummary(res);
        renderBalances(
            stripeBalanceList,
            res?.stripe_balance?.available || [],
            res?.stripe_balance?.pending || [],
        );
        renderPendingBalances(res?.platform_pending_balance || []);
        renderTransfers(res?.transfers || []);

        statusUI.setStatus(t(getLanguage(), "ready"), "neutral", null);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(err.message || t(getLanguage(), "error"), "bad", err.status || 0);
        setEmpty(accountSummary, t(getLanguage(), "stripe_account_empty_transfers"));
        setEmpty(stripeBalanceList, t(getLanguage(), "stripe_account_empty_balances"));
        setEmpty(pendingBalanceList, t(getLanguage(), "stripe_account_empty_pending"));
        setEmpty(transferList, t(getLanguage(), "stripe_account_empty_transfers"));
    }
}

initLang();
loadSummary();
