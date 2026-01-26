<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Context;

/**
 * Immutable snapshot of route parameters.
 * Serializable for encrypted URL transport.
 *
 * @consistent-constructor
 */
readonly class RequestContext
{
    protected function __construct(
        private array $params,
        private string $requestId,
    ) {}

    public static function create(array $params, string $requestId): self
    {
        return new self($params, $requestId);
    }

    /**
     * Reconstruct context from array, preserving subclass type if valid.
     *
     * @param array{_type?: string, params?: array<string, mixed>, requestId?: string} $data
     */
    public static function fromArray(array $data): static
    {
        $params = $data['params'] ?? [];
        $requestId = $data['requestId'] ?? '';
        $type = $data['_type'] ?? null;

        // If no type or type matches current class, create directly
        if ($type === null || $type === static::class) {
            return new static($params, $requestId);
        }

        // Validate and delegate to subclass
        if (class_exists($type) && is_subclass_of($type, self::class) && method_exists($type, 'fromArray')) {
            /** @var class-string<static> $type */
            return $type::fromArray($data);
        }

        // Fallback to base class for unknown types
        return new self($params, $requestId);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function all(): array
    {
        return $this->params;
    }

    public function toArray(): array
    {
        return [
            '_type' => static::class,
            'params' => $this->params,
            'requestId' => $this->requestId,
        ];
    }
}
