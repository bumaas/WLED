<?php

declare(strict_types=1);

/**
 * Gerätename für die Zusammenfassung kommt über den Datenfluss (Kernel-Stub, symcon/SymconStubs).
 *
 * Anlass: Store-Vorab-Review 02.11.2026, Punkt 4. Master und Segment holten /json/info per HTTP
 * direkt vom Gerät, nur um den Gerätenamen in die Zusammenfassung zu schreiben. WLED schickt
 * info aber schon über den WebSocket mit (beim Verbinden, bei jeder Änderung und als Antwort
 * auf {"v":true}). Der Splitter reicht den Namen jetzt an Master und Segmente weiter.
 *
 * Fixture tests/fixtures/ws-antwort-v.json: Antwort eines WLED-Controllers (0.15, „WLED Bumas“)
 * auf {"v":true} über ws://<host>/ws, am 02.11.2026 vom nuc aus mitgeschnitten. Nur die Werte
 * von info.ip, info.mac und info.wifi.bssid sind durch neutrale Werte im selben Format ersetzt.
 *
 * Aufruf: php tests/check-geraetename.php
 */

require_once __DIR__ . '/stubs/autoload.php';
require_once dirname(__DIR__) . '/SymconWLEDSplitter/module.php';
require_once dirname(__DIR__) . '/SymconWLEDMaster/module.php';
require_once dirname(__DIR__) . '/SymconWLEDSegment/module.php';

use libs\WLEDIds;

final class SplitterHarness extends WLEDSplitter
{
    /** @var list<string> jedes SendDataToChildren */
    public array $anKinder = [];

    protected function SendDataToChildren(string $Data): array
    {
        $this->anKinder[] = $Data;
        return [];
    }

    /** Nachrichten an die Kinder mit dieser DataID */
    public function nachrichten(string $dataID): array
    {
        return array_values(array_filter(
            $this->anKinder,
            static fn(string $d): bool => json_decode($d, true)['DataID'] === $dataID
        ));
    }
}

final class MasterHarness extends WLEDMaster
{
}

final class SegmentHarness extends WLEDSegment
{
}

$pruefungen = 0;
$fehler     = [];
function pruefe(bool $ok, string $text): void
{
    global $pruefungen, $fehler;
    $pruefungen++;
    if (!$ok) {
        $fehler[] = $text;
    }
    echo($ok ? '  ok   ' : '  FEHL ') . $text . "\n";
}

function instanz(string $klasse, string $modulID, string $name, int $typ): IPSModuleStrict
{
    $id = IPS\ObjectManager::registerObject(1 /* Instance */);
    IPS\InstanceManager::createInstance($id, ['ModuleID' => $modulID, 'ModuleName' => $name, 'ModuleType' => $typ, 'Class' => $klasse]);
    return IPS\InstanceManager::getInstanceInterface($id);
}

function instanzID(IPSModuleStrict $m): int
{
    return (int)(new ReflectionProperty(IPSModuleStrict::class, 'InstanceID'))->getValue($m);
}

function zusammenfassung(IPSModuleStrict $m): string
{
    return IPS_GetObject(instanzID($m))['ObjectSummary'];
}

IPS\Kernel::reset();
$fixture  = file_get_contents(__DIR__ . '/fixtures/ws-antwort-v.json');
$splitter = instanz(SplitterHarness::class, WLEDIds::MODULE_WLED_SPLITTER, 'WLEDSplitter', 2);
$master   = instanz(MasterHarness::class, WLEDIds::MODULE_WLED_MASTER, 'WLEDMaster', 3);
$segment  = instanz(SegmentHarness::class, '{920FA780-28E0-C329-63C4-D79F8EEEE502}', 'WLEDSegment', 3);

echo "Splitter verteilt die WebSocket-Antwort\n";
$splitter->ReceiveData(json_encode(['DataID' => WLEDIds::DATA_WEBSOCKET_TO_SPLITTER, 'Buffer' => bin2hex($fixture)], JSON_THROW_ON_ERROR));
$anMaster  = $splitter->nachrichten(WLEDIds::DATA_SPLITTER_TO_MASTER);
$anSegment = $splitter->nachrichten(WLEDIds::DATA_SPLITTER_TO_SEGMENT);
pruefe(count($anMaster) === 1, 'eine Nachricht an den Master');
pruefe(count($anSegment) === 1, 'eine Nachricht an das eine Segment der Fixture');

echo "Master übernimmt Zustand und Gerätenamen\n";
foreach ($anMaster as $nachricht) {
    $master->ReceiveData($nachricht);
}
pruefe(GetValue(IPS_GetObjectIDByIdent('VariableBrightness', instanzID($master))) === 30, 'Helligkeit 30 aus state.bri');
pruefe(zusammenfassung($master) === 'WLED Bumas:Master', 'Zusammenfassung „WLED Bumas:Master“ (ist: „' . zusammenfassung($master) . '“)');

echo "Segment übernimmt den Gerätenamen\n";
foreach ($anSegment as $nachricht) {
    $segment->ReceiveData($nachricht);
}
pruefe(zusammenfassung($segment) === 'WLED Bumas:0', 'Zusammenfassung „WLED Bumas:0“ (ist: „' . zusammenfassung($segment) . '“)');

echo "Kein HTTP-Abruf von /json/info in Master und Segment\n";
$code = file_get_contents(dirname(__DIR__) . '/libs/WLEDDeviceTrait.php')
    . file_get_contents(dirname(__DIR__) . '/SymconWLEDMaster/module.php')
    . file_get_contents(dirname(__DIR__) . '/SymconWLEDSegment/module.php');
pruefe(!str_contains($code, '/json/info'), 'weder Trait noch Master noch Segment fragen /json/info ab');

foreach ($fehler as $f) {
    echo 'FEHLER ', $f, "\n";
}
printf("\n%d Prüfungen, %d Fehler\n", $pruefungen, count($fehler));
exit($fehler === [] ? 0 : 1);
