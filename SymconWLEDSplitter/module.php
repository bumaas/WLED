<?php

class WLEDSplitter extends IPSModule
{
    private const MODID_WS_CLIENT    = '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}';
    private const MODID_WLED_SEGMENT = '{D2353839-DA64-DF79-7CD5-4DD827DCE82A}';
    private const MODID_WLED_MASTER  = '{79D5ACD0-7EED-FBA6-22D7-04AEB1BBBE97}';

    private const PROP_SYNCPOWER = 'SyncPower';

    public function Create()
    {
        parent::Create();
        $this->SendDebug(__FUNCTION__, '', 0);

        // Modul-Eigenschaftserstellung
        $this->RegisterPropertyBoolean(self::PROP_SYNCPOWER, true);

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


        $host = $this->getHostFromParentInstance();
        $this->SetSummary($host);
        if (!empty($host) && Sys_Ping($host, 300)) {
            $mac = $this->getData($host, '/json/info')['mac'];

            $wledEffects  = 'WLED.Effects.' . substr($mac, -4);
            $wledPalettes = 'WLED.Palettes.' . substr($mac, -4);

            if (!IPS_VariableProfileExists($wledEffects)) {
                IPS_CreateVariableProfile($wledEffects, VARIABLETYPE_INTEGER);
            }

            if (!IPS_VariableProfileExists($wledPalettes)) {
                IPS_CreateVariableProfile($wledPalettes, VARIABLETYPE_INTEGER);
            }

            $wledEffectsArray = $this->getData($host, "/json/eff");
            $this->updateAssociations($wledEffects, $wledEffectsArray);

            $wledPaletteArray = $this->getData($host, "/json/pal");
            $this->updateAssociations($wledPalettes, $wledPaletteArray);
        }

        if (!IPS_VariableProfileExists("WLED.Temperature")) {
            IPS_CreateVariableProfile("WLED.Temperature", VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileValues("WLED.Temperature", 1900, 10091, 1);
            IPS_SetVariableProfileText("WLED.Temperature", "", " %");
        }

        if (!IPS_VariableProfileExists("WLED.Transition")) {
            IPS_CreateVariableProfile("WLED.Transition", VARIABLETYPE_FLOAT);
            IPS_SetVariableProfileValues("WLED.Transition", 0.0, 25.5, 0.1);
            IPS_SetVariableProfileDigits("WLED.Transition", 1);
            IPS_SetVariableProfileText("WLED.Transition", "", " s");
        }

        if (!IPS_VariableProfileExists("WLED.NightlightDuration")) {
            IPS_CreateVariableProfile("WLED.NightlightDuration", VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileValues("WLED.NightlightDuration", 1, 255, 1);
            IPS_SetVariableProfileText("WLED.NightlightDuration", "", " Min.");
        }

        if (!IPS_VariableProfileExists("WLED.NightlightMode")) {
            IPS_CreateVariableProfile("WLED.NightlightMode", VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileValues("WLED.NightlightMode", 0, 0, 0);

            IPS_SetVariableProfileAssociation("WLED.NightlightMode", 0, "instant", "", -1);
            IPS_SetVariableProfileAssociation("WLED.NightlightMode", 1, "fade", "", -1);
            IPS_SetVariableProfileAssociation("WLED.NightlightMode", 2, "color fade", "", -1);
            IPS_SetVariableProfileAssociation("WLED.NightlightMode", 3, "sunrise", "", -1);
        }

        $this->SetStatus(IS_ACTIVE);
    }

    public function RequestAction($Ident, $Value)
    {
        $this->SendDebug(__FUNCTION__, sprintf('Ident: %s, Value: %s', $Ident, $Value), 0);
        switch ($Ident) {
            case 'updateProfileEffects':
            case 'updateProfilePallets':
            case 'updateProfilePresets':
            case 'updateProfilePlaylists':
                $this->processProfileUpdate($Ident);
                break;
            default:
                trigger_error('unknown ident: ' . $Ident);
        }
    }

    private function processProfileUpdate($profileType)
    {
        $host = $this->getHostFromParentInstance();
        if (empty($host) || !Sys_Ping($host, 300)) {
            return;
        }

        $mac         = $this->getData($host, '/json/info')['mac'];
        $profileName = $this->getProfileName($profileType, $mac);

        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, VARIABLETYPE_INTEGER);
        }

        $profileData = $this->fetchProfileData($host, $profileType);

        if ($profileData) {
            $this->updateAssociations($profileName, $profileData);
        }
    }

    private function getProfileName($type, $mac)
    {
        return sprintf('WLED.%s.%s', str_replace('updateProfile', '', $type), substr($mac, -4));
    }

    private function fetchProfileData($host, $profileType)
    {
        switch ($profileType) {
            case 'updateProfileEffects':
                return $this->getData($host, "/json/eff");
            case 'updateProfilePallets':
                return $this->getData($host, "/json/pal");
            case 'updateProfilePresets':
            case 'updateProfilePlaylists':
                $presets = $this->getData($host, '/presets.json');
                $data[-1]    = '-not active-';
                foreach ($presets as $key => $preset) {
                    if (isset($preset['n'], $preset[($profileType === 'updateProfilePresets' ? 'mainseg' : 'playlist')])) {
                        $data[$key] = $preset['n'];
                    }
                }
                return $data;
            default:
                return null;
        }
    }

    private function getParentInstanceId(int $instId): int
    {
        return IPS_GetInstance($instId)['ConnectionID'];
    }

    private function getHostFromParentInstance(): string
    {
        $url = IPS_GetProperty($this->getParentInstanceId($this->InstanceID), 'URL');
        return parse_url($url, PHP_URL_HOST) ? : '';
    }

    private function getData($host, $path)
    {
        $jsonData = @file_get_contents(sprintf('http://%s%s', $host, $path), false, stream_context_create([
                                                                                                              'http' => ['timeout' => 1]
                                                                                                          ]));
        if ($jsonData === false) {
            return [];
        }

        return json_decode($jsonData, true);
    }

    private function updateAssociations(string $profileName, array $dataArray)
    {
        $this->SendDebug(__FUNCTION__, sprintf('profile: %s, %s', $profileName, print_r($dataArray, true)), 0);
        // Deleting old associations
        foreach (IPS_GetVariableProfile($profileName)['Associations'] as $association) {
            IPS_SetVariableProfileAssociation($profileName, $association['Value'], '', '', -1);
        }
        // Updating with new associations
        $i = 1;
        foreach ($dataArray as $key => $name) {
            if ($i > 128) {
                array_splice($dataArray, 0, 128);
                $this->SendDebug(
                    __FUNCTION__,
                    'Das Maximum von 128 Profilen wurde überschritten. Folgende Assoziationen wurden nicht angelegt: ' . implode(
                        ', ',
                        $dataArray
                    ),
                    0
                );
                break;
            }
            IPS_SetVariableProfileAssociation($profileName, $key, $name, '', -1);
            $i++;
        }
    }

    public function SendData(string $jsonString)
    {
        $this->SendDataToParent(json_encode(["DataID" => self::MODID_WS_CLIENT, "FrameTyp" => 1, "Fin" => true, "Buffer" => $jsonString]));
        $this->SendDebug(__FUNCTION__, $jsonString, 0);
    }

    private function SendDataToSegment($jsonString)
    {
        $this->SendDataToChildren(json_encode(["DataID" => self::MODID_WLED_SEGMENT, "FrameTyp" => 1, "Fin" => true, "Buffer" => $jsonString]));
        $this->SendDebug(__FUNCTION__, $jsonString, 0);
    }

    private function SendDataToMaster($jsonString)
    {
        $this->SendDataToChildren(json_encode(["DataID" => self::MODID_WLED_MASTER, "FrameTyp" => 1, "Fin" => true, "Buffer" => $jsonString]));
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
        $data = json_decode($JSONString, true);
        $this->SendDebug(__FUNCTION__, $data['Buffer'], 0);

        $jsonData = json_decode($data['Buffer'], true);

        if (!isset($jsonData['state'])) {
            return;
        }

        $state = $jsonData['state'];

        //nachricht zum Master schicken
        $this->SendDataToMaster(json_encode($state));

        //nachricht an die Segmente schicken
        if (!isset($state['seg']) || !is_array($state['seg'])) {
            return;
        }

        $powerOn = false;

        foreach ($state["seg"] as $segmentData) {
            if ($this->ReadPropertyBoolean("SyncPower")) {
                if ($state["on"] === false) {
                    //alle Segmente ausschalten, wenn wled ausgeschalten wird!
                    $segmentData["on"] = false;
                    $this->SendDebug(__FUNCTION__, 'Turn off all segments', 0);
                }
            }

            $this->SendDataToSegment(json_encode($segmentData));

            if ($segmentData["on"] === true) {
                $powerOn = true;
            }
        }

        //prüfen, ob alle Segmente ausgeschalten wurden!
        if ($this->ReadPropertyBoolean("SyncPower")) {
            if ($state["on"] && $powerOn === false) {
                $this->SendData('{"on":false}'); //an den Parent schicken
            }
        }
    }

    public function ForwardData($JSONString)
    {
        $data = json_decode($JSONString, true);
        $this->SendDebug(__FUNCTION__, $data['Buffer'], 0);
        $data = json_decode($data['Buffer'], true);

        if (array_key_exists("seg", $data) && is_array($data["seg"]) && count($data["seg"]) > 0 && $data["seg"][0]["on"] === true) {
            // wenn segment eingeschaltet wird, dann wled mit einschalten
            //$this->SendData('{"on":true}'); //todo: auskommentiert da die app das nicht so macht. eventuell als Option verfügbar machen
        }

        $this->SendData(json_encode($data));
    }

    /**
     * Ergänzt SendDebug um Möglichkeit Objekte und Array auszugeben.
     *
     * @param string $Message Nachricht für Data.
     * @param mixed  $Data    Daten für die Ausgabe.
     * @param int    $Format
     *
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
        } elseif (IPS_GetKernelRunlevel() === KR_READY) {
            parent::SendDebug($Message, (string)$Data, $Format);
        } else {
            $this->LogMessage($Message . ':' . $Data, KL_DEBUG);
        }
    }

}
