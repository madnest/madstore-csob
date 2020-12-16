<?php

use OndraKoupil\Csob\GatewayUrl;

return [

    'merchant_id' => env('CSOB_MERCHANT_ID'),

    'private_key' => storage_path(env('CSOB_PRIVATE_KEY_PATH')),

    'public_key' => storage_path(env('CSOB_PUBLIC_KEY_PATH')),

    'shop_name' => env('APP_NAME', 'madstore'),

    'return_url' => env('CSOB_RETURN_URL'),

    'api_url' => env('CSOB_API_URL', GatewayUrl::TEST_LATEST),

];
