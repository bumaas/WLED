[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-1.0-blue.svg)]()
[![Version](https://img.shields.io/badge/Symcon%20Version-7.0%20%3E-green.svg)](https://www.symcon.de/forum/threads/30857-IP-Symcon-5-3-%28Stable%29-Changelog)

# WLED
Die Bibliothek dient zum Steuern von LED-Stripes an einem mit [WLED](http://kno.wled.ge) geflashten ESP-Modul.

## Dokumentation

1. [Voraussetzungen](#1-voraussetzungen)
2. [Funktionsumfang](#2-funktionsumfang)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen](#5-statusvariablen)
6. [Lizenz](#6-lizenz)

## 1. Voraussetzungen
- IPS 7.0 oder hoeher
- ESP Modul mit installiertem [WLED](https://kno.wled.ge/)

## 2. Funktionsumfang

Es werden vier Instanzen zur Verfuegung gestellt:

- __WLED Discovery__
  Findet WLED Instanzen im Netzwerk per mDNS und bietet die automatische Anlage der WLED-Instanzen an.

- __WLED Splitter__
  Der Splitter stellt die Verbindung zwischen dem WebSocket-Client und den WLED-Instanzen her.

- __WLED Segment__
  Hierueber wird ein einzelnes Segment gesteuert.

- __WLED Master__
  Zur Steuerung des Masters.

Alle vier Instanzen besitzen im Konfigurationsformular einen Bereich __Expert__ mit der Option __EnableExpertDebug__ für erweiterte Debug-Ausgaben.

## 3. Software-Installation
Ueber den Module Store das Modul "WLED" installieren.

## 4. Einrichten der Instanzen in IP-Symcon

### WS-Client
Bei der Anlage der Instanzen wird, sofern noch kein WebSocket angelegt wurde, ein WebSocket unter den IO-Instanzen angelegt.
Dort ist der Endpunkt einzutragen, unter dem der WLED-Server erreichbar ist:

**ws://[WLED_IP]/ws**

### WLED Splitter
#### Konfigurationsseite
- Sync Power with Segments: Synchronisiert den Einschaltzustand des Masters mit den Segmenten.
- Update profile WLED.Presets
- Update profile WLED.Playlists
- Update profile WLED.Effects
- Update profile WLED.Palettes
- Expert: EnableExpertDebug

### WLED Master
#### Konfigurationsseite
- Presets: Legt die Statusvariable "Presets" an.
- Playlist: Legt die Statusvariable "Wiedergabeliste" an.
- Nightlight: Legt Statusvariablen fuer den Nachtlichtmodus an.
- Expert: EnableExpertDebug

### WLED Segment
#### Konfigurationsseite
- Segment ID
- Effect Mode
- Palettes
- Channel White
- Colors (2 + 3)
- CCT
- Expert: EnableExpertDebug

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
- White Tone Control 1, 2, 3 (Integer)

## 6. Lizenz
[GNU GENERAL PUBLIC LICENSE](http://www.gnu.org/licenses/)
