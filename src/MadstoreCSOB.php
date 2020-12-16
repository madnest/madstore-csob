<?php

namespace Madnest\MadstoreCSOB;

use Cartalyst\Stripe\Stripe;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Madnest\Madstore\Payment\Contracts\HasPayerInfo;
use Madnest\Madstore\Payment\Contracts\PaymentOption;
use Madnest\Madstore\Payment\Contracts\Purchasable;
use Madnest\Madstore\Payment\Contracts\PurchasableItem;
use Madnest\Madstore\Payment\Enums\PaymentStatus;
use Madnest\Madstore\Payment\PaymentResponse;
use Madnest\Madstore\Shipping\Contracts\ShippableItem;
use Madnest\MadstoreCSOB\Items\PurchaseItem;
use Madnest\MadstoreCSOB\Items\ShippingItem;

class MadstoreCSOB implements PaymentOption
{
    protected $stripe;

    public function __construct()
    {
        $this->stripe = new Stripe(env('STRIPE_API_KEY'));
    }

    /**
     * Creates new payment.
     *
     * @param Purchasable $purchasable
     * @param array $params
     * @param array $options
     * @return PaymentResponse
     */
    public function createPayment(Purchasable $purchasable, array $params = [], array $options = []): PaymentResponse
    {
        $response = $this->stripe->paymentIntents()->create($this->mapParams($purchasable, $params, $options));

        return new PaymentResponse([
            'statusCode' => 200,
            'status' => PaymentStatus::CREATED,
            'paymentId' => $response['id'],
            'orderNumber' => $response['description'],
            'amount' => $response['amount'],
            'currency' => strtoupper($response['currency']),
            'paymentMethod' => $response['payment_method'] ?? '',
            'gateway' => 'stripe',
            'clientSecret' => $response['client_secret'],
            'redirect' => false,
        ]);
    }

    /**
     * Get status of payment.
     *
     * @param mixed $id
     * @return PaymentResponse
     */
    public function getStatus($id): PaymentResponse
    {
        $response = $this->stripe->events()->find($id);

        $eventType = $response['type'];

        $objectType = $response['data']['object']['object'];
        $objectId = $response['data']['object']['id'];

        switch ($objectType) {
            case 'charge':
                $response = $this->stripe->charges()->find($objectId);
                break;
            case 'payment_intent':
                $response = $this->stripe->paymentIntents()->find($objectId);
                break;
            default:
                throw new InvalidArgumentException('Cannot handle this type of object.');
        }

        return new PaymentResponse([
            'statusCode' => 200,
            'status' => $this->translateEventToPaymentStatus($eventType),
            'paymentId' => $response['id'],
            'orderNumber' => $response['description'],
            'amount' => $response['amount'],
            'currency' => strtoupper($response['currency']),
            'paymentMethod' => $response['payment_method'] ?? '',
            'gateway' => 'stripe',
            'redirect' => false,
        ]);
    }

    /**
     * Map payment parameters.
     *
     * @param Purchasable $model
     * @param array $params
     * @param array $options
     * @return array
     */
    protected function mapParams(Purchasable $model, array $params = [], array $options = []): array
    {
        $params = [
            'amount' => $model->getFinalPrice(), // Stripe does not want cents, but rather final price
            'currency' => strtolower($model->getCurrency()), // Stripe wants lowercase currency
            'description' => $model->getUUID(), // We use UUID to reffer back to given Order
            'metadata' => [],
            'receipt_email' => $model->getPayerInfo()->getEmail(),
            // 'return_url' => config('madstore-stripe.return_url'),
        ];

        return $params;
    }

    /**
     * Loop through purchasable items and map them,
     * possible to also add discount items
     * and delivery item.
     *
     * @param Purchasable $model
     * @return array
     */
    protected function mapItems(Purchasable $model): array
    {
        if ($model->getItems()->isEmpty()) {
            throw new \InvalidArgumentException('There are no items to be purchased.');
        }

        $purchaseItems = $model->getItems()->map(fn ($item) => $this->getPurchaseItem($item))->toArray();

        return $purchaseItems;
    }

    /**
     * Get PurchaseItem.
     *
     * @param PurchasableItem $item
     * @return array
     */
    protected function getPurchaseItem(PurchasableItem $item): array
    {
        $purchaseItem = new PurchaseItem(
            $item->getTitle(),
            $item->getAmount(),
            $item->getQuantity(),
            $item->getVATRate()
        );

        $additionalKeys = ['url', 'ean'];

        foreach ($additionalKeys as $key) {
            $key = Str::camel($key);
            $getMethod = 'get'.$key;
            if (method_exists($item, $getMethod)) {
                $setMethod = 'set'.$key;

                $purchaseItem->$setMethod($item->$getMethod());
            }
        }

        return $purchaseItem->toArray();
    }

    /**
     * Get ShippingItem.
     *
     * @param ShippingItem $shipping
     * @return array
     */
    protected function getShippingItem(ShippableItem $shipping): array
    {
        $shippingItem = new ShippingItem($shipping->getTitle(), $shipping->getAmount());

        return $shippingItem->toArray();
    }

    /**
     * Map payer info.
     *
     * @param HasPayerInfo $model
     * @return array
     */
    protected function mapPayerInfo(HasPayerInfo $model): array
    {
        return [
            'contact' => [
                'first_name' => $model->getFirstName(),
                'last_name' => $model->getLastName(),
                'email' => $model->getEmail(),
                'phone_number' => $model->getPhoneNumber(),
                'city' => $model->getCity(),
                'street' => $model->getStreet(),
                'postal_code' => $model->getZipCode(),
                'country_code' => $model->getCountryIso3Code(),
            ],
        ];
    }

    protected function translateEventToPaymentStatus(string $eventType): string
    {
        $translations = [
            'payment_intent.succeeded' => PaymentStatus::PAID,
            'charge.succeeded' => PaymentStatus::PAID,
        ];

        if (! array_key_exists($eventType, $translations)) {
            return 'undefined';
        }

        return $translations[$eventType];
    }
}
