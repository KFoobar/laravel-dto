# Basic Data Transfer Object for Laravel

Basic Data Transfer Object for Laravel 5.5+

## Installation

You can install the package via composer:

```
$ composer require kfoobar/laravel-dto
```

## Basic Usage

First, create a class that extends KFoobar\LaravelData\Objects\DataTransferObject:

```
class PostData extends DataTransferObject
{
    public int $id;
    public string $title;
    public string $content;
}
```

You can then initiate and populate your object using the following methods:

```
$postData = new PostData::fromArray([...]);
$postData = new PostData::fromRequest($request);
$postData = new PostData::fromModel($post);
```

## Contributing

Contributions are welcome!

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
