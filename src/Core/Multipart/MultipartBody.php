<?php

declare(strict_types=1);

namespace Retrofit\Core\Multipart;

use Psr\Http\Message\StreamInterface;
use Override;

/**
 * @api
 */
class MultipartBody
{
    /**
     * Build a new part.
     */
    public static function Part(): PartInterface
    {
        return new readonly class () implements PartInterface {
            public function __construct(
                private string $name = '',
                private StreamInterface|string $body = '',
                /** @var array<string, string> */
                private array $headers = [],
                private ?string $filename = null,
            )
            {
            }

            #[Override]
            public function getName(): string
            {
                return $this->name;
            }

            #[Override]
            public function getBody(): StreamInterface|string
            {
                return $this->body;
            }

            #[Override]
            public function getHeaders(): array
            {
                return $this->headers;
            }

            #[Override]
            public function getFilename(): ?string
            {
                return $this->filename;
            }

            /**
             * Creates part which is used in multipart bodies.
             *
             * @param array<string, string> $headers
             */
            public static function createFromData(
                string $name,
                StreamInterface|string $body,
                array $headers = [],
                ?string $filename = null,
            ): PartInterface
            {
                return new self($name, $body, $headers, $filename);
            }
        };
    }
}
