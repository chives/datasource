<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FSi\Component\DataSource\Tests\Driver\Doctrine\DBAL\Fixtures;

use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use FSi\Component\DataSource\Driver\DriverAbstractExtension;
use FSi\Component\DataSource\Event\DriverEvents;

/**
 * Class to test DoctrineDriver extensions calls.
 */
class DBALDriverExtension extends DriverAbstractExtension implements EventSubscriberInterface
{
    /**
     * @var array
     */
    private $calls = array();

    /**
     * @var QueryBuilder
     */
    private $queryBuilder;

    public function getExtendedDriverTypes()
    {
        return array('doctrine-dbal');
    }

    public static function getSubscribedEvents()
    {
        return array(
            DriverEvents::PRE_GET_RESULT => array('preGetResult', 128),
            DriverEvents::POST_GET_RESULT => array('postGetResult', 128),
        );
    }

    /**
     * Returns array of calls.
     *
     * @return array
     */
    public function getCalls()
    {
        return $this->calls;
    }

    /**
     * Resets calls.
     */
    public function resetCalls()
    {
        $this->calls = array();
    }

    /**
     * Catches called method.
     */
    public function __call($name, $arguments)
    {
        if ($name == 'preGetResult') {
            $args = array_shift($arguments);
            $this->queryBuilder = $args->getDriver()->getQueryBuilder();
        }
        $this->calls[] = $name;
    }

    /**
     * Loads itself as subscriber.
     *
     * @return array
     */
    public function loadSubscribers()
    {
        return array($this);
    }

    /**
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        return $this->queryBuilder;
    }
}