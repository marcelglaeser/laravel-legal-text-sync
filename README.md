# Legal Text Sync

Ein kleines Laravel-Demo-Projekt: Onlinehändler pflegen ihre Rechtstexte (Impressum, AGB, Datenschutzerklärung, Widerrufsbelehrung) versioniert an einer Stelle. Jede veröffentlichte Version wird automatisch an alle Shops des Händlers verteilt – Shopify, JTL-Shop oder ein beliebiger Shop per signiertem Webhook. Partner können die aktuellen Texte über eine REST-API abrufen.

**Live:** _https://legal-text-sync.laravel.cloud_ (Platzhalter – wird nach dem Deployment ersetzt)
Demo-Login: `demo@example.com` / `password` · API-Doku: [`/docs/api`](https://legal-text-sync.laravel.cloud/docs/api)

## Stack

- Laravel 13, PHP 8.4
- Livewire 4, Alpine.js, Flux UI (freie Komponenten), Tailwind CSS 4 – auf Basis des offiziellen Livewire Starter Kits
- Queues mit Database-Treiber
- Sanctum für die Partner-API, OpenAPI-Doku via [Scramble](https://scramble.dedoc.co)
- Pest, Larastan (Level 8), Pint
- GitHub Actions: Pint, Larastan und Pest (gegen SQLite **und** PostgreSQL) bei jedem Push
- SQLite lokal, PostgreSQL auf Laravel Cloud

## Lokal starten

```bash
composer setup
php artisan db:seed
composer run dev
```

`composer setup` installiert alle Abhängigkeiten, legt die `.env` an, migriert die SQLite-Datenbank und baut die Assets. `composer run dev` startet Webserver, Queue-Worker, Vite und Log-Ausgabe gemeinsam. Danach unter <http://localhost:8000> mit `demo@example.com` / `password` anmelden.

Der Seeder legt einen Demo-Händler mit drei Shops an. Einer davon nutzt den eingebauten **Mock-Shop**, der per `MOCK_SHOP_FAILURE_RATE` (Standard `0.3`) zufällig mit HTTP 503 antwortet. So sieht man Retries, Backoff und endgültige Fehler live im Dashboard.

Qualitätschecks:

```bash
composer test
```

## Beispiel-API-Aufruf

Token unter *Settings → API tokens* erzeugen, dann:

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
Händler veröffentlicht Version
  └─ LegalTextVersion::publish()            atomar: nur wenn published_at noch NULL
       └─ Event LegalTextVersionPublished
            └─ Queued Listener DistributeLegalTextVersion
                 └─ pro Shop: Delivery anlegen (unique shop + version)
                      └─ Job DeliverLegalTextVersion   5 Versuche, Backoff 10/30/60/120 s
                           └─ ShopAdapter je Shoptyp   Shopify (Mock) · JTL (Mock) · Generic Webhook (HMAC)
```

## Entscheidungen

**Versionierung:** Ein `LegalText` pro Händler und Typ, darunter unveränderliche `LegalTextVersion`-Einträge mit fortlaufender Nummer. Speichern erzeugt immer eine neue Version, alte werden nie überschrieben. So bleibt nachvollziehbar, welcher Text wann galt, und genau das braucht man bei Abmahnungen. Unveränderter Inhalt erzeugt keine neue Version. „Aktuell“ ist die zuletzt veröffentlichte Version (`hasOne()->ofMany()`); Entwürfe bleiben unsichtbar, bis sie veröffentlicht werden.

**Adapter pro Shoptyp:** Jedes Shopsystem spricht eine andere API. Deshalb gibt es ein schmales `ShopAdapter`-Interface mit einer Implementierung pro Typ. Die Zuordnung steckt im Enum (`ShopType::adapter()`), der Container löst den Adapter auf. Ein neuer Shoptyp bedeutet: ein Enum-Case und eine Klasse, sonst nichts. Shopify und JTL sind hier Mocks; der Generic-Webhook ist echt und signiert den Body mit HMAC-SHA256 (`X-Signature: sha256=…`, Vergleich mit `hash_equals`).

**Idempotenz auf drei Ebenen:**
1. *Veröffentlichen:* `publish()` setzt `published_at` per `UPDATE … WHERE published_at IS NULL`. Nur wer diese Zeile tatsächlich ändert, löst das Event aus – Doppelklicks und parallele Requests verteilen nichts doppelt.
2. *Verteilen:* Der Unique-Index `(shop_id, legal_text_version_id)` auf `deliveries` plus `createOrFirst()` garantiert eine Zustellung pro Shop und Version, auch wenn der Listener doppelt läuft (Queues liefern *at least once*).
3. *Zustellen:* Der Job überspringt bereits zugestellte Deliveries und schickt einen `Idempotency-Key` mit, damit der Shop wiederholte Requests erkennt.

**Warum Queues:** Externe Shops sind langsam oder zeitweise down. Im Request würde ein einziger hängender Shop das Veröffentlichen blockieren, und ein Fehler wäre schwer zu wiederholen. Mit einem Job pro Shop sind die Shops voneinander isoliert, Laravel übernimmt Retries mit Backoff, und nach dem letzten Versuch landet der Job in `failed_jobs`. Die Delivery steht dann auf `failed` und lässt sich im Dashboard per Klick neu senden. Der Database-Treiber reicht für dieses Volumen und braucht keine zusätzliche Infrastruktur.

**Mandantentrennung:** Händler = User. Alle Abfragen laufen über die Relationen des eingeloggten Users (`$user->shops()`, `$user->legalTexts()`); Aktionen mit IDs vom Client prüfen zusätzlich Policies. Für mehrere Benutzer pro Händler wäre der nächste Schritt ein Team-Modell.

## Von Symfony zu Laravel

- **Messenger → Queues:** Statt Message + Handler + Transport-Routing gibt es Jobs, die sich selbst beschreiben: `$tries`, `backoff()` und `failed()` stehen direkt an der Klasse. Events mit `ShouldQueue`-Listenern ersetzen asynchrone Event-Subscriber; Listener werden automatisch entdeckt.
- **Doctrine → Eloquent:** Active Record statt Data Mapper. Domänenlogik wie `publish()` oder `retry()` liegt direkt am Model, Relationen sind Methoden, Casts übernehmen Enums und Verschlüsselung (`'secret' => 'encrypted'`). Migrationen schreibe ich von Hand, statt sie aus Entity-Diffs zu generieren.
- **Twig → Livewire/Blade:** Statt Controller + Formular-Typ + Twig-Template steckt eine interaktive Seite in einer einzigen Livewire-Komponente; Polling (`wire:poll`) und Aktionen (`wire:click`) brauchen kein eigenes JavaScript. Formularlogik liegt in Livewire-Form-Objekten.
- **Voter → Policies:** Eine Policy pro Model mit einer Methode pro Fähigkeit, automatisch über Namenskonventionen gefunden und per `$this->authorize('publish', $version)` geprüft.
- **Services/DI-Konfiguration → Container ohne YAML:** Autowiring ist da, aber kaum Konfiguration nötig. Contextual Attributes wie `#[CurrentUser]` injizieren den eingeloggten Benutzer direkt in Controller-Methoden.

## Deployment auf Laravel Cloud

1. Repository in Laravel Cloud verbinden, neue Application anlegen (Region Frankfurt).
2. Im Environment eine **Laravel Serverless Postgres**-Datenbank anlegen und anhängen – die `DB_*`-Variablen setzt Cloud selbst.
3. Am **App cluster** unter *Background processes* → *New background process* einen **Queue worker** (1 Prozess) hinzufügen. Hinweis: *Managed queues* nicht verwenden, die würden `QUEUE_CONNECTION=cloud` setzen; dieses Projekt nutzt bewusst den Database-Treiber.
4. Umgebungsvariablen ergänzen: `QUEUE_CONNECTION=database`, `MOCK_SHOP_FAILURE_RATE=0.3`.
5. Deploy-Befehl `php artisan migrate --force` (Standard) beibehalten, deployen und einmalig unter *Commands* `php artisan db:seed --force` ausführen.

Scale-to-Zero kann aktiv bleiben: Cloud weckt Laravel-Umgebungen für Queue-Jobs auf. Ein Job, der beim Einschlafen noch läuft, wird allerdings abgebrochen und beim nächsten Versuch wiederholt.

## Projektstruktur

| Pfad | Inhalt |
| --- | --- |
| `app/Models` | `Shop`, `LegalText`, `LegalTextVersion`, `Delivery` |
| `app/Enums` | `ShopType` (inkl. Adapter-Zuordnung), `LegalTextType`, `DeliveryStatus` |
| `app/Events`, `app/Listeners`, `app/Jobs` | Veröffentlichen → Verteilen → Zustellen |
| `app/Services/ShopAdapters` | Interface und Adapter pro Shoptyp |
| `app/Support/WebhookSignature.php` | HMAC-Signatur und -Prüfung |
| `app/Http/Controllers/Api`, `app/Http/Resources` | Partner-API |
| `app/Policies` | Mandantentrennung für Aktionen |
| `resources/views/pages` | Livewire-Seiten (Dashboard, Shops, Rechtstexte, API-Tokens) |
| `tests/Feature`, `tests/Unit` | Pest-Tests |

---

Dieses Projekt wurde mit [Claude Code](https://claude.com/claude-code) entwickelt.
