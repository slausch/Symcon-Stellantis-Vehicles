# Stellantis Vehicles für IP-Symcon

Native IP-Symcon-Anbindung für Opel- und weitere ehemalige PSA-Fahrzeuge über
die Stellantis-Cloud. Home Assistant wird nicht benötigt. Zielplattform ist
IP-Symcon 8.0 oder neuer auf einer SymBox.

## Entwicklungsstand

Phase 1 ist als testbarer Prototyp implementiert:

- browsergestützter MyOpel-OAuth-Login ohne Speicherung des MyOpel-Passworts
- automatische Erneuerung des OAuth-Tokens
- Erkennung der Fahrzeuge eines Kontos
- automatische Anlage einer Fahrzeuginstanz pro VIN
- SOC, Reichweite, Ladekabel, Ladestatus, Ladeart und Restladezeit
- Vorklimatisierungs-/Vorheizstatus, Türen, Temperatur und Kilometerstand
- Position sowie Zeitstempel und Alter der Fahrzeugdaten

Noch nicht implementiert sind die SMS-/PIN-Geräteaktivierung und der
TLS-MQTT-Kanal für Fernbefehle. Diese folgen nach dem Live-Test von Phase 1.

## Einrichtung des Prototyps

1. Bibliothek über das IP-Symcon Module Control laden.
2. Über `Instanz hinzufügen` nach `Stellantis Account` suchen und die Instanz
   erstellen. Sie wird als normale Geräteinstanz angeboten; `Alle Module
   anzeigen` ist nicht erforderlich.
3. Land, Statusintervall sowie berechtigte MyOpel Client-ID und Client-Secret
   einstellen und übernehmen.
4. Unter `Testumgebung` die MyOpel-Anmeldung öffnen.
5. Nach erfolgreicher Anmeldung versucht der Browser eine Adresse beginnend
   mit `mymopsdk://oauth2redirect/` aufzurufen. Diese vollständige Adresse aus
   der Adresszeile kopieren.
6. Adresse in das OAuth-Feld der Testumgebung einfügen und übernehmen.

MyOpel-E-Mail und -Passwort werden ausschließlich auf der Opel-Anmeldeseite
eingegeben und nicht vom Modul gespeichert.

Aus Sicherheits- und Lizenzgründen enthält das öffentliche Modul keine
Zugangsdaten der mobilen MyOpel-App. Die Client-Zugangsdaten müssen vom
Betreiber rechtmäßig bezogen und pro Account-Instanz eingetragen werden. Das
Client-Secret wird im Konfigurationsformular maskiert, liegt in der
IP-Symcon-Konfiguration jedoch nicht in einem externen Secret Store.

Installation über die IP-Symcon-Modulverwaltung:

```text
https://github.com/slausch/Symcon-Stellantis-Vehicles
```

## Geplanter Ausbau

- SMS/PIN-Aktivierung für Remote Services
- MQTT 3.1.1 über TLS
- Fahrzeug aufwecken
- Laden starten/stoppen und Ladeplanung
- Vorklimatisierung/Vorheizung starten und stoppen
- optional Ladegrenze, Verriegelung, Licht und Hupe je nach Fahrzeugservice
- austauschbarer offizieller Mobilisights-B2C-Provider

Phase 2 wird ohne zusätzlich zu installierende Linux-, Python- oder
Home-Assistant-Komponenten für die SymBox umgesetzt.

## Hinweis

Der MyOpel-Kompatibilitätsmodus verwendet den Login- und API-Weg der mobilen
App. Stellantis kann diesen ohne Vorankündigung verändern. Das Modul ist daher
bis zum Abschluss von Live- und Langzeittests nicht für unbeaufsichtigte oder
sicherheitskritische Automationen vorgesehen.

Siehe auch [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
