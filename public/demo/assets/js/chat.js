import { apiRequest } from "./api.js";
import { getLanguage, setLanguage } from "./lang.js";
import { t } from "./i18n/index.js";
import { createStatusUI } from "./ui.js";
import { getAuthSession } from "./auth.js";
import { getToken } from "./auth.js";
import { config } from "./config.js";

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

const searchInput = document.getElementById("searchInput");
const chatList = document.getElementById("chatList");

const threadTitle = document.getElementById("threadTitle");
const threadBox = document.getElementById("threadBox");
const messageForm = document.getElementById("messageForm");
const messageInput = document.getElementById("messageInput");
const sendBtn = document.getElementById("sendBtn");
const unreadBadge = document.getElementById("unreadBadge");

let conversations = [];
let activeConversation = null;
let chatChannel = null;
let userChannel = null;
let pusher = null;

function applyTranslations(lang) {
    pageTitle.textContent = t(lang, "chat_title");
    pageSubtitle.textContent = t(lang, "chat_subtitle");
    searchInput.placeholder = t(lang, "chat_search");
    messageInput.placeholder = t(lang, "chat_message_placeholder");
    sendBtn.textContent = t(lang, "chat_send");
    statusTitle.textContent = t(lang, "status");
    if (badgeText) badgeText.textContent = t(lang, "badge");
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

function setUnreadCount(count) {
    const code = unreadBadge.querySelector(".code");
    if (code) code.textContent = String(count || 0);
}

function setEmptyList() {
    chatList.innerHTML = "";
    const msg = document.createElement("div");
    msg.style.color = "rgba(255,255,255,0.7)";
    msg.style.fontSize = "13px";
    msg.textContent = t(getLanguage(), "chat_empty");
    chatList.appendChild(msg);
}

function renderChatList() {
    chatList.innerHTML = "";
    if (!conversations.length) {
        setEmptyList();
        return;
    }

    conversations.forEach((c) => {
        const item = document.createElement("button");
        item.type = "button";
        item.style.textAlign = "left";
        item.style.width = "100%";
        item.style.marginTop = "0";
        item.style.display = "grid";
        item.style.gap = "4px";
        item.style.padding = "10px 12px";
        item.style.borderRadius = "10px";
        item.style.border = "1px solid rgba(255,255,255,0.12)";
        item.style.background = "rgba(0,0,0,0.12)";

        const title = document.createElement("div");
        title.style.fontWeight = "700";
        title.textContent = c.peer?.name || c.peer?.email || `#${c.id}`;

        const last = document.createElement("div");
        last.style.fontSize = "12px";
        last.style.color = "rgba(255,255,255,0.7)";
        last.textContent = c.latest_message?.body || "—";

        if (c.unread_count > 0) {
            const badge = document.createElement("span");
            badge.textContent = `• ${c.unread_count}`;
            last.appendChild(badge);
        }

        item.appendChild(title);
        item.appendChild(last);
        item.addEventListener("click", () => openConversation(c.id));
        chatList.appendChild(item);
    });
}

function renderThread(messages) {
    threadBox.innerHTML = "";
    if (!messages?.length) {
        const msg = document.createElement("div");
        msg.style.color = "rgba(255,255,255,0.7)";
        msg.style.fontSize = "13px";
        msg.textContent = t(getLanguage(), "chat_select_prompt");
        threadBox.appendChild(msg);
        return;
    }

    messages.forEach((m) => {
        const bubble = document.createElement("div");
        bubble.style.padding = "8px 10px";
        bubble.style.borderRadius = "10px";
        bubble.style.border = "1px solid rgba(255,255,255,0.12)";
        bubble.style.background = "rgba(0,0,0,0.12)";
        bubble.style.maxWidth = "80%";
        bubble.style.justifySelf =
            m.isMine ? "end" : "start";

        const text = document.createElement("div");
        text.textContent = m.body;
        bubble.appendChild(text);

        threadBox.appendChild(bubble);
    });
}

function appendMessage(payload) {
    if (!activeConversation || payload.conversation_id !== activeConversation.id) {
        return;
    }

    const meId = getAuthSession()?.user?.id;
    const bubble = document.createElement("div");
    bubble.style.padding = "8px 10px";
    bubble.style.borderRadius = "10px";
    bubble.style.border = "1px solid rgba(255,255,255,0.12)";
    bubble.style.background = "rgba(0,0,0,0.12)";
    bubble.style.maxWidth = "80%";
    bubble.style.justifySelf =
        meId && payload.sender?.id === meId ? "end" : "start";

    const text = document.createElement("div");
    text.textContent = payload.body;
    bubble.appendChild(text);
    threadBox.appendChild(bubble);
}

async function loadConversations(query = "") {
    try {
        statusUI.setRequestMeta("GET", "/api/conversations");
        statusUI.setStatus("Loading conversations...", "neutral", null);
        const qs = new URLSearchParams({ q: query, per_page: "20" });
        const res = await apiRequest(`/conversations?${qs.toString()}`, {
            method: "GET",
        });
        statusUI.setDebug(res);

        const result = res?.result ?? res;
        conversations = result?.data ?? [];
        renderChatList();
        statusUI.setStatus("Conversations loaded.", "ok", 200);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(
            err.message || "Failed to load conversations.",
            "bad",
            err.status || 0,
        );
    }
}

function initRealtime() {
    if (!window.Pusher) return;
    const token = getToken();
    if (!token) return;

    pusher = new window.Pusher(config.pusher.key, {
        cluster: config.pusher.cluster,
        authEndpoint: config.pusher.authEndpoint,
        auth: {
            headers: {
                Authorization: `Bearer ${token}`,
            },
        },
    });

    const meId = getAuthSession()?.user?.id;
    if (meId) {
        userChannel = pusher.subscribe(`private-user.${meId}`);
        userChannel.bind("conversations.unread", (payload) => {
            setUnreadCount(payload?.total || 0);
        });
    }
}

function subscribeToConversation(conversationId) {
    if (!pusher) return;
    if (chatChannel) {
        chatChannel.unbind_all();
        pusher.unsubscribe(chatChannel.name);
        chatChannel = null;
    }

    chatChannel = pusher.subscribe(`private-chat.${conversationId}`);
    chatChannel.bind("message.sent", (payload) => {
        appendMessage({ ...payload, conversation_id: conversationId });
    });
    chatChannel.bind("message.read", () => {
        loadUnreadCount();
    });
    chatChannel.bind("message.delivered", () => {});
}

async function openConversation(id) {
    const convo = conversations.find((c) => String(c.id) === String(id));
    activeConversation = convo || { id };
    threadTitle.textContent = convo?.peer?.name || `#${id}`;
    const meId = getAuthSession()?.user?.id;

    try {
        statusUI.setRequestMeta("GET", `/api/conversations/${id}/messages`);
        statusUI.setStatus("Loading messages...", "neutral", null);

        const res = await apiRequest(`/conversations/${id}/messages`, {
            method: "GET",
        });
        statusUI.setDebug(res);

        const result = res?.result ?? res;
        const peer = convo?.peer;
        if (!peer && result?.data?.[0]?.sender) {
            threadTitle.textContent = result.data[0].sender.name || `#${id}`;
        }
    const items = result?.data ?? [];
        const normalized = items.map((m) => ({
            ...m,
            isMine: meId ? m.sender_id === meId : m.sender_id !== convo?.peer?.id,
        }));

        renderThread(normalized);
        await apiRequest(`/conversations/${id}/read`, { method: "POST" });
        statusUI.setStatus("Messages loaded.", "ok", 200);
        await loadUnreadCount();
        subscribeToConversation(id);
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(
            err.message || "Failed to load messages.",
            "bad",
            err.status || 0,
        );
    }
}

async function startDirectChat(userId) {
    try {
        statusUI.setRequestMeta("POST", "/api/conversations/direct");
        statusUI.setStatus("Starting chat...", "neutral", null);
        const res = await apiRequest("/conversations/direct", {
            method: "POST",
            body: JSON.stringify({ user_id: userId }),
        });
        const convo = res?.conversation;
        if (convo?.id) {
            await loadConversations();
            await openConversation(convo.id);
        }
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(
            err.message || "Failed to start chat.",
            "bad",
            err.status || 0,
        );
    }
}

async function loadUnreadCount() {
    try {
        const res = await apiRequest("/conversations/unread-count", {
            method: "GET",
        });
        setUnreadCount(res?.result?.total || 0);
    } catch {
        setUnreadCount(0);
    }
}

initLang();
initRealtime();
loadConversations();
loadUnreadCount();

const target = sessionStorage.getItem("chat_target_user");
if (target) {
    const parsed = JSON.parse(target);
    sessionStorage.removeItem("chat_target_user");
    if (parsed?.id) startDirectChat(parsed.id);
}

messageForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    if (!activeConversation?.id) {
        statusUI.setStatus(
            t(getLanguage(), "chat_select_prompt"),
            "bad",
            0,
        );
        return;
    }
    const body = messageInput.value.trim();
    if (!body) return;
    sendBtn.disabled = true;

    try {
        statusUI.setRequestMeta("POST", `/api/conversations/${activeConversation.id}/messages`);
        statusUI.setStatus("Sending message...", "neutral", null);
        const res = await apiRequest(
            `/conversations/${activeConversation.id}/messages`,
            {
                method: "POST",
                body: JSON.stringify({ body }),
            },
        );
        statusUI.setDebug(res);
        messageInput.value = "";
        await openConversation(activeConversation.id);
        await loadUnreadCount();
    } catch (err) {
        statusUI.setDebug(err.data || { error: err.message });
        statusUI.setStatus(
            err.message || "Failed to send message.",
            "bad",
            err.status || 0,
        );
    } finally {
        sendBtn.disabled = false;
    }
});

searchInput.addEventListener("input", () => {
    loadConversations(searchInput.value);
});
