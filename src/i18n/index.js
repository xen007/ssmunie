import { useCallback, useSyncExternalStore } from 'react';
import fr from './locales/fr.json';
import en from './locales/en.json';

const dictionaries = { fr, en };
const STORAGE_KEY = 'ssmunie-lang';
const FALLBACK_LANG = 'fr';

function readStoredLang() {
  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored && dictionaries[stored]) return stored;
  } catch {
    /* ignore */
  }
  return FALLBACK_LANG;
}

let currentLang = readStoredLang();
const listeners = new Set();

function subscribe(listener) {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

function getSnapshot() {
  return currentLang;
}

function emit() {
  listeners.forEach((listener) => listener());
}

/** Change la langue active et la persiste. */
export function setLang(lang) {
  if (!dictionaries[lang]) return;
  currentLang = lang;
  try {
    localStorage.setItem(STORAGE_KEY, lang);
  } catch {
    /* ignore */
  }
  try {
    document.documentElement.lang = lang;
  } catch {
    /* ignore */
  }
  emit();
}

/**
 * Traduction directe (hors React).
 * Supporte l'interpolation : t('x', { name: 'A' }) → remplace {name}.
 */
export function t(key, lang = currentLang, vars = null) {
  let str = dictionaries[lang]?.[key] ?? dictionaries[FALLBACK_LANG]?.[key] ?? key;
  if (vars && typeof str === 'string') {
    for (const [k, v] of Object.entries(vars)) {
      str = str.replaceAll(`{${k}}`, String(v));
    }
  }
  return str;
}

/** Hook : { lang, setLang } */
export function useLang() {
  const lang = useSyncExternalStore(subscribe, getSnapshot, getSnapshot);
  return { lang, setLang };
}

/** Hook : (key, vars?) => traduction (ou la clé si absente). */
export function useT() {
  const lang = useSyncExternalStore(subscribe, getSnapshot, getSnapshot);
  // Mémoïsé : référence stable tant que la langue ne change pas
  // (évite les boucles de rendu quand `t` est une dépendance d'effet).
  return useCallback((key, vars) => t(key, lang, vars), [lang]);
}

export default { useLang, useT, t, setLang };
