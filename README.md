[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-1.1-blue.svg)]()
[![Version](https://img.shields.io/badge/Symcon%20Version-9.0%20%3E-green.svg)](https://www.symcon.de/forum/)

# WLED
Die Bibliothek integriert [WLED](http://kno.wled.ge) nahtlos in IP-Symcon und ermöglicht die Steuerung kompatibler LED-Installationen.
Über die Discovery-Funktion werden WLED-Geräte im Netzwerk automatisch erkannt und für die weitere Einrichtung vorbereitet.
Master- und Segment-Module bilden zentrale sowie segmentbezogene Funktionen wie Helligkeit, Farben, Effekte, Paletten, Presets und Playlists in Symcon ab.
Damit entsteht eine flexible Grundlage für kleine Licht-Setups ebenso wie für umfangreiche, segmentierte Installationen.

## Dokumentation

1. [Voraussetzungen](#1-voraussetzungen)
2. [Funktionsumfang](#2-funktionsumfang)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen](#5-statusvariablen)
6. [Lizenz](#6-lizenz)

## 1. Voraussetzungen
- Symcon 9.0 oder höher
- ESP Modul mit installiertem [WLED](https://kno.wled.ge/)

## 2. Funktionsumfang

Es werden vier Instanzen zur Verfügung gestellt:

- __WLED Discovery__
  Findet WLED Instanzen im Netzwerk per mDNS und bietet die automatische Anlage der WLED-Instanzen an.

- __WLED Splitter__
  Der Splitter stellt die Verbindung zwischen dem WebSocket-Client und den WLED-Instanzen her.

- __WLED Segment__
  Hierüber wird ein einzelnes Segment gesteuert.

- __WLED Master__
  Zur Steuerung des Masters.

Alle vier Instanzen besitzen im Konfigurationsformular einen Bereich __Expert__ mit der Option __EnableExpertDebug__ für erweiterte Debug-Ausgaben.

## 3. Software-Installation
Über den Module Store das Modul "WLED" installieren.

## 4. Einrichten der Instanzen in IP-Symcon

### WS-Client
Bei der Anlage der Instanzen wird, sofern noch kein WebSocket angelegt wurde, ein WebSocket unter den IO-Instanzen angelegt.
Dort ist der Endpunkt einzutragen, unter dem der WLED-Server erreichbar ist:

**ws://[WLED_IP]/ws**

### WLED Splitter
#### Konfigurationsseite
- Sync Power with Segments: Synchronisiert den Einschaltzustand des Masters mit den Segmenten.
- Expert: EnableExpertDebug

### WLED Master
#### Konfigurationsseite
- Presets: Legt die Statusvariable "Presets" an.
- Playlist: Legt die Statusvariable "Wiedergabeliste" an.
- Nightlight: Legt Statusvariablen für den Nachtlichtmodus an.
- Expert: EnableExpertDebug

Hinweis:
- Presets, Playlists, Effects und Palettes werden als Darstellungen (Enumeration) direkt aus den WLED-Endpunkten geladen.
- Es werden keine dynamischen WLED.*-Variablenprofile mehr benötigt.
- Im Aktionsbereich steht "Presets/Playlists aktualisieren" zur Verfügung.
- Die Schaltfläche wird nur angezeigt, wenn Presets oder Playlist aktiviert sind.

### WLED Segment
#### Konfigurationsseite
- Segment ID
- Effect Mode
- Palettes
- Channel White
- Colors (2 + 3)
- CCT
- Expert: EnableExpertDebug
- Im Aktionsbereich steht "Effekte/Paletten aktualisieren" zur Verfügung.
- Die Schaltfläche wird nur angezeigt, wenn Effect Mode oder Palettes aktiviert sind.

## 5. Statusvariablen

### WLED Master
- Power (Boolean)
- Brightness (Integer)
- Transition (Float)
- Presets (Integer)
- Playlist (Integer)
- Nightlight On (Boolean)
- Nightlight Duration (Integer)
- Nightlight Mode (Integer)
- Nightlight Target Brightness (Integer)
- Remaining Nightlight Duration (Integer)

### WLED Segment
- Power (Boolean)
- Brightness (Integer)
- Effects (Integer)
- Effect Speed (Integer)
- Effect Intensity (Integer)
- Color 1, 2, 3 (Integer)
- White 1, 2, 3 (Integer)
- White Tone Control 1, 2, 3 (Integer)

## 6. Lizenz
[GNU GENERAL PUBLIC LICENSE](http://www.gnu.org/licenses/)
