<?php

namespace Crm\SubscriptionsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Extension\ExtendLastExtension;
use Crm\SubscriptionsModule\Repository\SubscriptionExtensionMethodsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionLengthMethodsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypeContentAccess;
use Crm\SubscriptionsModule\Repository\SubscriptionTypeNamesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class ExtendLastExtensionTest extends DatabaseTestCase
{
    private SubscriptionsRepository $subscriptionsRepository;

    private ExtendLastExtension $extension;

    private ActiveRow $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension = $this->inject(ExtendLastExtension::class);
        $this->subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);
        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $this->user = $userManager->addNewUser('test@example.com');
    }

    public function tearDown(): void
    {
        // reset NOW; it affects tests run after this class
        $this->extension->setNow(null);
        $this->subscriptionsRepository->setNow(null);

        parent::tearDown();
    }

    protected function requiredRepositories(): array
    {
        return [
            SubscriptionsRepository::class,
            SubscriptionTypeContentAccess::class,
            SubscriptionTypesRepository::class,
            SubscriptionTypeNamesRepository::class,
            SubscriptionExtensionMethodsRepository::class,
            SubscriptionLengthMethodsRepository::class,
            UsersRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            SubscriptionTypeNamesSeeder::class,
            ContentAccessSeeder::class,
        ];
    }

    private function getSubscriptionType()
    {
        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

        $subscriptionTypeRow = $subscriptionTypeBuilder
            ->createNew()
            // use only seeded accesses Crm\SubscriptionsModule\Seeders\ContentAccessSeeder
            ->setContentAccessOption('web')
            ->setNameAndUserLabel(random_int(0, 9999))
            ->setActive(1)
            ->setPrice(1)
            ->setLength(30)
            ->save();

        return $subscriptionTypeRow;
    }

    private function getDifferentSubscriptionType()
    {
        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

        $subscriptionTypeRow = $subscriptionTypeBuilder
            ->createNew()
            // use only seeded accesses Crm\SubscriptionsModule\Seeders\ContentAccessSeeder
            ->setContentAccessOption('web', 'print')
            ->setNameAndUserLabel(random_int(0, 9999))
            ->setActive(1)
            ->setPrice(1)
            ->setLength(30)
            ->save();

        return $subscriptionTypeRow;
    }

    private function addSubscription(ActiveRow $subscriptionType, DateTime $from, DateTime $to)
    {
        $this->subscriptionsRepository->add(
            $subscriptionType,
            false,
            true,
            $this->user,
            SubscriptionsRepository::TYPE_REGULAR,
            $from,
            $to
        );
    }

    public function testNoSubscription()
    {
        $nowDate = DateTime::from('2021-02-01');
        $this->extension->setNow($nowDate);

        $subscriptionType = $this->getSubscriptionType();

        $result = $this->extension->getStartTime($this->user, $subscriptionType);

        $this->assertEquals($nowDate, $result->getDate());
        $this->assertFalse($result->isExtending());
    }

    // same as ExtendActualExtensionTest::testActualSubscription
    public function testActualSubscription()
    {
        $nowDate = DateTime::from('2021-02-01');
        $this->extension->setNow($nowDate);
        $this->subscriptionsRepository->setNow($nowDate);

        $subscriptionType = $this->getSubscriptionType();
        $this->addSubscription($subscriptionType, $nowDate->modifyClone('-5 days'), $nowDate->modifyClone('+25 days'));

        $result = $this->extension->getStartTime($this->user, $subscriptionType);

        $this->assertEquals($nowDate->modifyClone('+25 days'), $result->getDate());
        $this->assertTrue($result->isExtending());
    }

    // same as ExtendActualExtensionTest::testExpiredSubscription
    public function testExpiredSubscription()
    {
        $nowDate = DateTime::from('2021-02-01');
        $this->extension->setNow($nowDate);
        $this->subscriptionsRepository->setNow($nowDate);

        $subscriptionType = $this->getSubscriptionType();
        $this->addSubscription($subscriptionType, $nowDate->modifyClone('-35 days'), $nowDate->modifyClone('-5 days'));

        $result = $this->extension->getStartTime($this->user, $subscriptionType);

        $this->assertEquals($nowDate, $result->getDate());
        $this->assertFalse($result->isExtending());
    }

    // different than ExtendActualExtensionTest::testLastActualSubscription
    public function testLastActualSubscription()
    {
        $nowDate = DateTime::from('2021-02-01');
        $this->extension->setNow($nowDate);
        $this->subscriptionsRepository->setNow($nowDate);

        $subscriptionType = $this->getSubscriptionType();
        $subscriptionTypeDifferent = $this->getDifferentSubscriptionType();
        $this->addSubscription($subscriptionType, $nowDate->modifyClone('-5 days'), $nowDate->modifyClone('+25 days'));
        $this->addSubscription($subscriptionTypeDifferent, $nowDate->modifyClone('-10 days'), $nowDate->modifyClone('+20 days'));
        // this subscription will be extended; we ignore gaps and search for last one
        $this->addSubscription($subscriptionType, $nowDate->modifyClone('+180 days'), $nowDate->modifyClone('+210 days'));

        $result = $this->extension->getStartTime($this->user, $subscriptionType);

        $this->assertEquals($nowDate->modifyClone('+210 days'), $result->getDate());
        $this->assertTrue($result->isExtending());
    }

    public function testFutureSubscription()
    {
        $nowDate = DateTime::from('2021-02-01');
        $this->extension->setNow($nowDate);
        $this->subscriptionsRepository->setNow($nowDate);

        $subscriptionType = $this->getSubscriptionType();
        $this->addSubscription($subscriptionType, $nowDate->modifyClone('+180 days'), $nowDate->modifyClone('+210 days'));

        $result = $this->extension->getStartTime($this->user, $subscriptionType);

        $this->assertEquals($nowDate->modifyClone('+210 days'), $result->getDate());
        $this->assertTrue($result->isExtending());
    }

    public function testSubscriptionDifferentContentTypes()
    {
        $nowDate = DateTime::from('2021-02-01');
        $this->extension->setNow($nowDate);
        $this->subscriptionsRepository->setNow($nowDate);

        $subscriptionType = $this->getSubscriptionType();
        $this->addSubscription($subscriptionType, $nowDate->modifyClone('+180 days'), $nowDate->modifyClone('+210 days'));

        $subscriptionTypeDifferent = $this->getDifferentSubscriptionType();
        $result = $this->extension->getStartTime($this->user, $subscriptionTypeDifferent);

        $this->assertEquals($nowDate->modifyClone('+210 days'), $result->getDate());
        $this->assertTrue($result->isExtending());
    }
}
