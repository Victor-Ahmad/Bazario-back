const KEY = "demo_lang";

function emitLangChanged() {
    window.dispatchEvent(new CustomEvent("demo:lang-changed"));
}

export function getLanguage() {
    return localStorage.getItem(KEY) || "en";
}

export function setLanguage(lang) {
    localStorage.setItem(KEY, lang);

    document.documentElement.lang = lang;
    document.documentElement.dir = lang === "ar" ? "rtl" : "ltr";

    emitLangChanged();
}
