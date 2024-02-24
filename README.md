[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-1.0-blue.svg)]()
[![Version](https://img.shields.io/badge/Symcon%20Version-7.0%20%3E-green.svg)](https://www.symcon.de/forum/threads/30857-IP-Symcon-5-3-%28Stable%29-Changelog)

# WLED
Die Bibiliotek dient zum Steuern von LED Stripes an einem mit [WLED](http://kno.wled.ge) geflashten ESP Modul.

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)  
2. [Voraussetzungen](#2-voraussetzungen)  
3. [Lizenz](#3-lizenz)

## 1. Funktionsumfang

Es werden drei Instanzen zur Verfügung gestellt:

- __WLED Splitter__<br>
	Der Splitter stellt die Verbindung zwischen dem WebScoket Client und den WLED Instanzen her.
	
- __WLED Segment__ ([Dokumentation](SymconWLEDSegment))  
	Hierüber wird ein einzelnes Segment gesteuert.

- __WLED Master__ ([Dokumentation](SymconWLEDSegment))  
	Zur Steuerung des Masters.

Bei der Anlage der Instanzen wird - sofern noch kein WebSocket angelegt wurde - ein WebSocket unter den IO Instanzen angelegt. Dort ist der Endpunkt einzutragen, unter dem der WLED Server erreicht werden kann.

Er hat die Form "ws://[WLED_IP]/ws"

## 2. Voraussetzungen

 - IPS 7.0 oder höher
 - ESP Modul mit installiertem WLED

## 3. Lizenz

  [GNU GENERAL PUBLIC LICENSE](http://www.gnu.org/licenses/)  
 
