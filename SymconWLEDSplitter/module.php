<?php

require_once __DIR__ . '/../libs/WLEDIds.php';
require_once __DIR__ . '/../libs/WLEDHttp.php';
require_once __DIR__ . '/../libs/ModuleDebug.php';

use libs\WLEDHttp;
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
        // Diese Zeile nicht loeschen
        parent::ApplyChanges();
        $this->debugExpert(__FUNCTION__, 'Lifecycle event');

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }

        $this->processHostData();
    }

    private function processHostData(): void
    {
        $host = WLEDHttp::getHostFromSplitter($this->InstanceID);
        $this->SetSummary($host);
        if (!empty($host) && Sys_Ping($host, 300)) {
            $this->debugExpert(__FUNCTION__, 'Host reachable');
        }
        $this->SetStatus(IS_ACTIVE);
    }

    public function RequestAction($Ident, $Value): void
    {
        $this->debugExpert(__FUNCTION__, 'Action requested', ['ident' => $Ident, 'value' => $Value]);
        switch ($Ident) {
            case 'updateProfileEffects':
            case 'updateProfilePalettes':
            case 'updateProfilePallets':
            case 'updateProfilePresets':
            case 'updateProfilePlaylists':
                // Legacy no-op: profile-based updates were replaced by variable presentations.
                $this->debugExpert(__FUNCTION__, 'Legacy profile update ignored', ['ident' => $Ident]);
                break;
            default:
                trigger_error('unknown ident: ' . $Ident);
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

        // Nachricht zum Master schicken
        $this->SendDataToMaster(json_encode($state, JSON_THROW_ON_ERROR));

        // Nachricht an die Segmente schicken
        if (!isset($state['seg']) || !is_array($state['seg'])) {
            return '';
        }

        $powerOn = false;

        foreach ($state['seg'] as $segmentData) {
            if ($state['on'] === false && $this->ReadPropertyBoolean(self::PROP_SYNCPOWER)) {
                // Alle Segmente ausschalten, wenn WLED ausgeschaltet wird.
                $segmentData['on'] = false;
                $this->debugExpert(__FUNCTION__, 'Turn off all segments');
            }

            $this->SendDataToSegment(json_encode($segmentData, JSON_THROW_ON_ERROR));

            if (($segmentData['on'] ?? false) === true) {
                $powerOn = true;
            }
        }

        // Pruefen, ob alle Segmente ausgeschaltet wurden
        if ($state['on'] && ($powerOn === false) && $this->ReadPropertyBoolean(self::PROP_SYNCPOWER)) {
            $this->SendData('{"on":false}'); // an den Parent schicken
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
