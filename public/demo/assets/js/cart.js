import { getAuthSession } from "./auth.js";

const CART_KEY = "demo_cart";

function cartKey() {
    const userId = getAuthSession()?.user?.id;
    return userId ? `${CART_KEY}_${userId}` : `${CART_KEY}_guest`;
}

function defaultCart() {
    return { products: [], services: [] };
}

export function getCart() {
    const key = cartKey();
    const guestKey = `${CART_KEY}_guest`;
    const guestRaw = localStorage.getItem(guestKey);
    const raw = localStorage.getItem(key);

    if (key !== guestKey && guestRaw) {
        const guestCart = safeParse(guestRaw) || defaultCart();
        const userCart = safeParse(raw) || defaultCart();
        const merged = mergeCarts(userCart, guestCart);
        saveCart(merged);
        localStorage.removeItem(guestKey);
        return merged;
    }

    if (!raw) return defaultCart();
    const data = safeParse(raw);
    if (!data) return defaultCart();
    return {
        products: Array.isArray(data?.products) ? data.products : [],
        services: Array.isArray(data?.services) ? data.services : [],
    };
}

export function saveCart(cart) {
    localStorage.setItem(cartKey(), JSON.stringify(cart));
}

export function addProductToCart(product) {
    const cart = getCart();
    const id = product?.id;
    if (!id) return cart;

    const existing = cart.products.find((p) => p.id === id);
    if (existing) {
        existing.qty += 1;
    } else {
        const price = Number(product?.price ?? 0);
        const img = product?.images?.[0]?.image
            ? `/${product.images[0].image}`
            : null;
        cart.products.push({
            id,
            name: product?.name || `#${id}`,
            price: Number.isFinite(price) ? price : 0,
            qty: 1,
            image: img,
        });
    }

    saveCart(cart);
    return cart;
}

export function addServiceBookingToCart(service, slot, timezone) {
    const cart = getCart();
    if (!service?.id || !slot?.starts_at || !slot?.ends_at) return cart;

    const existing = cart.services.find(
        (s) =>
            s.service_id === service.id &&
            s.starts_at === slot.starts_at &&
            s.ends_at === slot.ends_at,
    );
    if (existing) return cart;

    const price = Number(service?.price ?? 0);
    cart.services.push({
        id: `${service.id}-${slot.starts_at}`,
        service_id: service.id,
        title: service?.title || `#${service.id}`,
        price: Number.isFinite(price) ? price : 0,
        starts_at: slot.starts_at,
        ends_at: slot.ends_at,
        timezone: timezone || "",
    });

    saveCart(cart);
    return cart;
}

export function clearCart() {
    saveCart(defaultCart());
}

function safeParse(raw) {
    try {
        return JSON.parse(raw);
    } catch {
        return null;
    }
}

function mergeCarts(a, b) {
    const merged = defaultCart();

    const products = new Map();
    [...(a.products || []), ...(b.products || [])].forEach((p) => {
        const existing = products.get(p.id);
        if (existing) {
            existing.qty += p.qty || 0;
        } else {
            products.set(p.id, { ...p });
        }
    });

    const services = new Map();
    [...(a.services || []), ...(b.services || [])].forEach((s) => {
        const key = `${s.service_id}-${s.starts_at}-${s.ends_at}`;
        if (!services.has(key)) services.set(key, { ...s });
    });

    merged.products = Array.from(products.values());
    merged.services = Array.from(services.values());

    return merged;
}
