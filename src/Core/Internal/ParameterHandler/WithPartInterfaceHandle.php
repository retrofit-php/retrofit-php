<?php

declare(strict_types=1);

namespace Retrofit\Core\Internal\ParameterHandler;

use Ouzo\Utilities\Arrays;
use Retrofit\Core\Internal\RequestBuilder;
use Retrofit\Core\Multipart\PartInterface;

/**
 * @internal
 */
trait WithPartInterfaceHandle
{
    private const string CONTENT_TRANSFER_ENCODING_HEADER = 'Content-Transfer-Encoding';

    public function handle(RequestBuilder $requestBuilder, PartInterface $value): void
    {
        $headers = $value->getHeaders();
        $headerNames = Arrays::mapKeys($headers, fn(string $key): string => strtolower($key));
        if (!in_array(strtolower(self::CONTENT_TRANSFER_ENCODING_HEADER), $headerNames)) {
            $headers[self::CONTENT_TRANSFER_ENCODING_HEADER] = $this->encoding->value;
        }
        $requestBuilder->addPart($value->getName(), $value->getBody(), $headers, $value->getFilename());
    }
}
