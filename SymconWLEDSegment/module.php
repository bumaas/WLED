<?php

class WLEDSegment extends IPSModule
{
    private const PROP_SEGMENT_ID = 'SegmentID';
    private const PROP_MORE_COLORS = 'MoreColors';
    private const PROP_SHOW_TEMPERATURE = 'ShowTemperature';
    private const PROP_SHOW_EFFECTS = 'ShowEffects';
    private const PROP_SHOW_PALETTS = 'ShowPalettes';

    private const ATTR_DEVICE_INFO = 'DeviceInfo';

    public function Create()
    {
        parent::Create();
        $this->SendDebug(__FUNCTION__, '', 0);

        // Modul-Eigenschaftserstellung
        $this->RegisterPropertyInteger(self::PROP_SEGMENT_ID, 0);
        $this->RegisterPropertyBoolean(self::PROP_MORE_COLORS, false);
        $this->RegisterPropertyBoolean(self::PROP_SHOW_TEMPERATURE, false);
        $this->RegisterPropertyBoolean(self::PROP_SHOW_EFFECTS, false);
        $this->RegisterPropertyBoolean(self::PROP_SHOW_PALETTS, false);

        $this->RegisterAttributeString('DeviceInfo', json_encode([]));

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

        $this->SetReceiveDataFilter('.*id\\\":[ \\\"]*('.$this->ReadPropertyInteger(self::PROP_SEGMENT_ID).')[\\\”]*.*');

        $this->RegisterVariables();

        $this->GetUpdate();

        $host = $this->getHostFromIOInstance();
        $deviceInfo = $this->getData($host, '/json/info');
        if (count($deviceInfo)){
            $this->WriteAttributeString(self::ATTR_DEVICE_INFO, json_encode($deviceInfo));
            $this->SetSummary(sprintf('%s:%s', $deviceInfo['name'], $this->ReadPropertyInteger(self::PROP_SEGMENT_ID)));
        }
        $this->SetStatus(IS_ACTIVE);
    }

    private function RegisterVariables()
    {
        $this->RegisterVariableBoolean("VariablePower", "Power", "~Switch", 0);
        $this->RegisterVariableInteger("VariableBrightness", "Brightness", "~Intensity.255", 10);
        $this->EnableAction("VariablePower");
        $this->EnableAction("VariableBrightness");

        $this->RegisterVariableInteger("VariableColor1", "Color", "~HexColor", 20);
        $this->EnableAction("VariableColor1");
        if ($this->ReadPropertyBoolean(self::PROP_MORE_COLORS)) {
            $this->RegisterVariableInteger("VariableColor2", "Color 2", "~HexColor", 21);
            $this->RegisterVariableInteger("VariableColor3", "Color 3", "~HexColor", 22);
            $this->EnableAction("VariableColor2");
            $this->EnableAction("VariableColor3");
        }

        $this->RegisterVariableInteger("VariableWhite", "White", "~Intensity.255", 23);
        $this->EnableAction("VariableWhite");
        if ($this->ReadPropertyBoolean(self::PROP_SHOW_TEMPERATURE)) {
            $this->RegisterVariableInteger("VariableTemperature", "Temperature", "WLED.Temperature", 24);
            $this->EnableAction("VariableTemperature");
        }

        if ($this->ReadPropertyBoolean(self::PROP_SHOW_EFFECTS) || $this->ReadPropertyBoolean(self::PROP_SHOW_PALETTS)){
            $deviceInfo  = json_decode($this->ReadAttributeString(self::ATTR_DEVICE_INFO), true);
            $this->SendDebug(__FUNCTION__, sprintf('deviceInfo: %s', json_encode($deviceInfo)), 0);
            $wledEffects = isset($deviceInfo['mac']) ? 'WLED.Effects.' . substr($deviceInfo['mac'], -4) : '';
            $wledPalettes = isset($deviceInfo['mac']) ? 'WLED.Palettes.' . substr($deviceInfo['mac'], -4) : '';

            if ($this->ReadPropertyBoolean(self::PROP_SHOW_EFFECTS)) {
                $this->RegisterVariableInteger("VariableEffects", "Effects", $wledEffects, 60);
                $this->RegisterVariableInteger("VariableEffectsSpeed", "Effect Speed", "~Intensity.255", 61);
                $this->RegisterVariableInteger("VariableEffectsIntensity", "Effect Intensity", "~Intensity.255", 62);
                $this->EnableAction("VariableEffects");
                $this->EnableAction("VariableEffectsSpeed");
                $this->EnableAction("VariableEffectsIntensity");
            }

            if ($this->ReadPropertyBoolean(self::PROP_SHOW_PALETTS)) {
                $this->RegisterVariableInteger("VariablePalettes", "Palettes", $wledPalettes, 50);
                $this->EnableAction("VariablePalettes");
            }
        }
    }

        public function GetUpdate(){
        $this->SendData(json_encode(['v' => true]));
    }
    public function SendData(string $jsonString)
    {
        @$this->SendDataToParent(json_encode(Array("DataID" => "{7B4E5B18-F847-8F8A-F148-3FB3F482E295}", "FrameTyp" => 1, "Fin" => true, "Buffer" =>  $jsonString)));
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
            $this->SetValue("VariablePower", $data["on"]);
        }
        if (array_key_exists("bri", $data)) {
            $this->SetValue("VariableBrightness", $data["bri"]);
        }

        if (array_key_exists("col", $data)) {
            $this->SetValue("VariableColor1", $this->RGBToHex($data["col"][0]));

            if ($this->ReadPropertyBoolean(self::PROP_MORE_COLORS)) {
                $this->SetValue("VariableColor2", $this->RGBToHex($data["col"][1]));
                $this->SetValue("VariableColor3", $this->RGBToHex($data["col"][2]));
            }

            if (count($data["col"][0]) > 3) { //weiskanal
                $this->SetValue("VariableWhite", $data["col"][0][3]);
            }
        }

        if (array_key_exists("cct", $data)) {
            if ($this->ReadPropertyBoolean(self::PROP_SHOW_TEMPERATURE)) {
                $this->SetValue("VariableTemperature", $data["cct"]);
            }
        }

        if (array_key_exists("pal", $data)) {
            if ($this->ReadPropertyBoolean(self::PROP_SHOW_PALETTS)) {
                $this->SetValue("VariablePalettes", $data["pal"]);
            }
        }

        if (array_key_exists("fx", $data)) {
            if ($this->ReadPropertyBoolean(self::PROP_SHOW_EFFECTS)) {
                $this->SetValue("VariableEffects", $data["fx"]);
            }
        }
        if (array_key_exists("sx", $data)) {
            if ($this->ReadPropertyBoolean(self::PROP_SHOW_EFFECTS)) {
                $this->SetValue("VariableEffectsSpeed", $data["sx"]);
            }
        }
        if (array_key_exists("ix", $data)) {
            if ($this->ReadPropertyBoolean(self::PROP_SHOW_EFFECTS)) {
                $this->SetValue("VariableEffectsIntensity", $data["ix"]);
            }
        }
    }
    public function RequestAction($Ident, $Value) {
        $sendArr = array();
        $segArr = array();
        $segArr["id"] = $this->ReadPropertyInteger(self::PROP_SEGMENT_ID);

        switch($Ident) {
            case "VariablePower":
                $segArr["on"] = $Value;

                $sendArr["seg"][] = $segArr;
                $sendStr = json_encode($sendArr);
                $this->SendData($sendStr);
                $this->SetValue($Ident, $Value);
                break;
            case "VariableBrightness":
                $segArr["bri"] = $Value;

                $sendArr["seg"][] = $segArr;
                $sendStr = json_encode($sendArr);
                $this->SendData($sendStr);
                $this->SetValue($Ident, $Value);
                break;
            case "VariableColor1":
            case "VariableColor2":
            case "VariableColor3":
            case "VariableWhite":
                $this->SetValue($Ident, $Value);

                $segArr["col"][0] = $this->HexToRGB($this->GetValue("VariableColor1"));

                if($this->ReadPropertyBoolean(self::PROP_MORE_COLORS)) {
                    $segArr["col"][1] = $this->HexToRGB($this->GetValue("VariableColor2"));
                    $segArr["col"][2] = $this->HexToRGB($this->GetValue("VariableColor3"));
                }else{
                    $segArr["col"][1] = array(0,0,0);
                    $segArr["col"][2] = array(0,0,0);
                }

                $wID = @$this->GetIDForIdent("VariableWhite");
                if($wID !== false){
                    $white = $this->GetValue("VariableWhite");
                    $segArr["col"][0][3] = $white;
                    $segArr["col"][1][3] = $white;
                    $segArr["col"][2][3] = $white;
                }

                $sendArr["seg"][] = $segArr;
                $sendStr = json_encode($sendArr);
                $this->SendData($sendStr);
                break;
            case "VariableTemperature":
                $segArr["cct"] = $Value;

                $sendArr["seg"][] = $segArr;
                $sendStr = json_encode($sendArr);
                $this->SendData($sendStr);
                $this->SetValue($Ident, $Value);
                break;
            case "VariablePalettes":
                $segArr["pal"] = $Value;

                $sendArr["seg"][] = $segArr;
                $sendStr = json_encode($sendArr);
                $this->SendData($sendStr);
                $this->SetValue($Ident, $Value);
                break;
            case "VariableEffects":
                $segArr["fx"] = $Value;

                $sendArr["seg"][] = $segArr;
                $sendStr = json_encode($sendArr);
                $this->SendData($sendStr);
                $this->SetValue($Ident, $Value);
                break;
            case "VariableEffectsSpeed":
                $segArr["sx"] = $Value;

                $sendArr["seg"][] = $segArr;
                $sendStr = json_encode($sendArr);
                $this->SendData($sendStr);
                $this->SetValue($Ident, $Value);
                break;
            case "VariableEffectsIntensity":
                $segArr["ix"] = $Value;

                $sendArr["seg"][] = $segArr;
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
     * @param string                                           $Message Nachricht für Data.
     * @param mixed $Data    Daten für die Ausgabe.
     *
     * @return int $Format Ausgabeformat für Strings.
     */
    protected function SendDebug($Message, $Data, $Format): int
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
            return parent::SendDebug($Message, ($Data ? 'TRUE' : 'FALSE'), 0);
        } else {
            if (IPS_GetKernelRunlevel() == KR_READY) {
                return parent::SendDebug($Message, (string) $Data, $Format);
            }

            $this->LogMessage($Message . ':' . (string) $Data, KL_DEBUG);
        }

        return 0;
    }
    private function HexToRGB($hexInt){
        $arr = array();
        $arr[0]   = floor($hexInt/65536);
        $arr[1]  = floor(($hexInt-($arr[0]*65536))/256);
        $arr[2] = $hexInt-($arr[1]*256)-($arr[0]*65536);

        return $arr;
    }
    private function RGBToHex($rgb_arr){
        return $rgb_arr[0]*256*256 + $rgb_arr[1]*256 + $rgb_arr[2];
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
