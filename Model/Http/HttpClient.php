<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Model\Http;

/**
 * Minimal HTTP client for the providers' OAuth2 endpoints (JSON responses).
 */
class HttpClient
{
    private const TIMEOUT = 15;
    private const CONNECT_TIMEOUT = 5;

    /**
     * @param array<string, scalar> $fields
     * @return array<string, mixed>
     * @throws HttpException
     */
    public function postForm(string $url, array $fields): array
    {
        return $this->request($url, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);
    }

    /**
     * @param array<string, scalar> $query
     * @return array<string, mixed>
     * @throws HttpException
     */
    public function get(string $url, array $query = [], ?string $bearerToken = null): array
    {
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $headers = ['Accept: application/json'];
        if ($bearerToken !== null) {
            $headers[] = 'Authorization: Bearer ' . $bearerToken;
        }

        return $this->request($url, [CURLOPT_HTTPGET => true, CURLOPT_HTTPHEADER => $headers]);
    }

    /**
     * @param array<int, mixed> $options
     * @return array<string, mixed>
     */
    private function request(string $url, array $options): array
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        $handle = curl_init($url);
        curl_setopt_array($handle, $options + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        unset($handle);

        if ($body === false) {
            throw new HttpException(sprintf('Connection error to %s: %s', $host, $error));
        }

        $data = json_decode((string) $body, true);

        if ($status < 200 || $status >= 300) {
            $code = $this->errorCode($data);
            throw new HttpException(
                sprintf('HTTP %d from %s%s', $status, $host, $code !== '' ? ' (' . $code . ')' : ''),
                $status
            );
        }

        if (!is_array($data)) {
            throw new HttpException(sprintf('Non-JSON response from %s', $host));
        }

        return $data;
    }

    /**
     * Extracts only the OAuth error code (e.g. "invalid_grant"), never the whole body.
     */
    private function errorCode(mixed $data): string
    {
        if (!is_array($data) || !isset($data['error'])) {
            return '';
        }

        $error = $data['error'];
        $code = '';
        if (is_string($error)) {
            $code = $error;
        } elseif (is_array($error) && isset($error['type']) && is_scalar($error['type'])) {
            $code = (string) $error['type'];
        }

        return substr((string) preg_replace('/[^A-Za-z0-9_.-]/', '', $code), 0, 64);
    }
}
