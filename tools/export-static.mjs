// Übernimmt die Seiten aus dem statischen Entwurf (Repo SGS_HomePage_static) als WordPress-Inhalte.
// Aufruf: node tools/export-static.mjs ../SGS_HomePage_static
//
// Ergebnis: wp-content/plugins/sgs-verein/import/pages.json und data/*.json.
// Das Plugin «SGS Verein» legt daraus beim Import (Werkzeuge › SGS-Import) die Seiten an.
//
// Was in WordPress gepflegt wird, erscheint in den Seiten nicht als fester Text, sondern als Shortcode:
//   Werte & Preise   {{angebote.schnupperflug.preis|chf}} -> [sgs_wert k=angebote.schnupperflug.preis f=chf]
//   Personen         [sgs_kontakt …], [sgs_mail …], [sgs_wert k=person.praesident.email]
//   Listen           [sgs_termine], [sgs_flotte], [sgs_personen], [sgs_kiosk], [sgs_berichte], [sgs_presse]
//   Formulare        [sgs_formular name=ppr]<form …>…</form>[/sgs_formular]
import { readdirSync, statSync, writeFileSync, mkdirSync, cpSync } from 'node:fs';
import { join, relative, resolve, dirname } from 'node:path';
import { pathToFileURL, fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const staticRepo = resolve(process.argv[2] || join(here, '..', '..', 'SGS_HomePage_static'));
const target = join(here, '..', 'wp-content', 'plugins', 'sgs-verein', 'import');

const lib = await import(pathToFileURL(join(staticRepo, 'src', 'lib.mjs')));
const components = await import(pathToFileURL(join(staticRepo, 'src', 'components.mjs')));

// ---------- Werte als Platzhalter {{schlüssel}} ----------
// Ein Wert aus site/angebote/kiosk/airfield wird beim Einsetzen in den Text zu {{schlüssel}}.
class Val extends String {
  constructor(key, raw) { super(`{{${key}}}`); this.key = key; this.raw = raw; }
}
const deep = (obj, prefix) => new Proxy(obj, {
  get(t, k) {
    const v = t[k];
    if (typeof k !== 'string' || k.startsWith('_')) return v;
    if (Array.isArray(v)) return v; // Listen (Menü, Gebühren …) bleiben echte Daten
    if (v && typeof v === 'object') return deep(v, `${prefix}.${k}`);
    if (typeof v === 'string' || typeof v === 'number') return new Val(`${prefix}.${k}`, v);
    return v;
  },
});
const valued = { site: 'site', angebote: 'angebote', kiosk: 'kiosk', airfield: 'airfield' };
const data = (name) => (valued[name] ? deep(lib.data(name), name) : lib.data(name));

const esc = (s = '') => (s instanceof Val ? s.toString() : lib.esc(s));
const chf = (n) => (n instanceof Val ? `{{${n.key}|chf}}` : lib.chf(n));
const attr = (s) => String(s).replace(/"/g, '&quot;');

// Personen: Name, Funktion und E-Mail kommen aus den Personen in WordPress.
const person = (key) => {
  const p = lib.person(key);
  return { ...p, name: `{{person.${key}.name}}`, title: `{{person.${key}.title}}`, email: `{{person.${key}.email}}` };
};
const contactBlock = (key, { role, before = '', phones = true } = {}) =>
  `[sgs_kontakt person=${key}${role ? ` rolle="${attr(role)}"` : ''}${phones ? '' : ' telefon=nein'}]${before}[/sgs_kontakt]`;
const mailLink = (key, text) => (text ? `[sgs_mail person=${key}]${text}[/sgs_mail]` : `[sgs_mail person=${key}]`);
const festDates = () => ({ year: '[sgs_fest]', label: '[sgs_fest f=datum]' });

// Seitenkopf als Shortcode, damit Titel und Einleitung im Editor gut zu finden sind.
const pageHead = ({ crumb, crumbHref, title, lead }) =>
  `\n[sgs_seitenkopf${crumb ? ` rubrik="${attr(crumb)}" link="${attr(crumbHref)}"` : ''} titel="${attr(title)}"]${lead || ''}[/sgs_seitenkopf]\n`;

const helpers = {
  ...lib, ...components, data, esc, chf, person, contactBlock, mailLink, festDates, pageHead,
  formMail: () => '', u: (p) => `@root@${p}`,
};

// ---------- Elemente durch Shortcodes ersetzen ----------
// Findet das Element, das mit `open` beginnt, samt passendem Schluss-Tag.
function findElement(html, open, from = 0) {
  const start = html.indexOf(open, from);
  if (start < 0) return null;
  const tag = open.match(/^<([a-z0-9]+)/)[1];
  const re = new RegExp(`<${tag}[\\s>]|</${tag}>`, 'g');
  re.lastIndex = start + 1;
  let depth = 1; let m;
  while ((m = re.exec(html))) {
    depth += m[0].startsWith('</') ? -1 : 1;
    if (depth === 0) return { start, end: m.index + m[0].length };
  }
  throw new Error(`Kein Schluss-Tag für ${open}`);
}
function replaceElement(html, open, replacement, { all = false } = {}) {
  let el = findElement(html, open); let first = true;
  while (el) {
    const rep = first ? replacement : '';
    html = html.slice(0, el.start) + rep + html.slice(el.end);
    first = false;
    if (!all) break;
    el = findElement(html, open, el.start + rep.length);
  }
  return html;
}

const forms = { kontakt: 'Kontakt', schnupper: 'Schnupperflug', mitglied: 'Mitgliedschaft', ppr: 'PPR-Anfrage', safety: 'Safety-Meldung', gutschein: 'Gutschein' };

function transform(path, body) {
  // Formulare: Markup bleibt, der Shortcode setzt Ziel, Schutzfelder und Empfänger ein.
  body = body.replace(/<form class="form"[^>]*data-form="([a-z]+)"[^>]*>[\s\S]*?<\/form>/g, (form, key) => {
    if (!forms[key]) throw new Error(`Unbekanntes Formular ${key}`);
    const inner = form
      .replace(/ action="[^"]*"/, '').replace(/ data-mail="[^"]*"/, '')
      .replace(/\s*<div class="hp"[\s\S]*?<\/div>/, '');
    return `[sgs_formular name=${key}]${inner}[/sgs_formular]`;
  });
  const map = {
    '': [['<ul class="events events-row">', '[sgs_termine anzahl=4 darstellung=reihe]'],
      ['<div class="report-cards">', '<div class="report-cards">[sgs_berichte anzahl=3]</div>'],
      ['<ul class="price-list">', '[sgs_kiosk auswahl=3]'],
      ['<dl class="hours">', '[sgs_kiosk_zeiten]']],
    'verein/': [['<ul class="events measure">', '[sgs_termine]']],
    'verein/flotte/': [['<div class="fleet-group">', '[sgs_flotte]', { all: true }]],
    'verein/vorstand/': [['<div class="wrap people">', '<div class="wrap">[sgs_personen liste=vorstand]</div>']],
    'kontakt/': [['<div class="people">', '[sgs_personen liste=kontakt]']],
    'besuchen/': [['<div class="menu-card">', '[sgs_kiosk]'], ['<dl class="hours">', '[sgs_kiosk_zeiten]']],
    'verein/medien/': [['<div class="report-cards">', '<div class="report-cards">[sgs_berichte anzahl=3]</div>'],
      ['<div class="report-archive">', '<div class="report-archive">[sgs_berichte_archiv ab=4]</div>'],
      ['<ul class="press">', '[sgs_presse]']],
  };
  for (const [open, rep, opt] of map[path] || []) {
    if (!body.includes(open)) throw new Error(`${path}: ${open} nicht gefunden`);
    body = replaceElement(body, open, rep, opt);
  }
  // Berichtzahl im Seitentext der Medienseite («58 Berichte seit 2020») kommt aus WordPress.
  if (path === 'verein/medien/') body = body.replace(/<p class="eyebrow">\d+ Berichte seit \d{4}<\/p>/, '<p class="eyebrow">[sgs_berichte_anzahl]</p>');
  // Platzhalter in Shortcodes umwandeln. In href="tel:…" die Telefonnummer ohne Leerzeichen.
  body = body
    .replace(/tel:\{\{([a-z0-9_.]+)\}\}/gi, 'tel:[sgs_wert k=$1 f=tel]')
    .replace(/\{\{([a-z0-9_.]+)\|chf\}\}/gi, '[sgs_wert k=$1 f=chf]')
    .replace(/\{\{([a-z0-9_.]+)\}\}/gi, '[sgs_wert k=$1]');
  // Im Titel des Seitenkopfs (Shortcode-Attribut) gehen keine Shortcodes: dort {schlüssel} bzw. {fest}.
  body = body.replace(/titel="[^"]*"/g, (t) => t.replace(/\[sgs_wert k=([a-z0-9_.]+)\]/gi, '{$1}').replace(/\[sgs_fest\]/g, '{fest}'));
  const left = body.match(/\{\{[^}]*\}\}|\[object |NaN|undefined/);
  if (left) throw new Error(`${path}: nicht umgewandelt: ${left[0]}`);
  return body;
}

// Inhalt in Blöcke aufteilen: Seitenkopf als Shortcode-Block, jeder Abschnitt als HTML-Block.
function toBlocks(body) {
  return body.trim().split(/\n(?=<[a-z]|\[sgs_seitenkopf)/).map((part) => part.trim()).filter(Boolean).map((part) => {
    if (part.startsWith('[sgs_seitenkopf')) {
      const end = part.indexOf('[/sgs_seitenkopf]') + '[/sgs_seitenkopf]'.length;
      const head = `<!-- wp:shortcode -->\n${part.slice(0, end)}\n<!-- /wp:shortcode -->`;
      const rest = part.slice(end).trim();
      return rest ? `${head}\n\n<!-- wp:html -->\n${rest}\n<!-- /wp:html -->` : head;
    }
    return `<!-- wp:html -->\n${part}\n<!-- /wp:html -->`;
  }).join('\n\n');
}

function walk(dir) {
  return readdirSync(dir).flatMap((f) => {
    const p = join(dir, f);
    return statSync(p).isDirectory() ? walk(p) : p.endsWith('.mjs') ? [p] : [];
  });
}

const pagesDir = join(staticRepo, 'src', 'pages');
const pages = [];
for (const file of walk(pagesDir)) {
  const rel = relative(pagesDir, file).replace(/\\/g, '/').replace(/\.mjs$/, '');
  if (rel.split('/').pop().startsWith('_')) continue; // Berichte werden Beiträge
  const path = rel === 'index' ? '' : `${rel.replace(/\/?index$/, '')}/`;
  const mod = await import(pathToFileURL(file));
  const res = mod.default({ ...helpers, page: path });
  pages.push({
    path,
    title: path === '' ? 'Startseite' : String(res.title).replace(/\{\{.*?\}\}|\[sgs_fest\]/g, '').trim(),
    description: res.description ? String(res.description) : '',
    section: res.section || '',
    content: toBlocks(transform(path, res.body)),
  });
}
pages.sort((a, b) => a.path.split('/').length - b.path.split('/').length || a.path.localeCompare(b.path));
// Beschreibungen mit Werten: als Text mit dem aktuellen Wert (Meta-Beschreibung, nur für Suchmaschinen).
for (const p of pages) p.description = p.description.replace(/\{\{([a-z0-9_.]+)(\|chf)?\}\}/gi, (_, k, f) => {
  const v = k.split('.').reduce((o, x) => o?.[x], { site: lib.data('site'), angebote: lib.data('angebote'), kiosk: lib.data('kiosk'), airfield: lib.data('airfield') });
  return f ? lib.chf(v) : v;
});

mkdirSync(target, { recursive: true });
writeFileSync(join(target, 'pages.json'), JSON.stringify(pages, null, 1));
cpSync(join(staticRepo, 'src', 'data'), join(target, 'data'), { recursive: true });
console.log(`${pages.length} Seiten nach ${relative(process.cwd(), join(target, 'pages.json'))} geschrieben.`);
