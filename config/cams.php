<?php

return [

    /*
    | Philippine VAT (NIRC Sec. 106/108) — standard rate on VAT-registered
    | suppliers' sales. Receipts show VAT-INCLUSIVE prices, so input tax is
    | extracted from the gross: gross x rate / (100 + rate). Override only if
    | the law changes.
    */
    'vat_rate' => (float) env('CAMS_VAT_RATE', 12.0),

];
