<?php /** @noinspection AutoloadingIssuesInspection */

require_once __DIR__ . '/../libs/WLEDIds.php';
require_once __DIR__ . '/../libs/WLEDHttp.php';
require_once __DIR__ . '/../libs/WLEDPresentations.php';
require_once __DIR__ . '/../libs/ModuleDebug.php';

use libs\WLEDHttp;
use libs\WLEDIds;
use libs\WLEDPresentations;

class WLEDMaster extends IPSModuleStrict
{
    use ModuleDebugTrait;

    private const string ACTION_REFRESH_DYNAMIC_LISTS = 'RefreshDynamicLists';

    //Properties
    private const string PROP_SHOWNIGHTLIGHT = 'ShowNightlight';
    private const string PROP_SHOWPRESETS    = 'ShowPresets';
    private const string PROP_SHOWPLAYLIST = 'ShowPlaylist';

    //Variables
    private const string VAR_IDENT_POWER      = 'VariablePower';
    private const string VAR_IDENT_BRIGHTNESS = 'VariableBrightness';
    private const string VAR_IDENT_TRANSITION = 'VariableTransition';
    private const string VAR_IDENT_PRESET     = 'VariablePresetsID';
    private const string VAR_IDENT_PLAYLIST = 'VariablePlaylistID';
    private const string VAR_IDENT_NIGHTLIGHT_ON = 'VariableNightlightOn';
    private const string VAR_IDENT_NIGHTLIGHT_DURATION = 'VariableNightlightDuration';
    private const string VAR_IDENT_NIGHTLIGHT_MODE     = 'VariableNightlightMode';
    private const string VAR_IDENT_NIGHTLIGHT_TARGETBRIGHTNESS = 'VariableNightlightTargetBrightness';
    private const string VAR_IDENT_NIGHTLIGHT_REMAININGDURATION = 'VariableNightlightRemainingDuration';

    //Attributes
    private const string ATTR_DEVICE_INFO = 'DeviceInfo';


    public function Create(): void
    {
        parent::Create();
        $this->debugExpert(__FUNCTION__, 'Lifecycle event');

        // Modul-Eigenschaftserstellung
        $this->RegisterPropertyBoolean(self::PROP_SHOWNIGHTLIGHT, false);
        $this->RegisterPropertyBoolean(self::PROP_SHOWPRESETS, false);
        $this->RegisterPropertyBoolean(self::PROP_SHOWPLAYLIST, false);
        $this->RegisterPropertyBoolean('EnableExpertDebug', false);

        $this->RegisterAttributeString(self::ATTR_DEVICE_INFO, json_encode([], JSON_THROW_ON_ERROR));

        //$this->ConnectParent("{F2FEBC51-7E07-3D45-6F71-3D0560DE6375}");
    }

    public function ApplyChanges(): void
    {
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
        // Diese Zeile nicht löschen
        parent::ApplyChanges();
        $this->debugExpert(__FUNCTION__, 'Lifecycle event');

        $this->RegisterVariables();

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }

        $this->updateDeviceInfo();
    }
    public function GetConfigurationForm(): string
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true, 512, JSON_THROW_ON_ERROR);
        $showRefreshButton = $this->ReadPropertyBoolean(self::PROP_SHOWPRESETS) || $this->ReadPropertyBoolean(self::PROP_SHOWPLAYLIST);
        if (isset($form['actions'][1])) {
            $form['actions'][1]['visible'] = $showRefreshButton;
        }

        return json_encode($form, JSON_THROW_ON_ERROR);
    }
    private function updateDeviceInfo(): void
    {
        $this->GetUpdate();
        $host       = WLEDHttp::getHostFromDevice($this->InstanceID);
        $deviceInfo = WLEDHttp::getData($host, '/json/info', 2);
        if (count($deviceInfo)) {
            $this->WriteAttributeString(self::ATTR_DEVICE_INFO, json_encode($deviceInfo, JSON_THROW_ON_ERROR));
            $this->SetSummary(sprintf('%s:Master', $deviceInfo['name']));
        }
        $this->SetStatus(IS_ACTIVE);
    }

    private function RegisterVariables(): void
    {
        $this->RegisterVariableBoolean(
            self::VAR_IDENT_POWER,
            $this->translate("Power"),
            WLEDPresentations::switch(),
            0
        );
        $this->RegisterVariableInteger(
            self::VAR_IDENT_BRIGHTNESS,
            $this->translate("Brightness"),
            WLEDPresentations::slider(0, 255, 1, '', 2),
            10
        );
        $this->EnableAction(self::VAR_IDENT_POWER);
        $this->EnableAction(self::VAR_IDENT_BRIGHTNESS);

        $this->RegisterVariableFloat(
            self::VAR_IDENT_TRANSITION,
            $this->translate("Transition"),
            WLEDPresentations::slider(0.0, 6553.5, 0.1, ' s'),
            20
        );
        $this->EnableAction(self::VAR_IDENT_TRANSITION);


        if ($this->ReadPropertyBoolean(self::PROP_SHOWPRESETS)) {
            $presetOptions = $this->loadPresetOrPlaylistOptions(false);
            $this->RegisterVariableInteger(
                self::VAR_IDENT_PRESET,
                $this->translate('Presets'),
                WLEDPresentations::enumeration($presetOptions),
                30
            );
            $this->EnableAction(self::VAR_IDENT_PRESET);
        }

        if ($this->ReadPropertyBoolean(self::PROP_SHOWPLAYLIST)) {
            $playlistOptions = $this->loadPresetOrPlaylistOptions(true);
            $this->RegisterVariableInteger(
                self::VAR_IDENT_PLAYLIST,
                $this->translate('Playlist'),
                WLEDPresentations::enumeration($playlistOptions),
                35
            );
            $this->EnableAction(self::VAR_IDENT_PLAYLIST);
        }

        if ($this->ReadPropertyBoolean(self::PROP_SHOWNIGHTLIGHT)) {
            $this->RegisterVariableBoolean(
                self::VAR_IDENT_NIGHTLIGHT_ON,
                $this->translate("Nightlight On"),
                WLEDPresentations::switch(),
                50
            );
            $this->RegisterVariableInteger(
                self::VAR_IDENT_NIGHTLIGHT_DURATION,
                $this->translate("Nightlight Duration"),
                WLEDPresentations::slider(1, 255, 1, ' Min.'),
                51
            );
            $this->RegisterVariableInteger(
                self::VAR_IDENT_NIGHTLIGHT_MODE,
                $this->translate("Nightlight Mode"),
                WLEDPresentations::enumeration([
                    ['Value' => 0, 'Caption' => 'instant'],
                    ['Value' => 1, 'Caption' => 'fade'],
                    ['Value' => 2, 'Caption' => 'color fade'],
                    ['Value' => 3, 'Caption' => 'sunrise']
                ]),
                52
            );
            $this->RegisterVariableInteger(
                self::VAR_IDENT_NIGHTLIGHT_TARGETBRIGHTNESS,
                $this->translate("Nightlight Target Brightness"),
                WLEDPresentations::slider(0, 255, 1, '', 2),
                53
            );
            $this->EnableAction(self::VAR_IDENT_NIGHTLIGHT_ON);
            $this->EnableAction(self::VAR_IDENT_NIGHTLIGHT_DURATION);
            $this->EnableAction(self::VAR_IDENT_NIGHTLIGHT_MODE);
            $this->EnableAction(self::VAR_IDENT_NIGHTLIGHT_TARGETBRIGHTNESS);

            //restdauer
            $this->RegisterVariableInteger(
                self::VAR_IDENT_NIGHTLIGHT_REMAININGDURATION,
                $this->translate("Remaining Nightlight Duration"),
                WLEDPresentations::timeOnly(),
                54
            );
        }
    }

    private function loadPresetOrPlaylistOptions(bool $playlist): array
    {
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return [['Value' => -1, 'Caption' => $this->translate('-not active-')]];
        }

        $host = WLEDHttp::getHostFromDevice($this->InstanceID);
        if ($host === '') {
            return [['Value' => -1, 'Caption' => $this->translate('-not active-')]];
        }

        $presets = WLEDHttp::getData($host, '/presets.json', 2);
        if (!is_array($presets)) {
            return [['Value' => -1, 'Caption' => $this->translate('-not active-')]];
        }

        $options   = [['Value' => -1, 'Caption' => $this->translate('-not active-')]];
        $fieldName = $playlist ? 'playlist' : 'mainseg';
        foreach ($presets as $key => $preset) {
            if (!is_numeric((string)$key) || !is_array($preset)) {
                continue;
            }
            if (!isset($preset['n'], $preset[$fieldName])) {
                continue;
            }

            $options[] = [
                'Value'   => (int)$key,
                'Caption' => (string)$preset['n']
            ];
        }

        return $options;
    }

    public function GetUpdate(): void
    {
        $this->SendData(json_encode(['v' => true], JSON_THROW_ON_ERROR));
    }

    public function SendData(string $jsonString): void
    {
        @$this->SendDataToParent(
            json_encode(["DataID" => WLEDIds::DATA_DEVICE_TO_SPLITTER, "FrameTyp" => 1, "Fin" => true, "Buffer" => bin2hex($jsonString)],
                        JSON_THROW_ON_ERROR)
        );
        $this->debugExpert(__FUNCTION__, 'Payload', ['payload' => $jsonString]);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if (($Message === IPS_KERNELMESSAGE) && ($Data[0] === KR_READY)) {
            $this->updateDeviceInfo();
        }
    }

    public function ReceiveData($JSONString): string
    {
        $data = json_decode($JSONString, false, 512, JSON_THROW_ON_ERROR);
        $this->debugExpert(__FUNCTION__, 'Buffer', ['buffer' => $data->Buffer]);
        $data = json_decode($data->Buffer, true, 512, JSON_THROW_ON_ERROR);

        //daten verarbeiten!
        if (array_key_exists("on", $data)) {
            $this->checkVariableAndSetValue(self::VAR_IDENT_POWER, $data["on"]);
        }
        if (array_key_exists("bri", $data)) {
            $this->checkVariableAndSetValue(self::VAR_IDENT_BRIGHTNESS, $data["bri"]);
        }
        if (array_key_exists("transition", $data)) {
            $this->checkVariableAndSetValue(self::VAR_IDENT_TRANSITION, ($data["transition"] / 10));
        }

        if (array_key_exists("ps", $data)) {
            $this->checkVariableAndSetValue(self::VAR_IDENT_PRESET, $data["ps"]);
        }

        if (array_key_exists("pl", $data)) {
            $this->checkVariableAndSetValue(self::VAR_IDENT_PLAYLIST, $data["pl"]);
        }

        if (array_key_exists("nl", $data)) {
            if (array_key_exists("on", $data["nl"])) {
                $this->checkVariableAndSetValue(self::VAR_IDENT_NIGHTLIGHT_ON, $data["nl"]["on"]);
            }

            if (array_key_exists("dur", $data["nl"])) {
                $this->checkVariableAndSetValue(self::VAR_IDENT_NIGHTLIGHT_DURATION, $data["nl"]["dur"]);
            }

            if (array_key_exists("mode", $data["nl"])) {
                $this->checkVariableAndSetValue(self::VAR_IDENT_NIGHTLIGHT_MODE, $data["nl"]["mode"]);
            }

            if (array_key_exists("tbri", $data["nl"])) {
                $this->checkVariableAndSetValue(self::VAR_IDENT_NIGHTLIGHT_TARGETBRIGHTNESS, $data["nl"]["tbri"]);
            }

            if (array_key_exists("rem", $data["nl"])) {
                if ($data["nl"]["rem"] < 0) {
                    $data["nl"]["rem"] = 0;
                }

                $s = $data["nl"]["rem"] % 60;
                $m = floor(($data["nl"]["rem"] % 3600) / 60);
                $h = floor(($data["nl"]["rem"] % 86400) / 3600);

                $time = new DateTime('2001-01-01');
                $time->setTime($h, $m, $s);

                $this->checkVariableAndSetValue(self::VAR_IDENT_NIGHTLIGHT_REMAININGDURATION, $time->getTimestamp());
            }
        }
        return '';
    }

    public function RequestAction($Ident, $Value): void
    {
        if ((string)$Ident === self::ACTION_REFRESH_DYNAMIC_LISTS) {
            $this->doRefreshDynamicLists();
            return;
        }

        $sendArr = $this->buildPayloadForAction((string)$Ident, $Value);
        $this->SendData(json_encode($sendArr, JSON_THROW_ON_ERROR));
    }

    public function RefreshDynamicLists(): void
    {
        $this->doRefreshDynamicLists();
    }

    private function doRefreshDynamicLists(): void
    {
        $this->debugExpert(__FUNCTION__, 'Refreshing dynamic list presentations');
        $this->RegisterVariables();
    }

    private function buildPayloadForAction(string $ident, mixed $value): array
    {
        $sendArr = [];

        switch ($ident) {
            case self::VAR_IDENT_POWER:
                $sendArr['on'] = $value;
                break;
            case self::VAR_IDENT_BRIGHTNESS:
                $sendArr['bri'] = $value;
                break;
            case self::VAR_IDENT_TRANSITION:
                $sendArr['transition'] = (int)round(((float)$value) * 10);
                // Ask WLED for a state response after setting transition.
                $sendArr['v'] = true;
                break;
            case self::VAR_IDENT_PRESET:
                $sendArr['ps'] = $value;
                break;
            case self::VAR_IDENT_PLAYLIST:
                $sendArr['pl'] = $value;
                break;
            case self::VAR_IDENT_NIGHTLIGHT_ON:
                $sendArr['nl']['on'] = $value;
                $sendArr['v'] = true;
                break;
            case self::VAR_IDENT_NIGHTLIGHT_DURATION:
                $sendArr['nl']['dur'] = $value;
                $sendArr['v'] = true;
                break;
            case self::VAR_IDENT_NIGHTLIGHT_MODE:
                $sendArr['nl']['mode'] = $value;
                $sendArr['v'] = true;
                break;
            case self::VAR_IDENT_NIGHTLIGHT_TARGETBRIGHTNESS:
                $sendArr['nl']['tbri'] = $value;
                $sendArr['v'] = true;
                break;
            default:
                throw new RuntimeException('Invalid Ident');
        }

        return $sendArr;
    }

    /**
     * Prüft, ob die angegebene Variable vorhanden ist, und setzt den Wert entsprechend.
     *
     * @param string $Ident Der Ident der Variablen.
     * @param mixed  $Value Der zu setzende Wert.
     *
     * @return void
     */
    private function checkVariableAndSetValue(string $Ident, mixed $Value): void
    {
        if (@$this->GetIDForIdent($Ident)) {
            $this->setValue($Ident, $Value);
        }
    }

}

