<?php

declare(strict_types=1);

namespace Retrofit\Client\Guzzle7;

use Closure;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Retrofit\Core\HttpClient;
use Override;

/**
 * Guzzle7 {@link HttpClient} implementation.
 *
 * @see HttpClient
 *
 * @api
 */
class Guzzle7HttpClient implements HttpClient
{
    /** @var array<int, array{promise: PromiseInterface, onResponse: Closure, onFailure: Closure}> */
    private array $requests = [];

    public function __construct(
        private readonly ClientInterface $client,
        private readonly int $concurrency = 5,
    )
    {
    }

    #[Override]
    public function send(RequestInterface $request): ResponseInterface
    {
        try {
            $response = $this->client->send($request);
        } catch (RequestException $exception) {
            $response = $exception->getResponse();
            if (is_null($response)) {
                throw $exception;
            }
        }
        return $response;
    }

    #[Override]
    public function sendAsync(RequestInterface $request, Closure $onResponse, Closure $onFailure): void
    {
        $this->requests[] = [
            'promise' => $this->client->sendAsync($request),
            'onResponse' => $onResponse,
            'onFailure' => $onFailure,
        ];
    }

    #[Override]
    public function wait(): void
    {
        if ($this->requests === []) {
            return;
        }

        $requestList = $this->requests;
        $this->requests = [];

        $requests = function () use ($requestList) {
            foreach ($requestList as $request) {
                yield fn() => $request['promise'];
            }
        };

        $pool = new Pool($this->client, $requests(), [
            'concurrency' => $this->concurrency,
            'fulfilled' => fn(ResponseInterface $response, $index) => $requestList[$index]['onResponse']($response),
            'rejected' => function ($reason, $index) use ($requestList) {
                if ($reason instanceof RequestException && !is_null($reason->getResponse())) {
                    return $requestList[$index]['onResponse']($reason->getResponse());
                }
                return $requestList[$index]['onFailure']($reason);
            },
        ]);

        $pool->promise()->wait();
    }
}
