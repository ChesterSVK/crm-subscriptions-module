<?php

namespace Crm\SubscriptionsModule\Events;

use Crm\UsersModule\User\IUserGetter;
use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

/**
 * Event emitted in case of subscription's basic parameters (start_time, end_time, subscription_type_id, user_id) update
 * Class SubscriptionUpdatedEvent
 * @package Crm\SubscriptionsModule\Events
 */
class SubscriptionUpdatedEvent extends AbstractEvent implements IUserGetter, SubscriptionEventInterface
{
    /** @var ActiveRow  */
    private $subscription;

    /** @var array|null */
    private $updateData;

    public function __construct(ActiveRow $subscription, ?array $updateData = null)
    {
        $this->subscription = $subscription;
        if (!$updateData) {
            $this->updateData = $updateData;
        }
    }

    public function getSubscription(): ActiveRow
    {
        return $this->subscription;
    }

    public function getUserId(): int
    {
        return $this->subscription->user_id;
    }

    public function getUpdateData(): ?array
    {
        return $this->updateData;
    }
}
