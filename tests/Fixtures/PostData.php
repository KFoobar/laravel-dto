<?php

namespace KFoobar\Data\Tests\Fixtures;

use Carbon\CarbonImmutable;
use KFoobar\Data\DataTransferObject;

class PostData extends DataTransferObject
{
    public int $id;
    public string $title;
    public bool $enabled = false;
    public array $metadata = [];
    public ?CarbonImmutable $published_at = null;
    public array $author = [];
    public string $secret = 'hidden';
    public string $status = 'draft';
}
