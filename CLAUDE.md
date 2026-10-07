# WLED — Projektwissen

Symcon-Modulbibliothek (kompatibel ab Symcon 9.0) zur Steuerung von
[WLED](https://kno.wled.ge)-LED-Controllern. Store-Bundle: `wled.symcon.acer90.module`
(ursprünglich von Swen Babenschneider/acer90, weitergepflegt von bumaas).

## Architektur (4 Module)

```
WLED-Gerät ⇄ WebSocket Client (I/O) ⇄ WLED Splitter ⇄ WLED Master
                                                    ⇄ WLED Segment (je Segment eine Instanz)
```

- **SymconWLEDDiscovery** (Typ 5): findet Geräte per mDNS (`_wled._tcp`); zeigt pro Host
  bevorzugt den WLED Master, sonst den Splitter. Konfigurationsform wird dynamisch in
  `GetConfigurationForm()` erzeugt (deshalb meldet der Locale-Check dort „verwaiste"
  Schlüssel — das ist korrekt).
- **SymconWLEDSplitter** (Typ 2): hängt am WebSocket Client (`ws://<host>/ws`), verteilt
  die WLED-State-JSONs an Master/Segmente.
- **SymconWLEDMaster** (Typ 3): globale Funktionen — Power, Helligkeit, Transition,
  Presets/Playlists (Start über `ps`; `pl` ist read-only!), Nachtlicht.
- **SymconWLEDSegment** (Typ 3): pro Segment — Power, Helligkeit, CCT, Effekte, Paletten,
  Farben/Weißkanäle 1–3. Anzuzeigende Variablen sind per Checkbox-Properties abwählbar.

## libs/

- `WLEDIds.php` — GUID-Konstanten
- `WLEDHttp.php` — HTTP-JSON-API-Helfer (`getHostFromDevice()`, `getData()`, z. B. für
  `/presets.json`, `/json/eff`, `/json/pal`)
- `WLEDPresentations.php` — Variablen-Darstellungen (Presentations statt Legacy-Profile)
- `WLEDDeviceTrait.php` — gemeinsame Basis von Master und Segment (SendData/GetUpdate/
  MessageSink, Geräteinfo, Konfigurationsform, Refresh der dynamischen Listen); die
  Klassen liefern `RegisterVariables()`, `showRefreshButton()` und `deviceSummarySuffix()`
- `ModuleDebug.php` — `ModuleDebugTrait` mit `debugExpert()` (Property „Enable extended
  debug output"); Debug-Kategorien sind an Methodennamen (`__FUNCTION__`) gekoppelt —
  beim Umbenennen von Methoden `BASIC_DEBUG_CATEGORIES` mitprüfen

Alle Module: `IPSModuleStrict` + `declare(strict_types=1)`.
Hinweis: Im Code kommt auch kleingeschriebenes `->translate()` vor — der Locale-Check
matcht deshalb case-insensitiv.

## Checks / CI

- Lokal: `C:\php\php tests\check_locale.php` (Übersetzungs-Vollständigkeit; Exit 1 bei Lücken)
- `tests\check-kernelready.php`: KR_READY-Muster aller Module (kein eigener `ApplyChanges()`-Aufruf,
  auch nicht als `IPS_ApplyChanges($this->InstanceID)`; wer `IPS_KERNELMESSAGE` registriert, prüft in
  `ApplyChanges` den Runlevel gegen `KR_READY` und erledigt die KR_READY-Arbeit im `MessageSink` über
  eine eigene Methode). Methodenrümpfe und Trait-Einbindung kommen aus `token_get_all()`. Anlass:
  Store-Review lehnte Stable 1.1 #41 ab, weil die Discovery im `MessageSink` direkt `ApplyChanges()`
  aufrief. Die Discovery registriert seit build 45 gar kein `IPS_KERNELMESSAGE` mehr, weil sie bei
  KR_READY nichts nachzuholen hat.
- CI: `.github/workflows/check.yml` — php -l (PHP 8.4, Module, libs und tests), JSON-Validität,
  Locale-Check, MCP-Prüfung (`bumaas/symcon-mcp-check@v1`), Modultests (`tests/check-*.php`)

## Konventionen

- Version/Build/Release-Ablauf: siehe globale `CLAUDE.md`
  („Build-/Versionspflege in Modul-Repos"); Commit-Subject `<version> build <NN>: <Beschreibung>`.
