<?php

namespace KFoobar\Data;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

abstract class DataTransferObject
{
    /**
     * Constructs a new instance.
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->collectProperties($data);
    }

    /**
     * Gets the specified key.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function __get($key)
    {
        return property_exists($this, $key)
            ? $this->{$key}
            : null;
    }

    /**
     * Sets the specified key.
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value)
    {
        if (!property_exists($this, $key)) {
            return;
        }

        $property = $this->getProperty($key);

        $this->{$key} = $this->setPropertyType(
            $property->getType()->getName(),
            $value
        );
    }

    /**
     * Converts from model.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return \KFoobar\Data\DataTransferObject
     */
    public static function fromModel(Model $model)
    {
        return new static($model->toArray());
    }

    /**
     * Converts from request.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \KFoobar\Data\DataTransferObject
     */
    public static function fromRequest(Request $request)
    {
        return new static($request->all());
    }

    /**
     * Converts from array.
     *
     * @param array $data
     *
     * @return \KFoobar\Data\DataTransferObject
     */
    public static function fromArray(array $data = [])
    {
        return new static($data);
    }

    /**
     * Gets the property.
     *
     * @param string $name
     *
     * @return \ReflectionProperty
     */
    protected function getProperty(string $name)
    {
        return (new \ReflectionClass(static::class))
            ->getProperty($name);
    }

    /**
     * Gets the properties.
     *
     * @return array
     */
    protected function getProperties()
    {
        return (new \ReflectionClass(static::class))
            ->getProperties(\ReflectionProperty::IS_PUBLIC);
    }

    /**
     * Collects property values from given array.
     *
     * @param array $data
     */
    protected function collectProperties(array $data = [])
    {
        foreach ($this->getProperties() as $property) {
            $propertyName = $property->getName();
            $propertyType = $property->getType();
            $propertyValue = $data[$propertyName] ?? null;

            $this->{$propertyName} = !is_null($propertyValue) && $property->hasType()
                ? $this->setPropertyType($propertyType->getName(), $data[$propertyName])
                : null;
        }
    }

    /**
     * Handles type casting given typen.
     *
     * @param null|string $type
     * @param mixed       $value
     *
     * @return mixed
     */
    protected function setPropertyType(?string $type, $value = null)
    {
        switch ($type) {
            case 'int':
                return intval($value);
            case 'float':
                return floatval($value);
            case 'string':
                return strval($value);
            case 'bool':
                return boolval($value);
            case 'object':
                return (object)$value;
            case 'bool':
                return (array)$value;
            case 'date':
                return Carbon::parse($value);
            default:
                return $value;
        }
    }
}
