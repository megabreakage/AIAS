#!/usr/bin/env python3
"""
AIAS Module Generator Script

Scaffolds a complete, production-ready Laravel module in one command,
following every AIAS architectural pattern. Supports both central and
tenant-scoped model types. All generated files contain real, working
code — no TODOs or placeholders.

Requires: Python 3.9+, no external dependencies (stdlib only)

Usage:
    python3 docs/prompts/aias/scripts/generate-module.py <ModuleName> [options]

Examples:
    python3 docs/prompts/aias/scripts/generate-module.py RiskAssessment
    python3 docs/prompts/aias/scripts/generate-module.py AuditStandard --central
    python3 docs/prompts/aias/scripts/generate-module.py Finding \\
        --fields "severity:string:required,status:string:required,resolution:text:nullable"
"""

from __future__ import annotations

import argparse
import logging
import os
import re
import sys
from datetime import datetime
from pathlib import Path
from typing import NamedTuple

# ---------------------------------------------------------------------------
# Minimum Python version check
# ---------------------------------------------------------------------------
if sys.version_info < (3, 9):
    sys.exit("Python 3.9+ is required.")

logger = logging.getLogger("aias-generator")

# ---------------------------------------------------------------------------
# Naming helpers
# ---------------------------------------------------------------------------

# Irregular plurals relevant to typical module names
_IRREGULAR_PLURALS: dict[str, str] = {
    "person": "people",
    "child": "children",
    "man": "men",
    "woman": "women",
    "mouse": "mice",
    "goose": "geese",
    "tooth": "teeth",
    "foot": "feet",
    "ox": "oxen",
    "analysis": "analyses",
    "crisis": "crises",
    "thesis": "theses",
    "datum": "data",
    "medium": "media",
    "criterion": "criteria",
    "index": "indices",
    "matrix": "matrices",
    "vertex": "vertices",
    "appendix": "appendices",
    "status": "statuses",
    "campus": "campuses",
    "bus": "buses",
    "alias": "aliases",
}


def _pluralise(word: str) -> str:
    """Naive English pluralisation — handles common suffixes."""
    low = word.lower()
    if low in _IRREGULAR_PLURALS:
        # Preserve original casing of first char
        plural = _IRREGULAR_PLURALS[low]
        return word[0] + plural[1:] if word[0].isupper() else plural

    if low.endswith(("s", "sh", "ch", "x", "z")):
        return word + "es"
    if low.endswith("y") and len(low) > 1 and low[-2] not in "aeiou":
        return word[:-1] + "ies"
    if low.endswith("f"):
        return word[:-1] + "ves"
    if low.endswith("fe"):
        return word[:-2] + "ves"
    return word + "s"


def _split_pascal(name: str) -> list[str]:
    """Split PascalCase into word list: AuditStandard -> ['Audit', 'Standard']"""
    parts = re.sub(r"([A-Z])", r" \1", name).split()
    return [p.strip() for p in parts if p.strip()]


class Names(NamedTuple):
    """All naming variants derived from a PascalCase model name."""

    pascal: str  # AuditStandard
    camel: str  # auditStandard
    snake: str  # audit_standard
    kebab: str  # audit-standard
    plural_snake: str  # audit_standards
    plural_pascal: str  # AuditStandards
    plural_kebab: str  # audit-standards
    table: str  # audit_standards
    title: str  # Audit Standard
    plural_title: str  # Audit Standards


def derive_names(pascal: str) -> Names:
    """Derive every naming variant from a PascalCase input."""
    parts = _split_pascal(pascal)
    if not parts:
        raise ValueError(f"Cannot derive names from empty input: {pascal!r}")

    snake = "_".join(p.lower() for p in parts)
    kebab = "-".join(p.lower() for p in parts)
    camel = parts[0].lower() + "".join(parts[1:])

    last_word = parts[-1]
    plural_last = _pluralise(last_word)
    plural_parts = parts[:-1] + [plural_last]

    plural_snake = "_".join(p.lower() for p in plural_parts)
    plural_pascal = "".join(plural_parts)
    plural_kebab = "-".join(p.lower() for p in plural_parts)
    title = " ".join(parts)
    plural_title = " ".join(plural_parts)

    return Names(
        pascal=pascal,
        camel=camel,
        snake=snake,
        kebab=kebab,
        plural_snake=plural_snake,
        plural_pascal=plural_pascal,
        plural_kebab=plural_kebab,
        table=plural_snake,
        title=title,
        plural_title=plural_title,
    )


def to_pascal(raw: str) -> str:
    """Normalise any casing (snake, kebab, pascal, camel) to PascalCase."""
    # If it already looks like PascalCase (starts upper, has no _ or -), validate and return
    if re.match(r"^[A-Z][a-zA-Z0-9]*$", raw) and _split_pascal(raw):
        return raw
    # Replace hyphens and underscores with spaces, split on boundaries, then title-case
    cleaned = re.sub(r"[-_]+", " ", raw)
    return "".join(w.capitalize() for w in cleaned.split())


# ---------------------------------------------------------------------------
# Field parsing
# ---------------------------------------------------------------------------

_VALID_TYPES = {"string", "text", "integer", "boolean", "date", "datetime", "decimal"}
_VALID_MODIFIERS = {"required", "nullable", "unique"}

# Maps field type -> migration column method
_MIGRATION_TYPE_MAP: dict[str, str] = {
    "string": "string",
    "text": "text",
    "integer": "integer",
    "boolean": "boolean",
    "date": "date",
    "datetime": "dateTime",
    "decimal": "decimal",
}

# Maps field type -> PHP cast (only for types needing explicit cast)
_CAST_MAP: dict[str, str] = {
    "boolean": "boolean",
    "date": "date",
    "datetime": "datetime",
    "decimal": "float",
}


class FieldSpec(NamedTuple):
    name: str
    type: str
    required: bool
    nullable: bool
    unique: bool


def parse_fields(raw: str) -> list[FieldSpec]:
    """Parse --fields string into list of FieldSpec."""
    if not raw or not raw.strip():
        return []

    fields: list[FieldSpec] = []
    for entry in raw.split(","):
        entry = entry.strip()
        if not entry:
            continue

        parts = entry.split(":")
        if len(parts) < 2:
            raise ValueError(
                f"Invalid field spec '{entry}'. Expected format: name:type[:modifiers]"
            )

        name = parts[0].strip()
        ftype = parts[1].strip().lower()
        modifiers = {m.strip().lower() for m in parts[2:] if m.strip()}

        if ftype not in _VALID_TYPES:
            raise ValueError(
                f"Unknown field type '{ftype}' for field '{name}'. "
                f"Valid types: {', '.join(sorted(_VALID_TYPES))}"
            )

        invalid = modifiers - _VALID_MODIFIERS
        if invalid:
            raise ValueError(
                f"Unknown modifier(s) {invalid} for field '{name}'. "
                f"Valid modifiers: {', '.join(sorted(_VALID_MODIFIERS))}"
            )

        fields.append(
            FieldSpec(
                name=name,
                type=ftype,
                required="required" in modifiers,
                nullable="nullable" in modifiers,
                unique="unique" in modifiers,
            )
        )

    return fields


# ---------------------------------------------------------------------------
# Rollback manager
# ---------------------------------------------------------------------------


class RollbackManager:
    """Tracks created files/dirs and removes them on failure."""

    def __init__(self) -> None:
        self._files: list[Path] = []
        self._dirs: list[Path] = []

    def track_file(self, path: Path) -> None:
        self._files.append(path)

    def track_dir(self, path: Path) -> None:
        self._dirs.append(path)

    def rollback(self) -> None:
        logger.warning("\u23ea  Rolling back\u2026")
        for f in reversed(self._files):
            if f.exists():
                f.unlink()
                logger.debug("  Removed file: %s", f)
        for d in reversed(self._dirs):
            if d.exists() and not any(d.iterdir()):
                d.rmdir()
                logger.debug("  Removed empty dir: %s", d)
        logger.warning("\u2714  Rollback complete \u2014 no partial files left.")


# ---------------------------------------------------------------------------
# File writer
# ---------------------------------------------------------------------------


def write_file(
    path: Path,
    content: str,
    *,
    overwrite: bool,
    dry_run: bool,
    rollback: RollbackManager,
) -> bool:
    """Write content to path. Returns True if written, False if skipped."""
    rel = path
    if path.exists() and not overwrite:
        logger.warning("  SKIP (exists): %s", rel)
        return False

    if dry_run:
        logger.info("  [DRY-RUN] Would create: %s", rel)
        return True

    # Create parent dirs
    parent = path.parent
    created_dirs: list[Path] = []
    parts_to_create: list[Path] = []
    cur = parent
    while not cur.exists():
        parts_to_create.append(cur)
        cur = cur.parent

    for d in reversed(parts_to_create):
        d.mkdir(parents=False, exist_ok=True)
        created_dirs.append(d)
        rollback.track_dir(d)

    path.write_text(content, encoding="utf-8")
    rollback.track_file(path)
    logger.info("  CREATED: %s", rel)
    return True


# ---------------------------------------------------------------------------
# Template generators — each returns (relative_path, content)
# ---------------------------------------------------------------------------


def _gen_migration(n: Names, fields: list[FieldSpec], central: bool, base: Path) -> tuple[Path, str]:
    """Stage 1: Migration."""
    ts = datetime.now().strftime("%Y_%m_%d_%H%M%S")
    if central:
        rel = base / "database" / "migrations" / f"{ts}_create_{n.table}_table.php"
    else:
        rel = base / "database" / "migrations" / "tenant" / f"{ts}_create_{n.table}_table.php"

    lines: list[str] = []
    lines.append("<?php")
    lines.append("")
    lines.append("declare(strict_types=1);")
    lines.append("")
    lines.append("use Illuminate\\Database\\Migrations\\Migration;")
    lines.append("use Illuminate\\Database\\Schema\\Blueprint;")
    lines.append("use Illuminate\\Support\\Facades\\Schema;")
    lines.append("")
    lines.append("return new class extends Migration")
    lines.append("{")
    lines.append("    public function up(): void")
    lines.append("    {")
    lines.append(f"        Schema::create('{n.table}', function (Blueprint $table): void {{")
    lines.append("            $table->id();")
    lines.append("            $table->uuid('identifier')->unique()->index();")

    if not central:
        lines.append("            $table->unsignedBigInteger('tenant_id')->index();")

    # Add name/title field by default if no fields specified
    if not fields:
        lines.append("            $table->string('name');")
        lines.append("            $table->text('description')->nullable();")
        lines.append("            $table->boolean('is_active')->default(true);")

    for f in fields:
        col_method = _MIGRATION_TYPE_MAP[f.type]
        col_line = f"            $table->{col_method}('{f.name}')"
        if f.type == "decimal":
            col_line = f"            $table->decimal('{f.name}', 15, 2)"
        if f.nullable:
            col_line += "->nullable()"
        if f.unique:
            col_line += "->unique()"
        col_line += ";"
        lines.append(col_line)

    lines.append("            $table->unsignedBigInteger('created_by')->nullable();")
    lines.append("            $table->unsignedBigInteger('updated_by')->nullable();")
    lines.append("            $table->timestamps();")
    lines.append("            $table->softDeletes();")
    lines.append("        });")
    lines.append("    }")
    lines.append("")
    lines.append("    public function down(): void")
    lines.append("    {")
    lines.append(f"        Schema::dropIfExists('{n.table}');")
    lines.append("    }")
    lines.append("};")
    lines.append("")

    return rel, "\n".join(lines)


def _gen_model(n: Names, fields: list[FieldSpec], central: bool, base: Path) -> tuple[Path, str]:
    """Stage 2: Model."""
    rel = base / "app" / "Models" / f"{n.pascal}.php"

    imports = [
        "use App\\Support\\Concerns\\HasUuidIdentifier;",
        "use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;",
        "use Illuminate\\Database\\Eloquent\\Model;",
        "use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;",
        "use Illuminate\\Database\\Eloquent\\SoftDeletes;",
    ]

    if central:
        imports.append("use OwenIt\\Auditing\\Auditable;")
        imports.append("use OwenIt\\Auditing\\Contracts\\Auditable as AuditableContract;")

    imports.sort()

    traits = ["HasFactory", "HasUuidIdentifier", "SoftDeletes"]
    if central:
        traits.insert(0, "Auditable")

    # Build fillable
    fillable = ["'identifier'"]
    if not central:
        fillable.append("'tenant_id'")
    if not fields:
        fillable.extend(["'name'", "'description'", "'is_active'"])
    else:
        for f in fields:
            fillable.append(f"'{f.name}'")
    fillable.extend(["'created_by'", "'updated_by'"])

    # Build casts
    casts: list[tuple[str, str]] = []
    if not fields:
        casts.append(("is_active", "boolean"))
    else:
        for f in fields:
            if f.type in _CAST_MAP:
                casts.append((f.name, _CAST_MAP[f.type]))
    casts.extend([
        ("created_at", "datetime"),
        ("updated_at", "datetime"),
        ("deleted_at", "datetime"),
    ])

    implements = " implements AuditableContract" if central else ""

    lines: list[str] = []
    lines.append("<?php")
    lines.append("")
    lines.append("declare(strict_types=1);")
    lines.append("")
    lines.append("namespace App\\Models;")
    lines.append("")
    for imp in imports:
        lines.append(imp)
    lines.append("")
    lines.append(f"final class {n.pascal} extends Model{implements}")
    lines.append("{")

    for t in traits:
        lines.append(f"    use {t};")
    lines.append("")

    if central:
        lines.append("    protected $connection = 'central';")
        lines.append("")

    lines.append(f"    protected $table = '{n.table}';")
    lines.append("")

    lines.append("    protected $fillable = [")
    for fv in fillable:
        lines.append(f"        {fv},")
    lines.append("    ];")
    lines.append("")

    lines.append("    protected function casts(): array")
    lines.append("    {")
    lines.append("        return [")
    for cname, ctype in casts:
        lines.append(f"            '{cname}' => '{ctype}',")
    lines.append("        ];")
    lines.append("    }")
    lines.append("")

    lines.append("    public function getRouteKeyName(): string")
    lines.append("    {")
    lines.append("        return 'identifier';")
    lines.append("    }")
    lines.append("")

    lines.append("    public function createdBy(): BelongsTo")
    lines.append("    {")
    lines.append("        return $this->belongsTo(User::class, 'created_by');")
    lines.append("    }")
    lines.append("")

    lines.append("    public function updatedBy(): BelongsTo")
    lines.append("    {")
    lines.append("        return $this->belongsTo(User::class, 'updated_by');")
    lines.append("    }")
    lines.append("}")
    lines.append("")

    return rel, "\n".join(lines)


def _gen_repository(n: Names, central: bool, base: Path) -> tuple[Path, str]:
    """Stage 3: Repository."""
    rel = base / "app" / "Repositories" / f"{n.pascal}Repository.php"

    lines: list[str] = []
    lines.append("<?php")
    lines.append("")
    lines.append("declare(strict_types=1);")
    lines.append("")
    lines.append("namespace App\\Repositories;")
    lines.append("")
    lines.append(f"use App\\Models\\{n.pascal};")
    lines.append("use Illuminate\\Contracts\\Pagination\\LengthAwarePaginator;")
    lines.append("use Illuminate\\Database\\Eloquent\\Builder;")
    lines.append("use Illuminate\\Database\\Eloquent\\Model;")
    lines.append("")
    lines.append(f"final class {n.pascal}Repository extends BaseRepository")
    lines.append("{")
    lines.append("    protected function model(): string")
    lines.append("    {")
    lines.append(f"        return {n.pascal}::class;")
    lines.append("    }")
    lines.append("")

    # browse method
    lines.append(f"    public function browse{n.plural_pascal}(")
    lines.append("        array $filters = [],")
    lines.append("        int $page = 1,")
    lines.append("        int $perPage = 15,")
    lines.append("    ): LengthAwarePaginator {")
    lines.append("        $query = $this->newQuery()->with(['createdBy', 'updatedBy']);")
    lines.append("")

    if not central:
        lines.append("        if (! auth()->user()?->hasRole('super-admin')) {")
        lines.append("            $query->where('tenant_id', auth()->user()?->tenant_id);")
        lines.append("        }")
        lines.append("")

    lines.append("        if (! empty($filters['search'])) {")
    lines.append("            $search = $filters['search'];")
    lines.append("            $query->where(function (Builder $q) use ($search): void {")
    lines.append("                $q->where('name', 'like', \"%{$search}%\")")
    lines.append("                  ->orWhere('description', 'like', \"%{$search}%\");")
    lines.append("            });")
    lines.append("        }")
    lines.append("")
    lines.append("        if (isset($filters['is_active'])) {")
    lines.append("            $query->where('is_active', (bool) $filters['is_active']);")
    lines.append("        }")
    lines.append("")
    lines.append("        return $query->orderBy('created_at', 'desc')")
    lines.append("            ->paginate(perPage: min($perPage, 100), page: max($page, 1));")
    lines.append("    }")
    lines.append("")

    # read method
    lines.append(f"    public function read{n.pascal}(string $identifier): Model")
    lines.append("    {")
    lines.append("        return $this->newQuery()")
    lines.append("            ->with(['createdBy', 'updatedBy'])")
    lines.append("            ->where('identifier', $identifier)")
    lines.append("            ->firstOrFail();")
    lines.append("    }")
    lines.append("")

    # create method
    lines.append(f"    public function create{n.pascal}(array $data): Model")
    lines.append("    {")
    lines.append("        return $this->create($data);")
    lines.append("    }")
    lines.append("")

    # update method
    lines.append(f"    public function update{n.pascal}(string $identifier, array $data): Model")
    lines.append("    {")
    lines.append("        $model = $this->findByIdentifier($identifier);")
    lines.append("")
    lines.append("        return $this->update($model, $data);")
    lines.append("    }")
    lines.append("")

    # delete method
    lines.append(f"    public function delete{n.pascal}(string $identifier): void")
    lines.append("    {")
    lines.append("        $model = $this->findByIdentifier($identifier);")
    lines.append("        $this->delete($model);")
    lines.append("    }")
    lines.append("")

    # restore method
    lines.append(f"    public function restore{n.pascal}(string $identifier): Model")
    lines.append("    {")
    lines.append(f"        $model = {n.pascal}::query()->withTrashed()")
    lines.append("            ->where('identifier', $identifier)")
    lines.append("            ->firstOrFail();")
    lines.append("")
    lines.append("        $model->restore();")
    lines.append("")
    lines.append("        return $model->fresh();")
    lines.append("    }")
    lines.append("}")
    lines.append("")

    return rel, "\n".join(lines)


def _gen_filters(n: Names, base: Path) -> list[tuple[Path, str]]:
    """Stage 4: Filter classes."""
    results: list[tuple[Path, str]] = []
    filter_dir = base / "app" / "Filters" / n.plural_pascal

    # Main filters class
    main_path = filter_dir / f"{n.pascal}Filters.php"
    main_lines = [
        "<?php",
        "",
        "declare(strict_types=1);",
        "",
        f"namespace App\\Filters\\{n.plural_pascal};",
        "",
        "use App\\Filters\\EloquentFilter;",
        f"use App\\Filters\\{n.plural_pascal}\\Filters\\IsActiveFilter;",
        f"use App\\Filters\\{n.plural_pascal}\\Filters\\SearchTermFilter;",
        "",
        f"final class {n.pascal}Filters extends EloquentFilter",
        "{",
        "    protected array $filters = [",
        "        'search' => SearchTermFilter::class,",
        "        'is_active' => IsActiveFilter::class,",
        "    ];",
        "}",
        "",
    ]
    results.append((main_path, "\n".join(main_lines)))

    # SearchTermFilter
    search_path = filter_dir / "Filters" / "SearchTermFilter.php"
    search_lines = [
        "<?php",
        "",
        "declare(strict_types=1);",
        "",
        f"namespace App\\Filters\\{n.plural_pascal}\\Filters;",
        "",
        "use App\\Filters\\EloquentFilter;",
        "use Illuminate\\Database\\Eloquent\\Builder;",
        "",
        "final class SearchTermFilter extends EloquentFilter",
        "{",
        "    public function __construct(",
        "        protected string $search",
        "    ) {}",
        "",
        "    public function apply(Builder $query): Builder",
        "    {",
        "        $search = str_replace(",
        "            ['%', '_'],",
        "            ['\\%', '\\_'],",
        "            trim($this->search)",
        "        );",
        "",
        "        return $query->where(function (Builder $q) use ($search): void {",
        "            $q->where('name', 'like', \"%{$search}%\")",
        "              ->orWhere('description', 'like', \"%{$search}%\");",
        "        });",
        "    }",
        "}",
        "",
    ]
    results.append((search_path, "\n".join(search_lines)))

    # IsActiveFilter
    active_path = filter_dir / "Filters" / "IsActiveFilter.php"
    active_lines = [
        "<?php",
        "",
        "declare(strict_types=1);",
        "",
        f"namespace App\\Filters\\{n.plural_pascal}\\Filters;",
        "",
        "use App\\Filters\\EloquentFilter;",
        "use Illuminate\\Database\\Eloquent\\Builder;",
        "",
        "final class IsActiveFilter extends EloquentFilter",
        "{",
        "    public function __construct(",
        "        protected bool $isActive",
        "    ) {}",
        "",
        "    public function apply(Builder $query): Builder",
        "    {",
        "        return $query->where('is_active', $this->isActive);",
        "    }",
        "}",
        "",
    ]
    results.append((active_path, "\n".join(active_lines)))

    return results


def _gen_form_requests(n: Names, fields: list[FieldSpec], base: Path) -> list[tuple[Path, str]]:
    """Stage 5: Form Requests."""
    results: list[tuple[Path, str]] = []
    req_dir = base / "app" / "Http" / "Requests" / n.plural_pascal

    # Build validation rules
    create_rules: list[str] = []
    update_rules: list[str] = []

    if not fields:
        create_rules.append(f"            'name' => ['required', 'string', 'max:255', Rule::unique('{n.table}', 'name')],")
        create_rules.append(f"            'description' => ['nullable', 'string', 'max:5000'],")
        create_rules.append(f"            'is_active' => ['sometimes', 'boolean'],")

        update_rules.append(f"            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('{n.table}', 'name')->ignore($this->route('{n.snake}'), 'identifier')],")
        update_rules.append(f"            'description' => ['nullable', 'string', 'max:5000'],")
        update_rules.append(f"            'is_active' => ['sometimes', 'boolean'],")
    else:
        for f in fields:
            rule_parts: list[str] = []
            upd_rule_parts: list[str] = []

            if f.required:
                rule_parts.append("'required'")
                upd_rule_parts.extend(["'sometimes'", "'required'"])
            elif f.nullable:
                rule_parts.append("'nullable'")
                upd_rule_parts.append("'nullable'")
            else:
                rule_parts.append("'sometimes'")
                upd_rule_parts.append("'sometimes'")

            # Type validation
            if f.type == "string":
                rule_parts.append("'string'")
                rule_parts.append("'max:255'")
                upd_rule_parts.append("'string'")
                upd_rule_parts.append("'max:255'")
            elif f.type == "text":
                rule_parts.append("'string'")
                rule_parts.append("'max:65535'")
                upd_rule_parts.append("'string'")
                upd_rule_parts.append("'max:65535'")
            elif f.type == "integer":
                rule_parts.append("'integer'")
                upd_rule_parts.append("'integer'")
            elif f.type == "boolean":
                rule_parts.append("'boolean'")
                upd_rule_parts.append("'boolean'")
            elif f.type in ("date", "datetime"):
                rule_parts.append("'date'")
                upd_rule_parts.append("'date'")
            elif f.type == "decimal":
                rule_parts.append("'numeric'")
                upd_rule_parts.append("'numeric'")

            if f.unique:
                rule_parts.append(f"Rule::unique('{n.table}', '{f.name}')")
                upd_rule_parts.append(
                    f"Rule::unique('{n.table}', '{f.name}')->ignore($this->route('{n.snake}'), 'identifier')"
                )

            rules_str = ", ".join(rule_parts)
            upd_rules_str = ", ".join(upd_rule_parts)
            create_rules.append(f"            '{f.name}' => [{rules_str}],")
            update_rules.append(f"            '{f.name}' => [{upd_rules_str}],")

    # --- Create Request ---
    create_path = req_dir / f"Create{n.pascal}Request.php"
    cl = [
        "<?php",
        "",
        "declare(strict_types=1);",
        "",
        f"namespace App\\Http\\Requests\\{n.plural_pascal};",
        "",
        "use Illuminate\\Foundation\\Http\\FormRequest;",
        "use Illuminate\\Support\\Facades\\Gate;",
        "use Illuminate\\Validation\\Rule;",
        "",
        f"final class Create{n.pascal}Request extends FormRequest",
        "{",
        "    public function authorize(): bool",
        "    {",
        f"        return Gate::allows('create', \\App\\Models\\{n.pascal}::class);",
        "    }",
        "",
        "    public function rules(): array",
        "    {",
        "        return [",
    ]
    cl.extend(create_rules)
    cl.extend([
        "        ];",
        "    }",
        "",
        "    public function messages(): array",
        "    {",
        "        return [",
        f"            'name.required' => 'The {n.title.lower()} name is required.',",
        f"            'name.unique' => 'A {n.title.lower()} with this name already exists.',",
        "        ];",
        "    }",
        "}",
        "",
    ])
    results.append((create_path, "\n".join(cl)))

    # --- Update Request ---
    update_path = req_dir / f"Update{n.pascal}Request.php"
    ul = [
        "<?php",
        "",
        "declare(strict_types=1);",
        "",
        f"namespace App\\Http\\Requests\\{n.plural_pascal};",
        "",
        "use Illuminate\\Foundation\\Http\\FormRequest;",
        "use Illuminate\\Support\\Facades\\Gate;",
        "use Illuminate\\Validation\\Rule;",
        "",
        f"final class Update{n.pascal}Request extends FormRequest",
        "{",
        "    public function authorize(): bool",
        "    {",
        "        return true;",
        "    }",
        "",
        "    public function rules(): array",
        "    {",
        "        return [",
    ]
    ul.extend(update_rules)
    ul.extend([
        "        ];",
        "    }",
        "",
        "    public function messages(): array",
        "    {",
        "        return [",
        f"            'name.required' => 'The {n.title.lower()} name is required.',",
        f"            'name.unique' => 'A {n.title.lower()} with this name already exists.',",
        "        ];",
        "    }",
        "}",
        "",
    ])
    results.append((update_path, "\n".join(ul)))

    return results


def _gen_resources(n: Names, fields: list[FieldSpec], base: Path) -> list[tuple[Path, str]]:
    """Stage 6: API Resource and Collection."""
    results: list[tuple[Path, str]] = []
    res_dir = base / "app" / "Http" / "Resources" / n.plural_pascal

    # Build resource data fields
    data_lines: list[str] = []
    data_lines.append("            'id' => $this->identifier,")
    if not fields:
        data_lines.append("            'name' => $this->name,")
        data_lines.append("            'description' => $this->description,")
        data_lines.append("            'is_active' => $this->is_active,")
    else:
        for f in fields:
            if f.type in ("date",):
                data_lines.append(f"            '{f.name}' => $this->{f.name}?->toDateString(),")
            elif f.type in ("datetime",):
                data_lines.append(f"            '{f.name}' => $this->{f.name}?->toISOString(),")
            else:
                data_lines.append(f"            '{f.name}' => $this->{f.name},")

    data_lines.append("            'created_by' => $this->whenLoaded('createdBy', fn () => [")
    data_lines.append("                'id' => $this->createdBy->identifier,")
    data_lines.append("                'name' => $this->createdBy->full_name ?? $this->createdBy->name ?? null,")
    data_lines.append("            ]),")
    data_lines.append("            'updated_by' => $this->whenLoaded('updatedBy', fn () => [")
    data_lines.append("                'id' => $this->updatedBy->identifier,")
    data_lines.append("                'name' => $this->updatedBy->full_name ?? $this->updatedBy->name ?? null,")
    data_lines.append("            ]),")
    data_lines.append("            'created_at' => $this->created_at?->toISOString(),")
    data_lines.append("            'updated_at' => $this->updated_at?->toISOString(),")

    # --- Resource ---
    res_path = res_dir / f"{n.pascal}Resource.php"
    rl = [
        "<?php",
        "",
        "declare(strict_types=1);",
        "",
        f"namespace App\\Http\\Resources\\{n.plural_pascal};",
        "",
        "use Illuminate\\Http\\Request;",
        "use Illuminate\\Http\\Resources\\Json\\JsonResource;",
        "",
        f"final class {n.pascal}Resource extends JsonResource",
        "{",
        "    protected ?string $message = null;",
        "",
        "    protected array $metadata = [];",
        "",
        f"    public function setMessage(string $message): static",
        "    {",
        "        $this->message = $message;",
        "",
        "        return $this;",
        "    }",
        "",
        f"    public function addMetadata(string $key, mixed $value): static",
        "    {",
        "        $this->metadata[$key] = $value;",
        "",
        "        return $this;",
        "    }",
        "",
        "    public function toArray(Request $request): array",
        "    {",
        "        return [",
    ]
    rl.extend(data_lines)
    rl.extend([
        "        ];",
        "    }",
        "",
        "    public function with(Request $request): array",
        "    {",
        "        $extra = [];",
        "",
        "        if ($this->message !== null) {",
        "            $extra['message'] = $this->message;",
        "        }",
        "",
        "        if (! empty($this->metadata)) {",
        "            $extra['metadata'] = $this->metadata;",
        "        }",
        "",
        "        return $extra;",
        "    }",
        "}",
        "",
    ])
    results.append((res_path, "\n".join(rl)))

    # --- Collection ---
    col_path = res_dir / f"{n.pascal}Collection.php"
    coll = [
        "<?php",
        "",
        "declare(strict_types=1);",
        "",
        f"namespace App\\Http\\Resources\\{n.plural_pascal};",
        "",
        "use Illuminate\\Http\\Request;",
        "use Illuminate\\Http\\Resources\\Json\\ResourceCollection;",
        "",
        f"final class {n.pascal}Collection extends ResourceCollection",
        "{",
        f"    public $collects = {n.pascal}Resource::class;",
        "",
        "    protected ?string $message = null;",
        "",
        "    protected array $metadata = [];",
        "",
        f"    public function setMessage(string $message): static",
        "    {",
        "        $this->message = $message;",
        "",
        "        return $this;",
        "    }",
        "",
        f"    public function addMetadata(string $key, mixed $value): static",
        "    {",
        "        $this->metadata[$key] = $value;",
        "",
        "        return $this;",
        "    }",
        "",
        "    public function toArray(Request $request): array",
        "    {",
        "        return $this->collection->toArray();",
        "    }",
        "",
        "    public function with(Request $request): array",
        "    {",
        "        $extra = [];",
        "",
        "        if ($this->message !== null) {",
        "            $extra['message'] = $this->message;",
        "        }",
        "",
        "        if (! empty($this->metadata)) {",
        "            $extra['metadata'] = $this->metadata;",
        "        }",
        "",
        "        return $extra;",
        "    }",
        "}",
        "",
    ]
    results.append((col_path, "\n".join(coll)))

    return results


def _gen_policy(n: Names, base: Path) -> tuple[Path, str]:
    """Stage 7: Policy."""
    rel = base / "app" / "Policies" / f"{n.pascal}Policy.php"

    lines = [
        "<?php",
        "",
        "declare(strict_types=1);",
        "",
        "namespace App\\Policies;",
        "",
        f"use App\\Models\\{n.pascal};",
        "use App\\Models\\User;",
        "use Illuminate\\Auth\\Access\\HandlesAuthorization;",
        "",
        f"final class {n.pascal}Policy",
        "{",
        "    use HandlesAuthorization;",
        "",
        "    public function viewAny(User $user): bool",
        "    {",
        f"        return $user->hasPermissionTo('{n.plural_kebab}.view');",
        "    }",
        "",
        f"    public function view(User $user, {n.pascal} ${n.camel}): bool",
        "    {",
        f"        return $user->hasPermissionTo('{n.plural_kebab}.view');",
        "    }",
        "",
        "    public function create(User $user): bool",
        "    {",
        f"        return $user->hasPermissionTo('{n.plural_kebab}.create');",
        "    }",
        "",
        f"    public function update(User $user, {n.pascal} ${n.camel}): bool",
        "    {",
        f"        return $user->hasPermissionTo('{n.plural_kebab}.edit');",
        "    }",
        "",
        f"    public function delete(User $user, {n.pascal} ${n.camel}): bool",
        "    {",
        f"        return $user->hasPermissionTo('{n.plural_kebab}.delete');",
        "    }",
        "",
        f"    public function restore(User $user, {n.pascal} ${n.camel}): bool",
        "    {",
        f"        return $user->hasPermissionTo('{n.plural_kebab}.delete');",
        "    }",
        "}",
        "",
    ]
    return rel, "\n".join(lines)


def _gen_controller(n: Names, central: bool, base: Path) -> tuple[Path, str]:
    """Stage 8: Controller."""
    rel = base / "app" / "Http" / "Controllers" / f"{n.pascal}Controller.php"

    lines = [
        "<?php",
        "",
        "declare(strict_types=1);",
        "",
        "namespace App\\Http\\Controllers;",
        "",
        f"use App\\Http\\Requests\\{n.plural_pascal}\\Create{n.pascal}Request;",
        f"use App\\Http\\Requests\\{n.plural_pascal}\\Update{n.pascal}Request;",
        f"use App\\Http\\Resources\\{n.plural_pascal}\\{n.pascal}Collection;",
        f"use App\\Http\\Resources\\{n.plural_pascal}\\{n.pascal}Resource;",
        f"use App\\Models\\{n.pascal};",
        f"use App\\Repositories\\{n.pascal}Repository;",
        "use Illuminate\\Database\\Eloquent\\ModelNotFoundException;",
        "use Illuminate\\Http\\JsonResponse;",
        "use Illuminate\\Http\\Request;",
        "use Illuminate\\Support\\Facades\\DB;",
        "use Illuminate\\Support\\Facades\\Gate;",
        "use Illuminate\\Support\\Facades\\Log;",
        "use Symfony\\Component\\HttpFoundation\\Response;",
        "",
        f"final class {n.pascal}Controller extends Controller",
        "{",
        "    public function __construct(",
        f"        protected {n.pascal}Repository $repository",
        "    ) {}",
        "",
        "    public function index(Request $request): JsonResponse",
        "    {",
        f"        Gate::authorize('viewAny', {n.pascal}::class);",
        "",
        "        $filters = [",
        "            'search' => $request->query('search'),",
        "            'is_active' => $request->query('is_active'),",
        "        ];",
        "",
        "        $page = $request->integer('page', 1);",
        "        $perPage = $request->integer('per_page', 15);",
        "",
        f"        ${n.plural_snake} = $this->repository->browse{n.plural_pascal}(",
        "            filters: $filters,",
        "            page: $page,",
        "            perPage: $perPage,",
        "        );",
        "",
        f"        return (new {n.pascal}Collection(${n.plural_snake}))",
        f"            ->setMessage('{n.plural_title} retrieved successfully')",
        "            ->response()",
        "            ->setStatusCode(Response::HTTP_OK);",
        "    }",
        "",
        f"    public function store(Create{n.pascal}Request $request): JsonResponse",
        "    {",
        f"        Gate::authorize('create', {n.pascal}::class);",
        "",
        "        try {",
        "            $data = $request->validated();",
        "",
        f"            Log::info('Creating {n.title.lower()}', ['data' => $data]);",
        "",
        f"            ${n.camel} = DB::transaction(function () use ($data) {{",
        f"                return $this->repository->create{n.pascal}($data);",
        "            });",
        "",
        f"            Log::info('{n.title} created', ['id' => ${n.camel}->id]);",
        "",
        f"            return (new {n.pascal}Resource(${n.camel}))",
        f"                ->setMessage('{n.title} created successfully')",
        "                ->response()",
        "                ->setStatusCode(Response::HTTP_CREATED);",
        "        } catch (\\Throwable $e) {",
        f"            Log::error('Failed to create {n.title.lower()}', [",
        "                'error' => $e->getMessage(),",
        "                'trace' => $e->getTraceAsString(),",
        "            ]);",
        "",
        "            return response()->json([",
        "                'status' => 'error',",
        f"                'message' => 'Failed to create {n.title.lower()}',",
        "                'data' => null,",
        "            ], Response::HTTP_INTERNAL_SERVER_ERROR);",
        "        }",
        "    }",
        "",
        "    public function show(string $id): JsonResponse",
        "    {",
        "        try {",
        f"            ${n.camel} = $this->repository->read{n.pascal}($id);",
        "",
        f"            Gate::authorize('view', ${n.camel});",
        "",
        f"            return (new {n.pascal}Resource(${n.camel}))",
        f"                ->setMessage('{n.title} retrieved successfully')",
        "                ->response();",
        "        } catch (ModelNotFoundException) {",
        "            return response()->json([",
        "                'status' => 'error',",
        f"                'message' => '{n.title} not found',",
        "                'data' => null,",
        "            ], Response::HTTP_NOT_FOUND);",
        "        }",
        "    }",
        "",
        f"    public function update(Update{n.pascal}Request $request, string $id): JsonResponse",
        "    {",
        "        try {",
        f"            ${n.camel} = $this->repository->read{n.pascal}($id);",
        "",
        f"            Gate::authorize('update', ${n.camel});",
        "",
        "            $data = $request->validated();",
        "",
        f"            Log::info('Updating {n.title.lower()}', ['id' => $id]);",
        "",
        f"            ${n.camel} = DB::transaction(function () use ($id, $data) {{",
        f"                return $this->repository->update{n.pascal}($id, $data);",
        "            });",
        "",
        f"            Log::info('{n.title} updated', ['id' => ${n.camel}->id]);",
        "",
        f"            return (new {n.pascal}Resource(${n.camel}))",
        f"                ->setMessage('{n.title} updated successfully')",
        "                ->response();",
        "        } catch (ModelNotFoundException) {",
        "            return response()->json([",
        "                'status' => 'error',",
        f"                'message' => '{n.title} not found',",
        "                'data' => null,",
        "            ], Response::HTTP_NOT_FOUND);",
        "        } catch (\\Throwable $e) {",
        f"            Log::error('Failed to update {n.title.lower()}', [",
        "                'error' => $e->getMessage(),",
        "                'trace' => $e->getTraceAsString(),",
        "            ]);",
        "",
        "            return response()->json([",
        "                'status' => 'error',",
        f"                'message' => 'Failed to update {n.title.lower()}',",
        "                'data' => null,",
        "            ], Response::HTTP_INTERNAL_SERVER_ERROR);",
        "        }",
        "    }",
        "",
        "    public function destroy(string $id): JsonResponse",
        "    {",
        "        try {",
        f"            ${n.camel} = $this->repository->read{n.pascal}($id);",
        "",
        f"            Gate::authorize('delete', ${n.camel});",
        "",
        f"            Log::info('Deleting {n.title.lower()}', ['id' => $id]);",
        "",
        "            DB::transaction(function () use ($id): void {",
        f"                $this->repository->delete{n.pascal}($id);",
        "            });",
        "",
        f"            Log::info('{n.title} deleted', ['id' => $id]);",
        "",
        "            return response()->json([",
        "                'status' => 'success',",
        f"                'message' => '{n.title} deleted successfully',",
        "                'data' => null,",
        "            ], Response::HTTP_OK);",
        "        } catch (ModelNotFoundException) {",
        "            return response()->json([",
        "                'status' => 'error',",
        f"                'message' => '{n.title} not found',",
        "                'data' => null,",
        "            ], Response::HTTP_NOT_FOUND);",
        "        } catch (\\Throwable $e) {",
        f"            Log::error('Failed to delete {n.title.lower()}', [",
        "                'error' => $e->getMessage(),",
        "                'trace' => $e->getTraceAsString(),",
        "            ]);",
        "",
        "            return response()->json([",
        "                'status' => 'error',",
        f"                'message' => 'Failed to delete {n.title.lower()}',",
        "                'data' => null,",
        "            ], Response::HTTP_INTERNAL_SERVER_ERROR);",
        "        }",
        "    }",
        "",
        "    public function restore(string $id): JsonResponse",
        "    {",
        "        try {",
        f"            ${n.camel} = $this->repository->read{n.pascal}($id);",
        "",
        f"            Gate::authorize('restore', ${n.camel});",
        "",
        f"            Log::info('Restoring {n.title.lower()}', ['id' => $id]);",
        "",
        f"            ${n.camel} = DB::transaction(function () use ($id) {{",
        f"                return $this->repository->restore{n.pascal}($id);",
        "            });",
        "",
        f"            Log::info('{n.title} restored', ['id' => $id]);",
        "",
        f"            return (new {n.pascal}Resource(${n.camel}))",
        f"                ->setMessage('{n.title} restored successfully')",
        "                ->response();",
        "        } catch (ModelNotFoundException) {",
        "            return response()->json([",
        "                'status' => 'error',",
        f"                'message' => '{n.title} not found',",
        "                'data' => null,",
        "            ], Response::HTTP_NOT_FOUND);",
        "        } catch (\\Throwable $e) {",
        f"            Log::error('Failed to restore {n.title.lower()}', [",
        "                'error' => $e->getMessage(),",
        "                'trace' => $e->getTraceAsString(),",
        "            ]);",
        "",
        "            return response()->json([",
        "                'status' => 'error',",
        f"                'message' => 'Failed to restore {n.title.lower()}',",
        "                'data' => null,",
        "            ], Response::HTTP_INTERNAL_SERVER_ERROR);",
        "        }",
        "    }",
        "}",
        "",
    ]
    return rel, "\n".join(lines)


def _gen_factory(n: Names, fields: list[FieldSpec], central: bool, base: Path) -> tuple[Path, str]:
    """Stage 9a: Factory."""
    rel = base / "database" / "factories" / f"{n.pascal}Factory.php"

    # Build fake data
    fake_lines: list[str] = []
    fake_lines.append("            'identifier' => fake()->uuid(),")
    if not fields:
        fake_lines.append("            'name' => fake()->unique()->words(3, true),")
        fake_lines.append("            'description' => fake()->sentence(),")
        fake_lines.append("            'is_active' => true,")
    else:
        for f in fields:
            if f.type == "string":
                fake_lines.append(f"            '{f.name}' => fake()->words(3, true),")
            elif f.type == "text":
                fake_lines.append(f"            '{f.name}' => fake()->paragraph(),")
            elif f.type == "integer":
                fake_lines.append(f"            '{f.name}' => fake()->numberBetween(1, 100),")
            elif f.type == "boolean":
                fake_lines.append(f"            '{f.name}' => fake()->boolean(),")
            elif f.type == "date":
                fake_lines.append(f"            '{f.name}' => fake()->date(),")
            elif f.type == "datetime":
                fake_lines.append(f"            '{f.name}' => fake()->dateTime(),")
            elif f.type == "decimal":
                fake_lines.append(f"            '{f.name}' => fake()->randomFloat(2, 0, 10000),")

    lines = [
        "<?php",
        "",
        "declare(strict_types=1);",
        "",
        "namespace Database\\Factories;",
        "",
        f"use App\\Models\\{n.pascal};",
        "use Illuminate\\Database\\Eloquent\\Factories\\Factory;",
        "",
        "/**",
        f" * @extends Factory<{n.pascal}>",
        " */",
        f"final class {n.pascal}Factory extends Factory",
        "{",
        f"    protected $model = {n.pascal}::class;",
        "",
        "    public function definition(): array",
        "    {",
        "        return [",
    ]
    lines.extend(fake_lines)
    lines.extend([
        "        ];",
        "    }",
        "}",
        "",
    ])
    return rel, "\n".join(lines)


def _gen_seeder(n: Names, central: bool, base: Path) -> tuple[Path, str]:
    """Stage 9b: Seeder."""
    rel = base / "database" / "seeders" / f"{n.pascal}Seeder.php"

    lines = [
        "<?php",
        "",
        "declare(strict_types=1);",
        "",
        "namespace Database\\Seeders;",
        "",
        f"use App\\Models\\{n.pascal};",
        "use Illuminate\\Database\\Seeder;",
        "",
        f"final class {n.pascal}Seeder extends Seeder",
        "{",
        "    public function run(): void",
        "    {",
        f"        {n.pascal}::factory()->count(10)->create();",
        "    }",
        "}",
        "",
    ]
    return rel, "\n".join(lines)


def _gen_feature_test(n: Names, central: bool, base: Path) -> tuple[Path, str]:
    """Stage 10a: Feature test (Pest v3)."""
    rel = base / "tests" / "Feature" / f"{n.pascal}Test.php"

    lines = [
        "<?php",
        "",
        "declare(strict_types=1);",
        "",
        f"use App\\Models\\{n.pascal};",
        "use App\\Models\\User;",
        "use Database\\Seeders\\DefaultTenantRoleSeeder;",
        "",
        "uses(Tests\\Traits\\RefreshDatabaseWithTenancy::class);",
        "",
        "beforeEach(function (): void {",
        "    $this->seed(DefaultTenantRoleSeeder::class);",
        "    $this->user = User::factory()->create();",
        "    $this->user->assignRole('tenant-admin');",
        "    $this->actingAs($this->user, 'api');",
        "});",
        "",
        f"it('can list {n.plural_snake}', function (): void {{",
        f"    {n.pascal}::factory()->count(3)->create();",
        "",
        f"    $response = $this->getJson('/api/v1/{n.plural_kebab}');",
        "",
        "    $response->assertOk()",
        "        ->assertJsonStructure(['data']);",
        "});",
        "",
        f"it('rejects unauthenticated access to {n.plural_snake}', function (): void {{",
        f"    $response = $this->withHeaders(['Authorization' => ''])",
        f"        ->getJson('/api/v1/{n.plural_kebab}');",
        "",
        "    $response->assertUnauthorized();",
        "});",
        "",
        f"it('can search {n.plural_snake} by term', function (): void {{",
        f"    {n.pascal}::factory()->create(['name' => 'Searchable Item']);",
        f"    {n.pascal}::factory()->create(['name' => 'Other Item']);",
        "",
        f"    $response = $this->getJson('/api/v1/{n.plural_kebab}?search=Searchable');",
        "",
        "    $response->assertOk();",
        "});",
        "",
        f"it('can filter {n.plural_snake} by active status', function (): void {{",
        f"    {n.pascal}::factory()->create(['is_active' => true]);",
        f"    {n.pascal}::factory()->create(['is_active' => false]);",
        "",
        f"    $response = $this->getJson('/api/v1/{n.plural_kebab}?is_active=1');",
        "",
        "    $response->assertOk();",
        "});",
        "",
        f"it('can show a {n.snake}', function (): void {{",
        f"    ${n.camel} = {n.pascal}::factory()->create();",
        "",
        f"    $response = $this->getJson('/api/v1/{n.plural_kebab}/' . ${n.camel}->identifier);",
        "",
        "    $response->assertOk()",
        f"        ->assertJsonPath('data.id', ${n.camel}->identifier);",
        "});",
        "",
        f"it('returns 404 for non-existent {n.snake}', function (): void {{",
        f"    $response = $this->getJson('/api/v1/{n.plural_kebab}/non-existent-id');",
        "",
        "    $response->assertNotFound();",
        "});",
        "",
        f"it('can create a {n.snake}', function (): void {{",
        f"    $response = $this->postJson('/api/v1/{n.plural_kebab}', [",
        f"        'name' => 'Test {n.title}',",
        f"        'description' => 'A test {n.title.lower()}.',",
        "    ]);",
        "",
        "    $response->assertCreated()",
        f"        ->assertJsonPath('data.name', 'Test {n.title}');",
        "});",
        "",
        f"it('rejects duplicate {n.snake} name on create', function (): void {{",
        f"    {n.pascal}::factory()->create(['name' => 'Duplicate']);",
        "",
        f"    $response = $this->postJson('/api/v1/{n.plural_kebab}', [",
        "        'name' => 'Duplicate',",
        "    ]);",
        "",
        "    $response->assertUnprocessable();",
        "});",
        "",
        f"it('can update a {n.snake}', function (): void {{",
        f"    ${n.camel} = {n.pascal}::factory()->create();",
        "",
        f"    $response = $this->putJson('/api/v1/{n.plural_kebab}/' . ${n.camel}->identifier, [",
        f"        'name' => 'Updated {n.title}',",
        "    ]);",
        "",
        "    $response->assertOk()",
        f"        ->assertJsonPath('data.name', 'Updated {n.title}');",
        "});",
        "",
        f"it('allows same name on update for same {n.snake}', function (): void {{",
        f"    ${n.camel} = {n.pascal}::factory()->create(['name' => 'Keep Name']);",
        "",
        f"    $response = $this->putJson('/api/v1/{n.plural_kebab}/' . ${n.camel}->identifier, [",
        "        'name' => 'Keep Name',",
        "    ]);",
        "",
        "    $response->assertOk();",
        "});",
        "",
        f"it('returns 404 when updating non-existent {n.snake}', function (): void {{",
        f"    $response = $this->putJson('/api/v1/{n.plural_kebab}/non-existent-id', [",
        f"        'name' => 'Does not matter',",
        "    ]);",
        "",
        "    $response->assertNotFound();",
        "});",
        "",
        f"it('can soft delete a {n.snake}', function (): void {{",
        f"    ${n.camel} = {n.pascal}::factory()->create();",
        "",
        f"    $response = $this->deleteJson('/api/v1/{n.plural_kebab}/' . ${n.camel}->identifier);",
        "",
        "    $response->assertOk();",
        f"    $this->assertSoftDeleted('{n.table}', ['id' => ${n.camel}->id]);",
        "});",
        "",
        f"it('can restore a soft-deleted {n.snake}', function (): void {{",
        f"    ${n.camel} = {n.pascal}::factory()->create();",
        f"    ${n.camel}->delete();",
        "",
        f"    $response = $this->postJson('/api/v1/{n.plural_kebab}/' . ${n.camel}->identifier . '/restore');",
        "",
        "    $response->assertOk();",
        f"    $this->assertDatabaseHas('{n.table}', [",
        f"        'id' => ${n.camel}->id,",
        "        'deleted_at' => null,",
        "    ]);",
        "});",
    ]

    if not central:
        lines.extend([
            "",
            f"it('cannot access other tenant {n.plural_snake}', function (): void {{",
            f"    ${n.camel} = {n.pascal}::factory()->create(['tenant_id' => 'other-tenant']);",
            "",
            f"    $response = $this->getJson('/api/v1/{n.plural_kebab}/' . ${n.camel}->identifier);",
            "",
            "    $response->assertNotFound();",
            "});",
        ])

    lines.append("")

    return rel, "\n".join(lines)


def _gen_unit_test(n: Names, base: Path) -> tuple[Path, str]:
    """Stage 10b: Unit test (Pest v3)."""
    rel = base / "tests" / "Unit" / f"{n.pascal}UnitTest.php"

    lines = [
        "<?php",
        "",
        "declare(strict_types=1);",
        "",
        f"use App\\Models\\{n.pascal};",
        "",
        f"it('has correct table name for {n.pascal}', function (): void {{",
        f"    $model = new {n.pascal}();",
        "",
        f"    expect($model->getTable())->toBe('{n.table}');",
        "});",
        "",
        f"it('uses identifier as route key for {n.pascal}', function (): void {{",
        f"    $model = new {n.pascal}();",
        "",
        "    expect($model->getRouteKeyName())->toBe('identifier');",
        "});",
        "",
        f"it('has fillable attributes for {n.pascal}', function (): void {{",
        f"    $model = new {n.pascal}();",
        "    $fillable = $model->getFillable();",
        "",
        "    expect($fillable)->toContain('identifier')",
        "        ->toContain('created_by')",
        "        ->toContain('updated_by');",
        "});",
        "",
        f"it('uses soft deletes for {n.pascal}', function (): void {{",
        f"    $model = new {n.pascal}();",
        "",
        "    expect(method_exists($model, 'trashed'))->toBeTrue();",
        "});",
        "",
    ]
    return rel, "\n".join(lines)


# ---------------------------------------------------------------------------
# Stage orchestrator
# ---------------------------------------------------------------------------

_STAGES = [
    ("migration", "_stage_migration"),
    ("model", "_stage_model"),
    ("repository", "_stage_repository"),
    ("filters", "_stage_filters"),
    ("form_requests", "_stage_form_requests"),
    ("resources", "_stage_resources"),
    ("policy", "_stage_policy"),
    ("controller", "_stage_controller"),
    ("factory_seeder", "_stage_factory_seeder"),
    ("tests", "_stage_tests"),
]


class ModuleGenerator:
    """Orchestrates all 10 generation stages."""

    def __init__(
        self,
        module_name: str,
        *,
        central: bool = False,
        fields: list[FieldSpec] | None = None,
        dry_run: bool = False,
        skip_tests: bool = False,
        overwrite: bool = False,
        base_dir: Path,
    ) -> None:
        self.names = derive_names(to_pascal(module_name))
        self.central = central
        self.fields = fields or []
        self.dry_run = dry_run
        self.skip_tests = skip_tests
        self.overwrite = overwrite
        self.base = base_dir
        self.rollback = RollbackManager()
        self.created_files: list[Path] = []

    def run(self) -> list[Path]:
        """Execute all stages. Returns list of created file paths."""
        n = self.names
        scope = "central" if self.central else "tenant-scoped"
        logger.info(
            "Generating %s module: %s (%s)", scope, n.pascal, "DRY RUN" if self.dry_run else "LIVE"
        )
        logger.info("Naming: %s", n._asdict())

        for stage_name, method_name in _STAGES:
            if stage_name == "tests" and self.skip_tests:
                logger.info("Stage '%s' skipped (--skip-tests)", stage_name)
                continue

            logger.info("Stage: %s", stage_name)
            try:
                method = getattr(self, method_name)
                method()
            except Exception as e:
                logger.error("Stage '%s' failed: %s", stage_name, e)
                if not self.dry_run:
                    self.rollback.rollback()
                raise

        logger.info("")
        logger.info("=" * 70)
        if self.dry_run:
            logger.info("DRY RUN complete — no files were written.")
        else:
            logger.info(
                "Module '%s' generated successfully! (%d files)",
                n.pascal,
                len(self.created_files),
            )
        logger.info("=" * 70)

        self._print_manual_steps()

        return self.created_files

    def _write(self, path: Path, content: str) -> None:
        if write_file(
            path, content, overwrite=self.overwrite, dry_run=self.dry_run, rollback=self.rollback
        ):
            self.created_files.append(path)

    # -- Stage methods --

    def _stage_migration(self) -> None:
        path, content = _gen_migration(self.names, self.fields, self.central, self.base)
        self._write(path, content)

    def _stage_model(self) -> None:
        path, content = _gen_model(self.names, self.fields, self.central, self.base)
        self._write(path, content)

    def _stage_repository(self) -> None:
        path, content = _gen_repository(self.names, self.central, self.base)
        self._write(path, content)

    def _stage_filters(self) -> None:
        for path, content in _gen_filters(self.names, self.base):
            self._write(path, content)

    def _stage_form_requests(self) -> None:
        for path, content in _gen_form_requests(self.names, self.fields, self.base):
            self._write(path, content)

    def _stage_resources(self) -> None:
        for path, content in _gen_resources(self.names, self.fields, self.base):
            self._write(path, content)

    def _stage_policy(self) -> None:
        path, content = _gen_policy(self.names, self.base)
        self._write(path, content)

    def _stage_controller(self) -> None:
        path, content = _gen_controller(self.names, self.central, self.base)
        self._write(path, content)

    def _stage_factory_seeder(self) -> None:
        path, content = _gen_factory(self.names, self.fields, self.central, self.base)
        self._write(path, content)
        path, content = _gen_seeder(self.names, self.central, self.base)
        self._write(path, content)

    def _stage_tests(self) -> None:
        path, content = _gen_feature_test(self.names, self.central, self.base)
        self._write(path, content)
        path, content = _gen_unit_test(self.names, self.base)
        self._write(path, content)

    def _print_manual_steps(self) -> None:
        n = self.names
        scope = "central" if self.central else "tenant"
        logger.info("")
        logger.info("MANUAL STEPS REQUIRED:")
        logger.info("-" * 40)
        logger.info("")
        logger.info("1. Register the policy in app/Providers/AppServiceProvider.php:")
        logger.info(
            "   Gate::policy(\\App\\Models\\%s::class, \\App\\Policies\\%sPolicy::class);",
            n.pascal,
            n.pascal,
        )
        logger.info("")
        logger.info("2. Add permissions to config/role-permission-map.php:")
        logger.info("   '%s.view', '%s.create', '%s.edit', '%s.delete'",
                     n.plural_kebab, n.plural_kebab, n.plural_kebab, n.plural_kebab)
        logger.info("")
        logger.info("3. Add routes inside the auth:api group in routes/api.php:")
        logger.info("   Route::apiResource('%s', %sController::class);", n.plural_kebab, n.pascal)
        logger.info(
            "   Route::post('%s/{id}/restore', [%sController::class, 'restore'])",
            n.plural_kebab,
            n.pascal,
        )
        logger.info("       ->name('%s.restore');", n.plural_snake)
        logger.info("")
        if self.central:
            logger.info("4. Run migration and seeder:")
            logger.info("   php artisan migrate")
            logger.info("   php artisan db:seed --class=%sSeeder", n.pascal)
        else:
            logger.info("4. Run migration and seeder:")
            logger.info("   php artisan tenants:migrate")
        logger.info("")
        logger.info("5. Run tests:")
        logger.info("   ./test.sh tests/Feature/%sTest.php", n.pascal)
        logger.info("   ./test.sh tests/Unit/%sUnitTest.php", n.pascal)
        logger.info("")
        logger.info("6. Format:")
        logger.info("   vendor/bin/pint --dirty")


# ---------------------------------------------------------------------------
# CLI entry point
# ---------------------------------------------------------------------------


def _build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="generate-module",
        description="AIAS Module Generator — scaffolds a complete Laravel module.",
    )
    parser.add_argument(
        "module_name",
        help="PascalCase module name (e.g. AuditStandard, RiskAssessment, Finding)",
    )
    parser.add_argument(
        "--central",
        action="store_true",
        default=False,
        help="Generate as a central database model (default: tenant-scoped)",
    )
    parser.add_argument(
        "--fields",
        type=str,
        default="",
        help='Comma-separated field definitions, e.g. "severity:string:required,status:string:required"',
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        default=False,
        help="Preview all files that would be created — writes nothing",
    )
    parser.add_argument(
        "--skip-tests",
        action="store_true",
        default=False,
        help="Skip Feature and Unit test generation",
    )
    parser.add_argument(
        "--overwrite",
        action="store_true",
        default=False,
        help="Overwrite existing files (default: skip existing)",
    )
    parser.add_argument(
        "--base-dir",
        type=str,
        default=".",
        help="AIAS project root (default: current directory)",
    )
    parser.add_argument(
        "--verbose",
        "-v",
        action="store_true",
        default=False,
        help="Enable DEBUG-level logging",
    )
    return parser


def main(argv: list[str] | None = None) -> int:
    """Main entry point. Returns 0 on success, 1 on error."""
    parser = _build_parser()
    args = parser.parse_args(argv)

    # Configure logging
    level = logging.DEBUG if args.verbose else logging.INFO
    logging.basicConfig(
        level=level,
        format="%(asctime)s  %(levelname)-8s %(message)s",
        datefmt="%H:%M:%S",
    )

    try:
        fields = parse_fields(args.fields)
    except ValueError as e:
        logger.error("Invalid --fields: %s", e)
        return 1

    # Validate module name
    pascal = to_pascal(args.module_name)
    if not pascal or not pascal[0].isupper():
        logger.error(
            "Invalid module name '%s'. Must be PascalCase (e.g. AuditStandard).",
            args.module_name,
        )
        return 1

    base_dir = Path(args.base_dir).resolve()
    if not (base_dir / "artisan").exists():
        logger.error(
            "Base directory '%s' does not appear to be a Laravel project (no artisan file found).",
            base_dir,
        )
        return 1

    generator = ModuleGenerator(
        module_name=pascal,
        central=args.central,
        fields=fields,
        dry_run=args.dry_run,
        skip_tests=args.skip_tests,
        overwrite=args.overwrite,
        base_dir=base_dir,
    )

    try:
        generator.run()
    except KeyboardInterrupt:
        logger.warning("Interrupted by user.")
        if not args.dry_run:
            generator.rollback.rollback()
        return 1
    except Exception as e:
        logger.error("Generation failed: %s", e)
        return 1

    return 0


if __name__ == "__main__":
    sys.exit(main())
