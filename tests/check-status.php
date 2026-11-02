<?php

declare(strict_types=1);

/**
 * Status folgt dem Parent, Hop für Hop (Kernel-Stub, symcon/SymconStubs).
 *
 * Anlass: Store-Vorab-Review 02.11.2026 (Punkt 3, fehlende Voraussetzungen sichtbar machen) und
 * der Fall am nuc am selben Tag: Der WebSocket Client hing nach einem Geräteausfall auf 200,
 * Splitter, Master und Segmente zeigten trotzdem 102. Jetzt spiegelt jeder Hop den Status seines
 * direkten Parents (Vertrag nach dem MQTT Client, siehe doku-und-interna „Statuspropagation"):
 *   - ohne Parent: 104 (inaktiv)
 *   - Parent nicht aktiv: 201 (keine Verbindung zum WLED-Gerät)
 *   - Parent aktiv: 102; Master und Segment fordern dabei den Zustand an ({"v":true})
 * Registriert wird IM_CHANGESTATUS nur auf dem direkten Parent, FM_CONNECT/FM_DISCONNECT auf
 * sich selbst. Der Stub verschickt keine Nachrichten; der Test ruft MessageSink so auf, wie es
 * der Kernel täte.
 *
 * Aufruf: php tests/check-status.php
 */

require_once __DIR__ . '/stubs/autoload.php';
require_once dirname(__DIR__) . '/SymconWLEDSplitter/module.php';
require_once dirname(__DIR__) . '/SymconWLEDMaster/module.php';
require_once dirname(__DIR__) . '/SymconWLEDSegment/module.php';

use libs\WLEDIds;

/** I/O-Attrappe an Stelle des WebSocket Clients; ihr Status wird im Test gesetzt. */
final class WebSocketAttrappe extends IPSModuleStrict
{
}

trait Mitschrift
{
    /** @var list<string> Buffer jedes SendDataToParent */
    public array $gesendet = [];

    protected function SendDataToParent(string $Data): string
    {
        $this->gesendet[] = hex2bin(json_decode($Data, true)['Buffer']);
        return '';
    }

    protected function SendDataToChildren(string $Data): array
    {
        return [];
    }

    public function nachrichten(): array
    {
        return $this->GetMessageList();
    }

    /** Buffer vorbelegen, so wie er nach einem Kernel-Neustart gespeichert wiederkommt */
    public function bufferVorbelegen(string $name, string $wert): void
    {
        $this->SetBuffer($name, $wert);
    }
}

final class SplitterHarness extends WLEDSplitter
{
    use Mitschrift;
}

final class MasterHarness extends WLEDMaster
{
    use Mitschrift;
}

final class SegmentHarness extends WLEDSegment
{
    use Mitschrift;
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

function instanz(string $klasse, string $modulID, int $typ): IPSModuleStrict
{
    $id = IPS\ObjectManager::registerObject(1 /* Instance */);
    IPS\InstanceManager::createInstance($id, ['ModuleID' => $modulID, 'ModuleName' => $klasse, 'ModuleType' => $typ, 'Class' => $klasse]);
    return IPS\InstanceManager::getInstanceInterface($id);
}

function id(IPSModuleStrict $m): int
{
    return (int)(new ReflectionProperty(IPSModuleStrict::class, 'InstanceID'))->getValue($m);
}

/** Verbindung direkt im Instanz-Manager setzen (der Stub prüft sonst die GUIDs geladener Bibliotheken). */
function verbinde(IPSModuleStrict $kind, int $parentID): void
{
    $p     = new ReflectionProperty(IPS\InstanceManager::class, 'instances');
    $liste = $p->getValue();
    $liste[id($kind)]['ConnectionID'] = $parentID;
    $p->setValue(null, $liste);
}

function status(IPSModuleStrict $m): int
{
    return IPS_GetInstance(id($m))['InstanceStatus'];
}

/** Parent ändert seinen Status; der Kernel meldet das an alle, die IM_CHANGESTATUS darauf registriert haben. */
function parentStatus(int $parentID, int $neu, IPSModuleStrict ...$kinder): void
{
    IPS\InstanceManager::setStatus($parentID, $neu);
    foreach ($kinder as $kind) {
        if (in_array(IM_CHANGESTATUS, $kind->nachrichten()[$parentID] ?? [], true)) {
            $kind->MessageSink(time(), $parentID, IM_CHANGESTATUS, [$neu, 0]);
        }
    }
}

IPS\Kernel::reset();

echo "Splitter ohne Parent\n";
$splitter = instanz(SplitterHarness::class, WLEDIds::MODULE_WLED_SPLITTER, 2);
pruefe(status($splitter) === IS_INACTIVE, 'Status 104 (ist ' . status($splitter) . ')');

echo "Splitter am WebSocket Client\n";
$ws = instanz(WebSocketAttrappe::class, '{D68FD31F-0E90-7019-F16C-1949BD3079EF}', 1);
IPS\InstanceManager::setStatus(id($ws), 200);
verbinde($splitter, id($ws));
$splitter->MessageSink(time(), id($splitter), FM_CONNECT, [id($ws), 0]);
pruefe(in_array(IM_CHANGESTATUS, $splitter->nachrichten()[id($ws)] ?? [], true), 'IM_CHANGESTATUS auf dem WebSocket Client registriert');
pruefe(status($splitter) === 201, 'WebSocket Client auf 200: Splitter 201 (ist ' . status($splitter) . ')');
parentStatus(id($ws), IS_ACTIVE, $splitter);
pruefe(status($splitter) === IS_ACTIVE, 'WebSocket Client aktiv: Splitter 102 (ist ' . status($splitter) . ')');

echo "Master und Segment folgen dem Splitter\n";
$master  = instanz(MasterHarness::class, WLEDIds::MODULE_WLED_MASTER, 3);
$segment = instanz(SegmentHarness::class, '{920FA780-28E0-C329-63C4-D79F8EEEE502}', 3);
pruefe(status($master) === IS_INACTIVE, 'Master ohne Parent 104 (ist ' . status($master) . ')');
foreach ([$master, $segment] as $kind) {
    verbinde($kind, id($splitter));
    $kind->MessageSink(time(), id($kind), FM_CONNECT, [id($splitter), 0]);
}
pruefe($master->gesendet === ['{"v":true}'] && $segment->gesendet === ['{"v":true}'], 'beim eigenen Verbinden fordern Master und Segment je einmal den Zustand an');
pruefe(in_array(IM_CHANGESTATUS, $master->nachrichten()[id($splitter)] ?? [], true), 'Master registriert IM_CHANGESTATUS nur auf dem Splitter');
pruefe(!isset($master->nachrichten()[id($ws)]), 'Master registriert nichts auf dem WebSocket Client');
pruefe(status($master) === IS_ACTIVE && status($segment) === IS_ACTIVE, 'Splitter aktiv: Master und Segment 102');

parentStatus(id($ws), 200, $splitter);
parentStatus(id($splitter), status($splitter), $master, $segment);
pruefe(status($splitter) === 201, 'WebSocket Client fällt aus: Splitter 201 (ist ' . status($splitter) . ')');
pruefe(status($master) === 201 && status($segment) === 201, 'Master und Segment ziehen mit: 201 (ist ' . status($master) . '/' . status($segment) . ')');

// Beim Wiederverbinden fragt nur der Splitter einmal; die Antwort geht an alle Kinder.
// Anlass: /code-review HEAD~2 am 02.11.2026, Befund 5 (vorher fragte jedes Kind einzeln).
$splitter->gesendet = $master->gesendet = $segment->gesendet = [];
parentStatus(id($ws), IS_ACTIVE, $splitter);
parentStatus(id($splitter), status($splitter), $master, $segment);
pruefe(status($master) === IS_ACTIVE && status($segment) === IS_ACTIVE, 'Verbindung wieder da: Master und Segment 102');
pruefe($splitter->gesendet === ['{"v":true}'], 'Splitter fordert beim Aktivwerden einmal den Zustand an (gesendet: ' . json_encode($splitter->gesendet) . ')');
pruefe($master->gesendet === [] && $segment->gesendet === [], 'Master und Segment fragen dabei nicht selbst (gesendet: ' . json_encode([$master->gesendet, $segment->gesendet]) . ')');

echo "Trennen\n";
verbinde($master, 0);
$master->MessageSink(time(), id($master), FM_DISCONNECT, [id($splitter), 0]);
pruefe(status($master) === IS_INACTIVE, 'Master ohne Parent: 104 (ist ' . status($master) . ')');
pruefe(!in_array(IM_CHANGESTATUS, $master->nachrichten()[id($splitter)] ?? [], true), 'Registrierung auf dem alten Splitter aufgehoben');

echo "Kernel-Neustart: Buffer gespeichert, Registrierungen weg\n";
// Der Kernel speichert Instanz-Buffer, Nachrichtenregistrierungen aber nicht. Nach dem Neustart
// steht in ParentID schon die aktuelle ConnectionID, und ApplyChanges muss trotzdem registrieren.
// Anlass: /code-review HEAD~2 am 02.11.2026, Befund 1.
$neustart = instanz(MasterHarness::class, WLEDIds::MODULE_WLED_MASTER, 3);
verbinde($neustart, id($splitter));
$neustart->bufferVorbelegen('ParentID', (string)id($splitter));
$neustart->ApplyChanges();
pruefe(in_array(IM_CHANGESTATUS, $neustart->nachrichten()[id($splitter)] ?? [], true), 'nach dem Neustart IM_CHANGESTATUS auf dem Splitter registriert');
parentStatus(id($ws), 200, $splitter);
parentStatus(id($splitter), status($splitter), $neustart);
pruefe(status($neustart) === 201, 'nach dem Neustart folgt der Master dem Splitter auf 201 (ist ' . status($neustart) . ')');
parentStatus(id($ws), IS_ACTIVE, $splitter);
parentStatus(id($splitter), status($splitter), $neustart);
pruefe(status($neustart) === IS_ACTIVE, 'und wieder zurück auf 102 (ist ' . status($neustart) . ')');

foreach ($fehler as $f) {
    echo 'FEHLER ', $f, "\n";
}
printf("\n%d Prüfungen, %d Fehler\n", $pruefungen, count($fehler));
exit($fehler === [] ? 0 : 1);
