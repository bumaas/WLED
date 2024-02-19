<?php

class WLEDMaster extends IPSModule
{

    //Properties
    private const PROP_SHOWNIGHTLIGHT = 'ShowNightlight';
    private const PROP_SHOWPRESETS    = 'ShowPresets';
    private const PROP_SHOWPLAYLIST   = 'ShowPlaylist';

    //Variables
    private const VAR_IDENT_POWER                        = 'VariablePower';
    private const VAR_IDENT_BRIGHTNESS                   = 'VariableBrightness';
    private const VAR_IDENT_TRANSITION                   = 'VariableTransition';
    private const VAR_IDENT_PRESET                       = 'VariablePresetsID';
    private const VAR_IDENT_PLAYLIST                     = 'VariablePlaylistID';
    private const VAR_IDENT_NIGHTLIGHT_ON                = 'VariableNightlightOn';
    private const VAR_IDENT_NIGHTLIGHT_DURATION          = 'VariableNightlightDuration';
    private const VAR_IDENT_NIGHTLIGHT_MODE              = 'VariableNightlightMode';
    private const VAR_IDENT_NIGHTLIGHT_TARGETBRIGHTNESS  = 'VariableNightlightTargetBrightness';
    private const VAR_IDENT_NIGHTLIGHT_REMAININGDURATION = 'VariableNightlightRemainingDuration';

    //Attributes
    private const ATTR_DEVICE_INFO = 'DeviceInfo';


    public function Create()
    {
        parent::Create();
        $this->SendDebug(__FUNCTION__, '', 0);

        // Modul-Eigenschaftserstellung
        $this->RegisterPropertyBoolean(self::PROP_SHOWNIGHTLIGHT, false);
        $this->RegisterPropertyBoolean(self::PROP_SHOWPRESETS, false);
        $this->RegisterPropertyBoolean(self::PROP_SHOWPLAYLIST, false);

        $this->RegisterAttributeString(self::ATTR_DEVICE_INFO, json_encode([]));

        $this->ConnectParent("{F2FEBC51-7E07-3D45-6F71-3D0560DE6375}");
    }

    public function ApplyChanges()
    {
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
        // Diese Zeile nicht löschen
        parent::ApplyChanges();
        $this->SendDebug(__FUNCTION__, '', 0);
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }

        $this->RegisterVariables();

        $this->GetUpdate();

        $host       = $this->getHostFromIOInstance();
        $deviceInfo = $this->getData($host, '/json/info');
        if (count($deviceInfo)) {
            $this->WriteAttributeString(self::ATTR_DEVICE_INFO, json_encode($deviceInfo));
            $this->SetSummary(sprintf('%s:Master', $deviceInfo['name']));
        }

        $this->SetStatus(IS_ACTIVE);
    }

    private function RegisterVariables()
    {
        $this->RegisterVariableBoolean(self::VAR_IDENT_POWER, "Power", "~Switch", 0);
        $this->RegisterVariableInteger(self::VAR_IDENT_BRIGHTNESS, "Brightness", "~Intensity.255", 10);
        $this->EnableAction(self::VAR_IDENT_POWER);
        $this->EnableAction(self::VAR_IDENT_BRIGHTNESS);

        $this->RegisterVariableFloat(self::VAR_IDENT_TRANSITION, "Transition", "WLED.Transition", 20);
        $this->EnableAction(self::VAR_IDENT_TRANSITION);


        if ($this->ReadPropertyBoolean(self::PROP_SHOWPRESETS)) {
            $deviceInfo  = json_decode($this->ReadAttributeString(self::ATTR_DEVICE_INFO), true);
            $wledPresets = isset($deviceInfo['mac']) ? 'WLED.Presets.' . substr($deviceInfo['mac'], -4) : '';
            $this->RegisterVariableInteger(self::VAR_IDENT_PRESET, 'Presets', IPS_VariableProfileExists($wledPresets) ? $wledPresets : '', 30);
            $this->EnableAction(self::VAR_IDENT_PRESET);
        }

        if ($this->ReadPropertyBoolean(self::PROP_SHOWPLAYLIST)) {
            $deviceInfo    = json_decode($this->ReadAttributeString(self::ATTR_DEVICE_INFO), true);
            $wledPlaylists = isset($deviceInfo['mac']) ? 'WLED.Playlists.' . substr($deviceInfo['mac'], -4) : '';
            $this->RegisterVariableInteger(self::VAR_IDENT_PLAYLIST, 'Playlists ID', IPS_VariableProfileExists($wledPlaylists) ? $wledPlaylists : '', 35);
            $this->EnableAction(self::VAR_IDENT_PLAYLIST);
        }

        if ($this->ReadPropertyBoolean(self::PROP_SHOWNIGHTLIGHT)) {
            $this->RegisterVariableBoolean(self::VAR_IDENT_NIGHTLIGHT_ON, "Nightlight On", "~Switch", 50);
            $this->RegisterVariableInteger(self::VAR_IDENT_NIGHTLIGHT_DURATION, "Nightlight Duration", "WLED.NightlightDuration", 51);
            $this->RegisterVariableInteger(self::VAR_IDENT_NIGHTLIGHT_MODE, "Nightlight Mode", "WLED.NightlightMode", 52);
            $this->RegisterVariableInteger(self::VAR_IDENT_NIGHTLIGHT_TARGETBRIGHTNESS, "Nightlight Target Brightness", "~Intensity.255", 53);
            $this->EnableAction(self::VAR_IDENT_NIGHTLIGHT_ON);
            $this->EnableAction(self::VAR_IDENT_NIGHTLIGHT_DURATION);
            $this->EnableAction(self::VAR_IDENT_NIGHTLIGHT_MODE);
            $this->EnableAction(self::VAR_IDENT_NIGHTLIGHT_TARGETBRIGHTNESS);

            //restdauer
            $this->RegisterVariableInteger(self::VAR_IDENT_NIGHTLIGHT_REMAININGDURATION, "Remaining Nightlight Duration", "~UnixTimestampTime", 54);
        }
    }

    public function GetUpdate()
    {
        $this->SendData(json_encode(['v' => true]));
    }

    public function SendData(string $jsonString)
    {
        @$this->SendDataToParent(
            json_encode(["DataID" => "{7B4E5B18-F847-8F8A-F148-3FB3F482E295}", "FrameTyp" => 1, "Fin" => true, "Buffer" => $jsonString])
        );
        $this->SendDebug(__FUNCTION__, $jsonString, 0);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if (($Message === IPS_KERNELMESSAGE) && ($Data[0] === KR_READY)) {
            $this->ApplyChanges();
        }
    }

    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);
        $this->SendDebug(__FUNCTION__, $data->Buffer, 0);
        $data = json_decode($data->Buffer, true);

        //daten verarbeiten!
        if (array_key_exists("on", $data)) {
            $this->SetValue(self::VAR_IDENT_POWER, $data["on"]);
        }
        if (array_key_exists("bri", $data)) {
            $this->SetValue(self::VAR_IDENT_BRIGHTNESS, $data["bri"]);
        }
        if (array_key_exists("transition", $data)) {
            $this->SetValue(self::VAR_IDENT_TRANSITION, ($data["transition"] / 10));
        }

        if ($this->ReadPropertyBoolean(self::PROP_SHOWPRESETS) && array_key_exists("ps", $data)) {
            $this->SetValue(self::VAR_IDENT_PRESET, $data["ps"]);
        }

        if ($this->ReadPropertyBoolean(self::PROP_SHOWPLAYLIST) && array_key_exists("pl", $data)) {
            $this->SetValue(self::VAR_IDENT_PLAYLIST, $data["pl"]);
        }

        if ($this->ReadPropertyBoolean(self::PROP_SHOWNIGHTLIGHT) && array_key_exists("nl", $data)) {
            if (array_key_exists("on", $data["nl"])) {
                $this->SetValue(self::VAR_IDENT_NIGHTLIGHT_ON, $data["nl"]["on"]);
            }

            if (array_key_exists("dur", $data["nl"])) {
                $this->SetValue(self::VAR_IDENT_NIGHTLIGHT_DURATION, $data["nl"]["dur"]);
            }

            if (array_key_exists("mode", $data["nl"])) {
                $this->SetValue(self::VAR_IDENT_NIGHTLIGHT_MODE, $data["nl"]["mode"]);
            }

            if (array_key_exists("tbri", $data["nl"])) {
                $this->SetValue(self::VAR_IDENT_NIGHTLIGHT_TARGETBRIGHTNESS, $data["nl"]["tbri"]);
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

                $this->SetValue(self::VAR_IDENT_NIGHTLIGHT_REMAININGDURATION, $time->getTimestamp());
            }
        }
    }

    public function RequestAction($Ident, $Value)
    {
        $sendArr = [];

        switch ($Ident) {
            case self::VAR_IDENT_POWER:
                $sendArr["on"] = $Value;

                $sendStr = json_encode($sendArr);
                $this->SendData($sendStr);
                $this->SetValue($Ident, $Value);
                break;
            case self::VAR_IDENT_BRIGHTNESS:
                $sendArr["bri"] = $Value;

                $sendStr = json_encode($sendArr);
                $this->SendData($sendStr);
                $this->SetValue($Ident, $Value);
                break;
            case self::VAR_IDENT_TRANSITION:
                $sendArr["transition"] = $Value * 10;

                $sendStr = json_encode($sendArr);
                $this->SendData($sendStr);
                $this->SetValue($Ident, $Value);
                break;
            case self::VAR_IDENT_PRESET:
                $sendArr["ps"] = $Value;

                $sendStr = json_encode($sendArr);
                $this->SendData($sendStr);
                $this->SetValue($Ident, $Value);
                break;
            case self::VAR_IDENT_PLAYLIST:
                $sendArr["pl"] = $Value;

                $sendStr = json_encode($sendArr);
                $this->SendData($sendStr);
                $this->SetValue($Ident, $Value);
                break;
            case self::VAR_IDENT_NIGHTLIGHT_ON:
                $sendArr["nl"]["on"] = $Value;

                $sendStr = json_encode($sendArr);
                $this->SendData($sendStr);
                $this->SetValue($Ident, $Value);
                break;
            case self::VAR_IDENT_NIGHTLIGHT_DURATION:
                $sendArr["nl"]["dur"] = $Value;

                $sendStr = json_encode($sendArr);
                $this->SendData($sendStr);
                $this->SetValue($Ident, $Value);
                break;
            case self::VAR_IDENT_NIGHTLIGHT_MODE:
                $sendArr["nl"]["mode"] = $Value;

                $sendStr = json_encode($sendArr);
                $this->SendData($sendStr);
                $this->SetValue($Ident, $Value);
                break;
            case self::VAR_IDENT_NIGHTLIGHT_TARGETBRIGHTNESS:
                $sendArr["nl"]["tbri"] = $Value;

                $sendStr = json_encode($sendArr);
                $this->SendData($sendStr);
                $this->SetValue($Ident, $Value);
                break;
            default:
                throw new Exception("Invalid Ident");
        }
    }

    /**
     * Ergänzt SendDebug um Möglichkeit Objekte und Array auszugeben.
     *
     * @param string $Message Nachricht für Data.
     * @param mixed  $Data    Daten für die Ausgabe.
     *
     * @return int $Format Ausgabeformat für Strings.
     */
    protected function SendDebug($Message, $Data, $Format): void
    {
        if (is_array($Data)) {
            if (count($Data) > 25) {
                $this->SendDebug($Message, array_slice($Data, 0, 20), 0);
                $this->SendDebug($Message . ':CUT', '-------------CUT-----------------', 0);
                $this->SendDebug($Message, array_slice($Data, -5, null, true), 0);
            } else {
                foreach ($Data as $Key => $DebugData) {
                    $this->SendDebug($Message . ':' . $Key, $DebugData, 0);
                }
            }
        } elseif (is_object($Data)) {
            foreach ($Data as $Key => $DebugData) {
                $this->SendDebug($Message . '->' . $Key, $DebugData, 0);
            }
        } elseif (is_bool($Data)) {
            parent::SendDebug($Message, ($Data ? 'TRUE' : 'FALSE'), 0);
        } else {
            if (IPS_GetKernelRunlevel() == KR_READY) {
                parent::SendDebug($Message, (string)$Data, $Format);
            } else {
                $this->LogMessage($Message . ':' . (string)$Data, KL_DEBUG);
            }
        }
    }

    private function HexToRGB($hexInt)
    {
        $arr    = [];
        $arr[0] = floor($hexInt / 65536);
        $arr[1] = floor(($hexInt - ($arr[0] * 65536)) / 256);
        $arr[2] = $hexInt - ($arr[1] * 256) - ($arr[0] * 65536);

        return $arr;
    }

    private function RGBToHex($rgb_arr)
    {
        return $rgb_arr[0] * 256 * 256 + $rgb_arr[1] * 256 + $rgb_arr[2];
    }

    private function getParentInstanceId(int $instId): int
    {
        return IPS_GetInstance($instId)['ConnectionID'];
    }

    private function getHostFromIOInstance(): string
    {
        $url = IPS_GetProperty($this->getParentInstanceId($this->getParentInstanceId($this->InstanceID)), 'URL');
        return parse_url($url, PHP_URL_HOST) ? : '';
    }

    private function getData($host, $path)
    {
        $jsonData = @file_get_contents(sprintf('http://%s%s', $host, $path), false, stream_context_create([
                                                                                                              'http' => ['timeout' => 2]
                                                                                                          ]));
        if ($jsonData === false) {
            return [];
        }

        return json_decode($jsonData, true);
    }

}
