# Webseite Segelfluggruppe Schaffhausen – WordPress (Entwurf)

WordPress-Fassung des statischen Entwurfs ([SGS_HomePage_static](https://github.com/Luc1829/SGS_HomePage_static)). Gleiches Design, gleiche Seiten. Neu: Die Formulare werden von der Webseite selbst verschickt und im Admin abgelegt, und Inhalte werden im WordPress-Admin gepflegt statt in JSON-Dateien.

Keine Kauf-Plugins, kein Page-Builder, keine Abhängigkeit von Google. Nötig sind nur WordPress (ab 6.4, PHP 8) und die drei Teile in diesem Repo:

| Teil | Pfad | Aufgabe |
|---|---|---|
| Theme «SGS» | `wp-content/themes/sgs/` | Kopf, Navigation, Fuss, Vorlagen für Seiten und Berichte, CSS/JS/Schrift/Icons aus dem Design-System |
| Plugin «SGS Verein» | `wp-content/plugins/sgs-verein/` | Termine, Flugzeugpark, Personen, Kiosk, Werte & Preise, «Flugbetrieb heute», Shortcodes, Import |
| Plugin «SGS Formulare» | `wp-content/plugins/sgs-formulare/` | Versand der 6 Formulare, Ablage unter «Anfragen», Spam-Schutz, Empfänger, SMTP |

## Lokal ausprobieren (Docker)

```bash
docker compose up -d
```

1. http://localhost:8080 öffnen, WordPress installieren (Sprache «Deutsch (Schweiz)»).
2. Unter Plugins «SGS Verein» und «SGS Formulare» aktivieren, unter Design › Themes «SGS» aktivieren.
3. Werkzeuge › SGS-Import › «Import starten» (dauert etwa eine Minute). Oder: `docker compose run --rm cli wp sgs import`
4. E-Mails der Formulare ansehen: Anfragen › Einstellungen › SMTP-Server `mailpit`, Port `1025`, Verschlüsselung «keine». Die E-Mails erscheinen dann unter http://localhost:8025.

## Auf den Webhoster (z. B. Infomaniak)

1. WordPress beim Hoster installieren (bei Infomaniak im Manager mit einem Klick).
2. Die drei Ordner aus `wp-content/` per FTP/SFTP in dasselbe Verzeichnis des Hosters hochladen.
3. Wie oben aktivieren und importieren.
4. Ein Postfach `webseite@…` auf der Vereinsdomain anlegen und unter Anfragen › Einstellungen als Absender und SMTP-Zugang eintragen (Infomaniak: `mail.infomaniak.com`, Port 587, TLS). Das Passwort am besten in `wp-config.php`: `define('SGS_SMTP_PASSWORT', '…');`
5. «Speichern und Testmail an mich senden».
6. Benutzer für den Vorstand mit der Rolle «Redakteur» anlegen: Sie können Seiten, Berichte, Termine, Kiosk, Werte und den Flugbetrieb-Status pflegen und Anfragen lesen, aber keine Plugins oder Einstellungen ändern.

## Inhalte pflegen

**Jede Angabe steht nur an einer Stelle.** Seiten zeigen Werte, Personen und Listen über Shortcodes an; wer etwas ändert, ändert es einmal, und es ist auf allen Seiten aktuell.

| Was | Wo im Admin |
|---|---|
| Flugbetrieb heute (grün/rot im Kopf und auf der Startseite) | Dashboard › «Flugbetrieb heute» (gilt nur für den Tag) |
| Berichte | Beiträge (Kategorie = Rubrik, Beitragsbild = Bild auf der Karte, Textauszug = Anriss) |
| Termine | SGS › Termine (vergangene verschwinden automatisch) |
| Preise, Eckdaten der Angebote, Telefon, Adresse, Bank, Frequenzen, Kiosk-Texte und -Zeiten | SGS › Werte & Preise |
| Personen, Vorstand, Kontaktseite | SGS › Personen (Kürzel nicht ändern, nur Name/E-Mail/Foto) |
| Wer erhält welches Formular | Anfragen › Einstellungen |
| Flugzeugpark | SGS › Flugzeugpark und SGS › Flugzeug-Gruppen |
| Kiosk-Karte | SGS › Kiosk und SGS › Kiosk-Kategorien |
| Navigation | Design › Menüs (Beschreibung der Einträge erscheint im Aufklappmenü) |
| Texte der Seiten | Seiten (Abschnitte als HTML-Blöcke, Seitenkopf als Shortcode) |
| Eingegangene Anfragen | Anfragen (werden nach 365 Tagen automatisch gelöscht, einstellbar) |

### Shortcodes

| Shortcode | Zeigt |
|---|---|
| `[sgs_wert k=angebote.schnupperflug.preis f=chf]` | einen Wert aus «Werte & Preise» (`f=chf` als Betrag, `f=tel` für `tel:`-Links); `k=person.praesident.email` aus den Personen |
| `[sgs_seitenkopf rubrik="Verein" link="/verein/" titel="…"]Einleitung[/sgs_seitenkopf]` | Seitenkopf mit Streifenmotiv |
| `[sgs_kontakt person=cheffluglehrer]`, `[sgs_mail person=vorstand]Vorstand[/sgs_mail]` | Ansprechperson, E-Mail-Link |
| `[sgs_termine]`, `[sgs_flotte]`, `[sgs_personen liste=vorstand]`, `[sgs_kiosk]`, `[sgs_kiosk_zeiten]` | Listen |
| `[sgs_berichte anzahl=3]`, `[sgs_berichte_archiv ab=4]`, `[sgs_presse]` | Berichte |
| `[sgs_fest]`, `[sgs_fest f=datum]` | Jahr/Datum des nächsten Flüüger-Fäschts |
| `[sgs_formular name=ppr]<form …>…</form>[/sgs_formular]` | Formular (kontakt, schnupper, mitglied, ppr, safety, gutschein) |

## Formulare

- Das Formular-Markup steht in der Seite und kann dort angepasst werden (Felder hinzufügen genügt, die Beschriftung erscheint automatisch in der E-Mail).
- Jede Anfrage wird zuerst unter «Anfragen» gespeichert, dann per E-Mail an die zuständige Person geschickt (Antworten geht direkt per «Antworten» im Mailprogramm). Konnte eine E-Mail nicht zugestellt werden, erscheint ein Hinweis im Dashboard.
- Auf Wunsch erhält der Absender eine Bestätigung (ohne seine Eingaben, damit das Formular nicht für Spam missbraucht werden kann).
- Spam-Schutz ohne Captcha: verstecktes Feld, signierter Zeitstempel (mindestens 3 Sekunden bis zum Absenden), höchstens 10 Anfragen pro Stunde und Absender.
- Funktioniert auch ohne JavaScript (Weiterleitung mit Meldung).

## Aus dem statischen Entwurf aktualisieren

Solange am statischen Entwurf weitergearbeitet wird, lassen sich die Seiten neu übernehmen:

```bash
node tools/export-static.mjs ../SGS_HomePage_static
```

Das schreibt `wp-content/plugins/sgs-verein/import/pages.json` und `import/data/`. Der Import legt nur fehlende Einträge an; bestehende Seiten bleiben unverändert (zum Neu-Übernehmen die Seite vorher löschen).

## Bekannte Grenzen des Entwurfs

- Seitentexte sind HTML-Blöcke: gut für das exakte Design, für Laien weniger bequem als normale Absatz-Blöcke. Häufig geänderte Inhalte (Preise, Termine, Kiosk, Personen, Berichte) liegen deshalb in eigenen Eingabemasken.
- Listen, die selten ändern (Flugplatz-Gebühren, AIP-Bemerkungen, Geschichte, Sponsoren, SPOT-Tracker, Schmerlat-Post-Ausgaben), stehen als Text in der jeweiligen Seite.
- Online bezahlen gibt es (noch) nicht; Gutscheine werden angefragt.
- Bilder und PDFs werden beim Import in die Mediathek übernommen (rund 60 MB).
