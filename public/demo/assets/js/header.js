import { apiRequest } from "./api.js";
import { getAuthSession, clearAuthSession, getToken } from "./auth.js";
import { getLanguage } from "./lang.js";
import { t } from "./i18n/index.js";
import { config } from "./config.js";

const PUSHER_CDN =
    "https://cdnjs.cloudflare.com/ajax/libs/pusher/8.4.0/pusher.min.js";

let unreadBadgeEl = null;
let unreadPusher = null;
let unreadChannel = null;
let unreadUserId = null;
let pusherLoadPromise = null;

function el(tag, attrs = {}, children = []) {
    const node = document.createElement(tag);
    Object.entries(attrs).forEach(([k, v]) => {
        if (k === "class") node.className = v;
        else if (k === "html") node.innerHTML = v;
        else node.setAttribute(k, v);
    });
    children.forEach((c) =>
        node.appendChild(
            typeof c === "string" ? document.createTextNode(c) : c,
        ),
    );
    return node;
}

function currentPath() {
    return window.location.pathname || "";
}

function isActive(path) {
    return currentPath().endsWith(path);
}

function normalizeRoles(roles) {
    if (!roles) return [];
    if (Array.isArray(roles)) {
        return roles
            .map((role) =>
                typeof role === "string"
                    ? role
                    : role?.name || role?.slug || role?.role || "",
            )
            .filter(Boolean);
    }
    if (Array.isArray(roles?.data)) return normalizeRoles(roles.data);
    if (Array.isArray(roles?.roles)) return normalizeRoles(roles.roles);
    return [];
}

function hasRole(roles, name) {
    return normalizeRoles(roles).includes(name);
}

async function doLogout(all = false, setMsg, setBusy) {
    try {
        setBusy(true);
        setMsg("");

        await apiRequest(all ? "/logout-all" : "/logout", { method: "POST" });

        clearAuthSession();
        window.location.href = "/demo/login.html";
    } catch (err) {
        const code = err?.status ?? "";
        setMsg(`Logout failed${code ? ` (${code})` : ""}.`);
    } finally {
        setBusy(false);
    }
}

function renderHeader(container) {
    const lang = getLanguage();
    const session = getAuthSession();
    const userName = session?.user?.name || "";
    const roles = session?.roles;
    const canUpgrade =
        session?.user &&
        !hasRole(roles, "seller") &&
        !hasRole(roles, "service_provider");
    const isAdmin = hasRole(roles, "admin");
    const isSeller = hasRole(roles, "seller");
    const isServiceProvider = hasRole(roles, "service_provider");
    const isCustomer = hasRole(roles, "customer");
    const isCustomerOnly =
        isCustomer && !isAdmin && !isSeller && !isServiceProvider;

    container.innerHTML = "";

    const msg = el("div", { class: "topbarMsg", id: "topbarMsg" });
    const brand = el("a", { class: "topbarBrand", href: "/demo/index.html" }, [
        "Marketplace Demo",
    ]);

    const navItems = [
        el(
            "a",
            {
                class: `navLink ${
                    currentPath().endsWith("/demo/") ||
                    currentPath().endsWith("/demo/index.html")
                        ? "active"
                        : ""
                }`,
                href: "/demo/index.html",
            },
            [t(lang, "nav_home")],
        ),
        el(
            "a",
            {
                class: `navLink ${isActive("/demo/products.html") ? "active" : ""}`,
                href: "/demo/products.html",
            },
            [t(lang, "nav_products")],
        ),

        el(
            "a",
            {
                class: `navLink ${isActive("/demo/services.html") ? "active" : ""}`,
                href: "/demo/services.html",
            },
            [t(lang, "nav_services")],
        ),

        el(
            "a",
            {
                class: `navLink ${isActive("/demo/ads.html") ? "active" : ""}`,
                href: "/demo/ads.html",
            },
            [t(lang, "nav_ads")],
        ),
    ];

    if (canUpgrade) {
        navItems.push(
            el(
                "a",
                {
                    class: `navLink ${
                        isActive("/demo/upgrade-account.html") ? "active" : ""
                    }`,
                    href: "/demo/upgrade-account.html",
                },
                [t(lang, "nav_upgrade")],
            ),
        );
    }

    if (isAdmin) {
        navItems.push(
            el(
                "a",
                {
                    class: `navLink ${
                        isActive("/demo/admin-upgrade-requests.html")
                            ? "active"
                            : ""
                    }`,
                    href: "/demo/admin-upgrade-requests.html",
                },
                [t(lang, "nav_admin_upgrade_requests")],
            ),
        );
    }

    if (isSeller) {
        navItems.push(
            el(
                "a",
                {
                    class: `navLink ${
                        isActive("/demo/my-products.html") ? "active" : ""
                    }`,
                    href: "/demo/my-products.html",
                },
                [t(lang, "nav_my_products")],
            ),
        );
    }

    if (isServiceProvider) {
        navItems.push(
            el(
                "a",
                {
                    class: `navLink ${
                        isActive("/demo/my-services.html") ? "active" : ""
                    }`,
                    href: "/demo/my-services.html",
                },
                [t(lang, "nav_my_services")],
            ),
        );

        navItems.push(
            el(
                "a",
                {
                    class: `navLink ${
                        isActive("/demo/service-availability.html") ? "active" : ""
                    }`,
                    href: "/demo/service-availability.html",
                },
                [t(lang, "nav_service_availability")],
            ),
        );
    }

    if (isCustomerOnly) {
        navItems.push(
            el(
                "a",
                {
                    class: `navLink ${
                        isActive("/demo/cart.html") ? "active" : ""
                    }`,
                    href: "/demo/cart.html",
                },
                [t(lang, "nav_cart")],
            ),
        );
    }

    if (session?.user) {
        navItems.push(
            el(
                "a",
                {
                    class: `navLink ${
                        isActive("/demo/my-ads.html") ? "active" : ""
                    }`,
                    href: "/demo/my-ads.html",
                },
                [t(lang, "nav_my_ads")],
            ),
        );
        navItems.push(
            el(
                "a",
                {
                    class: `navLink ${
                        isActive("/demo/chat.html") ? "active" : ""
                    }`,
                    href: "/demo/chat.html",
                },
                [
                    t(lang, "nav_chats"),
                    el(
                        "span",
                        {
                            class: "navBadge",
                            id: "navChatUnread",
                            "aria-live": "polite",
                        },
                        ["0"],
                    ),
                ],
            ),
        );
    }

    if (!session?.user) {
        navItems.push(
            el(
                "a",
                {
                    class: `navLink ${
                        isActive("/demo/register.html") ? "active" : ""
                    }`,
                    href: "/demo/register.html",
                },
                [t(lang, "nav_register")],
            ),
        );
        navItems.push(
            el(
                "a",
                {
                    class: `navLink ${
                        isActive("/demo/login.html") ? "active" : ""
                    }`,
                    href: "/demo/login.html",
                },
                [t(lang, "nav_login")],
            ),
        );
    }

    const nav = el("nav", { class: "topbarNav" }, navItems);

    const right = el("div", { class: "topbarRight" });

    let busy = false;
    const setBusy = (b) => {
        busy = b;
        container
            .querySelectorAll("button")
            .forEach((btn) => (btn.disabled = busy));
    };
    const setMsg = (text) => {
        msg.textContent = text || "";
        msg.style.display = text ? "block" : "none";
    };

    if (!session?.user) {
        // Not logged in: show links only
        right.appendChild(
            el("span", { class: "topbarHint" }, ["Not logged in"]),
        );
    } else {
        // Logged in: show greeting + logout buttons
        right.appendChild(
            el("span", { class: "topbarHint" }, [
                `${t(lang, "nav_hi")}, ${userName}`,
            ]),
        );

        const btnLogout = el("button", { class: "topbarBtn", type: "button" }, [
            t(lang, "nav_logout"),
        ]);
        btnLogout.addEventListener("click", () =>
            doLogout(false, setMsg, setBusy),
        );

        const btnLogoutAll = el(
            "button",
            { class: "topbarBtn secondary", type: "button" },
            [t(lang, "nav_logout_all")],
        );
        btnLogoutAll.addEventListener("click", () =>
            doLogout(true, setMsg, setBusy),
        );

        right.appendChild(btnLogout);
        right.appendChild(btnLogoutAll);
    }

    const inner = el("div", { class: "topbarInner" }, [brand, nav, right]);
    const header = el("header", { class: "topbar" }, [inner, msg]);

    container.appendChild(header);

    unreadBadgeEl = container.querySelector("#navChatUnread");
    syncUnreadCount(session);
}

export function initHeader() {
    const container = document.getElementById("appHeader");
    if (!container) return;

    renderHeader(container);

    // Re-render when auth or language changes
    window.addEventListener("demo:auth-changed", () => renderHeader(container));
    window.addEventListener("demo:lang-changed", () => renderHeader(container));
}

function setNavUnreadCount(count) {
    if (!unreadBadgeEl) return;
    const total = Number(count || 0);
    unreadBadgeEl.textContent = String(total);
    unreadBadgeEl.style.display = total > 0 ? "inline-flex" : "none";
}

async function loadUnreadCount() {
    try {
        const res = await apiRequest("/conversations/unread-count", {
            method: "GET",
        });
        setNavUnreadCount(res?.result?.total || 0);
    } catch {
        setNavUnreadCount(0);
    }
}

function cleanupUnreadRealtime() {
    if (unreadChannel && unreadPusher) {
        unreadChannel.unbind_all();
        unreadPusher.unsubscribe(unreadChannel.name);
    }
    if (unreadPusher) unreadPusher.disconnect();
    unreadChannel = null;
    unreadPusher = null;
    unreadUserId = null;
}

function loadPusherScript() {
    if (window.Pusher) return Promise.resolve(window.Pusher);
    if (pusherLoadPromise) return pusherLoadPromise;

    pusherLoadPromise = new Promise((resolve, reject) => {
        const script = document.createElement("script");
        script.src = PUSHER_CDN;
        script.async = true;
        script.onload = () => resolve(window.Pusher);
        script.onerror = () => reject(new Error("Failed to load Pusher"));
        document.head.appendChild(script);
    });

    return pusherLoadPromise;
}

async function initUnreadRealtime(session) {
    const token = getToken();
    const userId = session?.user?.id;
    if (!token || !userId || !config?.pusher?.key) {
        cleanupUnreadRealtime();
        return;
    }
    if (unreadPusher && unreadUserId === userId) return;

    cleanupUnreadRealtime();

    await loadPusherScript();
    if (!window.Pusher) return;

    unreadPusher = new window.Pusher(config.pusher.key, {
        cluster: config.pusher.cluster,
        authEndpoint: config.pusher.authEndpoint,
        auth: {
            headers: {
                Authorization: `Bearer ${token}`,
            },
        },
    });
    unreadUserId = userId;

    unreadChannel = unreadPusher.subscribe(`private-user.${userId}`);
    unreadChannel.bind("conversations.unread", (payload) => {
        setNavUnreadCount(payload?.total || 0);
    });
}

function syncUnreadCount(session) {
    if (!session?.user) {
        setNavUnreadCount(0);
        cleanupUnreadRealtime();
        return;
    }
    loadUnreadCount();
    initUnreadRealtime(session);
}

// Auto-init if the placeholder exists
initHeader();
