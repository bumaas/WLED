<?php

class WLEDSplitter extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->SendDebug(__FUNCTION__, '', 0);

        // Modul-Eigenschaftserstellung
        $this->RegisterPropertyBoolean("SyncPower", true);

        $this->RequireParent("{D68FD31F-0E90-7019-F16C-1949BD3079EF}");
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


        if (!IPS_VariableProfileExists("WLED.Effects")) {
            IPS_CreateVariableProfile("WLED.Effects", VARIABLETYPE_INTEGER);
        }

        if (!IPS_VariableProfileExists("WLED.Palettes")) {
            IPS_CreateVariableProfile("WLED.Palettes", VARIABLETYPE_INTEGER);
        }

        $host = $this->getHostFromParentInstance();
        $this->SetSummary($host);
        if (!empty($host) && Sys_Ping($host, 300)) {
            $wledEffects = "WLED.Effects";
            $wledPalettes = "WLED.Palettes";

            $wledEffectsArray = $this->getJsonData($host, "/json/eff");
            $this->updateAssociations($wledEffectsArray, $wledEffects);

            $wledPaletteArray = $this->getJsonData($host, "/json/pal");
            $this->updateAssociations($wledPaletteArray, $wledPalettes);
        }

        if (!IPS_VariableProfileExists("WLED.Temperature")) {
            IPS_CreateVariableProfile("WLED.Temperature", VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileValues("WLED.Temperature", 1900, 10091, 1);
        }

        if (!IPS_VariableProfileExists("WLED.Transition")) {
            IPS_CreateVariableProfile("WLED.Transition", VARIABLETYPE_FLOAT);
            IPS_SetVariableProfileValues("WLED.Transition", 0.0, 25.5, 0.1);
            IPS_SetVariableProfileDigits("WLED.Transition", 1);
            IPS_SetVariableProfileText("WLED.Transition", "", " s");
        }

        if (!IPS_VariableProfileExists("WLED.NightlightDuration")) {
            IPS_CreateVariableProfile("WLED.NightlightDuration", 1);
            IPS_SetVariableProfileValues("WLED.NightlightDuration", 1, 255, 1);
            IPS_SetVariableProfileText("WLED.NightlightDuration", "", " Min.");
        }

        if (!IPS_VariableProfileExists("WLED.NightlightMode")) {
            IPS_CreateVariableProfile("WLED.NightlightMode", 1);
            IPS_SetVariableProfileValues("WLED.NightlightMode", 0, 3, 1);

            IPS_SetVariableProfileAssociation("WLED.NightlightMode", 0, "instant", "", -1);
            IPS_SetVariableProfileAssociation("WLED.NightlightMode", 1, "fade", "", -1);
            IPS_SetVariableProfileAssociation("WLED.NightlightMode", 2, "color fade", "", -1);
            IPS_SetVariableProfileAssociation("WLED.NightlightMode", 3, "sunrise", "", -1);
        }

        $this->SetStatus(IS_ACTIVE);
    }

    private function getParentInstanceId(): int
    {
        return IPS_GetInstance($this->InstanceID)['ConnectionID'];
    }

    private function getHostFromParentInstance(): string
    {
        $url = IPS_GetProperty($this->getParentInstanceId(), 'URL');
        return parse_url($url, PHP_URL_HOST) ?: '';
    }
    private function getJsonData($ipAddress, $path) {
        $jsonData = @file_get_contents('http://{$ipAddress}{$path}', false, stream_context_create([
                                                                                                      'http' => ['timeout' => 2]]                                                                                                      )
                                                                                                  );
        if ($jsonData === false){
            return [];
        }

        return json_decode($jsonData, true);
    }

    private function updateAssociations($dataArray, $profile) {
        // Deleting old associations
        foreach (IPS_GetVariableProfile($profile)['Associations'] as $association) {
            IPS_SetVariableProfileAssociation($profile, $association['Value'], '', '', -1);
        }
        // Updating with new associations
        $i = 1;
        foreach ($dataArray as $item => $key) {
            if ($i > 128){
                array_splice($dataArray, 0, 128);
                $this->SendDebug(__FUNCTION__, 'Das Maximum von 128 Profilen wurde überschritten. Folgende Assoziationen wurden nicht angelegt: ' . implode(', ', $dataArray), 0);
                break;
            }
            IPS_SetVariableProfileAssociation($profile, $item, $key, "", -1);
            $i++;
        }
    }

    public function SendData(string $jsonString)
    {
        $this->SendDataToParent(json_encode(Array("DataID" => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}", "FrameTyp" => 1, "Fin" => true, "Buffer" => utf8_decode($jsonString))));
        $this->SendDebug(__FUNCTION__, $jsonString, 0);
    }
    private function SendDataToSegment($jsonString)
    {
        $this->SendDataToChildren(json_encode(Array("DataID" => "{D2353839-DA64-DF79-7CD5-4DD827DCE82A}", "FrameTyp" => 1, "Fin" => true, "Buffer" => utf8_decode($jsonString))));
        $this->SendDebug(__FUNCTION__, $jsonString, 0);
    }
    private function SendDataToMaster($jsonString)
    {
        $this->SendDataToChildren(json_encode(Array("DataID" => "{79D5ACD0-7EED-FBA6-22D7-04AEB1BBBE97}", "FrameTyp" => 1, "Fin" => true, "Buffer" => utf8_decode($jsonString))));
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

        $j_data = json_decode($data->Buffer, true);

        if(!array_key_exists("state", $j_data)) return;
        $this->SendDataToMaster(json_encode($j_data["state"]));

        if(!array_key_exists("seg", $j_data["state"])) return;
        if(!is_array($j_data["state"]["seg"])) return;

        $powerOn = false;

        if ($this->ReadPropertyBoolean("SyncPower")) {
            foreach ($j_data["state"]["seg"] as $item) {
                if ($j_data["state"]["on"] === false) {
                    //alle segmente ausschalten, wenn wled ausgeschalten wird!
                    $item["on"] = false;
                    $this->SendDebug(__FUNCTION__, 'Turn off all segments', 0);
                }

                $this->SendDataToSegment(json_encode($item));

                if ($item["on"] === true) {
                    $powerOn = true;
                }
            }

            //prüfen, ob alle Segmente ausgeschalten wurden!
            if($j_data["state"]["on"] && $powerOn == false){
                $this->SendData('{"on":false}');
            }

        }

    }

    public function ForwardData($JSONString)
    {
        $data = json_decode($JSONString);
        $this->SendDebug(__FUNCTION__, $data->Buffer, 0);
        $data = json_decode($data->Buffer, true);

        if (array_key_exists("seg", $data) && is_array($data["seg"]) && count($data["seg"]) > 0) {
            if ($data["seg"][0]["on"] === true) {
                // wenn segment eingeschalten wird wled mit einschalten
                //$data["state"]["on"] = true;
                $this->SendData('{"on":true}');
            }
        }

        $this->SendData(json_encode($data));
    }

    /**
     * Ergänzt SendDebug um Möglichkeit Objekte und Array auszugeben.
     *
     * @param string                                           $Message Nachricht für Data.
     * @param mixed $Data    Daten für die Ausgabe.
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
            if (IPS_GetKernelRunlevel() === KR_READY) {
                parent::SendDebug($Message, (string) $Data, $Format);
            } else {
                $this->LogMessage($Message . ':' . (string) $Data, KL_DEBUG);
            }
        }
    }

}
