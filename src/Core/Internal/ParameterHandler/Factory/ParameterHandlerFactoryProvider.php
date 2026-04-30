<?php

declare(strict_types=1);

namespace Retrofit\Core\Internal\ParameterHandler\Factory;

use Retrofit\Core\Attribute\Body;
use Retrofit\Core\Attribute\Field;
use Retrofit\Core\Attribute\FieldMap;
use Retrofit\Core\Attribute\Header;
use Retrofit\Core\Attribute\HeaderMap;
use Retrofit\Core\Attribute\ParameterAttribute;
use Retrofit\Core\Attribute\Part;
use Retrofit\Core\Attribute\PartMap;
use Retrofit\Core\Attribute\Path;
use Retrofit\Core\Attribute\Query;
use Retrofit\Core\Attribute\QueryMap;
use Retrofit\Core\Attribute\QueryName;
use Retrofit\Core\Attribute\Url;
use Retrofit\Core\Internal\ConverterProvider;

/**
 * @internal
 */
readonly class ParameterHandlerFactoryProvider
{
    /** @var array<string, AbstractParameterHandlerFactory<ParameterAttribute>> */
    private array $attributeNameToFactory;

    public function __construct(ConverterProvider $converterProvider)
    {
        $this->attributeNameToFactory = [
            Body::class => new BodyParameterHandlerFactory($converterProvider),
            Field::class => new FieldParameterHandlerFactory($converterProvider),
            FieldMap::class => new FieldMapParameterHandlerFactory($converterProvider),
            Header::class => new HeaderParameterHandlerFactory($converterProvider),
            HeaderMap::class => new HeaderMapParameterHandlerFactory($converterProvider),
            Part::class => new PartParameterHandlerFactory($converterProvider),
            PartMap::class => new PartMapParameterHandlerFactory($converterProvider),
            Path::class => new PathParameterHandlerFactory($converterProvider),
            Query::class => new QueryParameterHandlerFactory($converterProvider),
            QueryMap::class => new QueryMapParameterHandlerFactory($converterProvider),
            QueryName::class => new QueryNameParameterHandlerFactory($converterProvider),
            Url::class => new UrlParameterHandlerFactory($converterProvider),
        ];
    }

    /** @return AbstractParameterHandlerFactory<ParameterAttribute> */
    public function get(string $attributeName): AbstractParameterHandlerFactory
    {
        return $this->attributeNameToFactory[$attributeName];
    }
}
