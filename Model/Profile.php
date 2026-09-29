<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Model;

/**
 * Normalized user profile returned by a social provider.
 * Immutable; stored in session via toArray()/fromArray().
 */
final class Profile
{
    public function __construct(
        public readonly string $provider,
        public readonly string $providerUserId,
        public readonly ?string $email,
        public readonly bool $emailVerified,
        public readonly string $firstname,
        public readonly string $lastname
    ) {
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email !== null && $this->emailVerified;
    }

    public function hasNames(): bool
    {
        return $this->firstname !== '' && $this->lastname !== '';
    }

    /**
     * Data entered by the user in the completion form: the email is NOT
     * verified by the provider.
     */
    public function withContactData(string $email, string $firstname, string $lastname): self
    {
        return new self($this->provider, $this->providerUserId, $email, false, $firstname, $lastname);
    }

    /**
     * @return array<string, string|bool|null>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'provider_user_id' => $this->providerUserId,
            'email' => $this->email,
            'email_verified' => $this->emailVerified,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $provider = (string) ($data['provider'] ?? '');
        $providerUserId = (string) ($data['provider_user_id'] ?? '');
        if ($provider === '' || $providerUserId === '') {
            throw new \InvalidArgumentException('Invalid social profile: missing provider or ID.');
        }

        $email = $data['email'] ?? null;

        return new self(
            $provider,
            $providerUserId,
            $email === null || $email === '' ? null : (string) $email,
            (bool) ($data['email_verified'] ?? false),
            (string) ($data['firstname'] ?? ''),
            (string) ($data['lastname'] ?? '')
        );
    }
}
