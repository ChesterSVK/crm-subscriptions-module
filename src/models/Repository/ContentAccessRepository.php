<?php

namespace Crm\SubscriptionsModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;

class ContentAccessRepository extends Repository
{
    protected $tableName = 'content_access';

    public function all()
    {
        return $this->getTable()->order('sorting');
    }

    public function add($name, $description, $class = '', $sorting = 100)
    {
        return $this->getTable()->insert([
            'name' => $name,
            'description' => $description,
            'class' => $class,
            'sorting' => $sorting,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ]);
    }

    public function exists($name)
    {
        return $this->getTable()->where(['name' => $name])->count('*') > 0;
    }

    public function hasAccess(IRow $subscriptionType, $name)
    {
        return $this->getDatabase()->table('subscription_type_content_access')
            ->where([
                'subscription_type_id' => $subscriptionType->id,
                'content_access.name' => $name
            ])
            ->count('*') > 0;
    }

    public function addAccess(IRow $subscriptionType, $name)
    {
        $this->getDatabase()->table('subscription_type_content_access')->insert([
            'subscription_type_id' => $subscriptionType->id,
            'content_access_id' => $this->getId($name),
            'created_at' => new DateTime(),
        ]);
    }

    public function removeAccess(IRow $subscriptionType, $name)
    {
        $this->getDatabase()->table('subscription_type_content_access')->where([
            'subscription_type_id' => $subscriptionType->id,
            'content_access_id' => $this->getId($name),
        ])->delete();
    }

    public function getId($name)
    {
        return $this->getTable()->select('id')->where(['name' => $name])->limit(1)->fetch()->id;
    }

    /**
     * @param $contentAccess
     * @param DateTime $startTime
     * @param DateTime $endTime
     * @return \Nette\Database\Table\Selection
     */
    public function usersWithAccessActiveBetween($contentAccess, DateTime $startTime, DateTime $endTime)
    {
        return $this->database->table('users')
            ->where(':subscriptions.subscription_type:subscription_type_content_access.id = ?', $contentAccess->id)
            ->where(':access_tokens.last_used_at > ?', $startTime)
            ->where(':access_tokens.last_used_at < ?', $endTime)
            ->where('users.active = ?', true)
            ->group('users.id');
    }
}
