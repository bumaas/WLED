<?php /** @noinspection AutoloadingIssuesInspection */

require_once __DIR__ . '/../libs/WLEDIds.php';
require_once __DIR__ . '/../libs/ModuleDebug.php';

use libs\WLEDIds;

class WLEDMaster extends IPSModuleStrict
{
    use ModuleDebugTrait;


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

    private function updateDeviceInfo(): void
    {
        $this->GetUpdate();
        $host       = $this->getHostFromIOInstance();
        $deviceInfo = $this->getData($host, '/json/info');
        if (count($deviceInfo)) {
            $this->WriteAttributeString(self::ATTR_DEVICE_INFO, json_encode($deviceInfo, JSON_THROW_ON_ERROR));
            $this->SetSummary(sprintf('%s:Master', $deviceInfo['name']));
        }
        $this->SetStatus(IS_ACTIVE);
    }

    private function RegisterVariables(): void
    {
        $this->RegisterVariableBoolean(self::VAR_IDENT_POWER, $this->translate("Power"), "~Switch", 0);
        $this->RegisterVariableInteger(self::VAR_IDENT_BRIGHTNESS, $this->translate("Brightness"), "~Intensity.255", 10);
        $this->EnableAction(self::VAR_IDENT_POWER);
        $this->EnableAction(self::VAR_IDENT_BRIGHTNESS);

        $this->RegisterVariableFloat(self::VAR_IDENT_TRANSITION, $this->translate("Transition"), "WLED.Transition", 20);
        $this->EnableAction(self::VAR_IDENT_TRANSITION);


        if ($this->ReadPropertyBoolean(self::PROP_SHOWPRESETS)) {
            $deviceInfo  = json_decode($this->ReadAttributeString(self::ATTR_DEVICE_INFO), true, 512, JSON_THROW_ON_ERROR);
            $wledPresets = isset($deviceInfo['mac']) ? 'WLED.Presets.' . substr($deviceInfo['mac'], -4) : '';
            $this->RegisterVariableInteger(
                self::VAR_IDENT_PRESET,
                $this->translate('Presets'),
                IPS_VariableProfileExists($wledPresets) ? $wledPresets : '',
                30
            );
            $this->EnableAction(self::VAR_IDENT_PRESET);
        }

        if ($this->ReadPropertyBoolean(self::PROP_SHOWPLAYLIST)) {
            $deviceInfo    = json_decode($this->ReadAttributeString(self::ATTR_DEVICE_INFO), true, 512, JSON_THROW_ON_ERROR);
            $wledPlaylists = isset($deviceInfo['mac']) ? 'WLED.Playlists.' . substr($deviceInfo['mac'], -4) : '';
            $this->RegisterVariableInteger(
                self::VAR_IDENT_PLAYLIST,
                $this->translate('Playlist'),
                IPS_VariableProfileExists($wledPlaylists) ? $wledPlaylists : '',
                35
            );
            $this->EnableAction(self::VAR_IDENT_PLAYLIST);
        }

        if ($this->ReadPropertyBoolean(self::PROP_SHOWNIGHTLIGHT)) {
            $this->RegisterVariableBoolean(self::VAR_IDENT_NIGHTLIGHT_ON, $this->translate("Nightlight On"), "~Switch", 50);
            $this->RegisterVariableInteger(
                self::VAR_IDENT_NIGHTLIGHT_DURATION,
                $this->translate("Nightlight Duration"),
                "WLED.NightlightDuration",
                51
            );
            $this->RegisterVariableInteger(self::VAR_IDENT_NIGHTLIGHT_MODE, $this->translate("Nightlight Mode"), "WLED.NightlightMode", 52);
            $this->RegisterVariableInteger(
                self::VAR_IDENT_NIGHTLIGHT_TARGETBRIGHTNESS,
                $this->translate("Nightlight Target Brightness"),
                "~Intensity.255",
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
                "~UnixTimestampTime",
                54
            );
        }
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
        $sendArr = [];

        switch ($Ident) {
            case self::VAR_IDENT_POWER:
                $sendArr["on"] = $Value;
                break;
            case self::VAR_IDENT_BRIGHTNESS:
                $sendArr["bri"] = $Value;
                break;
            case self::VAR_IDENT_TRANSITION:
                $sendArr["transition"] = $Value * 10;
                break;
            case self::VAR_IDENT_PRESET:
                $sendArr["ps"] = $Value;
                break;
            case self::VAR_IDENT_PLAYLIST:
                $sendArr["pl"] = $Value;
                break;
            case self::VAR_IDENT_NIGHTLIGHT_ON:
                $sendArr["nl"]["on"] = $Value;
                break;
            case self::VAR_IDENT_NIGHTLIGHT_DURATION:
                $sendArr["nl"]["dur"] = $Value;
                break;
            case self::VAR_IDENT_NIGHTLIGHT_MODE:
                $sendArr["nl"]["mode"] = $Value;
                break;
            case self::VAR_IDENT_NIGHTLIGHT_TARGETBRIGHTNESS:
                $sendArr["nl"]["tbri"] = $Value;
                break;
            default:
                throw new RuntimeException("Invalid Ident");
        }

        $this->SendData(json_encode($sendArr, JSON_THROW_ON_ERROR));
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

    private function getParentInstanceId(int $instId): int
    {
        $instance = @IPS_GetInstance($instId);
        if (!is_array($instance)) {
            return 0;
        }
        return (int)($instance['ConnectionID'] ?? 0);
    }

    private function getHostFromIOInstance(): string
    {
        $splitterId = $this->getParentInstanceId($this->InstanceID);
        if ($splitterId <= 0) {
            return '';
        }

        $ioId = $this->getParentInstanceId($splitterId);
        if ($ioId <= 0) {
            return '';
        }

        $url = (string)@IPS_GetProperty($ioId, 'URL');
        if ($url === '') {
            return '';
        }

        return parse_url($url, PHP_URL_HOST) ? : '';
    }

    private function getData($host, $path): array
    {
        $jsonData = @file_get_contents(sprintf('http://%s%s', $host, $path), false, stream_context_create([
                                                                                                              'http' => ['timeout' => 2]
                                                                                                          ]));
        if ($jsonData === false) {
            return [];
        }

        return json_decode($jsonData, true, 512, JSON_THROW_ON_ERROR);
    }

}


