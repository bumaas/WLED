<?php

declare(strict_types=1);

/**
 * Splitter verteilt Segmentdaten an die richtige Segment-Instanz (Kernel-Stub, symcon/SymconStubs).
 *
 * Anlass: /code-review HEAD~2 am 02.11.2026, Befunde 2 und 3.
 *   2. Der ReceiveDataFilter des Segments hatte hinter der Segmentnummer keine Grenze. Die Instanz
 *      für Segment 1 nahm deshalb auch die Daten von Segment 10 bis 19 an.
 *   3. Der Splitter reichte state und jeden Eintrag aus state.seg an eine Closure mit Typ array.
 *      Ein Eintrag, der kein Objekt ist, warf unter strict_types einen TypeError, und kein Kind
 *      bekam ein Update.
 *
 * Grundlage ist der echte Mitschnitt tests/fixtures/ws-antwort-v.json (ein Segment, id 0). Für
 * mehrere Segmente wird dessen Segment-Eintrag kopiert und nur id (und bri zum Unterscheiden)
 * geändert. Der Eintrag, der kein Objekt ist, ist ein konstruierter Grenzfall; WLED selbst schickt
 * ihn nicht, der Splitter darf daran aber nicht abbrechen.
 *
 * Aufruf: php tests/check-segmentdaten.php
 */

require_once __DIR__ . '/stubs/autoload.php';
require_once dirname(__DIR__) . '/SymconWLEDSplitter/module.php';
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

    public function nachrichten(string $dataID): array
    {
        return array_values(array_filter(
            $this->anKinder,
            static fn(string $d): bool => json_decode($d, true)['DataID'] === $dataID
        ));
    }
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

/** Welche Nachrichten lässt der ReceiveDataFilter des Segments durch (Abgleich wie im Kernel-Stub)? */
function durchgelassen(IPSModuleStrict $segment, array $nachrichten): array
{
    $filter = $segment->GetReceiveDataFilter();
    return array_values(array_filter($nachrichten, static fn(string $n): bool => preg_match('/' . $filter . '/', $n) === 1));
}

function segmentID(string $nachricht): int
{
    return json_decode(json_decode($nachricht, true)['Buffer'], true)['id'];
}

/** Kann die ReceiveData des Splitters die Nachricht verarbeiten, ohne zu werfen? */
function verteile(SplitterHarness $splitter, array $nachricht): ?string
{
    $splitter->anKinder = [];
    try {
        $splitter->ReceiveData(json_encode(['DataID' => WLEDIds::DATA_WEBSOCKET_TO_SPLITTER, 'Buffer' => bin2hex(json_encode($nachricht, JSON_THROW_ON_ERROR))], JSON_THROW_ON_ERROR));
        return null;
    } catch (Throwable $e) {
        return get_class($e) . ': ' . $e->getMessage();
    }
}

IPS\Kernel::reset();
$fixture  = json_decode(file_get_contents(__DIR__ . '/fixtures/ws-antwort-v.json'), true, 512, JSON_THROW_ON_ERROR);
$vorlage  = $fixture['state']['seg'][0];
$splitter = instanz(SplitterHarness::class, WLEDIds::MODULE_WLED_SPLITTER, 'WLEDSplitter', 2);

echo "Segmentfilter trennt 1 von 10 bis 12\n";
$nachricht = $fixture;
$nachricht['state']['seg'] = [];
foreach ([0, 1, 2, 10, 11, 12] as $nr) {
    $nachricht['state']['seg'][] = ['id' => $nr, 'bri' => 100 + $nr] + $vorlage;
}
pruefe(verteile($splitter, $nachricht) === null, 'Splitter verteilt sechs Segmente ohne Fehler');
$anSegmente = $splitter->nachrichten(WLEDIds::DATA_SPLITTER_TO_SEGMENT);
pruefe(count($anSegmente) === 6, 'sechs Nachrichten an die Segmente (sind ' . count($anSegmente) . ')');

foreach ([0, 1, 2, 10, 12] as $nr) {
    $segment = instanz(SegmentHarness::class, '{920FA780-28E0-C329-63C4-D79F8EEEE502}', 'WLEDSegment', 3);
    IPS_SetProperty(instanzID($segment), 'SegmentID', $nr);
    $segment->ApplyChanges();
    $ids = array_map('segmentID', durchgelassen($segment, $anSegmente));
    pruefe($ids === [$nr], "Segment $nr nimmt nur seine eigenen Daten an (nimmt: " . implode(', ', $ids) . ')');
}

echo "Einträge, die kein Objekt sind\n";
$nachricht = $fixture;
$nachricht['state']['seg'] = [7, ['id' => 1] + $vorlage];
$fehlerText = verteile($splitter, $nachricht);
pruefe($fehlerText === null, 'kein Abbruch bei einem seg-Eintrag, der kein Objekt ist' . ($fehlerText === null ? '' : " ($fehlerText)"));
pruefe(count($splitter->nachrichten(WLEDIds::DATA_SPLITTER_TO_MASTER)) === 1, 'Master bekommt trotzdem den Zustand');
$anSegmente = $splitter->nachrichten(WLEDIds::DATA_SPLITTER_TO_SEGMENT);
pruefe(array_map('segmentID', $anSegmente) === [1], 'nur das gültige Segment 1 wird weitergereicht');

$nachricht = $fixture;
$nachricht['state'] = null;
$fehlerText = verteile($splitter, $nachricht);
pruefe($fehlerText === null, 'kein Abbruch bei "state": null' . ($fehlerText === null ? '' : " ($fehlerText)"));
pruefe($splitter->anKinder === [], 'bei "state": null geht nichts an die Kinder');

foreach ($fehler as $f) {
    echo 'FEHLER ', $f, "\n";
}
printf("\n%d Prüfungen, %d Fehler\n", $pruefungen, count($fehler));
exit($fehler === [] ? 0 : 1);
