[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/en/service/documentation/developer-area/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-1.2-blue.svg)]()
[![Version](https://img.shields.io/badge/Symcon%20Version-9.0%20%3E-green.svg)](https://www.symcon.de/forum/)
[![Checks](https://github.com/bumaas/WLED/actions/workflows/check.yml/badge.svg)](https://github.com/bumaas/WLED/actions/workflows/check.yml)

# WLED

Deutsche Fassung: [README.md](README.md)

This library integrates [WLED](https://kno.wled.ge) into Symcon and lets you control compatible LED installations.
The discovery finds WLED devices on the network and prepares them for setup.
The master and segment modules map global and per-segment functions such as brightness, colors, effects, palettes, presets and playlists to Symcon.
That works for a small light setup as well as for large installations split into many segments.

## Documentation

1. [Requirements](#1-requirements)
2. [Features](#2-features)
3. [Software installation](#3-software-installation)
4. [Setting up the instances in Symcon](#4-setting-up-the-instances-in-symcon)
5. [Status variables](#5-status-variables)
6. [License](#6-license)

## 1. Requirements
- Symcon 9.0 or later
- ESP module running [WLED](https://kno.wled.ge/)

## 2. Features

The library provides four instances:

- __WLED Discovery__
  Finds WLED devices on the network via mDNS and offers to create the WLED instances for them.
  The search uses the core instance DNS-SD Control that comes with Symcon. If it is missing, the discovery shows a note in its form and does not search.

- __WLED Splitter__
  Connects the WebSocket client to the WLED instances.

- __WLED Segment__
  Controls a single segment.

- __WLED Master__
  Controls the master, i.e. the device as a whole.

All four instances have an __Expert__ section in their configuration form with the option __EnableExpertDebug__ for extended debug output.

## 3. Software installation
Install the module "WLED" from the Module Store.

## 4. Setting up the instances in Symcon

### WS client
When you create the instances and no WebSocket exists yet, a WebSocket client is created among the I/O instances.
Enter the endpoint of the WLED device there:

**ws://[WLED_IP]/ws**

### WLED Splitter
#### Configuration
- Sync Power with Segments: syncs the power state of the master with the segments.
- Expert: EnableExpertDebug

### WLED Master
#### Configuration
- Presets: creates the status variable "Presets".
- Playlist: creates the status variable "Playlist".
- Nightlight: creates the status variables for the nightlight mode.
- Expert: EnableExpertDebug

Notes:
- Presets, playlists, effects and palettes are loaded straight from the WLED endpoints as presentations (enumeration).
- Dynamic WLED.* variable profiles are no longer needed.
- The action area has a button "Refresh Presets/Playlists".
- The button only shows up if Presets or Playlist is enabled.

### WLED Segment
#### Configuration
- Segment ID
- Effect Mode
- Palettes
- Channel White
- Colors (2 + 3)
- CCT
- Expert: EnableExpertDebug
- The action area has a button "Refresh Effects/Palettes".
- The button only shows up if Effect Mode or Palettes is enabled.

### Instance status
The splitter, the master and the segments show whether the connection to the WLED device is up. Each instance follows its parent: the splitter follows the WebSocket client, master and segments follow the splitter.

- Active (102): the connection is up.
- Inactive (104): no parent instance is connected.
- No connection to the WLED device (201): the WebSocket client or the splitter is not active, for example because the device is switched off, the address is wrong or the WebSocket client is not opened. As soon as the connection is back, the instances become active again on their own, and master and segments fetch the current state.

## 5. Status variables

### WLED Master
- Power (Boolean)
- Brightness (Integer)
- Transition (Float)
- Presets (Integer)
- Playlist (Integer)
- Nightlight On (Boolean)
- Nightlight Duration (Integer)
- Nightlight Mode (Integer): instant, fade, color fade, sunrise
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

## 6. License
[GNU GENERAL PUBLIC LICENSE](http://www.gnu.org/licenses/)
