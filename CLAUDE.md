# WLED: Projektwissen

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
  Schlüssel, das ist korrekt). DNS-SD Control ist eine Kerninstanz und wird vorausgesetzt;
  die Discovery legt sie nie selbst an (Store-Review-Punkte 6/13), sondern zeigt ohne sie den
  Hinweis `mdnsMissing` und sucht nicht (Test `tests/check-discovery-dnssd.php`, Kernel-Stub).
- **SymconWLEDSplitter** (Typ 2): hängt am WebSocket Client (`ws://<host>/ws`), verteilt
  die WLED-State-JSONs an Master/Segmente. Den Gerätenamen aus `info.name` hängt er unter
  `WLEDIds::KEY_DEVICE_NAME` an; daraus setzen Master und Segment ihre Zusammenfassung. Per HTTP
  holen Master/Segment nur noch die Listen (`/presets.json`, `/json/eff`, `/json/pal`), die WLED
  über den WebSocket nicht liefert (Test `tests/check-geraetename.php`, Fixture echter WS-Mitschnitt).
  Einträge in `state.seg`, die kein Objekt sind, überspringt er (Test `tests/check-segmentdaten.php`).
- **SymconWLEDMaster** (Typ 3): globale Funktionen wie Power, Helligkeit, Transition,
  Presets/Playlists (Start über `ps`; `pl` ist read-only!), Nachtlicht.
- **SymconWLEDSegment** (Typ 3): pro Segment Power, Helligkeit, CCT, Effekte, Paletten,
  Farben/Weißkanäle 1 bis 3. Anzuzeigende Variablen sind per Checkbox-Properties abwählbar.
  Der `ReceiveDataFilter` verlangt hinter der Segmentnummer eine Nicht-Ziffer, sonst nähme
  Segment 1 auch 10 bis 19 an (Test `tests/check-segmentdaten.php`).

## libs/

- `WLEDIds.php`: GUID-Konstanten
- `WLEDHttp.php`: HTTP-JSON-API-Helfer (`getHostFromDevice()`, `getData()`, z. B. für
  `/presets.json`, `/json/eff`, `/json/pal`)
- `WLEDPresentations.php`: Variablen-Darstellungen (Presentations statt Legacy-Profile)
- `WLEDDeviceTrait.php`: gemeinsame Basis von Master und Segment (SendData/GetUpdate/
  MessageSink, Zusammenfassung, Konfigurationsform, Refresh der dynamischen Listen); die
  Klassen liefern `RegisterVariables()`, `showRefreshButton()` und `deviceSummarySuffix()`
- `WLEDParentStatus.php` mit `WLEDParentStatusTrait`: Status folgt dem direkten Parent, Hop für
  Hop (104 ohne Parent, 201 Parent nicht aktiv, 102 aktiv; `IM_CHANGESTATUS` nur auf dem
  direkten Parent, `FM_CONNECT`/`FM_DISCONNECT` auf sich selbst). Splitter direkt, Master und
  Segment über `WLEDDeviceTrait`. Ob `IM_CHANGESTATUS` schon registriert ist, entscheidet
  `GetMessageList()`, nicht der Buffer `ParentID`: Buffer überstehen einen Kernel-Neustart, die
  Registrierungen nicht. Den Zustand (`{"v":true}`) fordert beim Aktivwerden nur der Splitter
  einmal an; Master und Segment fragen selbst nur nach eigenem `ApplyChanges` oder Verbinden
  (`followParentAndRequestState()`). `SetStatus(IS_INACTIVE)` steht am Ende von `Create()` (der
  Stub verbietet Properties danach). Test `tests/check-status.php`
- `ModuleDebug.php`: `ModuleDebugTrait` mit `debugExpert()` (Property „Enable extended
  debug output"); Debug-Kategorien sind an Methodennamen (`__FUNCTION__`) gekoppelt, deshalb
  beim Umbenennen von Methoden `BASIC_DEBUG_CATEGORIES` mitprüfen. `debugExpert()` darf auch
  vor dem Registrieren der Property laufen (in `Create()`); dann gilt sie als aus

Alle Module: `IPSModuleStrict` + `declare(strict_types=1)`.
Hinweis: Im Code kommt auch kleingeschriebenes `->translate()` vor, der Locale-Check
matcht deshalb case-insensitiv.

## Checks / CI

- Lokal: `C:\php\php tests\check_locale.php` (Übersetzungs-Vollständigkeit; Exit 1 bei Lücken).
  Meldet auch wörtliche `'Caption' => '<Text>'` im module.php (Darstellungsoptionen ohne
  `translate()`); kleines `caption` in dynamischen Formularen übersetzt Symcon selbst
- `tests\check-kernelready.php`: KR_READY-Muster aller Module (kein eigener `ApplyChanges()`-Aufruf,
  auch nicht als `IPS_ApplyChanges($this->InstanceID)`; wer `IPS_KERNELMESSAGE` registriert, prüft in
  `ApplyChanges` den Runlevel gegen `KR_READY` und erledigt die KR_READY-Arbeit im `MessageSink` über
  eine eigene Methode). Methodenrümpfe und Trait-Einbindung kommen aus `token_get_all()`. Anlass:
  Store-Review lehnte Stable 1.1 #41 ab, weil die Discovery im `MessageSink` direkt `ApplyChanges()`
  aufrief. Die Discovery registriert seit build 45 gar kein `IPS_KERNELMESSAGE` mehr, weil sie bei
  KR_READY nichts nachzuholen hat.
- CI: `.github/workflows/check.yml` mit php -l (PHP 8.4, Module, libs und tests), JSON-Validität,
  Locale-Check, MCP-Prüfung (`bumaas/symcon-mcp-check@v1`), Modultests (`tests/check-*.php`)
- `tests\check-fehlerunterdrueckung.php`: kein `@` vor `SendDataToParent`/`SendDataToChildren`
  (Best Practice 1.2); `SendData()` prüft stattdessen `HasActiveParent()` und schreibt ins Debug
- Kernel-Stub `symcon/SymconStubs` als Submodul `tests/stubs` (gepinnt), Checkout mit
  `submodules: true`, aus der JSON-Prüfung ausgenommen

## Konventionen

- Version/Build/Release-Ablauf: siehe globale `CLAUDE.md`
  („Build-/Versionspflege in Modul-Repos"); Commit-Subject `<version> build <NN>: <Beschreibung>`.
