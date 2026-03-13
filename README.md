[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-1.0-blue.svg)]()
[![Version](https://img.shields.io/badge/Symcon%20Version-7.0%20%3E-green.svg)](https://www.symcon.de/forum/threads/30857-IP-Symcon-5-3-%28Stable%29-Changelog)

# WLED
Die Bibliothek dient zum Steuern von LED-Stripes an einem mit [WLED](http://kno.wled.ge) geflashten ESP-Modul.

## Dokumentation

**Inhaltsverzeichnis**

1. [Voraussetzungen](#1-voraussetzungen)
2. [Funktionsumfang](#2-funktionsumfang)  
3. [Software-Installation](#3-software-installation)  
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen](#5-statusvariablen)
6. [Lizenz](#6-lizenz)

## 1. Voraussetzungen
- IPS 7.0 oder höher
- ESP Modul mit installiertem [WLED](https://kno.wled.ge/)

## 2. Funktionsumfang

Es werden vier Instanzen zur Verf�gung gestellt:

- __WLED Discovery__<br>
	Findet WLED Instanzen im Netzwerk per mDNS und bietet die automatische Anlage der WLED-Instanzen an.


- __WLED Splitter__<br>
	Der Splitter stellt die Verbindung zwischen dem Websocket Client und den WLED Instanzen her.
	
- __WLED Segment__ <br>
	Hierüber wird ein einzelnes Segment gesteuert.

- __WLED Master__  
	Zur Steuerung des Masters.

Alle vier Instanzen besitzen im Konfigurationsformular einen Bereich __Expert__ mit der Option __EnableExpertDebug__ f�r erweiterte Debug-Ausgaben.

Bei der Anlage der Instanzen wird – sofern noch kein WebSocket angelegt wurde – ein WebSocket unter den IO Instanzen angelegt. Dort ist der Endpunkt einzutragen, unter dem der WLED-Server erreicht werden kann.

Er hat die Form "ws://[WLED_IP]/ws"

## 3. Software-Installation
Über den Module Store das 'WLED'-Modul installieren.

## 4. Einrichten der Instanzen in IP-Symcon

Unter 'Instanz hinzufügen' können die 'Geräte' mithilfe des Schnellfilters gefunden werden.

### WS-Client
Bei der Anlage der Instanzen wird – sofern noch kein WebSocket angelegt wurde – ein WebSocket unter den IO Instanzen angelegt. Dort ist der Endpunkt einzutragen, unter dem der WLED-Server erreicht werden kann.

Er hat die Form **"ws://[WLED_IP]/ws"**
### WLED Splitter
#### Konfigurationsseite
Hier kann ausgewählt werden, ob der Einschaltzustand des Masters, mit dem die Segmente synchronisiert werden soll.<br>
Zudem stehen Funktionen zur Verfügung, um die Presets, die Playlisten, die Effekte und die Paletten neu einzulesen.

### WLED Master
#### Konfigurationsseite
| Name            | Beschreibung                                                       |
|:----------------|:-------------------------------------------------------------------|
| Presets         | es wird die Statusvariable 'Presets' angelegt                      |
| Wiedergabeliste | es wird die Statusvariable 'Wiedergabeliste' angelegt              |
| Nachtlicht      | es werden Statusvariablen zur Steuerung des Nachtlichts angelegt   |

### WLED Segment
#### Konfigurationsseite
| Name         | Beschreibung                                                        |
|:-------------|:--------------------------------------------------------------------|
| Segment Nr.  | Nummer des Segments, das gesteuert werden soll                      |
| Effekt Modus | es wird eine Statusvariable zur Auswahl des 'Effekt Modus' angelegt |
| Paletten     | es wird eine Statusvariable zur Auswahl einer 'Palette' angelegt    |
| Kanaal Weiß  | es wird eine Statusvariable zum Setzen des 'Kanal Weiß' angelegt    |
| Farben 2 + 3 | es werden Statusvariablen für die Farben 2 + 3 angelegt             |
| CCT          | es wird eine Statusvariable zur CCT-Steuerung angelegt              |

## 5. Statusvariablen

### WLED Master
| Name                          | Typ     | Beschreibung                         |
|:------------------------------|:--------|:-------------------------------------|
| Ein                           | Boolean | Ein-/Auschalten eines Masters        |
| Helligkeit                    | Integer | Setzen der Helligkeit                |
| Übergang                      | Float   | Setzen der Übergangszeit in Sekunden |
| Presets                       | Integer | Auswahl eines Presets                |
| Wiedergabeliste               | Integer | Auswahl einer Wiedergabliste         |
| Nachtlicht an                 | Boolean | Ein-/Auschalten des Nachtlichtmodus  |
| Nachtlicht Dauer              | Integer | Dauer des Nachtlichtmodus            |
| Nachtlicht Modus              | Integer | Art des Nachtlichts                  |
| Nachtlicht Zielhelligkeit     | Integer | Zielhelligkeit des Nachtlichts       |
| Nachtlicht verbleibende Dauer | Integer | verbleibende Dauer des Nachtlichts   |

### WLED Segment
| Name                        | Typ     | Beschreibung                          |
|:----------------------------|:--------|:--------------------------------------|
| Ein                         | Boolean | Ein-/Auschalten eines Segments        |
| Helligkeit                  | Integer | Setzen der Helligkeit                 |
| Effekte                     | Integer | Auswahl eines Effekts                 |
| Effekt Geschwindigkeit      | Integer | Setzen der Geshwindigkeit des Effekts |
| Effekt Intensität           | Integer | Setzen der Intensität des Effekts     |
| Farbe 1, 2, 3               | Integer | Setzen der Farbe                      |
| Weißton Einstellung 1, 2, 4 | Integer | Setzen des Weißtons in K              |

## 6. Lizenz

  [GNU GENERAL PUBLIC LICENSE](http://www.gnu.org/licenses/)  
 


