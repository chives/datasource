<?php

/*
 * This file is part of the FSi Component package.
 *
 * (c) Szczepan Cieslik <szczepan@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FSi\Component\DataSource\Event\FieldEvent;

use FSi\Component\DataSource\Field\FieldTypeInterface;

/**
 * Event class for Field.
 */
class ParameterEventArgs extends FieldEventArgs
{
    /**
     * @var mixed
     */
    private $parameter;

    /**
     * Constructor.
     *
     * @param FieldTypeInterface $field
     */
    public function __construct(FieldTypeInterface $field, $parameter)
    {
        parent::__construct($field);
        $this->setParameter($parameter);
    }

    /**
     * @param mixed $parameter
     */
    public function setParameter($parameter)
    {
        $this->parameter = $parameter;
    }

    /**
     * @return mixed
     */
    public function getParameter()
    {
        return $this->parameter;
    }
}