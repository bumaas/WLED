<?php /** @noinspection AutoloadingIssuesInspection */

declare(strict_types=1);

require_once __DIR__ . '/WLEDIds.php';
require_once __DIR__ . '/WLEDParentStatus.php';

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
    use WLEDParentStatusTrait;

    private const string ACTION_REFRESH_DYNAMIC_LISTS = 'RefreshDynamicLists';
    private const string BUFFER_SUMMARY               = 'Summary';

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

    /**
     * Nach eigenem ApplyChanges oder Verbinden: Status aus dem Splitter ableiten und, wenn er aktiv
     * ist, den Zustand anfordern. WLED antwortet über den WebSocket mit state und info; den
     * Gerätenamen daraus reicht der Splitter weiter (siehe updateSummaryFromData()).
     */
    private function followParentAndRequestState(): void
    {
        $this->updateStatusFromParent();
        if ($this->HasActiveParent()) {
            $this->GetUpdate();
        }
    }

    /**
     * Wird der Splitter aktiv, fordert er selbst einmal den Zustand an und verteilt die Antwort an
     * alle Kinder. Master und Segment fragen deshalb hier nicht noch einmal einzeln.
     */
    private function onParentActive(): void
    {
    }

    /** Zusammenfassung „<Gerätename>:<Suffix>“ aus dem Namen, den der Splitter mitschickt. */
    private function updateSummaryFromData(array $data): void
    {
        $name = $data[WLEDIds::KEY_DEVICE_NAME] ?? '';
        if (!is_string($name) || $name === '') {
            return;
        }
        $summary = sprintf('%s:%s', $name, $this->deviceSummarySuffix());
        if ($this->GetBuffer(self::BUFFER_SUMMARY) !== $summary) {
            $this->SetSummary($summary);
            $this->SetBuffer(self::BUFFER_SUMMARY, $summary);
        }
    }

    public function GetUpdate(): void
    {
        $this->SendData(json_encode(['v' => true], JSON_THROW_ON_ERROR));
    }

    public function SendData(string $jsonString): void
    {
        if (!$this->HasActiveParent()) {
            $this->debugExpert(__FUNCTION__, 'No active parent, payload not sent', ['payload' => $jsonString], true);
            return;
        }
        $this->SendDataToParent(
            json_encode(["DataID" => WLEDIds::DATA_DEVICE_TO_SPLITTER, "FrameTyp" => 1, "Fin" => true, "Buffer" => bin2hex($jsonString)],
                        JSON_THROW_ON_ERROR)
        );
        $this->debugExpert(__FUNCTION__, 'Payload', ['payload' => $jsonString]);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if ($this->handleParentMessage($SenderID, $Message)) {
            if ($Message === FM_CONNECT && $this->HasActiveParent()) {
                $this->GetUpdate();
            }
            return;
        }
        if (($Message === IPS_KERNELMESSAGE) && ($Data[0] === KR_READY)) {
            // Den Zustand fordert beim Start der Splitter an, sobald der WebSocket Client aktiv ist.
            $this->updateStatusFromParent();
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
