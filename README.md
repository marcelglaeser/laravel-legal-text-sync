# Legal Text Sync

Ein kleines Laravel-Demo-Projekt nach dem Vorbild eines Rechtstexte-Update-Service: Die Rechtsabteilung pflegt zentrale Vorlagen für Impressum, AGB, Datenschutzerklärung und Widerrufsbelehrung. Aus Vorlage und Stammdaten des Händlers entstehen dessen persönliche Rechtstexte. Ändert sich eine Vorlage (z. B. nach einer Gesetzesänderung), werden die Texte aller Händler neu erzeugt und automatisch an ihre Shops verteilt – Shopify, JTL-Shop oder ein beliebiger Shop per signiertem Webhook. Händler können optional verlangen, jede neue Fassung vorher freizugeben. Partner rufen die aktuellen Texte über eine REST-API ab.

Lokal in zwei Minuten startklar (siehe unten). Demo-Logins aus dem Seeder: Händler `demo@example.com` / `password`, Rechtsabteilung `admin@example.com` / `password`. API-Doku unter `/docs/api`.

## Stack

- Laravel 13, PHP 8.4
- Livewire 4, Alpine.js, Flux UI (freie Komponenten), Tailwind CSS 4 – auf Basis des offiziellen Livewire Starter Kits
- Queues mit Database-Treiber
- Oberfläche auf Deutsch über Laravels Lokalisierung (`lang/de.json`, Laravels eigene Texte aus `laravel-lang`), Code und API auf Englisch
- Sanctum für die Partner-API, OpenAPI-Doku via [Scramble](https://scramble.dedoc.co)
- Pest, Larastan (Level 8), Pint
- GitHub Actions: Pint, Larastan und Pest (gegen SQLite **und** PostgreSQL) bei jedem Push
- SQLite lokal, PostgreSQL-kompatibel (die CI testet gegen beide)

## Lokal starten

```bash
composer setup
php artisan migrate:fresh --seed
composer run dev
```

`composer setup` installiert alle Abhängigkeiten, legt die `.env` an, migriert die SQLite-Datenbank und baut die Assets. `composer run dev` startet Webserver, Queue-Worker, Vite und Log-Ausgabe gemeinsam. Danach unter <http://localhost:8000> anmelden.

Der Seeder legt an:
- einen Admin der Rechtsabteilung (`admin@example.com`) und veröffentlichte Vorlagen für alle vier Rechtstexte,
- einen Demo-Händler (`demo@example.com`) mit Stammdaten, aktivierter Freigabe und drei Shops,
- ein AGB-Update (Vorlage v2), das beim Händler auf Freigabe wartet.

Einer der Shops nutzt den eingebauten **Mock-Shop**, der per `MOCK_SHOP_FAILURE_RATE` (Standard `0.3`) zufällig mit HTTP 503 antwortet. So sieht man Retries, Backoff und endgültige Fehler live im Dashboard.

Qualitätschecks:

```bash
composer test
```

## Beispiel-API-Aufruf

Token unter *Einstellungen → API-Tokens* erzeugen, dann:

```bash
curl -H "Authorization: Bearer <token>" -H "Accept: application/json" \
  http://localhost:8000/api/legal-texts/terms
```

```json
{
  "data": {
    "type": "terms",
    "title": "AGB",
    "current_version": {
      "version": 2,
      "content": "§ 1 Geltungsbereich ...",
      "published_at": "2026-10-05T08:12:44+00:00"
    }
  }
}
```

| Endpoint | Beschreibung |
| --- | --- |
| `GET /api/legal-texts` | aktuelle veröffentlichte Version aller Rechtstexte |
| `GET /api/legal-texts/{type}` | aktuelle Version eines Rechtstexts |
| `GET /api/legal-texts/{type}/versions` | veröffentlichte Versionshistorie, neueste zuerst, paginiert |

Die OpenAPI-Spezifikation wird aus dem Code erzeugt: UI unter `/docs/api`, JSON unter `/docs/api.json`.

## Ablauf

```
Rechtsabteilung veröffentlicht Vorlage
  └─ LegalTemplateVersion::publish()        atomar: nur wenn published_at noch NULL
       └─ Event LegalTemplateVersionPublished
            └─ Queued Listener GenerateLegalTextsForMerchants
                 └─ pro Händler: Job GenerateLegalText
                      └─ LegalTextGenerator: Vorlage + Stammdaten → neue LegalTextVersion
                           ├─ ohne Freigabe: sofort publish()
                           └─ mit Freigabe:  wartet, bis der Händler freigibt → publish()

LegalTextVersion::publish()                 atomar, ebenso
  └─ Event LegalTextVersionPublished
            └─ Queued Listener DistributeLegalTextVersion
                 └─ pro Shop: Delivery anlegen (unique shop + version)
                      └─ Job DeliverLegalTextVersion   5 Versuche, Backoff 10/30/60/120 s
                           └─ ShopAdapter je Shoptyp   Shopify (Mock) · JTL (Mock) · Generic Webhook (HMAC)
```

## Entscheidungen

**Vorlagen statt Freitext:** Händler schreiben ihre Rechtstexte nicht selbst, dafür bezahlen sie ja den Dienstleister. Die Rechtsabteilung pflegt `LegalTemplateVersion`s mit Platzhaltern wie `{{ company_name }}`, der Händler liefert nur seine Stammdaten (`MerchantProfile`). Der `LegalTextGenerator` setzt beides zusammen. Eine neue Fassung entsteht, wenn sich die Vorlage **oder** die Stammdaten ändern. Bewusst einfach gehalten: `strtr` statt Template-Engine, ein `is_admin`-Flag mit Gate statt Rollensystem.

**Versionierung:** Vorlagen und Händlertexte sind beide versioniert und unveränderlich. Jede `LegalTextVersion` verweist auf die Vorlagenversion, aus der sie entstand. So lässt sich bei einer Abmahnung belegen, welcher Händler wann welche Fassung live hatte. Unveränderter Inhalt erzeugt keine neue Version. „Aktuell“ ist jeweils die zuletzt veröffentlichte Version (`hasOne()->ofMany()`).

**Optionale Freigabe:** Standardmäßig gehen Updates automatisch live, so wie man es von einem Update-Service erwartet. Händler, die jede Änderung sehen wollen, aktivieren im Profil „Updates freigeben“. Neue Fassungen warten dann, die Shops behalten bis zur Freigabe die bisherige Fassung. Die Regel gilt einheitlich für Vorlagen-Updates und Stammdatenänderungen. Freigeben lässt sich nur die neueste Fassung: Kommt ein weiteres Update, bevor der Händler reagiert, ist die ältere ausstehende Fassung überholt. Diese Regel steckt in der Policy (`LegalTextVersionPolicy::approve`).

**Fan-out in zwei Stufen:** Eine Vorlagenänderung betrifft alle Händler und deren Shops. Statt alles in einem Job zu erledigen, erzeugt ein Job pro Händler den Text, und jede Veröffentlichung startet wiederum einen Job pro Shop. Fehler bleiben so auf einen Händler bzw. Shop begrenzt. Generierungsjobs für eine inzwischen überholte Vorlage brechen ab, damit eine verspätete Queue keine alte Fassung erzeugt.

**Adapter pro Shoptyp:** Jedes Shopsystem spricht eine andere API. Deshalb gibt es ein schmales `ShopAdapter`-Interface mit einer Implementierung pro Typ. Die Zuordnung steckt im Enum (`ShopType::adapter()`), der Container löst den Adapter auf. Ein neuer Shoptyp bedeutet: ein Enum-Case und eine Klasse, sonst nichts. Shopify und JTL sind hier Mocks; der Generic-Webhook ist echt und signiert den Body mit HMAC-SHA256 (`X-Signature: sha256=…`, Vergleich mit `hash_equals`).

**Idempotenz auf drei Ebenen:**
1. *Veröffentlichen* (Vorlage wie Händlertext): `publish()` setzt `published_at` per `UPDATE … WHERE published_at IS NULL`. Nur wer diese Zeile tatsächlich ändert, löst das Event aus – Doppelklicks und parallele Requests verteilen nichts doppelt.
2. *Verteilen:* Der Unique-Index `(shop_id, legal_text_version_id)` auf `deliveries` plus `createOrFirst()` garantiert eine Zustellung pro Shop und Version, auch wenn der Listener doppelt läuft (Queues liefern *at least once*).
3. *Zustellen:* Der Job überspringt bereits zugestellte Deliveries und schickt einen `Idempotency-Key` mit, damit der Shop wiederholte Requests erkennt.

**Warum Queues:** Externe Shops sind langsam oder zeitweise down. Im Request würde ein einziger hängender Shop das Veröffentlichen blockieren, und ein Fehler wäre schwer zu wiederholen. Mit einem Job pro Shop sind die Shops voneinander isoliert, Laravel übernimmt Retries mit Backoff, und nach dem letzten Versuch landet der Job in `failed_jobs`. Die Delivery steht dann auf `failed` und lässt sich im Dashboard per Klick neu senden. Der Database-Treiber reicht für dieses Volumen und braucht keine zusätzliche Infrastruktur.

**SSRF-Schutz:** Händler geben eine beliebige Webhook-URL an, die der Server aufruft. Ohne Schutz könnte man so interne Dienste, Datenbanken oder Cloud-Metadaten (`169.254.169.254`) erreichen. Deshalb müssen alle aufgelösten Adressen global erreichbar sein (`FILTER_FLAG_GLOBAL_RANGE`). Geprüft wird beim Speichern **und** vor jeder Zustellung. Die Verbindung wird per `CURLOPT_RESOLVE` auf die geprüfte IP festgelegt, Redirects sind aus. So hilft auch DNS-Rebinding nicht weiter. Ein unsicheres Ziel ist ein dauerhafter Fehler: Die Zustellung scheitert sofort, statt fünfmal wiederholt zu werden. Einzige Ausnahme ist der von der App selbst erzeugte Mock-Shop-Endpunkt.

**Neue Shops:** Ein neu angelegter Shop bekommt sofort alle aktuell live geschalteten Rechtstexte, nicht erst beim nächsten Update. Dafür nutzt er dasselbe idempotente `Shop::deliver()` wie die normale Verteilung.

**Keine E-Mail-Verifizierung:** Für eine Demo ohne Mailversand bringt sie nichts. Statt einer `verified`-Middleware, die nichts schützt, ist die Verifizierung bewusst komplett entfernt. Für den Produktivbetrieb würde man sie mit echtem Mail-Transport wieder aktivieren (`MustVerifyEmail`, Fortify-Feature, `verified`-Middleware).

**Mandantentrennung:** Händler = User. Alle Abfragen laufen über die Relationen des eingeloggten Users (`$user->shops()`, `$user->legalTexts()`); Aktionen mit IDs vom Client prüfen zusätzlich Policies. Für mehrere Benutzer pro Händler wäre der nächste Schritt ein Team-Modell.

## Von Symfony zu Laravel

- **Messenger → Queues:** Statt Message + Handler + Transport-Routing gibt es Jobs, die sich selbst beschreiben: `$tries`, `backoff()` und `failed()` stehen direkt an der Klasse. Events mit `ShouldQueue`-Listenern ersetzen asynchrone Event-Subscriber; Listener werden automatisch entdeckt.
- **Doctrine → Eloquent:** Active Record statt Data Mapper. Domänenlogik wie `publish()` oder `retry()` liegt direkt am Model, Relationen sind Methoden, Casts übernehmen Enums und Verschlüsselung (`'secret' => 'encrypted'`). Migrationen schreibe ich von Hand, statt sie aus Entity-Diffs zu generieren.
- **Twig → Livewire/Blade:** Statt Controller + Formular-Typ + Twig-Template steckt eine interaktive Seite in einer einzigen Livewire-Komponente; Polling (`wire:poll`) und Aktionen (`wire:click`) brauchen kein eigenes JavaScript. Formularlogik liegt in Livewire-Form-Objekten.
- **Voter → Policies und Gates:** Eine Policy pro Model mit einer Methode pro Fähigkeit, automatisch über Namenskonventionen gefunden und per `$this->authorize('approve', $version)` geprüft. Für modellunabhängige Rechte wie den Admin-Bereich reicht ein Gate (`can:manage-templates` als Route-Middleware).
- **Services/DI-Konfiguration → Container ohne YAML:** Autowiring ist da, aber kaum Konfiguration nötig. Contextual Attributes wie `#[CurrentUser]` injizieren den eingeloggten Benutzer direkt in Controller-Methoden.

## Projektstruktur

| Pfad | Inhalt |
| --- | --- |
| `app/Models` | `LegalTemplateVersion`, `MerchantProfile`, `LegalText`, `LegalTextVersion`, `Shop`, `Delivery` |
| `app/Enums` | `ShopType` (inkl. Adapter-Zuordnung), `LegalTextType`, `DeliveryStatus` |
| `app/Events`, `app/Listeners`, `app/Jobs` | Vorlage veröffentlichen → Texte erzeugen → verteilen → zustellen |
| `app/Services/LegalTextGenerator.php` | Vorlage + Stammdaten → Händlertext, direkt live oder zur Freigabe |
| `app/Services/ShopAdapters` | Interface und Adapter pro Shoptyp |
| `app/Support/WebhookSignature.php` | HMAC-Signatur und -Prüfung |
| `app/Http/Controllers/Api`, `app/Http/Resources` | Partner-API |
| `app/Policies` | Mandantentrennung für Aktionen |
| `resources/views/pages` | Livewire-Seiten: Dashboard, Rechtstexte mit Freigabe, Stammdaten, Shops, API-Tokens, Vorlagen (Admin) |
| `tests/Feature`, `tests/Unit` | Pest-Tests |

---

Umgesetzt mit Unterstützung von KI-Werkzeugen ([Claude Code](https://claude.com/claude-code)). Fachmodell, Architekturentscheidungen und Code-Review stammen von mir.
