<?php

declare(strict_types=1);

/**
 * Datenfluss-Aufrufe ohne @ (Sperrklinke).
 *
 * Anlass: Store-Vorab-Review 02.11.2026, Best Practice 1.2. SendData() rief
 * @$this->SendDataToParent() auf; ohne aktiven Parent verschwand der Befehl still, ohne
 * Rückmeldung an den Nutzer. Datenfluss-Aufrufe (SendDataToParent, SendDataToChildren)
 * stehen deshalb nie hinter dem Fehlerunterdrückungs-Operator. Wer keinen aktiven Parent
 * hat, prüft das vorher mit HasActiveParent() und meldet es.
 *
 * Geprüft wird über token_get_all(), damit Kommentare und Strings nicht zählen.
 *
 * Aufruf: php tests/check-fehlerunterdrueckung.php
 */

$wurzel     = dirname(__DIR__);
$dateien    = array_merge(glob($wurzel . '/*/module.php'), glob($wurzel . '/libs/*.php'));
$fehler     = [];
$pruefungen = 0;

foreach ($dateien as $datei) {
    if (str_contains(str_replace('\\', '/', $datei), '/tests/')) {
        continue;
    }
    $pruefungen++;
    $tokens = array_values(array_filter(
        token_get_all(file_get_contents($datei)),
        static fn($t): bool => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
    ));
    foreach ($tokens as $i => $t) {
        if ($t !== '@') {
            continue;
        }
        // @ gefolgt von $this -> SendDataToParent bzw. SendDataToChildren
        $folge = array_slice($tokens, $i + 1, 3);
        $namen = array_map(static fn($x): string => is_array($x) ? $x[1] : $x, $folge);
        if (in_array($namen[2] ?? '', ['SendDataToParent', 'SendDataToChildren'], true)) {
            $zeile    = is_array($folge[0]) ? $folge[0][2] : 0;
            $fehler[] = sprintf('%s:%d: @%s unterdrückt Fehler ohne Rückmeldung', substr($datei, strlen($wurzel) + 1), $zeile, implode('', $namen));
        }
    }
}

foreach ($fehler as $f) {
    echo 'FEHLER ', $f, "\n";
}
printf("%d Prüfungen, %d Fehler\n", $pruefungen, count($fehler));
exit($fehler === [] ? 0 : 1);
