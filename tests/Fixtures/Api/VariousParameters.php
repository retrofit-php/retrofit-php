<?php

declare(strict_types=1);

namespace Retrofit\Tests\Fixtures\Api;

use Retrofit\Core\Attribute\GET;
use Retrofit\Core\Attribute\Path;
use Retrofit\Core\Attribute\Query;
use Retrofit\Core\Attribute\Response\ResponseBody;
use Retrofit\Core\Call;

interface VariousParameters
{
    #[GET('/users/{id}')]
    #[ResponseBody('void')]
    public function defaultValue(#[Path('id')] int $id = 100): Call;

    #[GET('/v1/personFields?limit=1000')]
    #[ResponseBody('void')]
    public function defaultQueryValue(#[Query('offset')] int $offset = 100): Call;

    #[GET('/v1/personFields?limit=1000')]
    #[ResponseBody('void')]
    public function nullableDefaultQueryValue(#[Query('offset')] ?int $offset = 100): Call;

    #[GET('/users/{id}')]
    #[ResponseBody('void')]
    public function passedByReference(#[Path('id')] int &$id): Call;

    #[GET('/users')]
    #[ResponseBody('void')]
    public function variadic(#[Query('ids')] int ...$ids): Call;
}
