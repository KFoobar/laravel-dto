<?php

namespace KFoobar\Data;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;

abstract class DataTransferObject
{
    public function __construct(array $data = [])
    {
        $this->collectProperties($data);
    }

    public function __get(string $key): mixed
    {
        if (!property_exists($this, $key)) {
            return null;
        }

        $property = $this->getProperty($key);

        return $property->isPublic() && !$property->isStatic()
            ? $property->getValue($this)
            : null;
    }

    public function __set(string $key, mixed $value): void
    {
        if (!property_exists($this, $key)) {
            return;
        }

        $property = $this->getProperty($key);

        if (!$property->isPublic() || $property->isStatic()) {
            return;
        }

        $property->setValue($this, $this->setPropertyType($property->getType(), $value));
    }

    public static function fromModel(Model $model): static
    {
        return new static($model->toArray());
    }

    public static function fromRequest(Request $request): static
    {
        return new static($request->all());
    }

    public static function fromArray(array $data = []): static
    {
        return new static($data);
    }

    protected function getProperty(string $name): ReflectionProperty
    {
        return (new ReflectionClass(static::class))->getProperty($name);
    }

    protected function getProperties(): array
    {
        return (new ReflectionClass(static::class))->getProperties(ReflectionProperty::IS_PUBLIC);
    }

    protected function collectProperties(array $data = []): void
    {
        foreach ($this->getProperties() as $property) {
            $name = $property->getName();

            if ($property->isStatic() || !array_key_exists($name, $data)) {
                continue;
            }

            $property->setValue($this, $this->setPropertyType($property->getType(), $data[$name]));
        }
    }

    protected function setPropertyType(?ReflectionType $type, mixed $value = null): mixed
    {
        // Reflection assignment applies PHP's native union and intersection type rules.
        if (!$type instanceof ReflectionNamedType || $value === null) {
            return $value;
        }

        $name = $type->getName();

        if (is_a($name, Carbon::class, true) || is_a($name, CarbonImmutable::class, true)) {
            if ($value instanceof $name) {
                return $value;
            }

            if ($value instanceof DateTimeInterface) {
                return $name::instance($value);
            }

            return is_string($value) ? $name::parse($value) : $value;
        }

        return match ($name) {
            'int' => (int) $value,
            'float' => (float) $value,
            'string' => (string) $value,
            'bool' => (bool) $value,
            'object' => (object) $value,
            'array' => (array) $value,
            default => $value,
        };
    }
}
