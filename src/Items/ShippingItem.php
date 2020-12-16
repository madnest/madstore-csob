<?php

namespace Madnest\MadstoreCSOB\Items;

class ShippingItem extends Item
{
    public function __construct(string $name, int $amount, int $quantity = 1, int $vatRate = 21)
    {
        $this->setType('SHIPPING_ITEM');

        $this->setName($name);

        $this->setAmount($amount);

        $this->setCount($quantity);

        $this->setVATRate($vatRate);
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setAmount(int $amount): void
    {
        $this->amount = $amount;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setCount(int $quantity): void
    {
        $this->count = $quantity;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function setVATRate(int $vatRate): void
    {
        $this->vat_rate = $vatRate;
    }

    public function getVATRate(): int
    {
        return $this->vat_rate;
    }
}
