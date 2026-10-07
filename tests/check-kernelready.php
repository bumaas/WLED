<?php

declare(strict_types=1);

/**
 * Prüft das KR_READY-Muster aller Module (Sperrklinke).
 *
 * Anlass: Die Stable-Einreichung 1.1 build 41 wurde im Store-Review abgelehnt, weil die
 * Discovery im MessageSink bei KR_READY weiterhin direkt ApplyChanges() aufrief. Das Muster
 * der übrigen Module gilt für alle:
 *   - ApplyChanges() wird nur vom Kernel aufgerufen; im Modulcode steht der Aufruf allein als
 *     parent::ApplyChanges() innerhalb von ApplyChanges(). Auch der Umweg über die API,
 *     IPS_ApplyChanges($this->InstanceID), zählt als Selbstaufruf;
 *   - wer IPS_KERNELMESSAGE registriert, prüft in ApplyChanges() den Runlevel gegen KR_READY
 *     (Arbeit, die den Kernel braucht, läuft erst danach) und erledigt die KR_READY-Arbeit im
 *     MessageSink über eine eigene Methode.
 *
 * Methodenrümpfe werden über token_get_all() ermittelt, damit Klammern in Strings und
 * Kommentaren das Ergebnis nicht verfälschen. Traits, die eine Modulklasse einbindet, zählen
 * zu ihrem Code (Master und Segment teilen MessageSink über WLEDDeviceTrait).
 *
 * Aufruf: php tests/check-kernelready.php
 */

$wurzel = dirname(__DIR__);
$fehler = [];
$pruefungen = 0;

/** Trait-Name => Datei, aus allen libs/*.php. */
function traitDateien(string $wurzel): array
{
    $dateien = [];
    foreach (glob($wurzel . '/libs/*.php') as $datei) {
        $tokens = token_get_all(file_get_contents($datei));
        $anzahl = count($tokens);
        foreach ($tokens as $i => $token) {
            if (!is_array($token) || $token[0] !== T_TRAIT) {
                continue;
            }
            // Nächstes Nicht-Whitespace-Token ist der Name.
            for ($j = $i + 1; $j < $anzahl; $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    continue;
                }
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    $dateien[$tokens[$j][1]] = $datei;
                }
                break;
            }
        }
    }
    return $dateien;
}

/** Namen der Traits, die im Klassenrumpf per use eingebunden sind (ohne Namespace). */
function eingebundeneTraits(string $code): array
{
    $tokens = token_get_all($code);
    $namen = [];
    $tiefe = 0;
    $anzahl = count($tokens);
    for ($i = 0; $i < $anzahl; $i++) {
        $token = $tokens[$i];
        if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
            $tiefe++;
            continue;
        }
        if ($token === '}') {
            $tiefe--;
            continue;
        }
        // use auf Tiefe 0 sind Namespace-Importe, use in Closures stehen hinter einer ')'.
        if ($tiefe !== 1 || !is_array($token) || $token[0] !== T_USE) {
            continue;
        }
        $text = '';
        for ($j = $i + 1; $j < $anzahl && $tokens[$j] !== ';' && $tokens[$j] !== '{'; $j++) {
            $text .= is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
        }
        foreach (explode(',', $text) as $name) {
            $name = trim($name);
            if ($name !== '') {
                $teile = explode('\\', $name);
                $namen[] = end($teile);
            }
        }
    }
    return $namen;
}

/**
 * Rumpf einer Methode (ohne äußere Klammern) aus den Tokens, '' wenn es sie nicht gibt oder
 * sie keinen Rumpf hat (abstract).
 */
function methodenrumpf(string $code, string $name): string
{
    $tokens = token_get_all($code);
    $anzahl = count($tokens);
    for ($i = 0; $i < $anzahl; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }
        // Name: nächstes T_STRING nach T_FUNCTION (ggf. hinter '&' und Whitespace).
        $j = $i + 1;
        while ($j < $anzahl && (($tokens[$j] === '&') || (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE))) {
            $j++;
        }
        if (!is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING || strcasecmp($tokens[$j][1], $name) !== 0) {
            continue;
        }
        // Bis zur öffnenden Klammer des Rumpfs; ein ';' vorher heißt: ohne Rumpf.
        while ($j < $anzahl && $tokens[$j] !== '{') {
            if ($tokens[$j] === ';') {
                return '';
            }
            $j++;
        }
        $tiefe = 0;
        $rumpf = '';
        for (; $j < $anzahl; $j++) {
            $token = $tokens[$j];
            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $tiefe++;
                if ($tiefe === 1) {
                    continue;
                }
            } elseif ($token === '}') {
                $tiefe--;
                if ($tiefe === 0) {
                    return $rumpf;
                }
            }
            $rumpf .= is_array($token) ? $token[1] : $token;
        }
        return $rumpf;
    }
    return '';
}

/** Code ohne Kommentare, damit erklärende Texte keine Treffer liefern. */
function ohneKommentare(string $code): string
{
    $ergebnis = '';
    foreach (token_get_all($code) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $ergebnis .= is_array($token) ? $token[1] : $token;
    }
    return $ergebnis;
}

$traitDateien = traitDateien($wurzel);

foreach (glob($wurzel . '/*/module.php') as $datei) {
    $modul = basename(dirname($datei));
    $code = file_get_contents($datei);

    foreach (eingebundeneTraits($code) as $trait) {
        if (isset($traitDateien[$trait])) {
            $code .= "\n" . file_get_contents($traitDateien[$trait]);
        }
    }
    $codeOhneKommentare = ohneKommentare($code);

    // 1. Kein Selbstaufruf von ApplyChanges() außerhalb von parent::ApplyChanges().
    //    IPS_ApplyChanges($id) auf fremde Instanzen (Discovery legt Instanzen an) ist erlaubt.
    $pruefungen++;
    $ohneParent = str_replace('parent::ApplyChanges()', '', $codeOhneKommentare);
    preg_match_all('/(?:\$this->|self::|static::)ApplyChanges\s*\(|IPS_ApplyChanges\s*\(\s*\$this->InstanceID/', $ohneParent, $treffer);
    if ($treffer[0] !== []) {
        $fehler[] = sprintf('%s: ruft ApplyChanges() selbst auf (%s). Arbeit bei KR_READY gehört in eine eigene Methode.', $modul, implode(', ', array_map('trim', $treffer[0])));
    }

    // 2. MessageSink reagiert auf KR_READY, ohne ApplyChanges() anzufassen.
    $sink = methodenrumpf($codeOhneKommentare, 'MessageSink');
    $pruefungen++;
    if ($sink === '' && preg_match('/function\s+MessageSink\b/i', $codeOhneKommentare)) {
        $fehler[] = sprintf('%s: MessageSink vorhanden, Rumpf aber nicht auswertbar.', $modul);
    } elseif (str_contains($sink, 'ApplyChanges')) {
        $fehler[] = sprintf('%s: MessageSink ruft ApplyChanges auf.', $modul);
    }

    // 3. Wer IPS_KERNELMESSAGE registriert, prüft in ApplyChanges() den Runlevel gegen KR_READY
    //    (beide Operandenreihenfolgen, !==/!= und das positive === sind zulässig).
    if (str_contains($codeOhneKommentare, 'IPS_KERNELMESSAGE')) {
        $pruefungen++;
        $apply = methodenrumpf($codeOhneKommentare, 'ApplyChanges');
        $vergleich = '/IPS_GetKernelRunlevel\(\)\s*[!=]==?\s*KR_READY|KR_READY\s*[!=]==?\s*IPS_GetKernelRunlevel\(\)/';
        if ($apply === '') {
            $fehler[] = sprintf('%s: registriert IPS_KERNELMESSAGE, hat aber kein ApplyChanges().', $modul);
        } elseif (!preg_match($vergleich, $apply)) {
            $fehler[] = sprintf('%s: ApplyChanges() ohne Prüfung von IPS_GetKernelRunlevel() gegen KR_READY.', $modul);
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
