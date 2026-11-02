// Translation for the vault pages. The page language comes from <html lang>;
// English strings are the keys and the fallback.
import pl from '../lang/pl.js';

const dictionaries = { pl };

export function tr(en, vars = {}) {
  const lang = typeof document !== 'undefined' && document.documentElement
    ? String(document.documentElement.lang || '').slice(0, 2).toLowerCase()
    : '';
  const dict = dictionaries[lang];
  const text = dict && Object.prototype.hasOwnProperty.call(dict, en) ? dict[en] : en;
  return text.replace(/\{(\w+)\}/g, (m, name) => (Object.prototype.hasOwnProperty.call(vars, name) ? String(vars[name]) : m));
}
