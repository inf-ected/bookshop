<?php

declare(strict_types=1);

return [
    'currency_code'        => env('SHOP_CURRENCY', 'RUB'),         // ISO 4217
    'currency_symbol'      => env('SHOP_CURRENCY_SYMBOL', '₽'),
    'currency_decimals'    => (int) env('SHOP_CURRENCY_DECIMALS', 0), // decimal places in display
    'currency_decimal_sep' => env('SHOP_CURRENCY_DECIMAL_SEP', ','), // decimal separator in display
];
