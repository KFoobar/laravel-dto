<?php

namespace KFoobar\Data\Tests;

use ArrayIterator;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Countable;
use DateTimeImmutable;
use Error;
use Iterator;
use KFoobar\Data\DataTransferObject;
use KFoobar\Data\Tests\Fixtures\PropertyData;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;
use TypeError;

class DataTransferObjectTest extends TestCase
{
    public function test_missing_properties_preserve_defaults_and_initialization(): void
    {
        $data = PropertyData::fromArray();

        self::assertSame('draft', $data->default);
        self::assertSame('draft', $data->nullableDefault);
        self::assertSame(0, $data->zero);
        self::assertFalse($data->disabled);
        self::assertSame('', $data->empty);
        self::assertSame([], $data->emptyItems);
        self::assertNull($data->untyped);

        foreach (['integer', 'nullable', 'anything', 'items'] as $name) {
            self::assertFalse((new ReflectionProperty($data, $name))->isInitialized($data));
        }
    }

    public function test_reading_a_missing_required_property_fails_clearly(): void
    {
        $data = new PropertyData();

        $this->expectException(Error::class);
        $this->expectExceptionMessage('must not be accessed before initialization');

        $data->integer;
    }

    public function test_explicit_null_is_preserved_for_nullable_mixed_and_untyped_properties(): void
    {
        $data = new PropertyData([
            'nullable' => null,
            'nullableDefault' => null,
            'anything' => null,
            'untyped' => null,
        ]);

        foreach (['nullable', 'nullableDefault', 'anything', 'untyped'] as $name) {
            self::assertTrue((new ReflectionProperty($data, $name))->isInitialized($data));
            self::assertNull($data->{$name});
        }
    }

    #[DataProvider('nonNullableProperties')]
    public function test_explicit_null_is_rejected_for_non_nullable_properties(string $name): void
    {
        $this->expectException(TypeError::class);

        new PropertyData([$name => null]);
    }

    public static function nonNullableProperties(): array
    {
        return array_map(fn (string $name): array => [$name], [
            'integer', 'decimal', 'text', 'enabled', 'items', 'object', 'default',
        ]);
    }

    #[DataProvider('scalarValues')]
    public function test_named_types_are_cast(string $name, mixed $input, mixed $expected): void
    {
        $data = new PropertyData([$name => $input]);

        self::assertSame($expected, $data->{$name});
    }

    public static function scalarValues(): array
    {
        return [
            'numeric string to integer' => ['integer', '42', 42],
            'decimal to integer' => ['integer', 4.9, 4],
            'zero integer' => ['integer', '0', 0],
            'numeric string to float' => ['decimal', '4.5', 4.5],
            'integer to float' => ['decimal', 4, 4.0],
            'integer to string' => ['text', 42, '42'],
            'empty string' => ['text', '', ''],
            'false to string' => ['text', false, ''],
            'true boolean' => ['enabled', 1, true],
            'false boolean' => ['enabled', 0, false],
            'string zero to boolean' => ['enabled', '0', false],
            'native boolean semantics' => ['enabled', 'false', true],
            'scalar to array' => ['items', 'tag', ['tag']],
            'object to array' => ['items', (object) ['tag' => 'news'], ['tag' => 'news']],
            'preserve array' => ['items', ['tag'], ['tag']],
            'preserve empty array' => ['items', [], []],
            'nullable scalar' => ['nullable', 42, '42'],
        ];
    }

    public function test_object_cast_and_existing_object_identity(): void
    {
        $data = new PropertyData(['object' => ['title' => 'Post']]);
        self::assertInstanceOf(stdClass::class, $data->object);
        self::assertSame('Post', $data->object->title);

        $object = new stdClass();
        self::assertSame($object, (new PropertyData(['object' => $object]))->object);
    }

    #[DataProvider('untypedValues')]
    public function test_untyped_and_mixed_properties_preserve_input(mixed $value): void
    {
        $data = new PropertyData(['untyped' => $value, 'anything' => $value]);

        self::assertSame($value, $data->untyped);
        self::assertSame($value, $data->anything);
    }

    public static function untypedValues(): array
    {
        return [[null], [false], [0], [''], ['value'], [['key' => 'value']], [new stdClass()]];
    }

    public function test_unknown_non_public_and_static_properties_are_ignored(): void
    {
        $data = new PropertyData([
            'unknown' => 'value',
            'protected' => 'changed',
            'private' => 'changed',
            'shared' => 'changed',
        ]);
        $data->unknown = 'changed';
        $data->protected = 'changed';
        $data->private = 'changed';
        $data->shared = 'changed';

        self::assertFalse(property_exists($data, 'unknown'));
        self::assertSame(['protected', 'private'], $data->internalValues());
        self::assertSame('original', PropertyData::$shared);
        self::assertNull($data->unknown);
        self::assertNull($data->protected);
        self::assertNull($data->private);
        self::assertNull($data->shared);
    }

    public function test_inherited_public_properties_are_populated(): void
    {
        $data = new class(['integer' => '42', 'child' => 'value']) extends PropertyData {
            public string $child;
        };

        self::assertSame(42, $data->integer);
        self::assertSame('value', $data->child);
        self::assertSame('draft', $data->default);
    }

    public function test_readonly_properties_can_be_initialized(): void
    {
        $data = new class(['identifier' => '42']) extends DataTransferObject {
            public readonly int $identifier;
        };

        self::assertSame(42, $data->identifier);

        $this->expectException(Error::class);
        $data->identifier = 43;
    }

    public function test_magic_setter_handles_untyped_named_and_union_properties(): void
    {
        $data = new class extends DataTransferObject {
            public $untyped;
            public array $items;
            public int|string $identifier;
        };

        $data->__set('untyped', ['value']);
        $data->__set('items', 'value');
        $data->__set('identifier', '42');

        self::assertSame(['value'], $data->__get('untyped'));
        self::assertSame(['value'], $data->__get('items'));
        self::assertSame('42', $data->__get('identifier'));
    }

    #[DataProvider('unionValues')]
    public function test_union_types_use_php_assignment_rules(string $name, mixed $input, mixed $expected): void
    {
        $data = new class([$name => $input]) extends DataTransferObject {
            public int|string $identifier;
            public int|float $number;
            public bool|string $flag;
            public array|string $items;
            public int|string|null $nullable;
        };

        self::assertSame($expected, $data->{$name});
    }

    public static function unionValues(): array
    {
        return [
            'keep string' => ['identifier', '0042', '0042'],
            'keep integer' => ['identifier', 42, 42],
            'keep float' => ['number', 4.5, 4.5],
            'keep numeric integer' => ['number', 4, 4],
            'numeric integer string' => ['number', '42', 42],
            'numeric decimal string' => ['number', '4.5', 4.5],
            'boolean to integer' => ['number', true, 1],
            'keep boolean' => ['flag', false, false],
            'keep boolean string' => ['flag', 'false', 'false'],
            'keep array' => ['items', ['tag'], ['tag']],
            'keep array alternative' => ['items', 'tag', 'tag'],
            'nullable union' => ['nullable', null, null],
        ];
    }

    public function test_union_rejects_an_incompatible_value(): void
    {
        $this->expectException(TypeError::class);

        new class(['identifier' => []]) extends DataTransferObject {
            public int|string $identifier;
        };
    }

    public function test_non_nullable_union_rejects_null(): void
    {
        $this->expectException(TypeError::class);

        new class(['identifier' => null]) extends DataTransferObject {
            public int|string $identifier;
        };
    }

    public function test_class_interface_intersection_and_dnf_types_preserve_objects(): void
    {
        $iterator = new ArrayIterator(['value']);
        $data = new class(array_fill_keys(['concrete', 'contract', 'intersection', 'union'], $iterator)) extends DataTransferObject {
            public ArrayIterator $concrete;
            public Countable $contract;
            public Countable&Iterator $intersection;
            public (Countable&Iterator)|null $union;
        };

        foreach (['concrete', 'contract', 'intersection', 'union'] as $name) {
            self::assertSame($iterator, $data->{$name});
        }
    }

    public function test_intersection_rejects_an_incompatible_object(): void
    {
        $this->expectException(TypeError::class);

        new class(['value' => new stdClass()]) extends DataTransferObject {
            public Countable&Iterator $value;
        };
    }

    public function test_self_and_parent_types_use_the_declaring_class(): void
    {
        $parent = new PropertyData();
        $data = new class(['parent' => $parent]) extends PropertyData {
            public self $sibling;
            public parent $parent;
        };
        $sibling = $data::fromArray(['sibling' => $data]);

        self::assertSame($parent, $data->parent);
        self::assertSame($data, $sibling->sibling);
    }

    public function test_literal_types_are_not_coerced(): void
    {
        $data = new class(['yes' => true, 'no' => false, 'nothing' => null]) extends DataTransferObject {
            public true $yes;
            public false $no;
            public null $nothing;
        };

        self::assertTrue($data->yes);
        self::assertFalse($data->no);
        self::assertNull($data->nothing);
    }

    public function test_literal_type_rejects_an_incompatible_value(): void
    {
        $this->expectException(TypeError::class);

        new class(['yes' => 1]) extends DataTransferObject {
            public true $yes;
        };
    }

    public function test_carbon_strings_are_parsed_for_mutable_immutable_and_nullable_properties(): void
    {
        $value = '2026-10-05T12:30:00+02:00';
        $data = new class(array_fill_keys(['mutable', 'immutable', 'nullable'], $value)) extends DataTransferObject {
            public Carbon $mutable;
            public CarbonImmutable $immutable;
            public ?Carbon $nullable;
        };

        self::assertInstanceOf(Carbon::class, $data->mutable);
        self::assertInstanceOf(CarbonImmutable::class, $data->immutable);
        self::assertInstanceOf(Carbon::class, $data->nullable);
        self::assertSame($value, $data->mutable->toIso8601String());
        self::assertSame($value, $data->immutable->toIso8601String());
        self::assertSame($value, $data->nullable->toIso8601String());
    }

    public function test_carbon_preserves_existing_instances(): void
    {
        $mutable = Carbon::parse('2026-10-05T12:30:00+02:00');
        $immutable = $mutable->toImmutable();
        $data = new class(compact('mutable', 'immutable')) extends DataTransferObject {
            public Carbon $mutable;
            public CarbonImmutable $immutable;
        };

        self::assertSame($mutable, $data->mutable);
        self::assertSame($immutable, $data->immutable);
    }

    public function test_carbon_accepts_datetime_instances_and_converts_mutability(): void
    {
        $source = new DateTimeImmutable('2026-10-05T12:30:00.123456+02:00');
        $data = new class(['mutable' => $source, 'immutable' => Carbon::instance($source)]) extends DataTransferObject {
            public Carbon $mutable;
            public CarbonImmutable $immutable;
        };

        self::assertInstanceOf(Carbon::class, $data->mutable);
        self::assertInstanceOf(CarbonImmutable::class, $data->immutable);
        self::assertSame($source->format('Y-m-d H:i:s.uP'), $data->mutable->format('Y-m-d H:i:s.uP'));
        self::assertSame($source->format('Y-m-d H:i:s.uP'), $data->immutable->format('Y-m-d H:i:s.uP'));
    }

    public function test_nullable_carbon_does_not_convert_null_to_now(): void
    {
        $data = new class(['date' => null]) extends DataTransferObject {
            public ?Carbon $date;
        };

        self::assertNull($data->date);
    }

    public function test_carbon_subclasses_are_instantiated(): void
    {
        $data = new class(['date' => '2026-10-05']) extends DataTransferObject {
            public \Illuminate\Support\Carbon $date;
        };

        self::assertInstanceOf(\Illuminate\Support\Carbon::class, $data->date);
        self::assertSame('2026-10-05', $data->date->toDateString());
    }

    public function test_carbon_union_preserves_matching_strings_and_objects(): void
    {
        $data = new class(['date' => '2026-10-05']) extends DataTransferObject {
            public Carbon|string $date;
        };
        $date = Carbon::parse('2026-10-05');

        self::assertSame('2026-10-05', $data->date);
        self::assertSame($date, $data::fromArray(['date' => $date])->date);
    }

    public function test_invalid_carbon_string_throws(): void
    {
        $this->expectException(InvalidFormatException::class);

        new class(['date' => 'not-a-date']) extends DataTransferObject {
            public Carbon $date;
        };
    }

    public function test_incompatible_carbon_input_throws(): void
    {
        $this->expectException(TypeError::class);

        new class(['date' => []]) extends DataTransferObject {
            public Carbon $date;
        };
    }
}
