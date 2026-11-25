// Segelfluggruppe Schaffhausen – Client-Skript (ohne Abhängigkeiten)
(() => {
  const root = document.currentScript?.dataset.root || './';
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];

  /* ---------- Navigation ---------- */
  const closeMenus = (except) => $$('.has-menu.open').forEach((li) => {
    if (li !== except) { li.classList.remove('open'); $('button', li).setAttribute('aria-expanded', 'false'); }
  });
  $$('.has-menu > button').forEach((btn) => {
    btn.addEventListener('click', () => {
      const li = btn.parentElement; const open = !li.classList.contains('open');
      closeMenus(li); li.classList.toggle('open', open); btn.setAttribute('aria-expanded', String(open));
    });
  });
  document.addEventListener('click', (e) => { if (!e.target.closest('.has-menu')) closeMenus(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeMenus(); });
  const toggle = $('.nav-toggle'); const navEl = $('#hauptnavigation');
  toggle?.addEventListener('click', () => {
    const open = !navEl.classList.contains('open');
    navEl.classList.toggle('open', open); toggle.setAttribute('aria-expanded', String(open));
  });

  /* ---------- Flüüger-Fäscht: immer das nächste erste Septemberwochenende ---------- */
  const fest = (() => {
    const now = new Date();
    const weekend = (y) => { const d = new Date(y, 8, 1); while (d.getDay() !== 6) d.setDate(d.getDate() + 1); return d; };
    let y = now.getFullYear(); let sat = weekend(y);
    if (now > new Date(y, 8, sat.getDate() + 1, 23, 59)) { y += 1; sat = weekend(y); }
    return { year: y, label: `${sat.getDate()}./${sat.getDate() + 1}. September ${y}` };
  })();
  $$('[data-fest-label]').forEach((el) => { el.textContent = `Flüüger-Fäscht ${fest.year}`; });
  $$('[data-fest-year]').forEach((el) => { el.textContent = fest.year; });
  $$('[data-fest-date]').forEach((el) => { el.textContent = fest.label; });

  /* ---------- Flugbetrieb (data/status.json) ---------- */
  const zurichDay = (d) => new Intl.DateTimeFormat('de-CH', { timeZone: 'Europe/Zurich', year: 'numeric', month: '2-digit', day: '2-digit' }).format(d);
  // WordPress: Status aus SGS › Dashboard «Flugbetrieb heute» (REST), sonst Datei wie im statischen Entwurf.
  fetch(document.currentScript?.dataset.status || root + 'data/status.json', { cache: 'no-store' })
    .then((r) => r.json())
    .then((s) => {
      const updated = new Date(s.updated);
      const today = zurichDay(updated) === zurichDay(new Date());
      const go = today && s.active;
      const title = go ? s.short : 'Heute kein Flugbetrieb';
      const text = today ? s.text : 'Für heute ist noch kein Flugbetrieb gemeldet.';
      const time = updated.toLocaleTimeString('de-CH', { hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Zurich' });
      $$('[data-status-chip]').forEach((el) => { el.classList.add(go ? 'is-go' : 'is-stop'); $('[data-status-short]', el).textContent = title; });
      $$('[data-status-box]').forEach((el) => {
        el.classList.add(go ? 'is-go' : 'is-stop');
        $('[data-status-title]', el).textContent = title;
        $('[data-status-text]', el).textContent = today ? `${text} Stand ${time} Uhr.` : text;
      });
    })
    .catch(() => $$('[data-status-text]').forEach((el) => { el.textContent = 'Status nicht verfügbar. Bitte am Flugplatz anrufen.'; }));

  /* ---------- Windfahne (Wind Barb) ----------
     Gezeichnet für Wind aus Norden (Schaft zeigt nach oben, Fahne am oberen = windzugewandten Ende,
     Striche rechts wie auf der Nordhalbkugel üblich), danach um die Windrichtung gedreht.
     Gerundet auf 5 kt: Dreieck = 50 kt, langer Strich = 10 kt, kurzer Strich = 5 kt. Unter 3 kt: kleiner Kreis in der Mitte mit «windstill». */
  const barb = (kt, cx, cy, r0, r1, len, pen, gap) => {
    let v = Math.round(kt / 5) * 5;
    if (v < 5) { const r = r1 > 40 ? 4 : 3.2; /* kleiner Kreis = windstill */ return { calm: true, line: `M${cx - r} ${cy}a${r} ${r} 0 1 0 ${2 * r} 0a${r} ${r} 0 1 0 ${-2 * r} 0`, fill: '' }; }
    const lean = 0.28; const n50 = Math.floor(v / 50); v -= n50 * 50;
    const n10 = Math.floor(v / 10); const n5 = v - n10 * 10 >= 5 ? 1 : 0;
    let line = `M${cx} ${cy - r0}V${cy - r1}`; let fill = ''; let y = cy - r1;
    for (let i = 0; i < n50; i++) {
      fill += `M${cx} ${y}L${cx + len} ${y + pen - lean * len}L${cx} ${y + pen}Z`;
      y += pen + (i === n50 - 1 ? gap * 0.6 : 0.6);
    }
    if (!n50 && !n10) y += gap; // nur 5 kt: kurzer Strich etwas vom Ende abgesetzt
    for (let i = 0; i < n10; i++) { line += `M${cx} ${y}l${len} ${-lean * len}`; y += gap; }
    if (n5) line += `M${cx} ${y}l${len / 2} ${-lean * len / 2}`;
    return { calm: false, line, fill };
  };
  const drawBarb = (g, kt, dir) => {
    const [r0, r1, len, pen, gap] = g.dataset.barb.split(',').map(Number);
    const b = barb(kt, 100, 100, r0, r1, len, pen, gap);
    $('.barb-halo', g).setAttribute('d', b.line + b.fill);
    $('.barb-line', g).setAttribute('d', b.line);
    $('.barb-fill', g).setAttribute('d', b.fill);
    g.style.transform = b.calm ? '' : `rotate(${dir}deg)`;
    g.classList.toggle('is-calm', b.calm);
    g.classList.remove('is-hidden');
  };
  const miniBarb = (kt, dir) => {
    const b = barb(kt, 16, 16, 0, 14, 7, 4, 2.6);
    return `<svg class="mini-barb" viewBox="0 0 32 32" aria-hidden="true"><g style="transform:rotate(${b.calm ? 0 : dir}deg);transform-origin:16px 16px">`
      + `<path d="${b.line}" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>`
      + (b.fill ? `<path d="${b.fill}" fill="currentColor"/>` : '') + '</g><circle cx="16" cy="16" r="1.8" fill="currentColor"/></svg>';
  };

  /* ---------- Wetter (Open-Meteo, frei für nicht-kommerzielle Nutzung) ---------- */
  const box = $('[data-weather]');
  if (box) {
    const { lat, lon, elev } = box.dataset;
    const runways = JSON.parse(box.dataset.runways || '[]');
    const hourly = box.hasAttribute('data-hourly');
    const url = new URL('https://api.open-meteo.com/v1/forecast');
    url.search = new URLSearchParams({
      latitude: lat, longitude: lon, timezone: 'Europe/Zurich', wind_speed_unit: 'kn', forecast_days: '1',
      current: 'temperature_2m,dew_point_2m,pressure_msl,wind_speed_10m,wind_direction_10m,wind_gusts_10m,cloud_cover',
      daily: 'sunset',
      ...(hourly ? { hourly: 'temperature_2m,wind_speed_10m,wind_direction_10m,cloud_cover', forecast_hours: '10' } : {}),
    });
    const set = (k, v) => $$(`[data-wx="${k}"]`, box).forEach((el) => { el.textContent = v; });
    const pad = (n) => String(Math.round(n)).padStart(3, '0');
    fetch(url).then((r) => r.json()).then((w) => {
      const c = w.current;
      set('wind', `${pad(c.wind_direction_10m)}° / ${Math.round(c.wind_speed_10m)} kt`);
      set('gust', Math.round(c.wind_gusts_10m));
      set('qnh', Math.round(c.pressure_msl));
      set('temp', `${Math.round(c.temperature_2m)}° / ${Math.round(c.dew_point_2m)}°`);
      // Faustregel Cumulus-Basis: (T − Td) × 400 ft über Grund
      const base = Math.max(0, (c.temperature_2m - c.dew_point_2m) * 400) + Number(elev);
      set('base', (Math.round(base / 100) * 100).toLocaleString('de-CH'));
      // Alle Zeiten in LT (Europe/Zurich), so wie Open-Meteo sie mit timezone liefert.
      set('valid', c.time.slice(11, 16));
      set('sunset', `${w.daily.sunset[0].slice(11, 16)} LT`);
      set('time', `Modelldaten Open-Meteo, Stand ${c.time.slice(11, 16)} LT, ohne Gewähr`);
      $$('[data-wind-barb]', box).forEach((g) => drawBarb(g, c.wind_speed_10m, c.wind_direction_10m));
      if (runways.length && c.wind_speed_10m >= 2.5) {
        const comp = runways.map((r) => {
          const a = (c.wind_direction_10m - r.heading) * Math.PI / 180;
          return { id: r.id, head: c.wind_speed_10m * Math.cos(a), cross: Math.abs(c.wind_speed_10m * Math.sin(a)) };
        }).sort((a, b) => b.head - a.head)[0];
        set('runway', `Günstiger: Piste ${comp.id} mit ${Math.round(comp.head)} kt Gegenwind und ${Math.round(comp.cross)} kt Seitenwind.`);
      } else if (runways.length) {
        set('runway', 'Windstill (unter 3 kt): beide Pistenrichtungen möglich.');
      }
      const h = $('[data-wx-hourly]', box);
      if (h && w.hourly) {
        h.innerHTML = w.hourly.time.map((t, i) => `<div><b>${t.slice(11, 16)} LT</b>
          ${miniBarb(w.hourly.wind_speed_10m[i], w.hourly.wind_direction_10m[i])}
          ${String(Math.round(w.hourly.wind_direction_10m[i])).padStart(3, '0')}° / ${Math.round(w.hourly.wind_speed_10m[i])} kt<br>${Math.round(w.hourly.temperature_2m[i])}°<br><span class="muted">${w.hourly.cloud_cover[i]}% Wolken</span></div>`).join('');
      }
    }).catch(() => set('time', 'Wetterdaten im Moment nicht erreichbar.'));
  }

  /* ---------- Formulare ---------- */
  $$('form[data-form]').forEach((form) => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const msg = $('.form-msg', form) || form.appendChild(Object.assign(document.createElement('p'), { className: 'form-msg', role: 'status' }));
      const btn = $('button[type="submit"]', form); btn.disabled = true;
      try {
        const r = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
        if (!r.ok) throw new Error();
        const copy = $('input[name="kopie"]', form)?.checked && $('input[name="email"]', form)?.value.trim();
        msg.className = 'form-msg ok';
        msg.textContent = (form.dataset.ok || 'Gesendet. Wir melden uns bei dir.') + (copy ? ` Eine Bestätigung geht an ${copy}.` : '');
        form.reset();
      } catch {
        msg.className = 'form-msg err';
        msg.innerHTML = `Senden fehlgeschlagen. Bitte per E-Mail an <a href="mailto:${form.dataset.mail}">${form.dataset.mail}</a> schicken.`;
      } finally { btn.disabled = false; }
    });
  });
})();
