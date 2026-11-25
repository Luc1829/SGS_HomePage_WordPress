# Webseite Segelfluggruppe Schaffhausen (SGS) – WordPress

WordPress-Fassung des statischen Entwurfs (Repo `Luc1829/SGS_HomePage_static`). Theme `wp-content/themes/sgs`, Plugins `sgs-verein` (Inhalte, Shortcodes, Import) und `sgs-formulare` (Versand, Anfragen, SMTP). Details in `README.md`.

## Arbeitsweise

- Nach jeder Änderung committen, pushen und einen Pull Request nach `main` erstellen.
- PHP prüfen: `for f in $(find wp-content -name '*.php'); do php -l $f; done`
- Testen ohne Docker: WordPress mit dem Plugin «SQLite Database Integration» und `php -S` (mit `DISABLE_WP_CRON`, sonst blockiert sich der Testserver), Import per `sgs_import_alles()`. Sichtprüfung mit dem vorinstallierten Playwright/Chromium (Desktop 1280 px, Handy 390 px). Nie Microsoft Edge verwenden.
- Design, Sprache und Regeln (du statt Sie, «…», CHF 45.–, ein Akzent-Button pro Seite, keine Pillen …) wie in der `CLAUDE.md` des statischen Repos. CSS nur mit Variablen aus `assets/css/tokens.css`; `site.css` ist eine Kopie aus dem statischen Repo, WordPress-Ergänzungen in `wordpress.css`.
- **Jede Angabe nur an einer Stelle**: Werte in «Werte & Preise» (`sgs_werte`), Personen als `sgs_person`, Listen über Shortcodes. Nie Preise, E-Mail-Adressen oder Frequenzen fest in Seiten oder Vorlagen schreiben.
- Antworten an den Nutzer auf Deutsch.
