<?php

declare(strict_types=1);

require_once __DIR__ . '/StellantisProvider.php';

final class MyOpelProvider implements StellantisProvider
{
    private const OAUTH_BASE_URL = 'https://idpcvs.opel.com/am/oauth2';
    private const API_BASE_URL = 'https://api.groupe-psa.com';
    private const REALM = 'clientsB2COpel';
    private const REDIRECT_SCHEME = 'mymopsdk';

    private string $clientId;
    private string $clientSecret;
    private string $locale;
    private string $culture;

    public function __construct(string $clientId, string $clientSecret, string $countryCode = 'DE')
    {
        $this->clientId = trim($clientId);
        $this->clientSecret = trim($clientSecret);
        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new InvalidArgumentException('MyOpel Client-ID und Client-Secret müssen in der Instanz eingetragen werden.');
        }

        $countryCode = strtoupper(trim($countryCode));
        $locales = [
            'AT' => 'de-AT',
            'BE' => 'fr-BE',
            'CH' => 'de-CH',
            'DE' => 'de-DE',
            'DK' => 'da-DK',
            'ES' => 'es-ES',
            'FI' => 'fi-FI',
            'FR' => 'fr-FR',
            'IT' => 'it-IT',
            'LU' => 'fr-LU',
            'NL' => 'nl-NL',
            'NO' => 'nb-NO',
            'PL' => 'pl-PL',
            'PT' => 'pt-PT',
            'SE' => 'sv-SE'
        ];

        $this->locale = $locales[$countryCode] ?? 'de-DE';
        $this->culture = strtolower($this->locale);
    }

    public function getName(): string
    {
        return 'MyOpel compatibility';
    }

    public function getAuthorizationUrl(): string
    {
        return self::OAUTH_BASE_URL . '/authorize?' . http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->getRedirectUri(),
            'scope' => 'openid profile email',
            'locale' => $this->locale
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function getTokenUrl(): string
    {
        return self::OAUTH_BASE_URL . '/access_token';
    }

    public function getVehiclesUrl(): string
    {
        return self::API_BASE_URL . '/connectedcar/v4/user/vehicles?' . http_build_query([
            'client_id' => $this->clientId,
            'locale' => $this->locale
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function getVehicleStatusUrl(string $vehicleId): string
    {
        return self::API_BASE_URL . '/connectedcar/v4/user/vehicles/' . rawurlencode($vehicleId) . '/status?' . http_build_query([
            'client_id' => $this->clientId,
            'locale' => $this->locale
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function getApiHeaders(string $accessToken): array
    {
        return [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/hal+json',
            'x-introspect-realm: ' . self::REALM
        ];
    }

    public function getAuthorizationCodeParameters(string $code): array
    {
        return [
            'redirect_uri' => $this->getRedirectUri(),
            'grant_type' => 'authorization_code',
            'code' => $code
        ];
    }

    public function getRefreshTokenParameters(string $refreshToken): array
    {
        return [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken
        ];
    }

    public function getBasicAuthorization(): string
    {
        return base64_encode($this->clientId . ':' . $this->clientSecret);
    }

    public static function extractAuthorizationCode(string $input): string
    {
        $input = html_entity_decode(trim($input), ENT_QUOTES | ENT_HTML5);
        if ($input === '') {
            return '';
        }

        if (!str_contains($input, '://') && !str_contains($input, '?')) {
            return $input;
        }

        $query = parse_url($input, PHP_URL_QUERY);
        if (!is_string($query)) {
            $questionMark = strpos($input, '?');
            $query = $questionMark === false ? '' : substr($input, $questionMark + 1);
        }

        parse_str($query, $parameters);
        return isset($parameters['code']) ? trim((string) $parameters['code']) : '';
    }

    private function getRedirectUri(): string
    {
        return self::REDIRECT_SCHEME . '://oauth2redirect/' . $this->culture;
    }
}
