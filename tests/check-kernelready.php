<?php

declare(strict_types=1);

/**
 * Prüft das KR_READY-Muster aller Module (Sperrklinke).
 *
 * Anlass: Die Stable-Einreichung 1.1 build 41 wurde im Store-Review abgelehnt, weil die
 * Discovery im MessageSink bei KR_READY weiterhin direkt ApplyChanges() aufrief. Das Muster
 * der übrigen Module gilt für alle:
 *   - ApplyChanges() wird nur vom Kernel aufgerufen; im Modulcode steht der Aufruf allein als
 *     parent::ApplyChanges() innerhalb von ApplyChanges();
 *   - wer IPS_KERNELMESSAGE registriert, bricht ApplyChanges() vor jeder Arbeit ab, solange
 *     IPS_GetKernelRunlevel() !== KR_READY ist, und erledigt diese Arbeit im MessageSink
 *     über eine eigene Methode.
 *
 * Aufruf: php tests/check-kernelready.php
 */

$wurzel = dirname(__DIR__);
$fehler = [];
$pruefungen = 0;

/** Rumpf einer Methode (ohne äußere Klammern), '' wenn es sie nicht gibt. */
function methodenrumpf(string $code, string $name): string
{
    if (!preg_match('/function\s+' . $name . '\s*\([^)]*\)\s*(?::\s*\??\w+\s*)?\{/i', $code, $m, PREG_OFFSET_CAPTURE)) {
        return '';
    }
    $start = $m[0][1] + strlen($m[0][0]);
    $tiefe = 1;
    $ende = $start;
    $laenge = strlen($code);
    while ($ende < $laenge && $tiefe > 0) {
        $zeichen = $code[$ende];
        if ($zeichen === '{') {
            $tiefe++;
        } elseif ($zeichen === '}') {
            $tiefe--;
        }
        $ende++;
    }
    return substr($code, $start, $ende - $start - 1);
}

foreach (glob($wurzel . '/*/module.php') as $datei) {
    $modul = basename(dirname($datei));
    $code = file_get_contents($datei);

    // Jede Trait-Datei, die das Modul einbindet, zählt mit (Master und Segment teilen MessageSink).
    foreach (['WLEDDeviceTrait'] as $trait) {
        if (str_contains($code, 'use ' . $trait . ';')) {
            $code .= "\n" . file_get_contents($wurzel . '/libs/' . $trait . '.php');
        }
    }

    // 1. Kein direkter ApplyChanges()-Aufruf außerhalb von parent::ApplyChanges().
    $pruefungen++;
    $ohneParent = str_replace('parent::ApplyChanges()', '', $code);
    if (preg_match_all('/(?:\$this->|self::|static::|IPS_)ApplyChanges\s*\(/', $ohneParent, $treffer)) {
        // IPS_ApplyChanges($id) auf fremde Instanzen (Discovery legt Instanzen an) ist erlaubt.
        $eigene = array_filter($treffer[0], static fn(string $t): bool => !str_starts_with($t, 'IPS_'));
        if ($eigene !== []) {
            $fehler[] = sprintf('%s: ruft ApplyChanges() selbst auf (%s). Arbeit bei KR_READY gehört in eine eigene Methode.', $modul, implode(', ', $eigene));
        }
    }

    // 2. MessageSink reagiert auf KR_READY, ohne ApplyChanges() anzufassen.
    $sink = methodenrumpf($code, 'MessageSink');
    $pruefungen++;
    if ($sink !== '' && str_contains($sink, 'ApplyChanges')) {
        $fehler[] = sprintf('%s: MessageSink ruft ApplyChanges auf.', $modul);
    }

    // 3. Wer IPS_KERNELMESSAGE registriert, bricht ApplyChanges() vor KR_READY ab.
    if (str_contains($code, 'IPS_KERNELMESSAGE')) {
        $pruefungen++;
        $apply = methodenrumpf($code, 'ApplyChanges');
        if ($apply === '') {
            $fehler[] = sprintf('%s: registriert IPS_KERNELMESSAGE, hat aber kein ApplyChanges().', $modul);
        } elseif (!preg_match('/IPS_GetKernelRunlevel\(\)\s*!==\s*KR_READY/', $apply)) {
            $fehler[] = sprintf('%s: ApplyChanges() ohne Abbruch bei IPS_GetKernelRunlevel() !== KR_READY.', $modul);
        }
        $pruefungen++;
        if ($sink === '' || !str_contains($sink, 'KR_READY')) {
            $fehler[] = sprintf('%s: registriert IPS_KERNELMESSAGE, MessageSink behandelt KR_READY aber nicht.', $modul);
        }
    }
}

foreach ($fehler as $f) {
    echo 'FEHLER ', $f, "\n";
}
printf("%d Prüfungen, %d Fehler\n", $pruefungen, count($fehler));
exit($fehler === [] ? 0 : 1);
