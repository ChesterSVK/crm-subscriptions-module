<?php

namespace Crm\SubscriptionsModule\Components;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;

/**
 * This widget fetches all subscriptions from db and renders
 * simple single stat widget with this value.
 *
 * @package Crm\SubscriptionsModule\Components
 */
class TotalSubscriptionsStatWidget extends BaseLazyWidget
{
    private $templateName = 'total_subscriptions_stat_widget.latte';

    private $subscriptionsRepository;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        SubscriptionsRepository $subscriptionsRepository
    ) {
        parent::__construct($lazyWidgetManager);
        $this->subscriptionsRepository = $subscriptionsRepository;
    }

    public function identifier()
    {
        return 'totalsubscriptionsstatwidget';
    }

    public function render()
    {
        $this->template->totalSubscriptions = $this->subscriptionsRepository->totalCount(true);
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
