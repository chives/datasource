<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FSi\Component\DataSource\Driver;

use FSi\Component\DataSource\Exception\DataSourceException;
use FSi\Component\DataSource\Field\FieldExtensionInterface;
use FSi\Component\DataSource\Field\FieldTypeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class DriverAbstractExtension implements DriverExtensionInterface, EventSubscriberInterface
{
    /**
     * @var array
     */
    private $fieldTypes;

    /**
     * @var array
     */
    private $fieldTypesExtensions;

    public static function getSubscribedEvents()
    {
        return [];
    }

    public function hasFieldType($type)
    {
        if (!isset($this->fieldTypes)) {
            $this->initFieldsTypes();
        }

        return isset($this->fieldTypes[$type]);
    }

    public function getFieldType($type)
    {
        if (!isset($this->fieldTypes)) {
            $this->initFieldsTypes();
        }

        if (!isset($this->fieldTypes[$type])) {
            throw new DataSourceException(sprintf('Field with type "%s" can\'t be loaded.', $type));
        }

        return $this->fieldTypes[$type];
    }

    public function hasFieldTypeExtensions($type)
    {
        if (!isset($this->fieldTypesExtensions)) {
            $this->initFieldTypesExtensions();
        }

        return isset($this->fieldTypesExtensions[$type]);
    }

    public function getFieldTypeExtensions($type)
    {
        if (!isset($this->fieldTypesExtensions)) {
            $this->initFieldTypesExtensions();
        }

        if (!isset($this->fieldTypesExtensions[$type])) {
            throw new DataSourceException(sprintf('Field extensions with type "%s" can\'t be loaded.', $type));
        }

        return $this->fieldTypesExtensions[$type];
    }

    public function loadSubscribers()
    {
        return [$this];
    }

    protected function loadFieldTypesExtensions()
    {
        return [];
    }

    protected function loadFieldTypes()
    {
        return [];
    }

    /**
     * Initializes every field type in extension.
     *
     * @throws DataSourceException
     */
    private function initFieldsTypes()
    {
        $this->fieldTypes = [];

        $fieldTypes = $this->loadFieldTypes();

        foreach ($fieldTypes as $fieldType) {
            if (!$fieldType instanceof FieldTypeInterface) {
                throw new DataSourceException(sprintf(
                    'Expected instance of %s, "%s" given.',
                    FieldTypeInterface::class,
                    get_class($fieldType)
                ));
            }

            if (isset($this->fieldTypes[$fieldType->getType()])) {
                throw new DataSourceException(
                    sprintf('Error during field types loading. Name "%s" already in use.', $fieldType->getType())
                );
            }

            $this->fieldTypes[$fieldType->getType()] = $fieldType;
        }
    }

    /**
     * Initializes every field extension if extension.
     *
     * @throws DataSourceException
     */
    private function initFieldTypesExtensions()
    {
        $fieldTypesExtensions = $this->loadFieldTypesExtensions();
        foreach ($fieldTypesExtensions as $extension) {
            if (!$extension instanceof FieldExtensionInterface) {
                throw new DataSourceException(sprintf(
                    "Expected instance of %s but %s got",
                    FieldExtensionInterface::class,
                    get_class($extension)
                ));
            }

            $types = $extension->getExtendedFieldTypes();
            foreach ($types as $type) {
                if (!isset($this->fieldTypesExtensions)) {
                    $this->fieldTypesExtensions[$type] = [];
                }
                $this->fieldTypesExtensions[$type][] = $extension;
            }
        }
    }
}
