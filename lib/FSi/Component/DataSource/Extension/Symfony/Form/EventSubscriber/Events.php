<?php

/*
 * This file is part of the FSi Component package.
 *
 * (c) Szczepan Cieslik <szczepan@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FSi\Component\DataSource\Extension\Symfony\Form\EventSubscriber;

use FSi\Component\DataSource\Field\FieldViewInterface;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use FSi\Component\DataSource\DataSourceInterface;
use FSi\Component\DataSource\Event\DataSourceEvents;
use FSi\Component\DataSource\Event\DataSourceEvent;
use FSi\Component\DataSource\Exception\DataSourceException;
use FSi\Component\DataSource\Extension\Symfony\Form\FormExtension;
use FSi\Component\DataSource\Extension\Symfony\Form\Field\FormFieldExtension;
use FSi\Component\DataSource\Field\FieldTypeInterface;

/**
 * Class contains method called during DataSource events.
 */
class Events implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            DataSourceEvents::POST_BUILD_VIEW => array('postBuildView'),
        );
    }

    public function postBuildView(DataSourceEvent\ViewEventArgs $event)
    {
        $fieldViews = $event->getView()->getFields();

        $positive = array();
        $negative = array();
        $neutral = array();

        $indexedViews = array();
        foreach ($fieldViews as $fieldView) {
            $field = $event->getDataSource()->getField($fieldView->getName());
            if ($field->hasOption('form_order')) {
                if (($order = $field->getOption('form_order')) >= 0) {
                    $positive[$field->getName()] = $order;
                } else {
                    $negative[$field->getName()] = $order;
                }
                $indexedViews[$field->getName()] = $fieldView;
            } else {
                $neutral[] = $fieldView;
            }
        }
        asort($positive);
        asort($negative);

        $fieldViews = array();
        foreach ($negative as $name => $order) {
            $fieldViews[] = $indexedViews[$name];
        }

        $fieldViews = array_merge($fieldViews, $neutral);
        foreach ($positive as $name => $order) {
            $fieldViews[] = $indexedViews[$name];
        }

        $event->getView()->setFields($fieldViews);
    }
}
