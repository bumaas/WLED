<?php

class WLEDSegment extends IPSModule
{
    private const MODID_WLED_SPLITTER    = '{F2FEBC51-7E07-3D45-6F71-3D0560DE6375}';

    private const PROP_SEGMENT_ID       = 'SegmentID';
    private const PROP_MORE_COLORS      = 'MoreColors';
    private const PROP_SHOW_CCT         = 'ShowTemperature';
    private const PROP_SHOW_EFFECTS     = 'ShowEffects';
    private const PROP_SHOW_PALLETS     = 'ShowPalettes';
    private const PROP_SHOW_WHITE_COLOR = 'ShowChannelWhite';

    //Variables
    private const VAR_IDENT_BRIGHTNESS    = "VariableBrightness";
    private const VAR_IDENT_TEMPERATURE   = 'VariableTemperature';
    private const VAR_IDENT_EFFECTS_SPEED = 'VariableEffectsSpeed';

    private const VAR_IDENT_COLOR1   = 'VariableColor1';
    private const VAR_IDENT_COLOR2   = 'VariableColor2';
    private const VAR_IDENT_COLOR3   = 'VariableColor3';
    private const VAR_IDENT_WHITE1   = 'VariableWhite';
    private const VAR_IDENT_WHITE2   = 'VariableWhite2';
    private const VAR_IDENT_WHITE3   = 'VariableWhite3';
    private const VAR_IDENT_TWCOLOR1 = 'VariableTWColor1';
    private const VAR_IDENT_TWCOLOR2 = 'VariableTWColor2';
    private const VAR_IDENT_TWCOLOR3 = 'VariableTWColor3';


    //Attributes
    private const ATTR_DEVICE_INFO = 'DeviceInfo';

    private const COLOR_TEMP_ACCURACY = 0.4;
    private const MIN_COLOR_TEMP      = 1000;
    private const MAX_COLOR_TEMP      = 12000;


    public function Create()
    {
        parent::Create();
        $this->SendDebug(__FUNCTION__, '', 0);

        // Modul-Eigenschaftserstellung
        $this->RegisterPropertyInteger(self::PROP_SEGMENT_ID, 0);
        $this->RegisterPropertyBoolean(self::PROP_SHOW_EFFECTS, false);
        $this->RegisterPropertyBoolean(self::PROP_SHOW_PALLETS, false);
        $this->RegisterPropertyBoolean(self::PROP_SHOW_WHITE_COLOR, false);
        $this->RegisterPropertyBoolean(self::PROP_MORE_COLORS, false);
        $this->RegisterPropertyBoolean(self::PROP_SHOW_CCT, false);

        $this->RegisterAttributeString(self::ATTR_DEVICE_INFO, json_encode([]));

        $this->ConnectParent(self::MODID_WLED_SPLITTER);
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

        $this->SetReceiveDataFilter('.*id\\\":[ \\\"]*(' . $this->ReadPropertyInteger(self::PROP_SEGMENT_ID) . ')[\\\”]*.*');

        $this->RegisterVariables();

        $this->GetUpdate();

        $host       = $this->getHostFromIOInstance();
        $deviceInfo = $this->getData($host, '/json/info');
        if (count($deviceInfo)) {
            $this->WriteAttributeString(self::ATTR_DEVICE_INFO, json_encode($deviceInfo));
            $this->SetSummary(sprintf('%s:%s', $deviceInfo['name'], $this->ReadPropertyInteger(self::PROP_SEGMENT_ID)));
        }
        $this->SetStatus(IS_ACTIVE);
    }

    private function RegisterVariables()
    {
        $this->RegisterVariableBoolean("VariablePower", $this->translate("Power"), "~Switch", 0);
        $this->EnableAction("VariablePower");
        $this->RegisterVariableInteger(self::VAR_IDENT_BRIGHTNESS, $this->translate("Brightness"), "~Intensity.255", 10);
        $this->EnableAction(self::VAR_IDENT_BRIGHTNESS);
        if ($this->ReadPropertyBoolean(self::PROP_SHOW_CCT)) {
            $this->RegisterVariableInteger(self::VAR_IDENT_TEMPERATURE, $this->translate("CCT"), "WLED.Temperature", 11);
            $this->EnableAction(self::VAR_IDENT_TEMPERATURE);
        }

        if ($this->ReadPropertyBoolean(self::PROP_SHOW_EFFECTS) || $this->ReadPropertyBoolean(self::PROP_SHOW_PALLETS)) {
            $deviceInfo = json_decode($this->ReadAttributeString(self::ATTR_DEVICE_INFO), true);
            $this->SendDebug(__FUNCTION__, sprintf('deviceInfo: %s', json_encode($deviceInfo)), 0);
            $wledEffects  = isset($deviceInfo['mac']) ? 'WLED.Effects.' . substr($deviceInfo['mac'], -4) : '';
            $wledPalletes = isset($deviceInfo['mac']) ? 'WLED.Palettes.' . substr($deviceInfo['mac'], -4) : '';

            if ($this->ReadPropertyBoolean(self::PROP_SHOW_EFFECTS)) {
                $this->RegisterVariableInteger("VariableEffects", $this->translate("Effects"), $wledEffects, 20);
                $this->RegisterVariableInteger(self::VAR_IDENT_EFFECTS_SPEED, $this->translate("Effect Speed"), "~Intensity.255", 21);
                $this->RegisterVariableInteger("VariableEffectsIntensity", $this->translate("Effect Intensity"), "~Intensity.255", 22);
                $this->EnableAction("VariableEffects");
                $this->EnableAction(self::VAR_IDENT_EFFECTS_SPEED);
                $this->EnableAction("VariableEffectsIntensity");
            }

            if ($this->ReadPropertyBoolean(self::PROP_SHOW_PALLETS)) {
                $this->RegisterVariableInteger("VariablePalettes", $this->translate("Palletes"), $wledPalletes, 23);
                $this->EnableAction("VariablePalettes");
            }
        }

        $this->RegisterVariableInteger(self::VAR_IDENT_COLOR1, $this->translate("Color 1"), "~HexColor", 30);
        $this->EnableAction(self::VAR_IDENT_COLOR1);
        if ($this->ReadPropertyBoolean(self::PROP_SHOW_WHITE_COLOR)) {
            $this->RegisterVariableInteger(self::VAR_IDENT_WHITE1, $this->translate("White 1"), "~Intensity.255", 31);
            $this->EnableAction(self::VAR_IDENT_WHITE1);
        }
        $this->RegisterVariableInteger(self::VAR_IDENT_TWCOLOR1, $this->translate("White Tone Control 1"), "~TWColor", 32);
        $this->EnableAction(self::VAR_IDENT_TWCOLOR1);

        if ($this->ReadPropertyBoolean(self::PROP_MORE_COLORS)) {
            $this->RegisterVariableInteger(self::VAR_IDENT_COLOR2, $this->translate("Color 2"), "~HexColor", 35);
            $this->EnableAction(self::VAR_IDENT_COLOR2);
            if ($this->ReadPropertyBoolean(self::PROP_SHOW_WHITE_COLOR)) {
                $this->RegisterVariableInteger(self::VAR_IDENT_WHITE2, $this->translate("White 2"), "~Intensity.255", 36);
                $this->EnableAction(self::VAR_IDENT_WHITE2);
            }
            $this->RegisterVariableInteger(self::VAR_IDENT_TWCOLOR2, $this->translate("White Tone Control 2"), "~TWColor", 37);
            $this->EnableAction(self::VAR_IDENT_TWCOLOR2);

            $this->RegisterVariableInteger(self::VAR_IDENT_COLOR3, $this->translate("Color 3"), "~HexColor", 40);
            $this->EnableAction(self::VAR_IDENT_COLOR3);
            if ($this->ReadPropertyBoolean(self::PROP_SHOW_WHITE_COLOR)) {
                $this->RegisterVariableInteger(self::VAR_IDENT_WHITE3, $this->translate("White 3"), "~Intensity.255", 41);
                $this->EnableAction(self::VAR_IDENT_WHITE3);
            }
            $this->RegisterVariableInteger(self::VAR_IDENT_TWCOLOR3, $this->translate("White Tone Control 3"), "~TWColor", 42);
            $this->EnableAction(self::VAR_IDENT_TWCOLOR3);
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
            $this->checkVariableAndSetValue("VariablePower", $data["on"]);
        }
        if (array_key_exists("bri", $data)) {
            $this->checkVariableAndSetValue(self::VAR_IDENT_BRIGHTNESS, $data["bri"]);
        }

        if (array_key_exists("col", $data)) {
            $this->checkVariableAndSetValue(self::VAR_IDENT_COLOR1, $this->RGBToHex($data["col"][0]));
            $this->checkVariableAndSetValue(self::VAR_IDENT_TWCOLOR1, $this->RGBToColorTemp($data["col"][0]));

            $this->checkVariableAndSetValue(self::VAR_IDENT_COLOR2, $this->RGBToHex($data["col"][1]));
            $this->checkVariableAndSetValue(self::VAR_IDENT_TWCOLOR2, $this->RGBToColorTemp($data["col"][1]));

            $this->checkVariableAndSetValue(self::VAR_IDENT_COLOR3, $this->RGBToHex($data["col"][2]));
            $this->checkVariableAndSetValue(self::VAR_IDENT_TWCOLOR3, $this->RGBToColorTemp($data["col"][2]));

            if (count($data["col"][0]) > 3) { //weißkanal
                $this->checkVariableAndSetValue(self::VAR_IDENT_WHITE1, $data["col"][0][3]);
            }
            if (count($data["col"][1]) > 3) { //weißkanal
                $this->checkVariableAndSetValue(self::VAR_IDENT_WHITE2, $data["col"][1][3]);
            }
            if (count($data["col"][2]) > 3) { //weißkanal
                $this->checkVariableAndSetValue(self::VAR_IDENT_WHITE3, $data["col"][2][3]);
            }
        }

        if (array_key_exists("cct", $data)) {
            $this->checkVariableAndSetValue(self::VAR_IDENT_TEMPERATURE, $data["cct"]);
        }
        if (array_key_exists("pal", $data)) {
            $this->checkVariableAndSetValue("VariablePalettes", $data["pal"]);
        }
        if (array_key_exists("fx", $data)) {
            $this->checkVariableAndSetValue("VariableEffects", $data["fx"]);
        }
        if (array_key_exists("sx", $data)) {
            $this->checkVariableAndSetValue(self::VAR_IDENT_EFFECTS_SPEED, $data["sx"]);
        }
        if (array_key_exists("ix", $data)) {
            $this->checkVariableAndSetValue("VariableEffectsIntensity", $data["ix"]);
        }
    }

    public function RequestAction($Ident, $Value)
    {
        $segArr       = [];
        $segArr["id"] = $this->ReadPropertyInteger(self::PROP_SEGMENT_ID);

        switch ($Ident) {
            case "VariablePower":
                $segArr["on"] = $Value;
                break;

            case self::VAR_IDENT_BRIGHTNESS:
                $segArr["bri"] = $Value;
                break;

            case self::VAR_IDENT_COLOR1:
            case self::VAR_IDENT_COLOR2:
            case self::VAR_IDENT_COLOR3:
            case self::VAR_IDENT_WHITE1:
            case self::VAR_IDENT_WHITE2:
            case self::VAR_IDENT_WHITE3:

                $segArr["col"][0] = $this->HexToRGB($Ident === self::VAR_IDENT_COLOR1 ? $Value : $this->GetValue(self::VAR_IDENT_COLOR1));
                if ($this->ReadPropertyBoolean(self::PROP_SHOW_WHITE_COLOR)) {
                    $segArr["col"][0][3] = $this->GetValue(self::VAR_IDENT_WHITE1);
                }
                if ($this->ReadPropertyBoolean(self::PROP_MORE_COLORS)) {
                    $segArr["col"][1] = $this->HexToRGB($Ident === self::VAR_IDENT_COLOR2 ? $Value : $this->GetValue(self::VAR_IDENT_COLOR2));
                    $segArr["col"][2] = $this->HexToRGB($Ident === self::VAR_IDENT_COLOR3 ? $Value : $this->GetValue(self::VAR_IDENT_COLOR3));
                    if ($this->ReadPropertyBoolean(self::PROP_SHOW_WHITE_COLOR)) {
                        $segArr["col"][1][3] = $this->GetValue(self::VAR_IDENT_WHITE2);
                        $segArr["col"][2][3] = $this->GetValue(self::VAR_IDENT_WHITE3);
                    }
                } else {
                    $segArr["col"][1] = [0, 0, 0];
                    $segArr["col"][2] = [0, 0, 0];
                }

                break;

            case "VariableTemperature":
                $segArr["cct"] = $Value;
                break;

            case "VariablePalettes":
                $segArr["pal"] = $Value;
                break;

            case "VariableEffects":
                $segArr["fx"] = $Value;
                break;

            case "VariableEffectsSpeed":
                $segArr["sx"] = $Value;
                break;

            case "VariableEffectsIntensity":
                $segArr["ix"] = $Value;
                break;

            case self::VAR_IDENT_TWCOLOR1:
                $segArr['col'][0] = $this->colorTempToRGB($Value);
                break;

            case self::VAR_IDENT_TWCOLOR2:
                $segArr['col'][1] = $this->colorTempToRGB($Value);
                break;

            case self::VAR_IDENT_TWCOLOR3:
                $segArr['col'][2] = $this->colorTempToRGB($Value);
                break;

            default:
                throw new Exception("Invalid Ident");
        }

        $this->sendAndUpdateValue($Ident, $Value, $segArr);

    }

    /**
     * Sends data and updates the value.
     *
     * @param string $ident   The identifier of the value.
     * @param mixed  $value   The value to be set.
     * @param array  $payload The payload to be sent.
     *
     * @return void
     */
    private function sendAndUpdateValue($ident, $value, array $payload)
    {
        $sendArr["seg"][] = $payload;
        $this->SendData(json_encode($sendArr));
        //        $this->SetValue($ident, $value); auskommentiert, da durch rückkanal gesetzt
    }

    /**
     * Convert color temperature to RGB values.
     * Algorithmus von Tanner Helland, leicht modifiziert
     *
     * @param int $kelvin The color temperature in Kelvin.
     *
     * @return array An array containing the red, green, and blue values as integers.
     */
    private function colorTempToRGB($kelvin)
    {
        $temp = $kelvin / 100;

        // Rot
        if ($temp <= 66) {
            $red = 255;
        } else {
            $red = $temp - 60;
            $red = 329.698727446 * $red ** -0.1332047592;
            $red = ($red < 0) ? 0 : $red;
            $red = ($red > 255) ? 255 : $red;
        }

        // Grün
        if ($temp <= 66) {
            $green = $temp;
            $green = 99.4708025861 * log($green) - 161.1195681661;
            $green = ($green < 0) ? 0 : $green;
            $green = ($green > 255) ? 255 : $green;
        } else {
            $green = $temp - 60;
            $green = 288.1221695283 * $green ** -0.0755148492;
            $green = ($green < 0) ? 0 : $green;
            $green = ($green > 255) ? 255 : $green;
        }

        // Blau
        if ($temp >= 66) {
            $blue = 255;
        } elseif ($temp <= 19) {
            $blue = 0;
        } else {
            $blue = $temp - 10;
            $blue = 138.5177312231 * log($blue) - 305.0447927307;
            $blue = ($blue < 0) ? 0 : $blue;
            $blue = ($blue > 255) ? 255 : $blue;
        }

        return [(int)$red, (int)$green, (int)$blue];
    }

    /**
     * Converts RGB color to color temperature.
     *
     * @param array $rgb An array containing RGB color values.
     *                   The array must have three elements representing Red, Green, and Blue values respectively.
     *
     * @return float The color temperature value.
     */

    private function RGBToColorTemp(array $rgb): float
    {
        if ($rgb[0] === 0){
            return self::MIN_COLOR_TEMP;
        }

        $colorTempMin = self::MIN_COLOR_TEMP;
        $colorTempMax = self::MAX_COLOR_TEMP;
        while ($colorTempMax - $colorTempMin > self::COLOR_TEMP_ACCURACY) {
            $averageColorTemp = ($colorTempMax + $colorTempMin) / 2;
            [$calculatedRed, $calculatedGreen, $calculatedBlue] = $this->colorTempToRGB($averageColorTemp);

            if ($calculatedRed === 0) {
                trigger_error(
                    sprintf('unexpected calculatedRed! rgb: %s, temp: %s', print_r($rgb, true), $averageColorTemp),
                    E_USER_ERROR
                );
            }

            if (($calculatedBlue / $calculatedRed) >= $rgb[2] / $rgb[0]) {
                $colorTempMax = $averageColorTemp;
            } else {
                $colorTempMin = $averageColorTemp;
            }
        }

        return ($colorTempMax + $colorTempMin) / 2;
    }

    /**
     * Ergänzt SendDebug um Möglichkeit Objekte und Array auszugeben.
     *
     * @param string $Message Nachricht für Data.
     * @param mixed  $Data    Daten für die Ausgabe.
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
                return parent::SendDebug($Message, (string)$Data, $Format);
            }

            $this->LogMessage($Message . ':' . (string)$Data, KL_DEBUG);
        }

        return 0;
    }

    /**
     * Prüft, ob die angegebene Variable vorhanden ist und setzt den Wert entsprechend.
     *
     * @param string $Ident Der Ident der Variablen.
     * @param mixed  $Value Der zu setzende Wert.
     *
     * @return void
     */
    private function checkVariableAndSetValue(string $Ident, $Value)
    {
        if (@$this->GetIDForIdent($Ident)) {
            $this->setValue($Ident, $Value);
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
