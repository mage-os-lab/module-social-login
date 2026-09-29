<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Model;

use Digitalway\SocialLogin\Api\ProviderInterface;

/**
 * Available providers, injected via etc/di.xml (key = provider code).
 * The di.xml order is the button order.
 */
class ProviderPool
{
    /**
     * @param array<string, ProviderInterface> $providers
     */
    public function __construct(private readonly array $providers = [])
    {
    }

    public function get(string $code): ?ProviderInterface
    {
        $provider = $this->providers[$code] ?? null;

        return $provider instanceof ProviderInterface ? $provider : null;
    }

    /**
     * @return list<ProviderInterface>
     */
    public function getEnabled(): array
    {
        return array_values(array_filter(
            $this->providers,
            static fn ($provider): bool => $provider instanceof ProviderInterface && $provider->isEnabled()
        ));
    }
}
