<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Context;

/**
 * Immutable snapshot of user/session state.
 * Safe to pass to background Fibers.
 */
final readonly class ViewContext
{
    private function __construct(
        private string $currency,
        private string $locale,
        private bool $isB2B,
        private bool $isVatExempt,
        private ?string $customerGroup,
        private bool $isPrivate,
    ) {}

    public static function create(
        string $currency,
        string $locale,
        bool $isB2B,
        bool $isVatExempt,
        ?string $customerGroup,
        bool $isPrivate = false,
    ): self {
        return new self($currency, $locale, $isB2B, $isVatExempt, $customerGroup, $isPrivate);
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function isB2B(): bool
    {
        return $this->isB2B;
    }

    public function isVatExempt(): bool
    {
        return $this->isVatExempt;
    }

    public function getCustomerGroup(): ?string
    {
        return $this->customerGroup;
    }

    public function isPrivate(): bool
    {
        return $this->isPrivate;
    }
}
