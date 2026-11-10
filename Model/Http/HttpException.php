<?php
declare(strict_types=1);

namespace MageOS\SocialLogin\Model\Http;

/**
 * Communication error with a provider. The message never contains
 * tokens, codes or secrets: it can be logged as is.
 */
class HttpException extends \RuntimeException
{
}
