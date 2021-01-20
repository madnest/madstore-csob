<?php

namespace Madnest\MadstoreCSOB;

use Illuminate\Support\Str;
use Madnest\Madstore\Payment\Contracts\HasPayerInfo;
use Madnest\Madstore\Payment\Contracts\PaymentOption;
use Madnest\Madstore\Payment\Contracts\Purchasable;
use Madnest\Madstore\Payment\Contracts\PurchasableItem;
use Madnest\Madstore\Payment\Enums\PaymentStatus;
use Madnest\Madstore\Payment\PaymentResponse;
use Madnest\Madstore\Shipping\Contracts\ShippableItem;
use Madnest\MadstoreCSOB\Items\PurchaseItem;
use Madnest\MadstoreCSOB\Items\ShippingItem;
use OndraKoupil\Csob\Client as CSOBClient;
use OndraKoupil\Csob\Config;
use OndraKoupil\Csob\Payment;

class MadstoreCSOB implements PaymentOption
{
    protected $csob;

    public function __construct()
    {
        $config = new Config(
            config('madstore-csob.merchant_id'),
            config('madstore-csob.private_key'),
            config('madstore-csob.public_key'),
            config('madstore-csob.shop_name'),
            config('madstore-csob.return_url'),
            config('madstore-csob.api_url'),
        );

        $this->csob = new CSOBClient($config);
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
        $payment = new Payment($purchasable->id);
        $payment->currency = $purchasable->getCurrency();
        $payment->returnUrl = config('madstore-csob.return_url')."?id={$purchasable->getUUID()}";

        $payment->addCartItem("Objednávka {$purchasable->getUUID()}", 1, $purchasable->getFinalAmount());

        $response = $this->csob->paymentInit($payment);

        return new PaymentResponse([
            'statusCode' => 200,
            'status' => config(
                "madstore-csob.payment_statuses.{$response['paymentStatus']}",
                PaymentStatus::ERROR
            ),
            'paymentId' => $response['payId'],
            'orderNumber' => $purchasable->getUUID(),
            'amount' => $payment->getTotalAmount(),
            'currency' => $purchasable->getCurrency(),
            'paymentMethod' => $response['payment_method'] ?? 'card',
            'gateway' => 'csob',
            'redirect' => true,
            'redirect_url' => $this->csob->getPaymentProcessUrl($payment),
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
        $response = $this->csob->paymentStatus($id, false);

        return new PaymentResponse([
            'statusCode' => 200,
            'status' => config(
                "madstore-csob.payment_statuses.{$response['paymentStatus']}",
                PaymentStatus::ERROR
            ),
            'paymentId' => $response['payId'],
            'orderNumber' => '',
            'amount' => 0,
            'currency' => '',
            'paymentMethod' => '',
            'gateway' => 'csob',
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
            // 'return_url' => config('madstore-csob.return_url'),
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
}
