import json
import pathlib
import re
import unittest


ROOT = pathlib.Path(__file__).resolve().parents[1]
ACCOUNT = ROOT / "StellantisAccount" / "module.php"
VEHICLE = ROOT / "StellantisVehicle" / "module.php"


class ModuleContractTest(unittest.TestCase):
    def test_json_metadata_is_valid_and_targets_symcon_8(self):
        files = [
            ROOT / "library.json",
            ROOT / "StellantisAccount" / "module.json",
            ROOT / "StellantisVehicle" / "module.json",
            ROOT / "tests" / "fixtures" / "status-electric.json",
        ]
        parsed = {}
        for path in files:
            with self.subTest(path=path):
                parsed[path.name + str(path.parent)] = json.loads(path.read_text(encoding="utf-8"))

        library = json.loads((ROOT / "library.json").read_text(encoding="utf-8"))
        self.assertEqual("8.0", library["compatibility"]["version"])
        self.assertEqual("Sepp Lausch", library["author"])
        self.assertEqual(
            "https://github.com/slausch/Symcon-Stellantis-Vehicles",
            library["url"],
        )

    def test_account_and_vehicle_data_interfaces_match(self):
        account = json.loads((ROOT / "StellantisAccount" / "module.json").read_text(encoding="utf-8"))
        vehicle = json.loads((ROOT / "StellantisVehicle" / "module.json").read_text(encoding="utf-8"))
        self.assertEqual(3, account["type"])
        self.assertEqual(3, vehicle["type"])
        self.assertEqual(account["childRequirements"], vehicle["implemented"])
        self.assertEqual(account["implemented"], vehicle["parentRequirements"])

    def test_symbox_runtime_has_no_external_process_dependency(self):
        source = "\n".join(
            path.read_text(encoding="utf-8")
            for path in [ACCOUNT, VEHICLE, ROOT / "libs" / "MyOpelProvider.php"]
        )
        for forbidden in [r"shell_exec\(", r"(?<![A-Za-z0-9_])exec\(", r"(?<![A-Za-z0-9_])system\(", r"passthru\(", r"python", r"home assistant"]:
            with self.subTest(forbidden=forbidden):
                self.assertIsNone(re.search(forbidden, source.lower()))

    def test_login_password_is_not_a_module_property(self):
        source = ACCOUNT.read_text(encoding="utf-8")
        self.assertNotIn("RegisterPropertyString('Email'", source)
        self.assertNotIn("RegisterPropertyString('Password'", source)
        self.assertNotIn("RegisterPropertyString('PIN'", source)
        self.assertIn("GetAuthorizationUrl", source)
        self.assertIn("ExchangeAuthorizationCode", source)

    def test_mobile_app_credentials_are_not_embedded(self):
        provider = (ROOT / "libs" / "MyOpelProvider.php").read_text(encoding="utf-8")
        self.assertNotRegex(provider, r"private const CLIENT_(?:ID|SECRET)\s*=")
        self.assertIn("$clientId", provider)
        self.assertIn("$clientSecret", provider)

    def test_symcon_overrides_do_not_narrow_framework_parameters(self):
        account = ACCOUNT.read_text(encoding="utf-8")
        vehicle = VEHICLE.read_text(encoding="utf-8")
        self.assertIn("function ForwardData($jsonString)", account)
        self.assertIn("function ReceiveData($jsonString)", vehicle)
        self.assertNotIn("function ForwardData(string $jsonString)", account)
        self.assertNotIn("function ReceiveData(string $jsonString)", vehicle)

    def test_vehicle_exposes_agreed_read_only_values(self):
        source = VEHICLE.read_text(encoding="utf-8")
        for ident in [
            "SOC",
            "Range",
            "ChargingPlugged",
            "Charging",
            "ChargingRemainingMinutes",
            "Preconditioning",
            "AmbientTemperature",
            "LastDataAt",
            "DataAge",
        ]:
            with self.subTest(ident=ident):
                self.assertIn("'" + ident + "'", source)
        self.assertIn("VARIABLE_PRESENTATION_VALUE_PRESENTATION", source)
        self.assertNotIn("IPS_CreateVariableProfile", source)


if __name__ == "__main__":
    unittest.main()
