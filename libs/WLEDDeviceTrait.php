<?php /** @noinspection AutoloadingIssuesInspection */

declare(strict_types=1);

require_once __DIR__ . '/WLEDIds.php';
require_once __DIR__ . '/WLEDHttp.php';

use libs\WLEDHttp;
use libs\WLEDIds;

/**
 * Gemeinsame Basis der Gerätemodule Master und Segment (Kommunikation mit dem Splitter,
 * Geräteinfo, Refresh der dynamischen Listen-Presentations).
 *
 * Die verwendende Klasse muss RegisterVariables(), showRefreshButton() und
 * deviceSummarySuffix() bereitstellen (siehe abstract-Deklarationen).
 */
trait WLEDDeviceTrait
{
    private const string ACTION_REFRESH_DYNAMIC_LISTS = 'RefreshDynamicLists';

    //Attributes
    private const string ATTR_DEVICE_INFO = 'DeviceInfo';

    /** (Re-)Registriert die Statusvariablen inkl. dynamischer Presentations. */
    abstract private function RegisterVariables(): void;

    /** Sichtbarkeit des "Aktualisieren"-Buttons in der Konfigurationsform. */
    abstract private function showRefreshButton(): bool;

    /** Suffix hinter dem Gerätenamen in der Instanz-Zusammenfassung (z. B. "Master" oder Segment-Nr.). */
    abstract private function deviceSummarySuffix(): string;

    public function GetConfigurationForm(): string
    {
        // __DIR__ zeigt im Trait auf libs/ — die form.json liegt neben der Klassendatei
        $formFile = dirname((new ReflectionClass($this))->getFileName()) . '/form.json';
        $form     = json_decode(file_get_contents($formFile), true, 512, JSON_THROW_ON_ERROR);
        foreach ($form['actions'] ?? [] as &$action) {
            if (($action['name'] ?? '') === 'refreshButton') {
                $action['visible'] = $this->showRefreshButton();
            }
        }
        unset($action);

        return json_encode($form, JSON_THROW_ON_ERROR);
    }

    private function updateDeviceInfo(): void
    {
        $this->GetUpdate();
        $host       = WLEDHttp::getHostFromDevice($this->InstanceID);
        $deviceInfo = WLEDHttp::getData($host, '/json/info', 2);
        if (count($deviceInfo)) {
            $this->WriteAttributeString(self::ATTR_DEVICE_INFO, json_encode($deviceInfo, JSON_THROW_ON_ERROR));
            $this->SetSummary(sprintf('%s:%s', $deviceInfo['name'], $this->deviceSummarySuffix()));
        }
        $this->SetStatus(IS_ACTIVE);
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

    public function RefreshDynamicLists(): void
    {
        $this->doRefreshDynamicLists();
    }

    private function doRefreshDynamicLists(): void
    {
        $this->debugExpert(__FUNCTION__, 'Refreshing dynamic list presentations');
        $this->RegisterVariables();
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
