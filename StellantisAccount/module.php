<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/MyOpelProvider.php';

class StellantisAccount extends IPSModule
{
    private const DATA_TO_CHILD = '{A02073EB-6357-438D-936A-DE8A5B431857}';
    private const VEHICLE_MODULE = '{55719996-CD7E-4825-8B64-294601469EB5}';

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyBoolean('Enabled', true);
        $this->RegisterPropertyString('Provider', 'myopel');
        $this->RegisterPropertyString('CountryCode', 'DE');
        $this->RegisterPropertyString('ClientID', '');
        $this->RegisterPropertyString('ClientSecret', '');
        $this->RegisterPropertyInteger('PollInterval', 300);
        $this->RegisterPropertyBoolean('AutoCreateVehicles', true);

        $this->RegisterAttributeString('OAuthAccessToken', '');
        $this->RegisterAttributeString('OAuthRefreshToken', '');
        $this->RegisterAttributeInteger('OAuthExpiresAt', 0);
        $this->RegisterAttributeString('Vehicles', '[]');

        $this->RegisterVariableBoolean('Connected', 'API verbunden', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
        ], 10);
        $this->RegisterVariableInteger('LastSuccessfulUpdate', 'Letzte erfolgreiche Aktualisierung', '~UnixTimestamp', 20);
        $this->RegisterVariableString('LastError', 'Letzter Fehler', [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
        ], 30);

        $this->RegisterTimer('Poll', 0, 'STLAACC_Refresh($_IPS["TARGET"]);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $interval = max(60, $this->ReadPropertyInteger('PollInterval'));
        $enabled = $this->ReadPropertyBoolean('Enabled');
        $authenticated = $this->ReadAttributeString('OAuthRefreshToken') !== '';
        $credentialsConfigured = $this->credentialsConfigured();

        $this->SetTimerInterval('Poll', $enabled && $authenticated && $credentialsConfigured ? $interval * 1000 : 0);
        if (!$enabled) {
            $this->SetStatus(104);
            return;
        }

        if (!$credentialsConfigured && $this->ReadPropertyString('Provider') === 'myopel') {
            $this->SetStatus(204);
            return;
        }

        $this->SetStatus($authenticated ? 102 : 200);
    }

    public function GetConfigurationForm(): string
    {
        $authenticated = $this->ReadAttributeString('OAuthRefreshToken') !== '';
        $expiresAt = $this->ReadAttributeInteger('OAuthExpiresAt');
        $tokenStatus = $authenticated
            ? 'Angemeldet; Token gültig bis ' . date('d.m.Y H:i:s', $expiresAt)
            : 'Noch nicht angemeldet';

        $form = [
            'elements' => [
                ['type' => 'CheckBox', 'name' => 'Enabled', 'caption' => 'Aktiv'],
                [
                    'type'    => 'Select',
                    'name'    => 'Provider',
                    'caption' => 'Zugangsart',
                    'options' => [
                        ['caption' => 'MyOpel-Kompatibilitätsmodus', 'value' => 'myopel'],
                        ['caption' => 'Offizielle Mobilisights B2C API (vorbereitet)', 'value' => 'official_b2c']
                    ]
                ],
                ['type' => 'ValidationTextBox', 'name' => 'CountryCode', 'caption' => 'Land (z. B. DE)'],
                ['type' => 'ValidationTextBox', 'name' => 'ClientID', 'caption' => 'MyOpel Client-ID'],
                ['type' => 'PasswordTextBox', 'name' => 'ClientSecret', 'caption' => 'MyOpel Client-Secret'],
                ['type' => 'NumberSpinner', 'name' => 'PollInterval', 'caption' => 'Statusintervall in Sekunden', 'minimum' => 60],
                ['type' => 'CheckBox', 'name' => 'AutoCreateVehicles', 'caption' => 'Fahrzeuginstanzen automatisch anlegen'],
                ['type' => 'Label', 'caption' => $tokenStatus]
            ],
            'actions' => [
                ['type' => 'Label', 'caption' => '1. MyOpel-Anmeldung im Browser öffnen. Nach der Weiterleitung die vollständige mymopsdk://-Adresse aus dem Browser kopieren.'],
                ['type' => 'Button', 'caption' => 'MyOpel-Anmeldung öffnen', 'onClick' => 'echo STLAACC_GetAuthorizationUrl($id);'],
                ['type' => 'ValidationTextBox', 'name' => 'AuthorizationCode', 'caption' => 'Weiterleitungsadresse oder OAuth-Code'],
                ['type' => 'Button', 'caption' => 'OAuth-Code übernehmen', 'onClick' => 'echo STLAACC_ExchangeAuthorizationCode($id, $AuthorizationCode);'],
                ['type' => 'Button', 'caption' => 'Fahrzeuge und Status aktualisieren', 'onClick' => 'echo STLAACC_Refresh($id);'],
                ['type' => 'Button', 'caption' => 'Anmeldung zurücksetzen', 'confirm' => 'OAuth-Token wirklich aus dieser Instanz entfernen?', 'onClick' => 'STLAACC_ResetAuthentication($id); echo "Anmeldung wurde zurückgesetzt.";']
            ],
            'status' => [
                ['code' => 102, 'icon' => 'active', 'caption' => 'Bereit'],
                ['code' => 104, 'icon' => 'inactive', 'caption' => 'Instanz ist deaktiviert'],
                ['code' => 200, 'icon' => 'error', 'caption' => 'MyOpel-Anmeldung erforderlich'],
                ['code' => 201, 'icon' => 'error', 'caption' => 'Authentifizierung fehlgeschlagen'],
                ['code' => 202, 'icon' => 'error', 'caption' => 'Kommunikationsfehler'],
                ['code' => 203, 'icon' => 'error', 'caption' => 'Offizieller B2C-Provider noch nicht konfiguriert'],
                ['code' => 204, 'icon' => 'error', 'caption' => 'MyOpel Client-Zugangsdaten fehlen']
            ]
        ];

        return json_encode($form, JSON_THROW_ON_ERROR);
    }

    public function GetAuthorizationUrl(): string
    {
        if ($this->ReadPropertyString('Provider') !== 'myopel') {
            return 'Der offizielle B2C-Provider wird nach Erhalt der Zugangsdaten ergänzt.';
        }
        return $this->provider()->getAuthorizationUrl();
    }

    public function ExchangeAuthorizationCode(string $redirectOrCode): string
    {
        try {
            $code = MyOpelProvider::extractAuthorizationCode($redirectOrCode);
            if ($code === '') {
                throw new RuntimeException('In der Eingabe wurde kein OAuth-Code gefunden.');
            }

            $provider = $this->provider();
            $response = $this->requestToken($provider->getAuthorizationCodeParameters($code));
            $this->storeTokens($response);
            $this->clearError();
            $this->SetStatus(102);

            $refreshMessage = $this->Refresh();
            return 'Anmeldung erfolgreich. ' . $refreshMessage;
        } catch (Throwable $exception) {
            $this->setError($exception->getMessage(), 201);
            return 'Anmeldung fehlgeschlagen: ' . $exception->getMessage();
        }
    }

    public function ResetAuthentication(): void
    {
        $this->WriteAttributeString('OAuthAccessToken', '');
        $this->WriteAttributeString('OAuthRefreshToken', '');
        $this->WriteAttributeInteger('OAuthExpiresAt', 0);
        $this->WriteAttributeString('Vehicles', '[]');
        $this->SetTimerInterval('Poll', 0);
        $this->setValue('Connected', false);
        $this->SetStatus(200);
    }

    public function Refresh(): string
    {
        if (!$this->ReadPropertyBoolean('Enabled')) {
            return 'Instanz ist deaktiviert.';
        }
        if ($this->ReadPropertyString('Provider') !== 'myopel') {
            $this->SetStatus(203);
            return 'Offizielle B2C-Zugangsdaten sind noch nicht eingerichtet.';
        }
        if ($this->ReadAttributeString('OAuthRefreshToken') === '') {
            $this->SetStatus(200);
            return 'Bitte zuerst bei MyOpel anmelden.';
        }

        try {
            $vehicles = $this->fetchVehicles();
            $this->WriteAttributeString('Vehicles', json_encode($vehicles, JSON_THROW_ON_ERROR));
            if ($this->ReadPropertyBoolean('AutoCreateVehicles')) {
                $this->ensureVehicleInstances($vehicles);
            }

            $updated = 0;
            foreach ($vehicles as $vehicle) {
                $vehicleId = (string) ($vehicle['id'] ?? '');
                $vin = (string) ($vehicle['vin'] ?? '');
                if ($vehicleId === '' || $vin === '') {
                    continue;
                }
                $status = $this->fetchVehicleStatus($vehicleId);
                $this->sendStatusToChildren($vin, $status);
                ++$updated;
            }

            $this->setValue('Connected', true);
            $this->setValue('LastSuccessfulUpdate', time());
            $this->clearError();
            $this->SetStatus(102);
            return sprintf('%d Fahrzeug(e) gefunden, %d Statusdatensätze aktualisiert.', count($vehicles), $updated);
        } catch (Throwable $exception) {
            $this->setValue('Connected', false);
            $this->setError($exception->getMessage(), 202);
            return 'Aktualisierung fehlgeschlagen: ' . $exception->getMessage();
        }
    }

    public function ForwardData($jsonString): string
    {
        try {
            $data = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
            $buffer = isset($data['Buffer']) && is_string($data['Buffer'])
                ? json_decode($data['Buffer'], true, 512, JSON_THROW_ON_ERROR)
                : $data;
            if (($buffer['Function'] ?? '') !== 'RefreshVehicle') {
                throw new InvalidArgumentException('Unbekannte Fahrzeuganfrage.');
            }

            $vehicleId = (string) ($buffer['VehicleID'] ?? '');
            if ($vehicleId === '') {
                throw new InvalidArgumentException('VehicleID fehlt.');
            }
            return json_encode($this->fetchVehicleStatus($vehicleId), JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            return json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchVehicles(): array
    {
        $response = $this->apiRequest($this->provider()->getVehiclesUrl());
        $vehicles = $response['_embedded']['vehicles'] ?? [];
        if (!is_array($vehicles)) {
            throw new RuntimeException('Die Fahrzeugliste hat ein unerwartetes Format.');
        }
        return array_values(array_filter($vehicles, 'is_array'));
    }

    /** @return array<string, mixed> */
    private function fetchVehicleStatus(string $vehicleId): array
    {
        return $this->apiRequest($this->provider()->getVehicleStatusUrl($vehicleId));
    }

    /** @return array<string, mixed> */
    private function apiRequest(string $url): array
    {
        $this->ensureAccessToken();
        $headers = $this->provider()->getApiHeaders($this->ReadAttributeString('OAuthAccessToken'));
        return $this->httpJson('GET', $url, $headers);
    }

    private function ensureAccessToken(): void
    {
        if ($this->ReadAttributeInteger('OAuthExpiresAt') > time() + 300) {
            return;
        }
        $refreshToken = $this->ReadAttributeString('OAuthRefreshToken');
        if ($refreshToken === '') {
            throw new RuntimeException('OAuth-Aktualisierungstoken fehlt.');
        }
        $this->storeTokens($this->requestToken($this->provider()->getRefreshTokenParameters($refreshToken)));
    }

    /** @param array<string, string> $parameters
     *  @return array<string, mixed>
     */
    private function requestToken(array $parameters): array
    {
        $provider = $this->provider();
        $url = $provider->getTokenUrl() . '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
        return $this->httpJson('POST', $url, [
            'Authorization: Basic ' . $provider->getBasicAuthorization(),
            'Content-Type: application/x-www-form-urlencoded'
        ]);
    }

    /** @param array<string, mixed> $response */
    private function storeTokens(array $response): void
    {
        $accessToken = (string) ($response['access_token'] ?? '');
        $refreshToken = (string) ($response['refresh_token'] ?? $this->ReadAttributeString('OAuthRefreshToken'));
        if ($accessToken === '' || $refreshToken === '') {
            throw new RuntimeException('Stellantis hat keine vollständigen OAuth-Token geliefert.');
        }
        $expiresIn = max(60, (int) ($response['expires_in'] ?? 3600));
        $this->WriteAttributeString('OAuthAccessToken', $accessToken);
        $this->WriteAttributeString('OAuthRefreshToken', $refreshToken);
        $this->WriteAttributeInteger('OAuthExpiresAt', time() + $expiresIn);
        $this->SetTimerInterval('Poll', max(60, $this->ReadPropertyInteger('PollInterval')) * 1000);
    }

    /** @param array<int, string> $headers
     *  @return array<string, mixed>
     */
    private function httpJson(string $method, string $url, array $headers): array
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('HTTP-Client konnte nicht initialisiert werden.');
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_USERAGENT      => 'IP-Symcon Stellantis Vehicles/0.1'
        ]);
        if ($method === 'POST') {
            curl_setopt($curl, CURLOPT_POSTFIELDS, '');
        }

        $body = curl_exec($curl);
        $curlError = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($body === false) {
            throw new RuntimeException('HTTP-Verbindung fehlgeschlagen: ' . $curlError);
        }

        $decoded = json_decode((string) $body, true);
        if ($statusCode === 404 && is_array($decoded) && (string) ($decoded['code'] ?? '') === '40400') {
            return [];
        }
        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($decoded)
                ? (string) ($decoded['error_description'] ?? $decoded['moreInformation'] ?? $decoded['message'] ?? ('HTTP ' . $statusCode))
                : 'HTTP ' . $statusCode;
            throw new RuntimeException($message);
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('Stellantis hat keine gültige JSON-Antwort geliefert.');
        }
        return $decoded;
    }

    /** @param array<int, array<string, mixed>> $vehicles */
    private function ensureVehicleInstances(array $vehicles): void
    {
        $existing = [];
        foreach (IPS_GetInstanceListByModuleID(self::VEHICLE_MODULE) as $instanceId) {
            $instance = IPS_GetInstance($instanceId);
            if (($instance['ConnectionID'] ?? 0) !== $this->InstanceID) {
                continue;
            }
            $vin = (string) IPS_GetProperty($instanceId, 'VIN');
            if ($vin !== '') {
                $existing[$vin] = $instanceId;
            }
        }

        foreach ($vehicles as $vehicle) {
            $vin = (string) ($vehicle['vin'] ?? '');
            $vehicleId = (string) ($vehicle['id'] ?? '');
            if ($vin === '' || $vehicleId === '') {
                continue;
            }
            if (isset($existing[$vin])) {
                $instanceId = $existing[$vin];
                if ((string) IPS_GetProperty($instanceId, 'VehicleID') !== $vehicleId) {
                    IPS_SetProperty($instanceId, 'VehicleID', $vehicleId);
                    IPS_ApplyChanges($instanceId);
                }
                continue;
            }

            $instanceId = IPS_CreateInstance(self::VEHICLE_MODULE);
            IPS_SetName($instanceId, $this->vehicleName($vehicle));
            IPS_ConnectInstance($instanceId, $this->InstanceID);
            IPS_SetProperty($instanceId, 'VIN', $vin);
            IPS_SetProperty($instanceId, 'VehicleID', $vehicleId);
            IPS_SetProperty($instanceId, 'Motorization', (string) ($vehicle['motorization'] ?? ''));
            IPS_ApplyChanges($instanceId);
        }
    }

    /** @param array<string, mixed> $vehicle */
    private function vehicleName(array $vehicle): string
    {
        $model = trim((string) ($vehicle['model'] ?? $vehicle['name'] ?? ''));
        $vin = (string) ($vehicle['vin'] ?? 'Fahrzeug');
        return $model !== '' ? 'Opel ' . $model : 'Opel ' . substr($vin, -6);
    }

    /** @param array<string, mixed> $status */
    private function sendStatusToChildren(string $vin, array $status): void
    {
        $this->SendDataToChildren(json_encode([
            'DataID' => self::DATA_TO_CHILD,
            'Buffer' => json_encode([
                'Function' => 'VehicleStatus',
                'VIN'      => $vin,
                'Status'   => $status
            ], JSON_THROW_ON_ERROR)
        ], JSON_THROW_ON_ERROR));
    }

    private function provider(): MyOpelProvider
    {
        if ($this->ReadPropertyString('Provider') !== 'myopel') {
            throw new RuntimeException('Der offizielle B2C-Provider ist vorbereitet, aber noch nicht konfiguriert.');
        }
        return new MyOpelProvider(
            $this->ReadPropertyString('ClientID'),
            $this->ReadPropertyString('ClientSecret'),
            $this->ReadPropertyString('CountryCode')
        );
    }

    private function credentialsConfigured(): bool
    {
        return trim($this->ReadPropertyString('ClientID')) !== ''
            && trim($this->ReadPropertyString('ClientSecret')) !== '';
    }

    private function setError(string $message, int $status): void
    {
        $safeMessage = substr($message, 0, 500);
        $this->setValue('LastError', $safeMessage);
        $this->SendDebug('Error', $safeMessage, 0);
        $this->SetStatus($status);
    }

    private function clearError(): void
    {
        $this->setValue('LastError', '');
    }

    private function setValue(string $ident, mixed $value): void
    {
        $variableId = $this->GetIDForIdent($ident);
        if (GetValue($variableId) !== $value) {
            SetValue($variableId, $value);
        }
    }
}
