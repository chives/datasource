<?php

/*
 * This file is part of the FSi Component package.
 *
 * (c) Szczepan Cieslik <szczepan@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FSi\Component\DataSource\Event\DataSourceEvent;

use FSi\Component\DataSource\DataSourceInterface;

/**
 * Event class for DataSource.
 */
class ResultEventArgs extends DataSourceEventArgs
{
    /**
     * @var mixed
     */
    private $result;

    /**
     * Constructor.
     *
     * @param DataSourceInterface $datasource
     * @param mixed $result
     */
    public function __construct(DataSourceInterface $datasource, $result)
    {
        parent::__construct($datasource);
        $this->setResult($result);
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @param mixed $result
     */
    public function setResult($result)
    {
        $this->result = $result;
    }
}