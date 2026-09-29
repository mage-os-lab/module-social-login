<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Model;

final class PendingLogin
{
    /**
     * @param string|null $claimedEmail email of an EXISTING account entered in the
     *        form: linking happens only after the password sign-in to that account.
     */
    public function __construct(
        public readonly Profile $profile,
        public readonly string $returnUrl,
        public readonly ?string $claimedEmail
    ) {
    }
}
