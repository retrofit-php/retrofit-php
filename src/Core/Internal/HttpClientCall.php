<?php

declare(strict_types=1);

namespace Retrofit\Core\Internal;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Retrofit\Core\Call;
use Retrofit\Core\Callback;
use Retrofit\Core\Converter\ResponseBodyConverter;
use Retrofit\Core\HttpClient;
use Retrofit\Core\Response;
use RuntimeException;
use Throwable;
use Override;

/**
 * @internal
 */
readonly class HttpClientCall implements Call
{
    public function __construct(
        private HttpClient $httpClient,
        private RequestInterface $request,
        private ?ResponseBodyConverter $responseBodyConverter,
        private ?ResponseBodyConverter $errorBodyConverter,
    )
    {
    }

    #[Override]
    public function execute(): Response
    {
        $response = $this->httpClient->send($this->request());
        return $this->createResponse($response);
    }

    #[Override]
    public function enqueue(Callback $callback): Call
    {
        $this->httpClient->sendAsync(
            $this->request(),
            fn(ResponseInterface $response) => $callback->onResponse($this, $this->createResponse($response)),
            fn(Throwable $throwable) => $callback->onFailure($this, $throwable),
        );
        return $this;
    }

    #[Override]
    public function wait(): void
    {
        $this->httpClient->wait();
    }

    #[Override]
    public function request(): RequestInterface
    {
        return $this->request;
    }

    private function createResponse(ResponseInterface $response): Response
    {
        $code = $response->getStatusCode();
        $body = $response->getBody();

        if ($this->isSuccessfulResponse($code)) {
            try {
                if (is_null($this->responseBodyConverter)) {
                    return new Response($response, null, null);
                }

                $responseBody = $this->responseBodyConverter->convert($body);
                return new Response($response, $responseBody, null);
            } catch (Throwable $throwable) {
                throw new RuntimeException('Retrofit: Could not convert response body.', 0, $throwable);
            }
        }

        if (is_null($this->errorBodyConverter)) {
            return new Response($response, null, null);
        }

        try {
            $errorBody = $this->errorBodyConverter->convert($body);
            return new Response($response, null, $errorBody);
        } catch (Throwable $throwable) {
            throw new RuntimeException('Retrofit: Could not convert error body.', 0, $throwable);
        }
    }

    private function isSuccessfulResponse(int $code): bool
    {
        return $code >= 200 && $code < 300;
    }
}
