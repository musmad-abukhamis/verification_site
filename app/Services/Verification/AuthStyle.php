<?php

namespace App\Services\Verification;

/**
 * The header/auth shapes Nigerian verification providers use, and how to apply
 * each one to an outgoing request.
 *
 * Observed in the wild:
 *   bearer      Authorization: Bearer {token}        (IDTRA, ArewaSmart)
 *   header_key  x-api-key: {token}                   (Prembly)
 *   key_secret  api-key: {key} + api-secret: {secret} (PayVessel)
 *   body_key    {"api_key": "...", ...} in the body  (TechHub)
 *   token       Authorization: Token {token}         (VTU-style hosts)
 *
 * Header and field names are configurable per provider (`auth_config`) because
 * even providers sharing a style disagree on spelling — `x-api-key` vs
 * `X-API-KEY` vs `apikey`.
 */
class AuthStyle
{
    /**
     * style => [label, credential fields it needs, auth_config fields it uses]
     */
    public const STYLES = [
        'none' => [
            'label' => 'No authentication',
            'credentials' => [],
            'config' => [],
        ],
        'bearer' => [
            'label' => 'Authorization: Bearer {token}',
            'credentials' => ['token'],
            'config' => [],
        ],
        'token' => [
            'label' => 'Authorization: Token {token}',
            'credentials' => ['token'],
            'config' => [],
        ],
        'header_key' => [
            'label' => 'Custom header (e.g. x-api-key: {token})',
            'credentials' => ['token'],
            'config' => ['header_name', 'prefix'],
        ],
        'key_secret' => [
            'label' => 'Two headers — key + secret (PayVessel style)',
            'credentials' => ['key', 'secret'],
            'config' => ['key_header', 'secret_header'],
        ],
        'basic' => [
            'label' => 'HTTP Basic (username / password)',
            'credentials' => ['username', 'password'],
            'config' => [],
        ],
        'body_key' => [
            'label' => 'API key inside the request body (TechHub style)',
            'credentials' => ['token'],
            'config' => ['body_field'],
        ],
        'query_key' => [
            'label' => 'API key as a query-string parameter',
            'credentials' => ['token'],
            'config' => ['query_param'],
        ],
    ];

    /** Credential keys that must never be echoed back to the browser or logged. */
    public const SECRET_FIELDS = ['token', 'key', 'secret', 'password'];

    public static function has(string $style): bool
    {
        return isset(self::STYLES[$style]);
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::STYLES);
    }

    /**
     * Credential field names this style needs.
     *
     * @return array<int, string>
     */
    public static function credentialFields(string $style): array
    {
        return self::STYLES[$style]['credentials'] ?? [];
    }

    /**
     * Build the auth headers for a style.
     *
     * @param  array<string, mixed>  $credentials  decrypted
     * @param  array<string, mixed>  $config       non-secret auth knobs
     * @return array<string, string>
     */
    public static function headers(string $style, array $credentials, array $config = []): array
    {
        $token = (string) ($credentials['token'] ?? '');

        return match ($style) {
            'bearer' => $token === '' ? [] : ['Authorization' => 'Bearer '.$token],
            'token' => $token === '' ? [] : ['Authorization' => 'Token '.$token],
            // `?? ''` before `?:` matters: the admin form strips blank
            // auth_config values before saving, so a provider relying on the
            // default header name has no key here at all.
            'header_key' => $token === '' ? [] : [
                self::setting($config, 'header_name', 'x-api-key') => trim(($config['prefix'] ?? '').' '.$token),
            ],
            'key_secret' => array_filter([
                self::setting($config, 'key_header', 'api-key') => (string) ($credentials['key'] ?? ''),
                self::setting($config, 'secret_header', 'api-secret') => (string) ($credentials['secret'] ?? ''),
            ], fn ($v) => $v !== ''),
            'basic' => ['Authorization' => 'Basic '.base64_encode(
                ($credentials['username'] ?? '').':'.($credentials['password'] ?? '')
            )],
            default => [], // none | body_key | query_key carry no auth header
        };
    }

    /**
     * Extra body fields the style injects (body_key only).
     *
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    public static function bodyFields(string $style, array $credentials, array $config = []): array
    {
        if ($style !== 'body_key' || empty($credentials['token'])) {
            return [];
        }

        return [self::setting($config, 'body_field', 'api_key') => (string) $credentials['token']];
    }

    /**
     * Extra query parameters the style injects (query_key only).
     *
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    public static function queryFields(string $style, array $credentials, array $config = []): array
    {
        if ($style !== 'query_key' || empty($credentials['token'])) {
            return [];
        }

        return [self::setting($config, 'query_param', 'api_key') => (string) $credentials['token']];
    }

    /**
     * An auth_config value, falling back to the style's default when the key is
     * missing or blank.
     *
     * @param  array<string, mixed>  $config
     */
    private static function setting(array $config, string $key, string $default): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        return $value !== '' ? $value : $default;
    }

    /**
     * Whether the style has everything it needs to authenticate.
     *
     * @param  array<string, mixed>  $credentials
     */
    public static function isConfigured(string $style, array $credentials): bool
    {
        foreach (self::credentialFields($style) as $field) {
            if (empty($credentials[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * The style catalog shaped for the admin UI, so the form can show only the
     * credential and config inputs the chosen style actually uses.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forFrontend(): array
    {
        return array_values(array_map(fn (string $key) => [
            'value' => $key,
            'label' => self::STYLES[$key]['label'],
            'credentials' => self::STYLES[$key]['credentials'],
            'config' => self::STYLES[$key]['config'],
        ], self::keys()));
    }
}
