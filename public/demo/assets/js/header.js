import { apiRequest } from "./api.js";
import { getAuthSession, clearAuthSession } from "./auth.js";
import { getLanguage } from "./lang.js";
import { t } from "./i18n/index.js";

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

    container.innerHTML = "";

    const msg = el("div", { class: "topbarMsg", id: "topbarMsg" });
    const brand = el("a", { class: "topbarBrand", href: "/demo/index.html" }, [
        "Marketplace Demo",
    ]);

    const nav = el("nav", { class: "topbarNav" }, [
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
        ...(canUpgrade
            ? [
                  el(
                      "a",
                      {
                          class: `navLink ${
                              isActive("/demo/upgrade-account.html")
                                  ? "active"
                                  : ""
                          }`,
                          href: "/demo/upgrade-account.html",
                      },
                      [t(lang, "nav_upgrade")],
                  ),
              ]
            : []),
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
    ]);

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
}

export function initHeader() {
    const container = document.getElementById("appHeader");
    if (!container) return;

    renderHeader(container);

    // Re-render when auth or language changes
    window.addEventListener("demo:auth-changed", () => renderHeader(container));
    window.addEventListener("demo:lang-changed", () => renderHeader(container));
}

// Auto-init if the placeholder exists
initHeader();
