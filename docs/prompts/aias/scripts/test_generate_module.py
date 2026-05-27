"""
Tests for the AIAS Module Generator Script.

Run with: python3 -m pytest docs/prompts/aias/scripts/test_generate_module.py -v
Or: python3 docs/prompts/aias/scripts/test_generate_module.py
"""

from __future__ import annotations

import os
import sys
import tempfile
import unittest
from pathlib import Path

# Add parent dir to path so we can import the module
sys.path.insert(0, str(Path(__file__).parent))
from importlib import import_module

# Import the module dynamically since it has hyphens in the original spec name
spec_path = Path(__file__).parent / "generate-module.py"
import importlib.util

_spec = importlib.util.spec_from_file_location("generate_module", spec_path)
gm = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(gm)

# Aliases for convenience
to_pascal = gm.to_pascal
derive_names = gm.derive_names
parse_fields = gm.parse_fields
ModuleGenerator = gm.ModuleGenerator
RollbackManager = gm.RollbackManager
main = gm.main


class TestToPascal(unittest.TestCase):
    """Test PascalCase normalisation."""

    def test_already_pascal(self):
        self.assertEqual(to_pascal("AuditStandard"), "AuditStandard")

    def test_snake_case(self):
        self.assertEqual(to_pascal("audit_standard"), "AuditStandard")

    def test_kebab_case(self):
        self.assertEqual(to_pascal("audit-standard"), "AuditStandard")

    def test_single_word(self):
        self.assertEqual(to_pascal("Finding"), "Finding")

    def test_single_word_lower(self):
        self.assertEqual(to_pascal("finding"), "Finding")

    def test_three_words(self):
        self.assertEqual(to_pascal("control_objective_item"), "ControlObjectiveItem")


class TestDeriveNames(unittest.TestCase):
    """Test naming convention derivation."""

    def test_audit_standard(self):
        n = derive_names("AuditStandard")
        self.assertEqual(n.pascal, "AuditStandard")
        self.assertEqual(n.camel, "auditStandard")
        self.assertEqual(n.snake, "audit_standard")
        self.assertEqual(n.kebab, "audit-standard")
        self.assertEqual(n.plural_snake, "audit_standards")
        self.assertEqual(n.plural_pascal, "AuditStandards")
        self.assertEqual(n.plural_kebab, "audit-standards")
        self.assertEqual(n.table, "audit_standards")
        self.assertEqual(n.title, "Audit Standard")
        self.assertEqual(n.plural_title, "Audit Standards")

    def test_single_word(self):
        n = derive_names("Country")
        self.assertEqual(n.pascal, "Country")
        self.assertEqual(n.camel, "country")
        self.assertEqual(n.snake, "country")
        self.assertEqual(n.table, "countries")
        self.assertEqual(n.plural_pascal, "Countries")
        self.assertEqual(n.plural_kebab, "countries")

    def test_finding(self):
        n = derive_names("Finding")
        self.assertEqual(n.table, "findings")
        self.assertEqual(n.plural_pascal, "Findings")

    def test_risk_assessment(self):
        n = derive_names("RiskAssessment")
        self.assertEqual(n.table, "risk_assessments")
        self.assertEqual(n.camel, "riskAssessment")

    def test_status_pluralisation(self):
        n = derive_names("AuditStatus")
        self.assertEqual(n.table, "audit_statuses")


class TestParseFields(unittest.TestCase):
    """Test field spec parsing."""

    def test_empty(self):
        self.assertEqual(parse_fields(""), [])
        self.assertEqual(parse_fields("  "), [])

    def test_single_field(self):
        fields = parse_fields("name:string:required")
        self.assertEqual(len(fields), 1)
        self.assertEqual(fields[0].name, "name")
        self.assertEqual(fields[0].type, "string")
        self.assertTrue(fields[0].required)
        self.assertFalse(fields[0].nullable)
        self.assertFalse(fields[0].unique)

    def test_multiple_fields(self):
        fields = parse_fields("severity:string:required,status:string:required,resolution:text:nullable")
        self.assertEqual(len(fields), 3)
        self.assertEqual(fields[0].name, "severity")
        self.assertEqual(fields[1].name, "status")
        self.assertEqual(fields[2].name, "resolution")
        self.assertTrue(fields[2].nullable)

    def test_unique_modifier(self):
        fields = parse_fields("code:string:required:unique")
        self.assertEqual(len(fields), 1)
        self.assertTrue(fields[0].required)
        self.assertTrue(fields[0].unique)

    def test_all_types(self):
        for t in ("string", "text", "integer", "boolean", "date", "datetime", "decimal"):
            fields = parse_fields(f"field:{t}")
            self.assertEqual(fields[0].type, t)

    def test_invalid_type(self):
        with self.assertRaises(ValueError):
            parse_fields("field:invalid_type")

    def test_invalid_modifier(self):
        with self.assertRaises(ValueError):
            parse_fields("field:string:bogus")

    def test_missing_type(self):
        with self.assertRaises(ValueError):
            parse_fields("fieldonly")


class TestRollbackManager(unittest.TestCase):
    """Test rollback cleans up files."""

    def test_rollback_removes_files(self):
        rm = RollbackManager()
        with tempfile.TemporaryDirectory() as tmpdir:
            f = Path(tmpdir) / "test.txt"
            f.write_text("hello")
            rm.track_file(f)
            self.assertTrue(f.exists())
            rm.rollback()
            self.assertFalse(f.exists())

    def test_rollback_removes_empty_dirs(self):
        rm = RollbackManager()
        with tempfile.TemporaryDirectory() as tmpdir:
            d = Path(tmpdir) / "sub"
            d.mkdir()
            rm.track_dir(d)
            self.assertTrue(d.exists())
            rm.rollback()
            self.assertFalse(d.exists())

    def test_rollback_keeps_non_empty_dirs(self):
        rm = RollbackManager()
        with tempfile.TemporaryDirectory() as tmpdir:
            d = Path(tmpdir) / "sub"
            d.mkdir()
            (d / "keep.txt").write_text("keep")
            rm.track_dir(d)
            rm.rollback()
            self.assertTrue(d.exists())


class TestModuleGeneratorDryRun(unittest.TestCase):
    """Test that dry-run doesn't create files."""

    def _make_fake_project(self) -> Path:
        tmpdir = tempfile.mkdtemp()
        (Path(tmpdir) / "artisan").write_text("<?php // artisan")
        (Path(tmpdir) / "database" / "migrations" / "tenant").mkdir(parents=True)
        (Path(tmpdir) / "database" / "migrations").mkdir(parents=True, exist_ok=True)
        (Path(tmpdir) / "database" / "factories").mkdir(parents=True)
        (Path(tmpdir) / "database" / "seeders").mkdir(parents=True)
        (Path(tmpdir) / "app" / "Models").mkdir(parents=True)
        (Path(tmpdir) / "app" / "Repositories").mkdir(parents=True)
        (Path(tmpdir) / "app" / "Http" / "Controllers").mkdir(parents=True)
        (Path(tmpdir) / "app" / "Http" / "Requests").mkdir(parents=True)
        (Path(tmpdir) / "app" / "Http" / "Resources").mkdir(parents=True)
        (Path(tmpdir) / "app" / "Policies").mkdir(parents=True)
        (Path(tmpdir) / "app" / "Filters").mkdir(parents=True)
        (Path(tmpdir) / "tests" / "Feature").mkdir(parents=True)
        (Path(tmpdir) / "tests" / "Unit").mkdir(parents=True)
        return Path(tmpdir)

    def test_dry_run_creates_no_files(self):
        base = self._make_fake_project()
        gen = ModuleGenerator(
            "TestWidget",
            central=False,
            dry_run=True,
            base_dir=base,
        )
        created = gen.run()
        # dry run should report files but not actually create them
        self.assertGreater(len(created), 0)
        for f in created:
            self.assertFalse(f.exists(), f"File should not exist in dry-run: {f}")

    def test_central_dry_run(self):
        base = self._make_fake_project()
        gen = ModuleGenerator(
            "AuditStandard",
            central=True,
            dry_run=True,
            base_dir=base,
        )
        created = gen.run()
        self.assertGreater(len(created), 0)
        # Check migration goes to central dir
        migration_files = [f for f in created if "migrations" in str(f)]
        self.assertEqual(len(migration_files), 1)
        self.assertNotIn("tenant", str(migration_files[0]))

    def test_tenant_dry_run(self):
        base = self._make_fake_project()
        gen = ModuleGenerator(
            "RiskAssessment",
            central=False,
            dry_run=True,
            base_dir=base,
        )
        created = gen.run()
        migration_files = [f for f in created if "migrations" in str(f)]
        self.assertEqual(len(migration_files), 1)
        self.assertIn("tenant", str(migration_files[0]))


class TestModuleGeneratorLive(unittest.TestCase):
    """Test actual file generation."""

    def _make_fake_project(self) -> Path:
        tmpdir = tempfile.mkdtemp()
        (Path(tmpdir) / "artisan").write_text("<?php // artisan")
        (Path(tmpdir) / "database" / "migrations" / "tenant").mkdir(parents=True)
        (Path(tmpdir) / "database" / "factories").mkdir(parents=True)
        (Path(tmpdir) / "database" / "seeders").mkdir(parents=True)
        (Path(tmpdir) / "app" / "Models").mkdir(parents=True)
        (Path(tmpdir) / "app" / "Repositories").mkdir(parents=True)
        (Path(tmpdir) / "app" / "Http" / "Controllers").mkdir(parents=True)
        (Path(tmpdir) / "app" / "Http" / "Requests").mkdir(parents=True)
        (Path(tmpdir) / "app" / "Http" / "Resources").mkdir(parents=True)
        (Path(tmpdir) / "app" / "Policies").mkdir(parents=True)
        (Path(tmpdir) / "app" / "Filters").mkdir(parents=True)
        (Path(tmpdir) / "tests" / "Feature").mkdir(parents=True)
        (Path(tmpdir) / "tests" / "Unit").mkdir(parents=True)
        return Path(tmpdir)

    def test_generates_all_14_files(self):
        base = self._make_fake_project()
        gen = ModuleGenerator(
            "AuditPlan",
            central=False,
            base_dir=base,
        )
        created = gen.run()
        # 1 migration + 1 model + 1 repo + 3 filters + 2 requests + 2 resources + 1 policy + 1 controller + 1 factory + 1 seeder + 2 tests = 16
        self.assertEqual(len(created), 16)
        for f in created:
            self.assertTrue(f.exists(), f"Expected file to exist: {f}")

    def test_central_model_has_auditable(self):
        base = self._make_fake_project()
        gen = ModuleGenerator("Widget", central=True, base_dir=base)
        gen.run()
        model = (base / "app" / "Models" / "Widget.php").read_text()
        self.assertIn("implements AuditableContract", model)
        self.assertIn("use Auditable;", model)
        self.assertIn("protected $connection = 'central';", model)

    def test_tenant_model_has_no_auditable(self):
        base = self._make_fake_project()
        gen = ModuleGenerator("Widget", central=False, base_dir=base)
        gen.run()
        model = (base / "app" / "Models" / "Widget.php").read_text()
        self.assertNotIn("Auditable", model)
        self.assertNotIn("$connection", model)
        self.assertIn("'tenant_id'", model)

    def test_skip_existing_files(self):
        base = self._make_fake_project()
        gen = ModuleGenerator("Item", central=True, base_dir=base)
        gen.run()

        # Run again without overwrite — should skip
        gen2 = ModuleGenerator("Item", central=True, base_dir=base)
        created2 = gen2.run()
        self.assertEqual(len(created2), 0, "Should skip all existing files")

    def test_overwrite_existing_files(self):
        base = self._make_fake_project()
        gen = ModuleGenerator("Item", central=True, base_dir=base)
        gen.run()

        # Run again with overwrite
        gen2 = ModuleGenerator("Item", central=True, overwrite=True, base_dir=base)
        created2 = gen2.run()
        # Migration gets a new timestamp so it creates a new file; other 15 are overwritten
        self.assertGreater(len(created2), 10)

    def test_skip_tests_flag(self):
        base = self._make_fake_project()
        gen = ModuleGenerator("Item", central=True, skip_tests=True, base_dir=base)
        created = gen.run()
        test_files = [f for f in created if "tests/" in str(f)]
        self.assertEqual(len(test_files), 0)

    def test_custom_fields_in_migration(self):
        base = self._make_fake_project()
        fields = gm.parse_fields("code:string:required:unique,notes:text:nullable")
        gen = ModuleGenerator("Task", central=True, fields=fields, base_dir=base)
        gen.run()

        migration_files = list((base / "database" / "migrations").glob("*_create_tasks_table.php"))
        self.assertEqual(len(migration_files), 1)
        content = migration_files[0].read_text()
        self.assertIn("$table->string('code')", content)
        self.assertIn("->unique()", content)
        self.assertIn("$table->text('notes')", content)
        self.assertIn("->nullable()", content)

    def test_policy_uses_has_permission_to(self):
        base = self._make_fake_project()
        gen = ModuleGenerator("Report", central=True, base_dir=base)
        gen.run()
        policy = (base / "app" / "Policies" / "ReportPolicy.php").read_text()
        self.assertIn("hasPermissionTo", policy)
        self.assertNotIn("->can(", policy)
        self.assertIn("reports.view", policy)
        self.assertIn("reports.create", policy)
        self.assertIn("reports.edit", policy)
        self.assertIn("reports.delete", policy)

    def test_controller_transaction_pattern(self):
        base = self._make_fake_project()
        gen = ModuleGenerator("Report", central=True, base_dir=base)
        gen.run()
        controller = (base / "app" / "Http" / "Controllers" / "ReportController.php").read_text()
        # Gate before transaction
        self.assertIn("Gate::authorize('create', Report::class);", controller)
        self.assertIn("DB::transaction(", controller)
        self.assertIn("Log::info(", controller)

    def test_repository_tenant_filter(self):
        base = self._make_fake_project()
        gen = ModuleGenerator("Report", central=False, base_dir=base)
        gen.run()
        repo = (base / "app" / "Repositories" / "ReportRepository.php").read_text()
        self.assertIn("tenant_id", repo)
        self.assertIn("super-admin", repo)

    def test_repository_no_tenant_filter_for_central(self):
        base = self._make_fake_project()
        gen = ModuleGenerator("Report", central=True, base_dir=base)
        gen.run()
        repo = (base / "app" / "Repositories" / "ReportRepository.php").read_text()
        self.assertNotIn("tenant_id", repo)

    def test_feature_test_uses_pest_syntax(self):
        base = self._make_fake_project()
        gen = ModuleGenerator("Report", central=True, base_dir=base)
        gen.run()
        test = (base / "tests" / "Feature" / "ReportTest.php").read_text()
        self.assertIn("it(", test)
        self.assertIn("uses(Tests\\Traits\\RefreshDatabaseWithTenancy::class)", test)
        self.assertNotIn("class ", test)

    def test_tenant_test_has_isolation_check(self):
        base = self._make_fake_project()
        gen = ModuleGenerator("Report", central=False, base_dir=base)
        gen.run()
        test = (base / "tests" / "Feature" / "ReportTest.php").read_text()
        self.assertIn("cannot access other tenant", test)

    def test_central_test_no_isolation_check(self):
        base = self._make_fake_project()
        gen = ModuleGenerator("Report", central=True, base_dir=base)
        gen.run()
        test = (base / "tests" / "Feature" / "ReportTest.php").read_text()
        self.assertNotIn("cannot access other tenant", test)


class TestRollbackOnFailure(unittest.TestCase):
    """Test that rollback cleans up on stage failure."""

    def test_rollback_on_stage_failure(self):
        tmpdir = tempfile.mkdtemp()
        base = Path(tmpdir)
        (base / "artisan").write_text("<?php // artisan")
        (base / "database" / "migrations" / "tenant").mkdir(parents=True)
        (base / "database" / "factories").mkdir(parents=True)
        (base / "database" / "seeders").mkdir(parents=True)
        (base / "app" / "Models").mkdir(parents=True)
        # Deliberately NOT creating app/Repositories so the repo stage fails
        # when trying to create parent dirs (actually, write_file creates them)
        # Instead, let's make a non-writable dir to force failure
        (base / "app" / "Repositories").mkdir(parents=True)
        (base / "app" / "Http" / "Controllers").mkdir(parents=True)
        (base / "app" / "Http" / "Requests").mkdir(parents=True)
        (base / "app" / "Http" / "Resources").mkdir(parents=True)
        (base / "app" / "Policies").mkdir(parents=True)
        (base / "app" / "Filters").mkdir(parents=True)
        (base / "tests" / "Feature").mkdir(parents=True)
        (base / "tests" / "Unit").mkdir(parents=True)

        # Create a generator, then monkey-patch a stage to fail
        gen = ModuleGenerator("FailTest", central=True, base_dir=base)
        original_stage = gen._stage_controller

        def failing_stage():
            raise RuntimeError("Simulated failure")

        gen._stage_controller = failing_stage

        with self.assertRaises(RuntimeError):
            gen.run()

        # All files created before the failing stage should be rolled back
        self.assertFalse((base / "app" / "Models" / "FailTest.php").exists())
        self.assertFalse((base / "app" / "Repositories" / "FailTestRepository.php").exists())


class TestCLI(unittest.TestCase):
    """Test CLI argument handling."""

    def test_invalid_base_dir(self):
        result = main(["SomeModule", "--base-dir", "/tmp/nonexistent_dir_xyz"])
        self.assertEqual(result, 1)

    def test_invalid_fields(self):
        # Create a temp project
        tmpdir = tempfile.mkdtemp()
        (Path(tmpdir) / "artisan").write_text("<?php")
        result = main(["SomeModule", "--base-dir", tmpdir, "--fields", "bad_spec_only"])
        self.assertEqual(result, 1)


if __name__ == "__main__":
    unittest.main()
