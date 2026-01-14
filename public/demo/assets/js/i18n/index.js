import en from "./en.js";
import de from "./de.js";
import ar from "./ar.js";

const dict = { en, de, ar };

export function t(lang, key) {
    return dict?.[lang]?.[key] ?? dict?.en?.[key] ?? key;
}

export function has(lang, key) {
    return Boolean(dict?.[lang]?.[key]);
}
