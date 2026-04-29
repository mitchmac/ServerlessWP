# PHP URI Template

This is a URI Template implementation in PHP based on [RFC 6570 URI Template](http://tools.ietf.org/html/rfc6570). In addition to URI expansion, it also supports URI extraction (used by [Google Cloud Core](https://github.com/googleapis/google-cloud-php-core) and [Google Cloud Client Library](https://github.com/googleapis/google-cloud-php)).

![CI](https://github.com/rize/UriTemplate/workflows/CI/badge.svg) [![Total Downloads](https://poser.pugx.org/rize/uri-template/downloads)](https://packagist.org/packages/rize/uri-template) [![Latest Stable Version](https://poser.pugx.org/rize/uri-template/v)](https://packagist.org/packages/rize/uri-template) [![PHP Version Require](https://poser.pugx.org/rize/uri-template/require/php)](https://packagist.org/packages/rize/uri-template)

> [!NOTE]
>
> Due to the deprecation of implictly nullable parameter types in [PHP 8.4](https://wiki.php.net/rfc/deprecate-implicitly-nullable-types), we must introduce breaking change by adding explicit nullable types (`?T`) which requires PHP 7.1+.
>
> As a result, version [0.4.0](https://github.com/rize/UriTemplate/releases/tag/0.4.0) and later will no longer support PHP versions below 8.1.

## Usage

### Expansion

A very simple usage (string expansion).

```php
<?php

use Rize\UriTemplate;

$uri = new UriTemplate();
$uri->expand('/{username}/profile', ['username' => 'john']);

>> '/john/profile'
```

`Rize\UriTemplate` supports all `Expression Types` and `Levels` specified by RFC6570.

```php
<?php

use Rize\UriTemplate;

$uri = new UriTemplate();
$uri->expand('/search/{term:1}/{term}/{?q*,limit}', [
    'term'  => 'john',
    'q'     => ['a', 'b'],
    'limit' => 10,
])

>> '/search/j/john/?q=a&q=b&limit=10'
```

#### `/` Path segment expansion

```php
<?php

use Rize\UriTemplate;

$uri = new UriTemplate();
$uri->expand('http://{host}{/segments*}/{file}{.extensions*}', [
    'host'       => 'www.host.com',
    'segments'   => ['path', 'to', 'a'],
    'file'       => 'file',
    'extensions' => ['x', 'y'],
]);

>> 'http://www.host.com/path/to/a/file.x.y'
```

`Rize\UriTemplate` accepts `base-uri` as a 1st argument and `default params` as a 2nd argument. This is very useful when you're working with API endpoint.

Take a look at real world example.

```php
<?php

use Rize\UriTemplate;

$uri = new UriTemplate('https://api.twitter.com/{version}', ['version' => 1.1]);
$uri->expand('/statuses/show/{id}.json', ['id' => '210462857140252672']);

>> https://api.twitter.com/1.1/statuses/show/210462857140252672.json
```

### Extraction

It also supports URI Extraction (extract all variables from URI). Let's take a look at the example.

```php
<?php

use Rize\UriTemplate;

$uri = new UriTemplate('https://api.twitter.com/{version}', ['version' => 1.1]);

$params = $uri->extract('/search/{term:1}/{term}/{?q*,limit}', '/search/j/john/?q=a&q=b&limit=10');

>> print_r($params);
(
    [term:1] => j
    [term] => john
    [q] => Array
        (
            [0] => a
            [1] => b
        )

    [limit] => 10
)
```

Note that in the example above, result returned by `extract` method has an extra keys named `term:1` for `prefix` modifier. This key was added just for our convenience to access prefix data.

#### `strict` mode

```php
<?php

use Rize\UriTemplate;

$uri = new UriTemplate();
$uri->extract($template, $uri, $strict = false)
```

Normally `extract` method will try to extract vars from a uri even if it's partially matched. For example

```php
<?php

use Rize\UriTemplate;

$uri = new UriTemplate();
$params = $uri->extract('/{?a,b}', '/?a=1')

>> print_r($params);
(
    [a] => 1
    [b] => null
)
```

With `strict mode`, it will allow you to extract uri only when variables in template are fully matched with given uri.

Which is useful when you want to determine whether the given uri is matched against your template or not (in case you want to use it as routing service).

```php
<?php

use Rize\UriTemplate;

$uri = new UriTemplate();

// Note that variable `b` is absent in uri
$params = $uri->extract('/{?a,b}', '/?a=1', true);

>>> null

// Now we give `b` some value
$params = $uri->extract('/{?a,b}', '/?a=1&b=2', true);

>>> print_r($params)
(
  [a] => 1
  [b] => 2
)
```

#### Array modifier `%`

By default, RFC 6570 only has 2 types of operators `:` and `*`. This `%` array operator was added to the library because current spec can't handle array style query e.g. `list[]=a` or `key[user]=john`.

Example usage for `%` modifier

```php
<?php

$uri->expand('{?list%,keys%}', [
    'list' => [
        'a', 'b',
    ),
    'keys' => [
        'a' => 1,
        'b' => 2,
    ),
]);

// '?list[]=a&list[]=b&keys[a]=1&keys[b]=2'
>> '?list%5B%5D=a&list%5B%5D=b&keys%5Ba%5D=1&keys%5Bb%5D=2'

// [] get encoded to %5B%5D i.e. '?list[]=a&list[]=b&keys[a]=1&keys[b]=2'
$params = $uri->extract('{?list%,keys%}', '?list%5B%5D=a&list%5B%5D=b&keys%5Ba%5D=1&keys%5Bb%5D=2', )

>> print_r($params);
(
    [list] => Array
        (
            [0] => a
            [1] => b
        )

    [keys] => Array
        (
            [a] => 1
            [b] => 2
        )
)
```

## Installation

Using `composer`

```
{
    "require": {
        "rize/uri-template": "~0.3"
    }
}
```

### Changelogs

* **0.2.0** Add a new modifier `%` which allows user to use `list[]=a&list[]=b` query pattern.
* **0.2.1** Add nested array support for `%` modifier
* **0.2.5** Add strict mode support for `extract` method
* **0.3.0** Improve code quality + RFC3986 support for `extract` method by @Maks3w
* **0.3.1** Improve `extract` method to parse two or more adjacent variables separated by dot by @corleonis
* **0.4.0** Fixes the deprecation of implicitly nullable parameter types introduced in PHP 8.4. This version requires PHP 8.1 or later.

## Contributors

### Code Contributors

This project exists thanks to all the people who contribute. [[Contribute](CONTRIBUTING.md)].
<a href="https://github.com/rize/UriTemplate/graphs/contributors"><img src="https://opencollective.com/rize-uri-template/contributors.svg?width=890&button=false" /></a>

### Financial Contributors

Become a financial contributor and help us sustain our community. [[Contribute](https://opencollective.com/rize-uri-template/contribute)]

#### Individuals

<a href="https://opencollective.com/rize-uri-template"><img src="https://opencollective.com/rize-uri-template/individuals.svg?width=890"></a>

#### Organizations

Support this project with your organization. Your logo will show up here with a link to your website. [[Contribute](https://opencollective.com/rize-uri-template/contribute)]

<a href="https://opencollective.com/rize-uri-template/organization/0/website"><img src="https://opencollective.com/rize-uri-template/organization/0/avatar.svg"></a>
<a href="https://opencollective.com/rize-uri-template/organization/1/website"><img src="https://opencollective.com/rize-uri-template/organization/1/avatar.svg"></a>
<a href="https://opencollective.com/rize-uri-template/organization/2/website"><img src="https://opencollective.com/rize-uri-template/organization/2/avatar.svg"></a>
<a href="https://opencollective.com/rize-uri-template/organization/3/website"><img src="https://opencollective.com/rize-uri-template/organization/3/avatar.svg"></a>
<a href="https://opencollective.com/rize-uri-template/organization/4/website"><img src="https://opencollective.com/rize-uri-template/organization/4/avatar.svg"></a>
<a href="https://opencollective.com/rize-uri-template/organization/5/website"><img src="https://opencollective.com/rize-uri-template/organization/5/avatar.svg"></a>
<a href="https://opencollective.com/rize-uri-template/organization/6/website"><img src="https://opencollective.com/rize-uri-template/organization/6/avatar.svg"></a>
<a href="https://opencollective.com/rize-uri-template/organization/7/website"><img src="https://opencollective.com/rize-uri-template/organization/7/avatar.svg"></a>
<a href="https://opencollective.com/rize-uri-template/organization/8/website"><img src="https://opencollective.com/rize-uri-template/organization/8/avatar.svg"></a>
<a href="https://opencollective.com/rize-uri-template/organization/9/website"><img src="https://opencollective.com/rize-uri-template/organization/9/avatar.svg"></a>
