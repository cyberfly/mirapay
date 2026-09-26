<?php

namespace Tests\Support;

use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\StripeClient;

/**
 * Stands in for Stripe's HTTP layer so tests exercise the real StripeClient without any network.
 */
class FakeStripeHttpClient implements ClientInterface
{
    /** @var array<string, array{0: int, 1: array<string, mixed>}> */
    protected array $responses = [];

    /** @var list<array{method: string, path: string, params: array<string, mixed>}> */
    public array $requests = [];

    public static function install(): static
    {
        $fake = new static;

        ApiRequestor::setHttpClient($fake);
        app()->bind(StripeClient::class, fn () => new StripeClient('sk_test_fake'));

        return $fake;
    }

    public static function uninstall(): void
    {
        ApiRequestor::setHttpClient(null);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function respond(string $method, string $path, array $body, int $status = 200): static
    {
        $this->responses[strtoupper($method).' '.$path] = [$status, $body];

        return $this;
    }

    /**
     * @return list<array{method: string, path: string, params: array<string, mixed>}>
     */
    public function requestsTo(string $method, string $path): array
    {
        return array_values(array_filter(
            $this->requests,
            fn (array $request): bool => $request['method'] === strtoupper($method) && $request['path'] === $path,
        ));
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $path = parse_url($absUrl, PHP_URL_PATH);
        $key = strtoupper($method).' '.$path;

        $this->requests[] = ['method' => strtoupper($method), 'path' => $path, 'params' => $params ?? []];

        [$status, $body] = $this->responses[$key]
            ?? throw new \LogicException("Unexpected Stripe request: {$key}");

        return [json_encode($body), $status, []];
    }
}
