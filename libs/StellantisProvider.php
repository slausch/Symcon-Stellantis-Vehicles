<?php

declare(strict_types=1);

interface StellantisProvider
{
    public function getName(): string;

    public function getAuthorizationUrl(): string;

    public function getTokenUrl(): string;

    public function getVehiclesUrl(): string;

    public function getVehicleStatusUrl(string $vehicleId): string;

    /** @return array<string, string> */
    public function getApiHeaders(string $accessToken): array;

    /** @return array<string, string> */
    public function getAuthorizationCodeParameters(string $code): array;

    /** @return array<string, string> */
    public function getRefreshTokenParameters(string $refreshToken): array;

    public function getBasicAuthorization(): string;
}
