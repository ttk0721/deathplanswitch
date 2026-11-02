// Translation for the vault pages. The page language comes from <html lang>;
// English strings are the keys and the fallback. A language is the module
// assets/lang/<lang>.js: `export default { English: 'Translated', '{n} day': [forms...] }`
// and `export const plural = '<rule id>'` (see lang/TRANSLATING.md). It is loaded
// from this site only, once, before the page code runs.

const PLURAL_CATEGORIES = {
  one_other: ['one', 'other'],
  fr: ['one', 'other'],
  none: ['other'],
  pl: ['one', 'few', 'many'],
  ru: ['one', 'few', 'many'],
  ar: ['zero', 'one', 'two', 'few', 'many', 'other'],
};

// Same rules as app/Plural.php.
export function pluralCategory(rule, n) {
  n = Math.abs(Math.trunc(n));
  const mod10 = n % 10;
  const mod100 = n % 100;
  const fewMany = mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14) ? 'few' : 'many';
  switch (rule) {
    case 'none': return 'other';
    case 'fr': return n <= 1 ? 'one' : 'other';
    case 'pl': return n === 1 ? 'one' : fewMany;
    case 'ru': return mod10 === 1 && mod100 !== 11 ? 'one' : fewMany;
    case 'ar':
      if (n === 0) return 'zero';
      if (n === 1) return 'one';
      if (n === 2) return 'two';
      if (mod100 >= 3 && mod100 <= 10) return 'few';
      return mod100 >= 11 ? 'many' : 'other';
    default: return n === 1 ? 'one' : 'other';
  }
}

export function pluralIndex(rule, n) {
  const cats = PLURAL_CATEGORIES[rule] || PLURAL_CATEGORIES.one_other;
  const i = cats.indexOf(pluralCategory(rule, n));
  return i < 0 ? 0 : i;
}

function pageLang() {
  return typeof document !== 'undefined' && document.documentElement
    ? String(document.documentElement.lang || '')
    : '';
}

const loaded = {};

async function load(lang) {
  if (!/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*$/.test(lang) || lang.split('-')[0].toLowerCase() === 'en') return null;
  try {
    const mod = await import('../lang/' + lang + '.js');
    return { dict: mod.default || {}, rule: mod.plural || 'one_other' };
  } catch {
    return null;
  }
}

// Top-level await: importers run only after the page language is ready.
const initial = pageLang();
loaded[initial] = await load(initial);

function entry() {
  const lang = pageLang();
  return Object.prototype.hasOwnProperty.call(loaded, lang) ? loaded[lang] : null;
}

function fill(text, vars) {
  return text.replace(/\{(\w+)\}/g, (m, name) => (Object.prototype.hasOwnProperty.call(vars, name) ? String(vars[name]) : m));
}

export function tr(en, vars = {}) {
  const lang = entry();
  const found = lang && Object.prototype.hasOwnProperty.call(lang.dict, en) ? lang.dict[en] : null;
  return fill(typeof found === 'string' ? found : en, vars);
}

// A counted phrase: trn('{n} day', '{n} days', n). The first form is the key.
export function trn(one, other, n, vars = {}) {
  return counted(entry(), one, other, n, vars);
}

// The lookup behind trn(); `lang` is { dict, rule } or null for English. Exported for tests.
export function counted(lang, one, other, n, vars = {}) {
  const found = lang && Object.prototype.hasOwnProperty.call(lang.dict, one) ? lang.dict[one] : null;
  const cats = lang ? PLURAL_CATEGORIES[lang.rule] || PLURAL_CATEGORIES.one_other : null;
  const text = Array.isArray(found) && cats && found.length === cats.length
    ? found[pluralIndex(lang.rule, n)]
    : (n === 1 ? one : other);
  return fill(text, { ...vars, n });
}

// How many forms a plural entry needs in a language with this rule.
export function pluralFormCount(rule) {
  return (PLURAL_CATEGORIES[rule] || PLURAL_CATEGORIES.one_other).length;
}
