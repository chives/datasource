<?php

/*
 * This file is part of the FSi Component package.
 *
 * (c) Szczepan Cieslik <szczepan@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FSi\Component\DataSource\Extension\Core\Ordering\Field;

use FSi\Component\DataSource\Field\FieldAbstractExtension;
use FSi\Component\DataSource\Field\FieldTypeInterface;
use FSi\Component\DataSource\Field\FieldViewInterface;
use FSi\Component\DataSource\DataSourceViewInterface;
use FSi\Component\DataSource\Extension\Core\Ordering\OrderingExtension;
use FSi\Component\DataSource\Event\DataSourceEvents;
use FSi\Component\DataSource\Event\FieldEvents;
use FSi\Component\DataSource\Event\FieldEvent;
use FSi\Component\DataSource\Event\DataSourceFieldEventInterface;
use FSi\Component\DataSource\Extension\Core\Pagination\PaginationExtension;

/**
 * Extension for fields.
 */
class FieldExtension extends FieldAbstractExtension
{
    /**
     * @var array
     */
    private $ordering = array();

    /**
     * {@inheritdoc}
     */
    public function getExtendedFieldTypes()
    {
        return array('text', 'number', 'date', 'time', 'datetime');
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            FieldEvents::POST_BUILD_VIEW => array('postBuildView')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initOptions(FieldTypeInterface $field)
    {
        $field->getOptionsResolver()
            ->setOptional(array('default_sort_priority'))
            ->setDefaults(array(
                'default_sort' => null,
                'sortable' => true
            ))
            ->setAllowedTypes(array(
                'default_sort_priority' => 'integer',
                'sortable' => 'bool',
            ))
            ->setAllowedValues(array(
                'default_sort' => array(null, 'asc', 'desc'),
            ))
        ;
    }

    public function setOrdering(FieldTypeInterface $field, $ordering)
    {
        $field_oid = spl_object_hash($field);
        $this->ordering[$field_oid] = $ordering;
    }

    public function getOrdering(FieldTypeInterface $field)
    {
        $field_oid = spl_object_hash($field);
        if (isset($this->ordering[$field_oid]))
            return $this->ordering[$field_oid];
        else
            return null;
    }

    /**
     * {@inheritdoc}
     */
    public function postBuildView(FieldEvent\ViewEventArgs $event)
    {
        $field = $event->getField();
        $field_oid = spl_object_hash($field);
        $view = $event->getView();

        $view->setAttribute('sortable', $field->getOption('sortable'));
        if (!$field->getOption('sortable')) {
            return;
        }

        $parameters = $field->getDataSource()->getParameters();
        $dataSourceName = $field->getDataSource()->getName();

        if (isset($this->ordering[$field_oid]['direction']) && (key($parameters[$dataSourceName][OrderingExtension::PARAMETER_SORT]) == $field->getName())) {
            $view->setAttribute('sorted_ascending', $this->ordering[$field_oid]['direction'] == 'asc');
            $view->setAttribute('sorted_descending', $this->ordering[$field_oid]['direction'] == 'desc');
        } else {
            $view->setAttribute('sorted_ascending', false);
            $view->setAttribute('sorted_descending', false);
        }

        if (isset($parameters[$dataSourceName][OrderingExtension::PARAMETER_SORT][$field->getName()]))
            unset($parameters[$dataSourceName][OrderingExtension::PARAMETER_SORT][$field->getName()]);
        if (!isset($parameters[$dataSourceName][OrderingExtension::PARAMETER_SORT]))
            $parameters[$dataSourceName][OrderingExtension::PARAMETER_SORT] = array();
        // little hack: we do not know if PaginationExtension is loaded but if it is we don't want page number in sorting URLs
        unset($parameters[$dataSourceName][PaginationExtension::PARAMETER_PAGE]);
        $fields = array_keys($parameters[$dataSourceName][OrderingExtension::PARAMETER_SORT]);
        array_unshift($fields, $field->getName());
        $directions = array_values($parameters[$dataSourceName][OrderingExtension::PARAMETER_SORT]);

        $parametersAsc = $parameters;
        $directionsAsc = $directions;
        array_unshift($directionsAsc, 'asc');
        $parametersAsc[$dataSourceName][OrderingExtension::PARAMETER_SORT] = array_combine($fields, $directionsAsc);
        $view->setAttribute('parameters_sort_ascending', $parametersAsc);

        $parametersDesc = $parameters;
        $directionsDesc = $directions;
        array_unshift($directionsDesc, 'desc');
        $parametersDesc[$dataSourceName][OrderingExtension::PARAMETER_SORT] = array_combine($fields, $directionsDesc);
        $view->setAttribute('parameters_sort_descending', $parametersDesc);
    }
}
