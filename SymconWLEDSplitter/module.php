<?php

require_once __DIR__ . '/../libs/WLEDIds.php';
require_once __DIR__ . '/../libs/ModuleDebug.php';

use libs\WLEDIds;

/** @noinspection AutoloadingIssuesInspection */

class WLEDSplitter extends IPSModuleStrict
{
    use ModuleDebugTrait;

    private const string MODID_WEBSOCKET_CLIENT = '{D68FD31F-0E90-7019-F16C-1949BD3079EF}';

    private const string PROP_SYNCPOWER = 'SyncPower';

    public function Create(): void
    {
        parent::Create();
        $this->debugExpert(__FUNCTION__, 'Lifecycle event');

        // Modul-Eigenschaftserstellung
        $this->RegisterPropertyBoolean(self::PROP_SYNCPOWER, true);
        $this->RegisterPropertyBoolean('EnableExpertDebug', false);

        //$this->RequireParent(self::MODID_WEBSOCKET_CLIENT);
    }

    public function ApplyChanges(): void
    {
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
        // Diese Zeile nicht löschen
        parent::ApplyChanges();
        $this->debugExpert(__FUNCTION__, 'Lifecycle event');

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

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }

        $this->processHostData();
    }

    private function processHostData(): void
    {
        $host = $this->getHostFromParentInstance();
        $this->SetSummary($host);
        if (!empty($host) && Sys_Ping($host, 300)) {
            $mac          = $this->getData($host, '/json/info')['mac'];
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
        $this->SetStatus(IS_ACTIVE);
    }

    public function RequestAction($Ident, $Value): void
    {
        $this->debugExpert(__FUNCTION__, 'Action requested', ['ident' => $Ident, 'value' => $Value]);
        switch ($Ident) {
            case 'updateProfileEffects':
            case 'updateProfilePallets':
            case 'updateProfilePresets':
            case 'updateProfilePlaylists':
                $this->processProfileUpdate(str_replace('updateProfile', '', $Ident));
                break;
            default:
                trigger_error('unknown ident: ' . $Ident);
        }
    }

    private function processProfileUpdate(string $profileType): void
    {
        $host = $this->getHostFromParentInstance();
        if (empty($host) || !Sys_Ping($host, 300)) {
            return;
        }

        $mac         = $this->getData($host, '/json/info')['mac'];
        $profileName = sprintf('WLED.%s.%s', $profileType, substr($mac, -4));

        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, VARIABLETYPE_INTEGER);
        }

        $profileData = $this->fetchProfileData($host, $profileType);

        if ($profileData) {
            $this->updateAssociations($profileName, $profileData);
        }
    }


    private function fetchProfileData(string $host, string $profileType): ?array
    {
        switch ($profileType) {
            case 'Effects':
                return $this->getData($host, "/json/eff");
            case 'Pallets':
                return $this->getData($host, "/json/pal");
            case 'Presets':
            case 'Playlists':
                $presets  = $this->getData($host, '/presets.json');
                $data[-1] = $this->translate('-not active-');
                foreach ($presets as $key => $preset) {
                    if (isset($preset['n'], $preset[($profileType === 'Presets' ? 'mainseg' : 'playlist')])) {
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

    private function getData($host, $path): array
    {
        $jsonData = @file_get_contents(sprintf('http://%s%s', $host, $path), false, stream_context_create([
                                                                                                              'http' => ['timeout' => 1]
                                                                                                          ]));
        if ($jsonData === false) {
            return [];
        }

        return json_decode($jsonData, true, 512, JSON_THROW_ON_ERROR);
    }

    private function updateAssociations(string $profileName, array $dataArray): void
    {
        $this->debugExpert(__FUNCTION__, 'Update profile associations', ['profile' => $profileName, 'entries' => count($dataArray)]);
        // Deleting old associations
        foreach (IPS_GetVariableProfile($profileName)['Associations'] as $association) {
            IPS_SetVariableProfileAssociation($profileName, $association['Value'], '', '', -1);
        }
        // Updating with new associations
        $i = 1;
        foreach ($dataArray as $key => $name) {
            if ($i > 128) {
                array_splice($dataArray, 0, 128);
                $this->debugExpert(__FUNCTION__, 'Association limit reached', ['profile' => $profileName, 'max' => 128], true);
                break;
            }
            IPS_SetVariableProfileAssociation($profileName, $key, $name, '', -1);
            $i++;
        }
    }

    public function SendData(string $jsonString): void
    {
        $this->SendDataToParent(
            json_encode(["DataID" => WLEDIds::DATA_WEBSOCKET_TO_SPLITTER, "FrameTyp" => 1, "Fin" => true, "Buffer" => bin2hex($jsonString)], JSON_THROW_ON_ERROR)
        );
        $this->debugExpert(__FUNCTION__, 'Payload', ['payload' => $jsonString]);
    }

    private function SendDataToSegment($jsonString): void
    {
        $this->SendDataToChildren(
            json_encode(["DataID" => WLEDIds::DATA_SPLITTER_TO_SEGMENT, "FrameTyp" => 1, "Fin" => true, "Buffer" => $jsonString], JSON_THROW_ON_ERROR)
        );
        $this->debugExpert(__FUNCTION__, 'Payload', ['payload' => $jsonString]);
    }

    private function SendDataToMaster($jsonString): void
    {
        $this->SendDataToChildren(
            json_encode(["DataID" => WLEDIds::DATA_SPLITTER_TO_MASTER, "FrameTyp" => 1, "Fin" => true, "Buffer" => $jsonString], JSON_THROW_ON_ERROR)
        );
        $this->debugExpert(__FUNCTION__, 'Payload', ['payload' => $jsonString]);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if (($Message === IPS_KERNELMESSAGE) && ($Data[0] === KR_READY)) {
            $this->processHostData();
        }
    }

    public function ReceiveData($JSONString): string
    {
        $data = json_decode($JSONString, true, 512, JSON_THROW_ON_ERROR);
        $buffer = hex2bin((string)$data['Buffer']);
        if ($buffer === false) {
            $buffer = (string)$data['Buffer'];
        }
        $this->debugExpert(__FUNCTION__, 'Buffer', ['buffer' => $buffer]);

        $jsonData = json_decode($buffer, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($jsonData['state'])) {
            return '';
        }

        $state = $jsonData['state'];

        //Nachricht zum Master schicken
        $this->SendDataToMaster(json_encode($state, JSON_THROW_ON_ERROR));

        //Nachricht an die Segmente schicken
        if (!isset($state['seg']) || !is_array($state['seg'])) {
            return '';
        }

        $powerOn = false;

        foreach ($state["seg"] as $segmentData) {
            if ($state["on"] === false && $this->ReadPropertyBoolean("SyncPower")) {
                //alle Segmente ausschalten, wenn wled ausgeschaltet wird!
                $segmentData["on"] = false;
                $this->debugExpert(__FUNCTION__, 'Turn off all segments');
            }

            $this->SendDataToSegment(json_encode($segmentData, JSON_THROW_ON_ERROR));

            if ($segmentData["on"] === true) {
                $powerOn = true;
            }
        }

        //prüfen, ob alle Segmente ausgeschaltet wurden
        if ($state["on"] && ($powerOn === false) && $this->ReadPropertyBoolean("SyncPower")) {
            $this->SendData('{"on":false}'); //an den Parent schicken
        }
        return '';
    }

    public function ForwardData($JSONString): string
    {
        $data = json_decode($JSONString, true, 512, JSON_THROW_ON_ERROR);
        $buffer = hex2bin((string)$data['Buffer']);
        if ($buffer === false) {
            $buffer = (string)$data['Buffer'];
        }
        $this->debugExpert(__FUNCTION__, 'Buffer', ['buffer' => $buffer]);
        $data = json_decode($buffer, true, 512, JSON_THROW_ON_ERROR);

        $this->SendData(json_encode($data, JSON_THROW_ON_ERROR));
        return '';
    }

}


