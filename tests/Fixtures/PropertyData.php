<?php

namespace KFoobar\Data\Tests\Fixtures;

use KFoobar\Data\DataTransferObject;

class PropertyData extends DataTransferObject
{
    public int $integer;
    public float $decimal;
    public string $text;
    public bool $enabled;
    public array $items;
    public object $object;
    public mixed $anything;
    public $untyped;
    public ?string $nullable;
    public string $default = 'draft';
    public ?string $nullableDefault = 'draft';
    public int $zero = 0;
    public bool $disabled = false;
    public string $empty = '';
    public array $emptyItems = [];
    public static string $shared = 'original';
    protected string $protected = 'protected';
    private string $private = 'private';

    public function internalValues(): array
    {
        return [$this->protected, $this->private];
    }
}
