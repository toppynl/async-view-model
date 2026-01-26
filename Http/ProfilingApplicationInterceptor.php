<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Http;

use Amp\Cancellation;
use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Toppy\AsyncViewModel\Profiler\HttpClientProfilerInterface;

/**
 * AmPHP HTTP client interceptor that profiles all requests.
 */
final class ProfilingApplicationInterceptor implements ApplicationInterceptor
{
    public function __construct(
        private readonly HttpClientProfilerInterface $profiler,
    ) {}

    /**
     * @throws \Random\RandomException When random_bytes fails
     * @throws \Throwable When the HTTP request fails
     */
    #[\Override]
    public function request(Request $request, Cancellation $cancellation, DelegateHttpClient $httpClient): Response
    {
        $requestId = bin2hex(random_bytes(8));

        // Extract headers for profiling
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        $this->profiler->start($requestId, $request->getMethod(), (string) $request->getUri(), $headers);

        try {
            $response = $httpClient->request($request, $cancellation);

            // Extract response headers
            $responseHeaders = [];
            foreach ($response->getHeaders() as $name => $values) {
                $responseHeaders[$name] = implode(', ', $values);
            }

            // Get body size from Content-Length header if available
            $bodySize = (int) ($response->getHeader('Content-Length') ?? 0);

            $this->profiler->finish($requestId, $response->getStatus(), $responseHeaders, $bodySize);

            return $response;
        } catch (\Throwable $e) {
            $this->profiler->fail($requestId, $e);

            throw $e;
        }
    }
}
