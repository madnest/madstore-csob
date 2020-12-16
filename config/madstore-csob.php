<?php

use Madnest\Madstore\Payment\Enums\PaymentStatus;
use OndraKoupil\Csob\GatewayUrl;

return [

    'merchant_id' => env('CSOB_MERCHANT_ID'),

    'private_key' => storage_path(env('CSOB_PRIVATE_KEY_PATH')),

    'public_key' => storage_path(env('CSOB_PUBLIC_KEY_PATH')),

    'shop_name' => env('APP_NAME', 'madstore'),

    'return_url' => env('CSOB_RETURN_URL'),

    'api_url' => env('CSOB_API_URL', GatewayUrl::TEST_LATEST),

    'payment_statuses' => [
        '1' => PaymentStatus::CREATED,
        '2' => PaymentStatus::CREATED,
        '3' => PaymentStatus::CANCELED,
        '4' => PaymentStatus::AUTHORIZED,
        '5' => PaymentStatus::CANCELED,
        '6' => PaymentStatus::CANCELED,
        '7' => PaymentStatus::PAID,
        '8' => PaymentStatus::PAID,
        '9' => PaymentStatus::REFUNDED,
        '10' => PaymentStatus::REFUNDED,
    ],

];
