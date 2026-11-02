<?php

declare(strict_types=1);

/**
 * Discovery und die Kerninstanz DNS-SD Control (Kernel-Stub, symcon/SymconStubs).
 *
 * Anlass: Store-Vorab-Review 02.11.2026, Punkte 6 und 13. Fehlte eine DNS-SD-Control-Instanz,
 * legte die Discovery sie selbst an, benannte sie und rief IPS_ApplyChanges auf, ausgelöst
 * schon durch das Öffnen des Formulars. DNS-SD Control ist eine Kerninstanz und wird als
 * vorhanden vorausgesetzt. Fehlt sie, legt die Discovery nichts an, sucht nicht und zeigt
 * im Formular einen Hinweis.
 *
 * Aufruf: php tests/check-discovery-dnssd.php
 */

require_once __DIR__ . '/stubs/autoload.php';

const DNSSD_ID = '{780B2D48-916C-4D59-AD35-5A429B2355A5}';

/** Aufrufe der DNS-SD-Funktionen; der Stub bringt sie nicht mit. */
$zcAufrufe = [];

function ZC_QueryServiceType(int $InstanceID, string $ServiceType, string $Domain): array
{
    global $zcAufrufe;
    $zcAufrufe[] = ['QueryServiceType', $InstanceID];
    return []; // kein WLED-Gerät im Netz
}

function ZC_QueryService(int $InstanceID, string $Name, string $Type, string $Domain): array
{
    global $zcAufrufe;
    $zcAufrufe[] = ['QueryService', $InstanceID];
    return [];
}

require_once dirname(__DIR__) . '/SymconWLEDDiscovery/module.php';

final class DiscoveryHarness extends WLEDDiscovery
{
    /** @var array<string, int> letztes SetTimerInterval je Timer */
    public array $timer = [];

    protected function SetTimerInterval(string $Ident, int $Milliseconds): bool
    {
        $this->timer[$Ident] = $Milliseconds;
        return parent::SetTimerInterval($Ident, $Milliseconds);
    }
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

/** Frischer Kernel mit dem Kernmodul DNS-SD Control (ohne Instanz) und einer Discovery. */
function neueDiscovery(): DiscoveryHarness
{
    global $zcAufrufe;
    $zcAufrufe = [];
    IPS\Kernel::reset();
    IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/CoreStubs/library.json');
    $id = IPS\ObjectManager::registerObject(1 /* Instance */);
    IPS\InstanceManager::createInstance($id, [
        'ModuleID'   => '{B1703D33-0B1E-41F7-8C85-3A9BBA2D6B91}', // SymconWLEDDiscovery/module.json
        'ModuleName' => 'WLED Discovery',
        'ModuleType' => 5,
        'Class'      => DiscoveryHarness::class,
    ]);
    return IPS\InstanceManager::getInstanceInterface($id);
}

/** Aktionselement des Formulars mit diesem Namen, sonst null. */
function formElement(DiscoveryHarness $d, string $name): ?array
{
    $form = json_decode($d->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
    foreach ($form['actions'] as $element) {
        if (($element['name'] ?? '') === $name) {
            return $element;
        }
    }
    return null;
}

function sichtbar(?array $element): bool
{
    return $element !== null && ($element['visible'] ?? true);
}

echo "Ohne DNS-SD-Control-Instanz\n";
$d       = neueDiscovery();
$hinweis = formElement($d, 'mdnsMissing');
pruefe(sichtbar($hinweis), 'Formular zeigt den Hinweis auf die fehlende DNS-SD-Control-Instanz');
pruefe(!sichtbar(formElement($d, 'searchingInfo')), 'Formular zeigt keinen Suchfortschritt');
pruefe(($d->timer['DiscoveryTimer'] ?? 0) === 0, 'Öffnen des Formulars startet keine Suche');
$d->RequestAction('discover', '');
pruefe(IPS_GetInstanceListByModuleID(DNSSD_ID) === [], 'Suche legt keine DNS-SD-Control-Instanz an');
pruefe(count(IPS_GetInstanceList()) === 1, 'Suche legt überhaupt keine Instanz an');
pruefe($zcAufrufe === [], 'Suche ruft keine ZC-Funktion auf');

echo "Mit DNS-SD-Control-Instanz\n";
$d    = neueDiscovery();
$zcID = IPS_CreateInstance(DNSSD_ID);
pruefe(!sichtbar(formElement($d, 'mdnsMissing')), 'Formular zeigt keinen Hinweis');
pruefe(($d->timer['DiscoveryTimer'] ?? 0) > 0, 'Öffnen des Formulars startet die Suche');
$d->RequestAction('discover', '');
pruefe(IPS_GetInstanceListByModuleID(DNSSD_ID) === [$zcID], 'Suche nutzt die vorhandene Instanz und legt keine weitere an');
pruefe($zcAufrufe !== [] && array_unique(array_column($zcAufrufe, 1)) === [$zcID], 'ZC-Abfragen gehen an die vorhandene Instanz');

foreach ($fehler as $f) {
    echo 'FEHLER ', $f, "\n";
}
printf("\n%d Prüfungen, %d Fehler\n", $pruefungen, count($fehler));
exit($fehler === [] ? 0 : 1);
