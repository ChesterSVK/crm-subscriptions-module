<?php

namespace Crm\SubscriptionsModule\PaymentItem;

use Crm\PaymentsModule\PaymentItem\PaymentItemInterface;
use Crm\PaymentsModule\PaymentItem\PaymentItemTrait;
use Nette\Database\Table\ActiveRow;

class SubscriptionTypePaymentItem implements PaymentItemInterface
{
    use PaymentItemTrait;

    const TYPE = 'subscription_type';

    private $meta;

    private $subscriptionTypeId;

    private $subscriptionTypeItemId;

    public function __construct(
        int $subscriptionTypeId,
        string $name,
        float $price,
        int $vat,
        int $count = 1,
        array $meta = [],
        ?int $subscriptionTypeItemId = null
    ) {
        $this->subscriptionTypeId = $subscriptionTypeId;
        $this->name = $name;
        $this->price = $price;
        $this->vat = $vat;
        $this->count = $count;
        $this->meta = $meta;
        $this->subscriptionTypeItemId = $subscriptionTypeItemId;
    }

    /**
     * @param ActiveRow $subscriptionType
     * @param int $count
     * @return static[]
     */
    public static function fromSubscriptionType(ActiveRow $subscriptionType, int $count = 1): array
    {
        $rows = [];
        foreach ($subscriptionType->related('subscription_type_items')->where('deleted_at', null) as $item) {
            $rows[] = static::fromSubscriptionTypeItem($item, $count);
        }
        return $rows;
    }

    /**
     * @param ActiveRow $subscriptionTypeItem
     * @param int $count
     * @return static
     */
    public static function fromSubscriptionTypeItem(ActiveRow $subscriptionTypeItem, int $count = 1)
    {
        return new SubscriptionTypePaymentItem(
            $subscriptionTypeItem->subscription_type_id,
            $subscriptionTypeItem->name,
            $subscriptionTypeItem->amount,
            $subscriptionTypeItem->vat,
            $count,
            $subscriptionTypeItem->related('subscription_type_item_meta')->fetchPairs('key', 'value'),
            $subscriptionTypeItem->id
        );
    }

    /**
     * @param ActiveRow $paymentItem
     * @return SubscriptionTypePaymentItem
     * @throws \Exception Thrown if payment item isn't `subscription_type` payment item type.
     */
    public static function fromPaymentItem(ActiveRow $paymentItem)
    {
        if ($paymentItem->type != self::TYPE) {
            throw new \Exception("Can not load SubscriptionTypePaymentItem from payment item of different type. Got [{$paymentItem->type}]");
        }
        return new SubscriptionTypePaymentItem(
            $paymentItem->subscription_type_id,
            $paymentItem->name,
            $paymentItem->amount,
            $paymentItem->vat,
            $paymentItem->count,
            [],
            $paymentItem->subscription_type_item_id
        );
    }

    public function data(): array
    {
        return [
            'subscription_type_id' => $this->subscriptionTypeId,
            'subscription_type_item_id' => $this->subscriptionTypeItemId,
        ];
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function forceName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function forceVat(int $vat): self
    {
        $this->vat = $vat;
        return $this;
    }

    public function forcePrice(float $price): self
    {
        $this->price = $price;
        return $this;
    }
}
