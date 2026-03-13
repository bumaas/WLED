<?php

declare(strict_types=1);

namespace libs;

final class WLEDIds
{
    // Datenfluss-IDs zwischen IO, Splitter und Kindmodulen
    public const DATA_WEBSOCKET_TO_SPLITTER = '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}';
    public const DATA_DEVICE_TO_SPLITTER    = '{7B4E5B18-F847-8F8A-F148-3FB3F482E295}';
    public const DATA_SPLITTER_TO_SEGMENT   = '{D2353839-DA64-DF79-7CD5-4DD827DCE82A}';
    public const DATA_SPLITTER_TO_MASTER    = '{79D5ACD0-7EED-FBA6-22D7-04AEB1BBBE97}';
}
