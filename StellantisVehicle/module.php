<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/VehicleStatusMapper.php';

class StellantisVehicle extends IPSModule
{
    private const ACCOUNT_MODULE = '{DF16825B-E212-4F22-9648-FC5F1825B504}';
    private const DATA_TO_ACCOUNT = '{AD5040B8-C984-4893-AF89-E4CBB35B9B7F}';

    public function Create(): void
    {
        parent::Create();
        $this->ConnectParent(self::ACCOUNT_MODULE);

        $this->RegisterPropertyString('VIN', '');
        $this->RegisterPropertyString('VehicleID', '');
        $this->RegisterPropertyString('Motorization', '');
        $this->RegisterPropertyBoolean('StoreRawStatus', false);

        $this->RegisterVariableFloat('SOC', 'Ladezustand', $this->valuePresentation('%', 1, 0, 100), 10);
        $this->RegisterVariableFloat('Range', 'Elektrische Reichweite', $this->valuePresentation(' km', 1), 20);
        $this->RegisterVariableBoolean('ChargingPlugged', 'Ladekabel angeschlossen', $this->readOnlyPresentation(), 30);
        $this->RegisterVariableBoolean('Charging', 'Ladevorgang aktiv', $this->readOnlyPresentation(), 40);
        $this->RegisterVariableString('ChargingStatus', 'Ladestatus', $this->readOnlyPresentation(), 50);
        $this->RegisterVariableString('ChargingMode', 'Ladeart', $this->readOnlyPresentation(), 60);
        $this->RegisterVariableInteger('ChargingRemainingMinutes', 'Verbleibende Ladezeit', $this->valuePresentation(' min', 0), 70);
        $this->RegisterVariableBoolean('Preconditioning', 'Vorklimatisierung/Vorheizung aktiv', $this->readOnlyPresentation(), 80);
        $this->RegisterVariableBoolean('DoorsLocked', 'Türen verriegelt', $this->readOnlyPresentation(), 90);
        $this->RegisterVariableFloat('AmbientTemperature', 'Außentemperatur', $this->valuePresentation(' °C', 1), 100);
        $this->RegisterVariableFloat('Mileage', 'Kilometerstand', $this->valuePresentation(' km', 1), 110);
        $this->RegisterVariableFloat('Latitude', 'Breitengrad', $this->valuePresentation('', 6), 120);
        $this->RegisterVariableFloat('Longitude', 'Längengrad', $this->valuePresentation('', 6), 130);
        $this->RegisterVariableInteger('LastDataAt', 'Zeitpunkt Fahrzeugdaten', '~UnixTimestamp', 140);
        $this->RegisterVariableInteger('DataAge', 'Alter der Fahrzeugdaten', $this->valuePresentation(' s', 0), 150);
        $this->RegisterVariableString('LastCommandStatus', 'Letzter Befehlsstatus', $this->readOnlyPresentation(), 160);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $vin = preg_quote($this->ReadPropertyString('VIN'), '/');
        $this->SetReceiveDataFilter($vin === '' ? '.*' : '.*"VIN":"' . $vin . '".*');

        if ($this->ReadPropertyString('VIN') === '' || $this->ReadPropertyString('VehicleID') === '') {
            $this->SetStatus(200);
        } else {
            $this->SetStatus(102);
        }
    }

    public function GetConfigurationForm(): string
    {
        return json_encode([
            'elements' => [
                ['type' => 'ValidationTextBox', 'name' => 'VIN', 'caption' => 'VIN'],
                ['type' => 'ValidationTextBox', 'name' => 'VehicleID', 'caption' => 'Stellantis Vehicle-ID'],
                ['type' => 'ValidationTextBox', 'name' => 'Motorization', 'caption' => 'Antriebsart'],
                ['type' => 'CheckBox', 'name' => 'StoreRawStatus', 'caption' => 'Rohdaten als Variable speichern']
            ],
            'actions' => [
                ['type' => 'Button', 'caption' => 'Status jetzt aktualisieren', 'onClick' => 'echo STLAVEH_Refresh($id) ? "Aktualisiert" : "Aktualisierung fehlgeschlagen";'],
                ['type' => 'Label', 'caption' => 'Vorklimatisierung und Vorheizung werden in Phase 2 als schaltbare Aktion ergänzt.']
            ],
            'status' => [
                ['code' => 102, 'icon' => 'active', 'caption' => 'Bereit'],
                ['code' => 200, 'icon' => 'error', 'caption' => 'VIN oder Vehicle-ID fehlt'],
                ['code' => 201, 'icon' => 'error', 'caption' => 'Keine Verbindung zum Account'],
                ['code' => 202, 'icon' => 'error', 'caption' => 'Status konnte nicht gelesen werden']
            ]
        ], JSON_THROW_ON_ERROR);
    }

    public function ReceiveData($jsonString): void
    {
        try {
            $data = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
            $buffer = json_decode((string) ($data['Buffer'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
            if (($buffer['Function'] ?? '') !== 'VehicleStatus') {
                return;
            }
            if (($buffer['VIN'] ?? '') !== $this->ReadPropertyString('VIN')) {
                return;
            }
            $status = $buffer['Status'] ?? null;
            if (!is_array($status)) {
                throw new RuntimeException('Ungültige Statusdaten.');
            }
            $this->applyStatus($status);
        } catch (Throwable $exception) {
            $this->SendDebug('ReceiveData', $exception->getMessage(), 0);
            $this->SetStatus(202);
        }
    }

    public function Refresh(): bool
    {
        if (!$this->HasActiveParent()) {
            $this->SetStatus(201);
            return false;
        }

        $request = [
            'DataID' => self::DATA_TO_ACCOUNT,
            'Buffer' => json_encode([
                'Function'  => 'RefreshVehicle',
                'VIN'       => $this->ReadPropertyString('VIN'),
                'VehicleID' => $this->ReadPropertyString('VehicleID')
            ], JSON_THROW_ON_ERROR)
        ];
        $response = $this->SendDataToParent(json_encode($request, JSON_THROW_ON_ERROR));

        try {
            $status = json_decode((string) $response, true, 512, JSON_THROW_ON_ERROR);
            if (isset($status['error'])) {
                throw new RuntimeException((string) $status['error']);
            }
            $this->applyStatus($status);
            return true;
        } catch (Throwable $exception) {
            $this->SendDebug('Refresh', $exception->getMessage(), 0);
            $this->SetStatus(202);
            return false;
        }
    }

    /** @param array<string, mixed> $status */
    private function applyStatus(array $status): void
    {
        $values = VehicleStatusMapper::map($status);
        foreach ($values as $ident => $value) {
            if ($value !== null && @$this->GetIDForIdent($ident) > 0) {
                $this->setValue($ident, $value);
            }
        }

        if ($this->ReadPropertyBoolean('StoreRawStatus')) {
            if (@$this->GetIDForIdent('RawStatus') === false) {
                $this->RegisterVariableString('RawStatus', 'Rohdaten', $this->readOnlyPresentation(), 1000);
            }
            $this->setValue('RawStatus', json_encode($status, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        $this->SetStatus(102);
    }

    /** @return array<string, mixed> */
    private function readOnlyPresentation(): array
    {
        return ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION];
    }

    /** @return array<string, mixed> */
    private function valuePresentation(string $suffix, int $digits, ?float $minimum = null, ?float $maximum = null): array
    {
        $presentation = [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'DIGITS'       => $digits
        ];
        if ($suffix !== '') {
            $presentation['SUFFIX'] = $suffix;
        }
        if ($minimum !== null) {
            $presentation['MIN'] = $minimum;
        }
        if ($maximum !== null) {
            $presentation['MAX'] = $maximum;
        }
        return $presentation;
    }

    private function setValue(string $ident, mixed $value): void
    {
        $variableId = $this->GetIDForIdent($ident);
        if (GetValue($variableId) !== $value) {
            SetValue($variableId, $value);
        }
    }
}
