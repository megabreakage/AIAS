#!/usr/bin/env python3
"""
AIAS Module Generator
======================
Auto-generates all files for a new Laravel module following the AIAS
(Adaptive Intelligent Audit System) architectural patterns:
BaseRepository, Central/Tenant Model, Repository, Controller, Pest Tests, etc.

Usage:
    python3 docs/prompts/aias/scripts/generate-module.py <ModuleName> [options]

Examples:
    python3 docs/prompts/aias/scripts/generate-module.py Country
    python3 docs/prompts/aias/scripts/generate-module.py AuditStandard --central
    python3 docs/prompts/aias/scripts/generate-module.py Finding --dry-run
    python3 docs/prompts/aias/scripts/generate-module.py RiskAssessment --fields "risk_level:string:required,score:decimal:nullable"

Rollback: On any failure, all created files are deleted automatically.
"""

from __future__ import annotations

import argparse
import logging
import os
import re
import sys
import traceback
from dataclasses import dataclass, field
from datetime import datetime
from pathlib import Path
from typing import Optional

# ─── ANSI Colours ────────────────────────────────────────────────────────────

RESET   = "\033[0m"
BOLD    = "\033[1m"
RED     = "\033[31m"
GREEN   = "\033[32m"
YELLOW  = "\033[33m"
CYAN    = "\033[36m"
BLUE    = "\033[34m"
MAGENTA = "\033[35m"
DIM     = "\033[2m"


def color(text: str, code: str) -> str:
    return f"{code}{text}{RESET}"


# ─── Logger setup ────────────────────────────────────────────────────────────

class ColorFormatter(logging.Formatter):
    LEVEL_COLORS = {
        "DEBUG":    DIM,
        "INFO":     CYAN,
        "WARNING":  YELLOW,
        "ERROR":    RED,
        "CRITICAL": BOLD + RED,
    }

    def format(self, record: logging.LogRecord) -> str:
        level_color = self.LEVEL_COLORS.get(record.levelname, "")
        record.levelname = color(f"{record.levelname:<8}", level_color)
        record.msg = str(record.msg)
        return super().format(record)


def setup_logger(verbose: bool = False) -> logging.Logger:
    logger = logging.getLogger("generate_module")
    logger.setLevel(logging.DEBUG if verbose else logging.INFO)
    handler = logging.StreamHandler(sys.stdout)
    handler.setFormatter(ColorFormatter(
        "%(asctime)s  %(levelname)s  %(message)s", datefmt="%H:%M:%S"
    ))
    logger.addHandler(handler)
    return logger


log: logging.Logger = logging.getLogger("generate_module")


# ─── Naming utilities ─────────────────────────────────────────────────────────

def to_snake_case(name: str) -> str:
    """AuditEngagement → audit_engagement"""
    s1 = re.sub(r"([A-Z]+)([A-Z][a-z])", r"\1_\2", name)
    s2 = re.sub(r"([a-z\d])([A-Z])", r"\1_\2", s1)
    return s2.lower()


def to_kebab_case(name: str) -> str:
    """AuditEngagement → audit-engagement"""
    return to_snake_case(name).replace("_", "-")


def to_pascal_case(name: str) -> str:
    """audit_engagement → AuditEngagement"""
    snake = to_snake_case(name)
    return "".join(word.capitalize() for word in snake.split("_"))


def to_camel_case(name: str) -> str:
    """AuditEngagement → auditEngagement"""
    pascal = to_pascal_case(name)
    return pascal[0].lower() + pascal[1:] if pascal else pascal


def pluralize(name: str) -> str:
    """Basic English pluralizer for Laravel table names."""
    irregulars = {
        "aircraft": "aircraft",
        "staff":    "staff",
        "news":     "news",
        "media":    "media",
    }
    lower = name.lower()
    if lower in irregulars:
        return irregulars[lower]
    if lower.endswith(("s", "sh", "ch", "x", "z")):
        return name + "es"
    if lower.endswith("y") and len(lower) > 1 and lower[-2] not in "aeiou":
        return name[:-1] + "ies"
    if lower.endswith("fe"):
        return name[:-2] + "ves"
    if lower.endswith("f") and not lower.endswith("ff"):
        return name[:-1] + "ves"
    return name + "s"


@dataclass
class Names:
    """All naming variants derived from a PascalCase module name."""
    pascal:        str
    camel:         str
    snake:         str
    kebab:         str
    plural_snake:  str
    plural_pascal: str
    plural_kebab:  str
    table:         str

    @classmethod
    def from_input(cls, raw: str) -> "Names":
        pascal      = to_pascal_case(raw)
        snake       = to_snake_case(pascal)
        kebab       = snake.replace("_", "-")
        plural_s    = pluralize(snake)
        return cls(
            pascal        = pascal,
            camel         = to_camel_case(pascal),
            snake         = snake,
            kebab         = kebab,
            plural_snake  = plural_s,
            plural_pascal = to_pascal_case(plural_s),
            plural_kebab  = plural_s.replace("_", "-"),
            table         = plural_s,
        )


# ─── Field descriptor ─────────────────────────────────────────────────────────

@dataclass
class FieldSpec:
    name:      str
    php_type:  str  = "string"
    nullable:  bool = False
    unique:    bool = False
    required:  bool = True

    @classmethod
    def parse(cls, spec: str) -> "FieldSpec":
        """Parse 'name:type:modifiers' e.g. 'risk_level:string:required,nullable'"""
        parts = spec.strip().split(":")
        name  = parts[0]
        ptype = parts[1] if len(parts) > 1 else "string"
        mods  = parts[2].split(",") if len(parts) > 2 else []
        return cls(
            name     = name,
            php_type = ptype,
            nullable = "nullable" in mods,
            unique   = "unique"   in mods,
            required = "required" in mods or ("nullable" not in mods),
        )

    def migration_column(self) -> str:
        type_map = {
            "string":   "string",
            "text":     "text",
            "integer":  "integer",
            "boolean":  "boolean",
            "date":     "date",
            "datetime": "dateTime",
            "decimal":  "decimal",
            "uuid":     "uuid",
        }
        col_type = type_map.get(self.php_type, "string")
        if col_type == "decimal":
            col = f"$table->decimal('{self.name}', 15, 2)"
        else:
            col = f"$table->{col_type}('{self.name}')"
        if self.nullable:
            col += "->nullable()"
        if self.unique:
            col += "->unique()"
        return col + ";"

    def validation_rule(self, table: str) -> str:
        rules = ["'required'" if self.required else "'nullable'"]
        type_map = {
            "string":   "'string'",
            "text":     "'string'",
            "integer":  "'integer'",
            "boolean":  "'boolean'",
            "date":     "'date'",
            "datetime": "'date'",
            "decimal":  "'numeric'",
        }
        if self.php_type in type_map:
            rules.append(type_map[self.php_type])
        if self.php_type in ("string", "text"):
            rules.append("'max:255'")
        if self.unique:
            rules.append(f"Rule::unique('{table}', '{self.name}')")
        return f"'{self.name}' => [{', '.join(rules)}],"

    def cast_entry(self) -> Optional[str]:
        cast_map = {
            "boolean":  "boolean",
            "integer":  "integer",
            "decimal":  "float",
            "date":     "date",
            "datetime": "datetime",
        }
        if self.php_type in cast_map:
            return f"'{self.name}' => '{cast_map[self.php_type]}',"
        return None


# ─── Rollback manager ─────────────────────────────────────────────────────────

class RollbackManager:
    """Tracks every file/dir created so they can be deleted on failure."""

    def __init__(self) -> None:
        self._created: list[Path] = []

    def track(self, path: Path) -> None:
        self._created.append(path)

    def rollback(self) -> None:
        log.warning(color("⏪  Rolling back…", YELLOW))
        for path in reversed(self._created):
            try:
                if path.is_file():
                    path.unlink()
                    log.debug(f"  deleted file  {path}")
                elif path.is_dir() and not any(path.iterdir()):
                    path.rmdir()
                    log.debug(f"  removed dir   {path}")
            except Exception as exc:
                log.error(f"  could not remove {path}: {exc}")
        log.warning(color("✔  Rollback complete — no partial files left.", YELLOW))

    @property
    def count(self) -> int:
        return len(self._created)


# ─── File writer ─────────────────────────────────────────────────────────────

class FileWriter:
    def __init__(
        self,
        base: Path,
        rollback: RollbackManager,
        dry_run: bool = False,
    ) -> None:
        self.base     = base
        self.rollback = rollback
        self.dry_run  = dry_run

    def write(self, rel_path: str, content: str, *, overwrite: bool = False) -> Path:
        target = self.base / rel_path
        target.parent.mkdir(parents=True, exist_ok=True)

        for parent in reversed(target.parents):
            if parent != self.base and not parent.exists():
                self.rollback.track(parent)

        if target.exists() and not overwrite:
            log.warning(color(f"  SKIP (exists)  {rel_path}", YELLOW))
            return target

        if self.dry_run:
            log.info(color(f"  DRY-RUN  {rel_path}", MAGENTA))
            return target

        target.write_text(content, encoding="utf-8")
        self.rollback.track(target)
        log.info(color(f"  ✔  {rel_path}", GREEN))
        return target


# ─── Timestamp prefix ─────────────────────────────────────────────────────────

def timestamp_prefix() -> str:
    return datetime.now().strftime("%Y_%m_%d_%H%M%S")


# ═══════════════════════════════════════════════════════════════════════════════
#  TEMPLATE GENERATORS
# ═══════════════════════════════════════════════════════════════════════════════

# ─── 1. Migration ─────────────────────────────────────────────────────────────

def gen_migration(n: Names, fields: list[FieldSpec], is_central: bool) -> str:
    extra_cols = ""
    for f in fields:
        extra_cols += "\n            " + f.migration_column()

    tenant_col = "" if is_central else """
            // Tenant reference — plain string, NO FK to central database
            $table->string('tenant_id');"""

    fk_comment = "// NO tenant_id — this is central reference data" if is_central else ""

    index_tenant = "" if is_central else "\n            $table->index('tenant_id');"

    return f"""<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{{
    public function up(): void
    {{
        Schema::create('{n.table}', function (Blueprint $table) {{
            $table->id();
            $table->uuid('identifier')->unique();
{tenant_col}
            // Business fields
            $table->string('name');{extra_cols}
            $table->boolean('status')->default(true);

            {fk_comment}
            // Creator tracking — plain integers, NO FK to central users table
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();
{index_tenant}
            $table->index('status');
        }});
    }}

    public function down(): void
    {{
        Schema::dropIfExists('{n.table}');
    }}
}};
"""


# ─── 2. Model ─────────────────────────────────────────────────────────────────

def gen_model_central(n: Names, fields: list[FieldSpec]) -> str:
    fillable_items = ["'identifier'", "'name'"]
    for f in fields:
        if f.name not in ("identifier", "name", "status", "created_by", "updated_by"):
            fillable_items.append(f"'{f.name}'")
    fillable_items += ["'status'", "'created_by'", "'updated_by'"]
    fillable = ",\n        ".join(fillable_items)

    casts = [
        "'status'     => 'boolean'",
        "'created_at' => 'datetime'",
        "'updated_at' => 'datetime'",
        "'deleted_at' => 'datetime'",
    ]
    for f in fields:
        c = f.cast_entry()
        if c:
            casts.append(c.rstrip(","))
    casts_body = ",\n            ".join(casts)

    return f"""<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;
use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;
use Illuminate\\Database\\Eloquent\\SoftDeletes;
use Illuminate\\Support\\Facades\\Auth;
use Illuminate\\Support\\Str;
use OwenIt\\Auditing\\Auditable;
use OwenIt\\Auditing\\Contracts\\Auditable as AuditableContract;
use Stancl\\Tenancy\\Database\\Concerns\\CentralConnection;

class {n.pascal} extends Model implements AuditableContract
{{
    use Auditable, CentralConnection, HasFactory, SoftDeletes;

    protected $connection = 'central';

    protected $fillable = [
        {fillable},
    ];

    protected function casts(): array
    {{
        return [
            {casts_body},
        ];
    }}

    public function getRouteKeyName(): string
    {{
        return 'identifier';
    }}

    protected static function boot(): void
    {{
        parent::boot();

        static::creating(function (self $model) {{
            if (empty($model->identifier)) {{
                $model->identifier = Str::uuid()->toString();
            }}
            if (Auth::check()) {{
                $model->created_by = Auth::id();
            }}
        }});

        static::updating(function (self $model) {{
            if (Auth::check()) {{
                $model->updated_by = Auth::id();
            }}
        }});
    }}

    public function creator(): BelongsTo
    {{
        return $this->belongsTo(User::class, 'created_by');
    }}

    public function updater(): BelongsTo
    {{
        return $this->belongsTo(User::class, 'updated_by');
    }}
}}
"""


def gen_model_tenant(n: Names, fields: list[FieldSpec]) -> str:
    fillable_items = ["'identifier'", "'tenant_id'", "'name'"]
    for f in fields:
        if f.name not in ("identifier", "tenant_id", "name", "status", "created_by", "updated_by"):
            fillable_items.append(f"'{f.name}'")
    fillable_items += ["'status'", "'created_by'", "'updated_by'"]
    fillable = ",\n        ".join(fillable_items)

    casts = [
        "'status'     => 'boolean'",
        "'created_at' => 'datetime'",
        "'updated_at' => 'datetime'",
        "'deleted_at' => 'datetime'",
    ]
    for f in fields:
        c = f.cast_entry()
        if c:
            casts.append(c.rstrip(","))
    casts_body = ",\n            ".join(casts)

    return f"""<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;
use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;
use Illuminate\\Database\\Eloquent\\SoftDeletes;
use Illuminate\\Support\\Str;
use Stancl\\Tenancy\\Database\\Concerns\\TenantConnection;

class {n.pascal} extends Model
{{
    use HasFactory, SoftDeletes, TenantConnection;

    protected $fillable = [
        {fillable},
    ];

    protected function casts(): array
    {{
        return [
            {casts_body},
        ];
    }}

    public function getRouteKeyName(): string
    {{
        return 'identifier';
    }}

    protected static function boot(): void
    {{
        parent::boot();

        static::creating(function (self $model) {{
            if (is_null($model->identifier)) {{
                $model->identifier = (string) Str::uuid();
            }}

            if (tenancy()->tenant) {{
                $model->tenant_id = tenancy()->tenant->getTenantKey();

                if (is_null($model->created_by) && auth()->check()) {{
                    $model->created_by = auth()->id();
                }}
            }}
        }});

        static::updating(function (self $model) {{
            if (auth()->check()) {{
                $model->updated_by = auth()->id();
            }}
        }});
    }}

    public function creator(): BelongsTo
    {{
        return $this->belongsTo(User::class, 'created_by');
    }}

    public function updater(): BelongsTo
    {{
        return $this->belongsTo(User::class, 'updated_by');
    }}
}}
"""


# ─── 3. Repository ────────────────────────────────────────────────────────────

def gen_repository(n: Names, is_central: bool) -> str:
    tenant_filter = "" if is_central else f"""
        // Mandatory tenant filtering for non-super-admin users
        if (! auth()->user()->hasRole('super-admin')) {{
            $query->where('tenant_id', auth()->user()->tenant_id);
        }}
"""

    doc = "Browse {n.plural_pascal} — central reference data, no tenant filtering." if is_central \
        else f"Browse {n.plural_pascal} with mandatory tenant filtering."

    return f"""<?php

namespace App\\Repositories;

use App\\Filters\\{n.plural_pascal}\\{n.pascal}Filters;
use App\\Models\\{n.pascal};
use Illuminate\\Contracts\\Pagination\\Paginator;
use Illuminate\\Database\\Eloquent\\Model;

class {n.pascal}Repository extends BaseRepository
{{
    protected array $dispatchesEvents = [];

    public function getClassName(): Model|string
    {{
        return {n.pascal}::class;
    }}

    /**
     * {doc}
     */
    public function browse{n.plural_pascal}(
        {n.pascal}Filters $filters,
        int $page = 1,
        int $perPage = 20,
        ?string $sortBy = null,
        bool $sortDesc = false
    ): Paginator {{
        $query = $this->query()->with(['creator', 'updater']);
{tenant_filter}
        $filters->apply($query);

        if ($sortBy) {{
            $query->orderBy($sortBy, $sortDesc ? 'desc' : 'asc');
        }} else {{
            $query->orderBy('name', 'asc');
        }}

        return $query->paginate(perPage: min($perPage, 100), page: max($page, 1));
    }}

    public function read{n.pascal}(int|string $id, array $with = []): Model
    {{
        return $this->query()
            ->with(array_merge(['creator', 'updater'], $with))
            ->where('identifier', $id)
            ->firstOrFail();
    }}

    public function create{n.pascal}(array $data): {n.pascal}|Model|bool
    {{
        $model = self::make($data);

        if ($model->save()) {{
            return $model->load(['creator', 'updater']);
        }}

        return false;
    }}

    public function update{n.pascal}(int|string $id, array $data): {n.pascal}|Model|bool
    {{
        $model = $this->query()->where('identifier', $id)->firstOrFail();
        $model->fill($data);

        if ($model->save()) {{
            return $model->load(['creator', 'updater']);
        }}

        return false;
    }}

    public function delete{n.pascal}(int|string $id): bool
    {{
        $model = $this->query()->where('identifier', $id)->firstOrFail();

        return $model->delete();
    }}

    public function restore{n.pascal}(int|string $id): bool
    {{
        $model = $this->query()->withTrashed()->where('identifier', $id)->firstOrFail();

        return (bool) $model->restore();
    }}
}}
"""


# ─── 4. Filters ───────────────────────────────────────────────────────────────

def gen_filter_main(n: Names) -> str:
    return f"""<?php

namespace App\\Filters\\{n.plural_pascal};

use App\\Filters\\EloquentFilter;
use App\\Filters\\{n.plural_pascal}\\Filters\\SearchTermFilter;
use App\\Filters\\{n.plural_pascal}\\Filters\\IsActiveFilter;
use Illuminate\\Http\\Request;

class {n.pascal}Filters extends EloquentFilter
{{
    protected array $filters = [
        'search' => SearchTermFilter::class,
        'status' => IsActiveFilter::class,
    ];

    public static function fromRequest(Request $request): static
    {{
        return new static($request->only(['search', 'status']));
    }}
}}
"""


def gen_filter_search(n: Names) -> str:
    return f"""<?php

namespace App\\Filters\\{n.plural_pascal}\\Filters;

use App\\Filters\\EloquentFilter;
use Illuminate\\Database\\Eloquent\\Builder;

class SearchTermFilter extends EloquentFilter
{{
    public function __construct(
        protected string $search
    ) {{}}

    public function apply(Builder $query): Builder
    {{
        $search = str_replace(
            ['\\\\', '%', '_'],
            ['\\\\\\\\', '\\\\%', '\\\\_'],
            trim($this->search)
        );

        return $query->where(function (Builder $q) use ($search) {{
            $q->where('name', 'like', "%{{$search}}%");
        }});
    }}
}}
"""


def gen_filter_active(n: Names) -> str:
    return f"""<?php

namespace App\\Filters\\{n.plural_pascal}\\Filters;

use App\\Filters\\EloquentFilter;
use Illuminate\\Database\\Eloquent\\Builder;

class IsActiveFilter extends EloquentFilter
{{
    public function __construct(
        protected string $status
    ) {{}}

    public function apply(Builder $query): Builder
    {{
        if ($this->status === '' || is_null($this->status)) {{
            return $query;
        }}

        return $query->where('status', filter_var($this->status, FILTER_VALIDATE_BOOLEAN));
    }}
}}
"""


# ─── 5. Form Requests ─────────────────────────────────────────────────────────

def gen_request_create(n: Names, fields: list[FieldSpec]) -> str:
    extra_rules = ""
    for f in fields:
        if f.name not in ("status",):
            req  = "'required'" if f.required else "'nullable'"
            trule = f"'{f.php_type}'"
            if f.php_type in ("string", "text"):
                trule += ", 'max:255'"
            unique = f", Rule::unique('{n.table}', '{f.name}')" if f.unique else ""
            extra_rules += f"\n            '{f.name}' => [{req}, {trule}{unique}],"

    return f"""<?php

namespace App\\Http\\Requests\\{n.pascal};

use Illuminate\\Foundation\\Http\\FormRequest;
use Illuminate\\Support\\Facades\\Gate;
use Illuminate\\Validation\\Rule;

class Create{n.pascal}Request extends FormRequest
{{
    public function authorize(): bool
    {{
        return Gate::allows('create', \\App\\Models\\{n.pascal}::class);
    }}

    public function rules(): array
    {{
        return [
            'name'   => ['required', 'string', 'max:255', Rule::unique('{n.table}', 'name')],
            'status' => ['nullable', 'boolean'],{extra_rules}
        ];
    }}

    public function messages(): array
    {{
        return [
            'name.required' => 'The name field is required.',
            'name.unique'   => 'A {n.pascal} with this name already exists.',
        ];
    }}
}}
"""


def gen_request_update(n: Names, fields: list[FieldSpec]) -> str:
    extra_rules = ""
    for f in fields:
        if f.name not in ("status",):
            trule = f"'{f.php_type}'"
            if f.php_type in ("string", "text"):
                trule += ", 'max:255'"
            unique = (
                f", Rule::unique('{n.table}', '{f.name}')"
                f"->ignore($this->route('{n.camel}'), 'identifier')"
                if f.unique else ""
            )
            extra_rules += f"\n            '{f.name}' => ['sometimes', {trule}{unique}],"

    return f"""<?php

namespace App\\Http\\Requests\\{n.pascal};

use Illuminate\\Foundation\\Http\\FormRequest;
use Illuminate\\Support\\Facades\\Gate;
use Illuminate\\Validation\\Rule;

class Update{n.pascal}Request extends FormRequest
{{
    public function authorize(): bool
    {{
        return Gate::allows('update', $this->route('{n.camel}'));
    }}

    public function rules(): array
    {{
        return [
            'name'   => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('{n.table}', 'name')->ignore($this->route('{n.camel}'), 'identifier'),
            ],
            'status' => ['sometimes', 'boolean'],{extra_rules}
        ];
    }}

    public function messages(): array
    {{
        return [
            'name.unique' => 'A {n.pascal} with this name already exists.',
        ];
    }}
}}
"""


# ─── 6. API Resources ─────────────────────────────────────────────────────────

def gen_resource(n: Names, fields: list[FieldSpec], is_central: bool) -> str:
    extra_lines = ""
    for f in fields:
        if f.name not in ("status",):
            extra_lines += f"\n            '{f.name}' => $this->{f.name},"

    creator_field = "identifier" if is_central else "identifier"
    creator_name  = "name" if is_central else "first_name . ' ' . $this->creator->last_name"

    return f"""<?php

namespace App\\Http\\Resources\\{n.plural_pascal};

use App\\Http\\Resources\\BaseResource;
use Illuminate\\Http\\Request;

class {n.pascal}Resource extends BaseResource
{{
    protected function resourceData(Request $request): array
    {{
        return [
            'id'         => $this->identifier,
            'name'       => $this->name,{extra_lines}
            'status'     => $this->status,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'creator'    => $this->whenLoaded('creator', fn () => [
                'id'   => $this->creator?->{creator_field},
                'name' => $this->creator?->name,
            ]),
            'updater'    => $this->whenLoaded('updater', fn () => [
                'id'   => $this->updater?->{creator_field},
                'name' => $this->updater?->name,
            ]),
        ];
    }}
}}
"""


def gen_resource_collection(n: Names) -> str:
    return f"""<?php

namespace App\\Http\\Resources\\{n.plural_pascal};

use Illuminate\\Http\\Request;
use Illuminate\\Http\\Resources\\Json\\ResourceCollection;

class {n.pascal}Collection extends ResourceCollection
{{
    public $collects = {n.pascal}Resource::class;

    protected string $response_status = 'success';
    protected ?string $message = null;
    protected array $metadata = [];

    public function setMessage(?string $message): static
    {{
        $this->message = $message;

        return $this;
    }}

    public function addMetadata(string $key, mixed $value): static
    {{
        $this->metadata[$key] = $value;

        return $this;
    }}

    public function with(Request $request): array
    {{
        return array_merge([
            'status'  => $this->response_status,
            'message' => $this->message,
        ], $this->metadata);
    }}
}}
"""


# ─── 7. Policy ────────────────────────────────────────────────────────────────

def gen_policy(n: Names) -> str:
    perm = n.plural_kebab  # e.g. audit-standards, risk-assessments

    return f"""<?php

namespace App\\Policies;

use App\\Models\\{n.pascal};
use App\\Models\\User;
use Illuminate\\Auth\\Access\\HandlesAuthorization;

class {n.pascal}Policy
{{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {{
        return $user->hasPermissionTo('{perm}.view');
    }}

    public function view(User $user, {n.pascal} $model): bool
    {{
        return $user->hasPermissionTo('{perm}.view');
    }}

    public function create(User $user): bool
    {{
        return $user->hasPermissionTo('{perm}.create');
    }}

    public function update(User $user, {n.pascal} $model): bool
    {{
        return $user->hasPermissionTo('{perm}.edit');
    }}

    public function delete(User $user, {n.pascal} $model): bool
    {{
        return $user->hasPermissionTo('{perm}.delete');
    }}

    public function restore(User $user, {n.pascal} $model): bool
    {{
        return $user->hasPermissionTo('{perm}.delete');
    }}
}}
"""


# ─── 8. Controller ────────────────────────────────────────────────────────────

def gen_controller(n: Names) -> str:
    return f"""<?php

namespace App\\Http\\Controllers;

use App\\Filters\\{n.plural_pascal}\\{n.pascal}Filters;
use App\\Http\\Requests\\{n.pascal}\\Create{n.pascal}Request;
use App\\Http\\Requests\\{n.pascal}\\Update{n.pascal}Request;
use App\\Http\\Resources\\{n.plural_pascal}\\{n.pascal}Collection;
use App\\Http\\Resources\\{n.plural_pascal}\\{n.pascal}Resource;
use App\\Models\\{n.pascal};
use App\\Repositories\\{n.pascal}Repository;
use Illuminate\\Database\\Eloquent\\ModelNotFoundException;
use Illuminate\\Http\\JsonResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Http\\Response;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Gate;
use Illuminate\\Support\\Facades\\Log;

class {n.pascal}Controller extends Controller
{{
    public function __construct(
        protected {n.pascal}Repository $repository
    ) {{}}

    public function index(Request $request): JsonResponse
    {{
        Gate::authorize('viewAny', {n.pascal}::class);

        $filters = {n.pascal}Filters::fromRequest($request);

        $items = $this->repository->browse{n.plural_pascal}(
            filters:  $filters,
            page:     $request->integer('page', 1),
            perPage:  $request->integer('per_page', 20),
            sortBy:   $request->input('sort_by'),
            sortDesc: $request->input('sort_order') === 'desc',
        );

        return (new {n.pascal}Collection($items))
            ->setMessage('{n.plural_pascal} retrieved successfully.')
            ->addMetadata('filters_applied', $request->only(['search', 'status', 'sort_by', 'sort_order']))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }}

    public function show(string ${n.camel}): JsonResponse
    {{
        try {{
            $model = $this->repository->read{n.pascal}(${n.camel});

            Gate::authorize('view', $model);

            return (new {n.pascal}Resource($model))
                ->setMessage('{n.pascal} retrieved successfully.')
                ->response()
                ->setStatusCode(Response::HTTP_OK);

        }} catch (ModelNotFoundException) {{
            return response()->json([
                'status'  => 'error',
                'message' => '{n.pascal} not found.',
                'data'    => null,
            ], Response::HTTP_NOT_FOUND);
        }}
    }}

    public function store(Create{n.pascal}Request $request): JsonResponse
    {{
        Gate::authorize('create', {n.pascal}::class);

        try {{
            $data = $request->validated();

            Log::info('Creating {n.pascal}', ['name' => $data['name']]);

            $model = DB::transaction(fn () => $this->repository->create{n.pascal}($data));

            Log::info('{n.pascal} created', ['id' => $model->identifier]);

            return (new {n.pascal}Resource($model))
                ->setMessage('{n.pascal} created successfully.')
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);

        }} catch (\\Throwable $e) {{
            Log::error('Failed to create {n.pascal}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to create {n.kebab}.',
                'data'    => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }}
    }}

    public function update(Update{n.pascal}Request $request, string ${n.camel}): JsonResponse
    {{
        try {{
            $model = $this->repository->read{n.pascal}(${n.camel});

            Gate::authorize('update', $model);

            $data = $request->validated();

            Log::info('Updating {n.pascal}', ['id' => ${n.camel}]);

            $model = DB::transaction(fn () => $this->repository->update{n.pascal}(${n.camel}, $data));

            Log::info('{n.pascal} updated', ['id' => $model->identifier]);

            return (new {n.pascal}Resource($model))
                ->setMessage('{n.pascal} updated successfully.')
                ->response()
                ->setStatusCode(Response::HTTP_OK);

        }} catch (ModelNotFoundException) {{
            return response()->json([
                'status'  => 'error',
                'message' => '{n.pascal} not found.',
                'data'    => null,
            ], Response::HTTP_NOT_FOUND);
        }} catch (\\Throwable $e) {{
            Log::error('Failed to update {n.pascal}', [
                'id'    => ${n.camel},
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update {n.kebab}.',
                'data'    => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }}
    }}

    public function destroy(string ${n.camel}): JsonResponse
    {{
        try {{
            $model = $this->repository->read{n.pascal}(${n.camel});

            Gate::authorize('delete', $model);

            Log::info('Deleting {n.pascal}', ['id' => ${n.camel}]);

            DB::transaction(fn () => $this->repository->delete{n.pascal}(${n.camel}));

            Log::info('{n.pascal} deleted', ['id' => ${n.camel}]);

            return response()->json([
                'status'  => 'success',
                'message' => '{n.pascal} deleted successfully.',
                'data'    => null,
            ], Response::HTTP_OK);

        }} catch (ModelNotFoundException) {{
            return response()->json([
                'status'  => 'error',
                'message' => '{n.pascal} not found.',
                'data'    => null,
            ], Response::HTTP_NOT_FOUND);
        }} catch (\\Throwable $e) {{
            Log::error('Failed to delete {n.pascal}', [
                'id'    => ${n.camel},
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete {n.kebab}.',
                'data'    => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }}
    }}

    public function restore(string ${n.camel}): JsonResponse
    {{
        try {{
            $model = $this->repository->query()->withTrashed()
                ->where('identifier', ${n.camel})->firstOrFail();

            Gate::authorize('restore', $model);

            Log::info('Restoring {n.pascal}', ['id' => ${n.camel}]);

            $this->repository->restore{n.pascal}(${n.camel});

            Log::info('{n.pascal} restored', ['id' => ${n.camel}]);

            return response()->json([
                'status'  => 'success',
                'message' => '{n.pascal} restored successfully.',
                'data'    => null,
            ], Response::HTTP_OK);

        }} catch (ModelNotFoundException) {{
            return response()->json([
                'status'  => 'error',
                'message' => '{n.pascal} not found.',
                'data'    => null,
            ], Response::HTTP_NOT_FOUND);
        }} catch (\\Throwable $e) {{
            Log::error('Failed to restore {n.pascal}', [
                'id'    => ${n.camel},
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to restore {n.kebab}.',
                'data'    => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }}
    }}
}}
"""


# ─── 9. Factory & Seeder ──────────────────────────────────────────────────────

def gen_factory(n: Names) -> str:
    return f"""<?php

namespace Database\\Factories;

use App\\Models\\{n.pascal};
use Illuminate\\Database\\Eloquent\\Factories\\Factory;

/**
 * @extends \\Illuminate\\Database\\Eloquent\\Factories\\Factory<\\App\\Models\\{n.pascal}>
 */
class {n.pascal}Factory extends Factory
{{
    protected $model = {n.pascal}::class;

    public function definition(): array
    {{
        return [
            'name'   => $this->faker->unique()->words(rand(2, 3), true),
            'status' => $this->faker->boolean(90),
        ];
    }}

    public function inactive(): static
    {{
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }}
}}
"""


def gen_seeder(n: Names) -> str:
    return f"""<?php

namespace Database\\Seeders;

use App\\Models\\{n.pascal};
use Illuminate\\Database\\Seeder;

class {n.pascal}Seeder extends Seeder
{{
    public function run(): void
    {{
        $items = [
            ['name' => 'Example {n.pascal} One',   'status' => true],
            ['name' => 'Example {n.pascal} Two',   'status' => true],
            ['name' => 'Example {n.pascal} Three', 'status' => false],
        ];

        foreach ($items as $item) {{
            {n.pascal}::firstOrCreate(
                ['name' => $item['name']],
                $item
            );
        }}
    }}
}}
"""


# ─── 10. Tests (Pest v3) ──────────────────────────────────────────────────────

def gen_feature_test(n: Names, is_central: bool) -> str:
    perm = n.plural_kebab

    isolation_test = "" if is_central else f"""
it('cannot access other tenant {n.plural_kebab}', function () {{
    $tenant1 = \\App\\Models\\Tenant::factory()->create();
    $tenant2 = \\App\\Models\\Tenant::factory()->create();

    $user1 = \\App\\Models\\User::factory()->create(['tenant_id' => $tenant1->id]);
    $user1->assignRole('tenant-admin');

    $tenant2->run(function () {{
        {n.pascal}::factory()->count(2)->create();
    }});

    $response = $this->actingAs($user1, 'api')
        ->getJson('/api/{n.plural_kebab}');

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
}});
"""

    role = "audit-manager" if not is_central else "tenant-admin"

    return f"""<?php

use App\\Models\\{n.pascal};
use App\\Models\\User;
use Tests\\Traits\\RefreshDatabaseWithTenancy;

uses(RefreshDatabaseWithTenancy::class);

beforeEach(function () {{
    $this->artisan('db:seed', ['--class' => 'RolePermissionsSeeder']);
    $this->user = User::factory()->create();
    $this->user->assignRole('{role}');
}});

// ── Index ─────────────────────────────────────────────────────────────────────

it('can list {n.plural_kebab}', function () {{
    {n.pascal}::factory()->count(3)->create();

    $response = $this->actingAs($this->user, 'api')
        ->getJson('/api/{n.plural_kebab}');

    $response->assertOk()
        ->assertJsonStructure([
            'status', 'message',
            'data' => ['*' => ['id', 'name', 'status']],
        ]);
}});

it('rejects unauthenticated requests to list {n.plural_kebab}', function () {{
    $response = $this->getJson('/api/{n.plural_kebab}');

    $response->assertUnauthorized();
}});

it('can filter {n.plural_kebab} by search term', function () {{
    {n.pascal}::factory()->create(['name' => 'Alpha Record']);
    {n.pascal}::factory()->create(['name' => 'Beta Record']);

    $response = $this->actingAs($this->user, 'api')
        ->getJson('/api/{n.plural_kebab}?search=Alpha');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
}});

it('can filter {n.plural_kebab} by status', function () {{
    {n.pascal}::factory()->create(['status' => true]);
    {n.pascal}::factory()->inactive()->create();

    $response = $this->actingAs($this->user, 'api')
        ->getJson('/api/{n.plural_kebab}?status=1');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
}});
{isolation_test}
// ── Show ──────────────────────────────────────────────────────────────────────

it('can view a {n.kebab}', function () {{
    $model = {n.pascal}::factory()->create();

    $response = $this->actingAs($this->user, 'api')
        ->getJson("/api/{n.plural_kebab}/{{$model->identifier}}");

    $response->assertOk()
        ->assertJsonPath('data.id', $model->identifier);
}});

it('returns 404 for unknown {n.kebab} identifier', function () {{
    $response = $this->actingAs($this->user, 'api')
        ->getJson('/api/{n.plural_kebab}/00000000-0000-0000-0000-000000000000');

    $response->assertNotFound();
}});

// ── Store ─────────────────────────────────────────────────────────────────────

it('can create a {n.kebab}', function () {{
    $payload = ['name' => 'Test {n.pascal}', 'status' => true];

    $response = $this->actingAs($this->user, 'api')
        ->postJson('/api/{n.plural_kebab}', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Test {n.pascal}');

    $this->assertDatabaseHas('{n.table}', ['name' => 'Test {n.pascal}']);
}});

it('requires name to create a {n.kebab}', function () {{
    $response = $this->actingAs($this->user, 'api')
        ->postJson('/api/{n.plural_kebab}', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
}});

it('requires unique name to create a {n.kebab}', function () {{
    {n.pascal}::factory()->create(['name' => 'Duplicate Name']);

    $response = $this->actingAs($this->user, 'api')
        ->postJson('/api/{n.plural_kebab}', ['name' => 'Duplicate Name']);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
}});

it('prevents unauthorised user from creating a {n.kebab}', function () {{
    $restricted = User::factory()->create();
    $restricted->assignRole('reviewer');

    $response = $this->actingAs($restricted, 'api')
        ->postJson('/api/{n.plural_kebab}', ['name' => 'Unauthorized {n.pascal}']);

    $response->assertForbidden();
}});

// ── Update ────────────────────────────────────────────────────────────────────

it('can update a {n.kebab}', function () {{
    $model = {n.pascal}::factory()->create();

    $response = $this->actingAs($this->user, 'api')
        ->patchJson("/api/{n.plural_kebab}/{{$model->identifier}}", ['name' => 'Updated Name']);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Name');
}});

it('allows updating a {n.kebab} with its existing name', function () {{
    $model = {n.pascal}::factory()->create(['name' => 'Same Name']);

    $response = $this->actingAs($this->user, 'api')
        ->patchJson("/api/{n.plural_kebab}/{{$model->identifier}}", ['name' => 'Same Name']);

    $response->assertOk();
}});

it('returns 404 when updating a non-existent {n.kebab}', function () {{
    $response = $this->actingAs($this->user, 'api')
        ->patchJson('/api/{n.plural_kebab}/00000000-0000-0000-0000-000000000000', ['name' => 'x']);

    $response->assertNotFound();
}});

// ── Destroy ───────────────────────────────────────────────────────────────────

it('can delete a {n.kebab}', function () {{
    $model = {n.pascal}::factory()->create();

    $response = $this->actingAs($this->user, 'api')
        ->deleteJson("/api/{n.plural_kebab}/{{$model->identifier}}");

    $response->assertOk();
    $this->assertSoftDeleted('{n.table}', ['identifier' => $model->identifier]);
}});

// ── Restore ───────────────────────────────────────────────────────────────────

it('can restore a deleted {n.kebab}', function () {{
    $model = {n.pascal}::factory()->create();
    $model->delete();

    $response = $this->actingAs($this->user, 'api')
        ->postJson("/api/{n.plural_kebab}/{{$model->identifier}}/restore");

    $response->assertOk();
    $this->assertNotSoftDeleted('{n.table}', ['identifier' => $model->identifier]);
}});
"""


def gen_unit_test(n: Names, fields: list[FieldSpec], is_central: bool) -> str:
    tenant_fill = "" if is_central else "'tenant_id', "
    fillable_items  = ["'identifier'", f"'{tenant_fill}name'".replace("''", "'")]
    # Rebuild properly
    fillable_core = ["'identifier'"]
    if not is_central:
        fillable_core.append("'tenant_id'")
    fillable_core.append("'name'")
    for f in fields:
        if f.name not in ("identifier", "tenant_id", "name", "status", "created_by", "updated_by"):
            fillable_core.append(f"'{f.name}'")
    fillable_core += ["'status'", "'created_by'", "'updated_by'"]
    fillable_assert = ", ".join(fillable_core)

    boot_test = "" if is_central else f"""
it('{n.snake} auto-sets tenant_id on creation', function () {{
    $tenant = \\App\\Models\\Tenant::factory()->create();

    $model = $tenant->run(function () {{
        return {n.pascal}::factory()->create();
    }});

    expect($model->tenant_id)->toBe($tenant->id);
}});
"""

    return f"""<?php

use App\\Models\\{n.pascal};
use Tests\\Traits\\RefreshDatabaseWithTenancy;

uses(RefreshDatabaseWithTenancy::class);

it('{n.snake} has correct fillable fields', function () {{
    $model = new {n.pascal}();
    expect($model->getFillable())->toBe([{fillable_assert}]);
}});

it('{n.snake} casts status as boolean', function () {{
    $model = new {n.pascal}();
    expect($model->getCasts())->toHaveKey('status');
    expect($model->getCasts()['status'])->toBe('boolean');
}});

it('{n.snake} factory creates a valid model', function () {{
    $model = {n.pascal}::factory()->make();
    expect($model->name)->not->toBeEmpty();
}});

it('{n.snake} generates a uuid identifier on creation', function () {{
    $this->artisan('db:seed', ['--class' => 'RolePermissionsSeeder']);

    $model = {n.pascal}::factory()->create();

    expect($model->identifier)->toMatch(
        '/^[0-9a-f]{{8}}-[0-9a-f]{{4}}-[0-9a-f]{{4}}-[0-9a-f]{{4}}-[0-9a-f]{{12}}$/'
    );
}});

it('{n.snake} supports soft deletes', function () {{
    $this->artisan('db:seed', ['--class' => 'RolePermissionsSeeder']);

    $model = {n.pascal}::factory()->create();
    $model->delete();

    $this->assertSoftDeleted('{n.table}', ['id' => $model->id]);
}});

it('{n.snake} uses identifier as route key', function () {{
    $model = new {n.pascal}();
    expect($model->getRouteKeyName())->toBe('identifier');
}});
{boot_test}"""


# ─── Routes snippet ────────────────────────────────────────────────────────────

def gen_routes_snippet(n: Names) -> str:
    return f"""// Add inside the auth:api group in routes/api.php:

Route::apiResource('{n.plural_kebab}', \\App\\Http\\Controllers\\{n.pascal}Controller::class);
Route::post('{n.plural_kebab}/{{id}}/restore', [\\App\\Http\\Controllers\\{n.pascal}Controller::class, 'restore'])
    ->name('{n.plural_snake}.restore');
"""


# ═══════════════════════════════════════════════════════════════════════════════
#  GENERATOR ORCHESTRATOR
# ═══════════════════════════════════════════════════════════════════════════════

@dataclass
class GeneratorConfig:
    module_name:  str
    fields:       list[FieldSpec] = field(default_factory=list)
    is_central:   bool = False
    dry_run:      bool = False
    skip_tests:   bool = False
    overwrite:    bool = False
    base_dir:     Path = Path(".")


class ModuleGenerator:
    STAGE_COUNT = 10

    def __init__(self, cfg: GeneratorConfig) -> None:
        self.cfg      = cfg
        self.n        = Names.from_input(cfg.module_name)
        self.rollback = RollbackManager()
        self.writer   = FileWriter(cfg.base_dir, self.rollback, cfg.dry_run)
        self._stage   = 0

    # ── Stage progress ─────────────────────────────────────────────────────────

    def _stage_header(self, title: str) -> None:
        self._stage += 1
        bar = "━" * 58
        log.info(color(f"\n┌{bar}┐", BLUE))
        log.info(color(f"│  Stage {self._stage:>2}/{self.STAGE_COUNT}  {title:<46}│", BLUE))
        log.info(color(f"└{bar}┘", BLUE))

    # ── Stages ────────────────────────────────────────────────────────────────

    def _stage_migration(self) -> None:
        self._stage_header("Database Migration")
        ts       = timestamp_prefix()
        location = "database/migrations" if self.cfg.is_central \
            else "database/migrations/tenant"
        path = f"{location}/{ts}_create_{self.n.table}_table.php"
        self.writer.write(
            path,
            gen_migration(self.n, self.cfg.fields, self.cfg.is_central),
            overwrite=self.cfg.overwrite,
        )

    def _stage_model(self) -> None:
        self._stage_header("Model")
        content = gen_model_central(self.n, self.cfg.fields) if self.cfg.is_central \
            else gen_model_tenant(self.n, self.cfg.fields)
        self.writer.write(
            f"app/Models/{self.n.pascal}.php",
            content,
            overwrite=self.cfg.overwrite,
        )

    def _stage_repository(self) -> None:
        self._stage_header("Repository")
        self.writer.write(
            f"app/Repositories/{self.n.pascal}Repository.php",
            gen_repository(self.n, self.cfg.is_central),
            overwrite=self.cfg.overwrite,
        )

    def _stage_filters(self) -> None:
        self._stage_header("Filters")
        base = f"app/Filters/{self.n.plural_pascal}"
        self.writer.write(
            f"{base}/{self.n.pascal}Filters.php",
            gen_filter_main(self.n),
            overwrite=self.cfg.overwrite,
        )
        self.writer.write(
            f"{base}/Filters/SearchTermFilter.php",
            gen_filter_search(self.n),
            overwrite=self.cfg.overwrite,
        )
        self.writer.write(
            f"{base}/Filters/IsActiveFilter.php",
            gen_filter_active(self.n),
            overwrite=self.cfg.overwrite,
        )

    def _stage_requests(self) -> None:
        self._stage_header("Form Requests")
        base = f"app/Http/Requests/{self.n.pascal}"
        self.writer.write(
            f"{base}/Create{self.n.pascal}Request.php",
            gen_request_create(self.n, self.cfg.fields),
            overwrite=self.cfg.overwrite,
        )
        self.writer.write(
            f"{base}/Update{self.n.pascal}Request.php",
            gen_request_update(self.n, self.cfg.fields),
            overwrite=self.cfg.overwrite,
        )

    def _stage_resources(self) -> None:
        self._stage_header("API Resources")
        base = f"app/Http/Resources/{self.n.plural_pascal}"
        self.writer.write(
            f"{base}/{self.n.pascal}Resource.php",
            gen_resource(self.n, self.cfg.fields, self.cfg.is_central),
            overwrite=self.cfg.overwrite,
        )
        self.writer.write(
            f"{base}/{self.n.pascal}Collection.php",
            gen_resource_collection(self.n),
            overwrite=self.cfg.overwrite,
        )

    def _stage_policy(self) -> None:
        self._stage_header("Policy")
        self.writer.write(
            f"app/Policies/{self.n.pascal}Policy.php",
            gen_policy(self.n),
            overwrite=self.cfg.overwrite,
        )

    def _stage_controller(self) -> None:
        self._stage_header("Controller")
        self.writer.write(
            f"app/Http/Controllers/{self.n.pascal}Controller.php",
            gen_controller(self.n),
            overwrite=self.cfg.overwrite,
        )

    def _stage_database(self) -> None:
        self._stage_header("Factory & Seeder")
        self.writer.write(
            f"database/factories/{self.n.pascal}Factory.php",
            gen_factory(self.n),
            overwrite=self.cfg.overwrite,
        )
        self.writer.write(
            f"database/seeders/{self.n.pascal}Seeder.php",
            gen_seeder(self.n),
            overwrite=self.cfg.overwrite,
        )

    def _stage_tests(self) -> None:
        self._stage_header("Pest v3 Tests")
        if self.cfg.skip_tests:
            log.info(color("  SKIPPED (--skip-tests)", YELLOW))
            return
        self.writer.write(
            f"tests/Feature/{self.n.pascal}Test.php",
            gen_feature_test(self.n, self.cfg.is_central),
            overwrite=self.cfg.overwrite,
        )
        self.writer.write(
            f"tests/Unit/{self.n.pascal}UnitTest.php",
            gen_unit_test(self.n, self.cfg.fields, self.cfg.is_central),
            overwrite=self.cfg.overwrite,
        )

    # ── Post-generation instructions ──────────────────────────────────────────

    def _print_next_steps(self) -> None:
        n    = self.n
        perm = n.plural_kebab
        db_cmd = "php artisan migrate" if self.cfg.is_central else "php artisan tenants:migrate"
        scope  = "Central (no tenant filtering)" if self.cfg.is_central \
            else "Tenant-scoped (mandatory tenant filtering)"

        steps = [
            "",
            f"  {color('Manual post-generation steps required:', BOLD + YELLOW)}",
            "",
            f"  1. {color('Register policy', CYAN)} in app/Providers/AppServiceProvider.php:",
            f"     Gate::policy(\\App\\Models\\{n.pascal}::class, \\App\\Policies\\{n.pascal}Policy::class);",
            "",
            f"  2. {color('Add permissions', CYAN)} to config/role-permission-map.php:",
            f"     '{perm}.view', '{perm}.create', '{perm}.edit', '{perm}.delete'",
            "",
            f"  3. {color('Add routes', CYAN)} inside the auth:api group in routes/api.php:",
            f"     Route::apiResource('{n.plural_kebab}', {n.pascal}Controller::class);",
            f"     Route::post('{n.plural_kebab}/{{id}}/restore', [{n.pascal}Controller::class, 'restore'])",
            f"         ->name('{n.plural_snake}.restore');",
            "",
            f"  4. {color('Run migration:', CYAN)}",
            f"     {db_cmd}",
            f"     php artisan db:seed --class={n.pascal}Seeder",
            "",
            f"  5. {color('Run tests:', CYAN)}",
            f"     ./test.sh tests/Feature/{n.pascal}Test.php",
            f"     ./test.sh tests/Unit/{n.pascal}UnitTest.php",
            "",
            f"  6. {color('Format code:', CYAN)}",
            f"     vendor/bin/pint --dirty",
            "",
            f"  {color('Model scope:', DIM)} {scope}",
            "",
        ]
        for s in steps:
            print(s)

    # ── Main run ───────────────────────────────────────────────────────────────

    def run(self) -> bool:
        model_type = "Central" if self.cfg.is_central else "Tenant-scoped"
        log.info(color(f"\n{'═' * 62}", BOLD + BLUE))
        log.info(color(f"  AIAS Module Generator", BOLD + CYAN))
        log.info(color(f"  Module  : {self.n.pascal}", BOLD))
        log.info(color(f"  Table   : {self.n.table}", BOLD))
        log.info(color(f"  Type    : {model_type}", BOLD))
        log.info(color(f"  Fields  : {len(self.cfg.fields)} custom field(s)", BOLD))
        log.info(color(f"  Dry-run : {self.cfg.dry_run}", BOLD))
        log.info(color(f"{'═' * 62}\n", BOLD + BLUE))

        stages = [
            self._stage_migration,
            self._stage_model,
            self._stage_repository,
            self._stage_filters,
            self._stage_requests,
            self._stage_resources,
            self._stage_policy,
            self._stage_controller,
            self._stage_database,
            self._stage_tests,
        ]

        for stage_fn in stages:
            try:
                stage_fn()
            except KeyboardInterrupt:
                log.error(color("\nInterrupted by user.", RED))
                self.rollback.rollback()
                return False
            except Exception as exc:
                log.error(color(f"\nStage '{stage_fn.__name__}' failed: {exc}", RED))
                log.debug(traceback.format_exc())
                self.rollback.rollback()
                return False

        log.info(color(f"\n{'═' * 62}", BOLD + GREEN))
        log.info(color(
            f"  ✅  Generation complete — "
            f"{self.rollback.count} file(s) written",
            BOLD + GREEN,
        ))
        log.info(color(f"{'═' * 62}", BOLD + GREEN))

        self._print_next_steps()
        return True


# ═══════════════════════════════════════════════════════════════════════════════
#  CLI
# ═══════════════════════════════════════════════════════════════════════════════

def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        prog="generate-module.py",
        description=color(
            "AIAS — Laravel module scaffolder\n"
            "Generates all files following the AIAS architectural patterns.",
            BOLD,
        ),
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  # Tenant-scoped module (default)
  python3 docs/prompts/aias/scripts/generate-module.py RiskAssessment

  # Central reference data module
  python3 docs/prompts/aias/scripts/generate-module.py AuditStandard --central

  # With custom fields
  python3 docs/prompts/aias/scripts/generate-module.py Finding \\
    --fields "severity:string:required,status:string:required,resolution:text:nullable"

  # Preview only — writes nothing
  python3 docs/prompts/aias/scripts/generate-module.py AuditPlan --dry-run

  # Overwrite existing files
  python3 docs/prompts/aias/scripts/generate-module.py Country --central --overwrite
        """,
    )
    parser.add_argument(
        "module",
        help="Module name in PascalCase (e.g. AuditStandard, RiskAssessment, Finding)",
    )
    parser.add_argument(
        "--central",
        action="store_true",
        help=(
            "Generate as a central database model (uses CentralConnection + Auditable). "
            "Default: tenant-scoped (uses TenantConnection, includes tenant_id)."
        ),
    )
    parser.add_argument(
        "--fields",
        default="",
        help=(
            "Comma-separated field specs: 'name:type:modifiers'. "
            "type: string|text|integer|boolean|date|datetime|decimal. "
            "modifiers: required|nullable|unique. "
            "e.g.: 'severity:string:required,description:text:nullable,score:decimal:nullable'"
        ),
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Print what would be created without writing any files.",
    )
    parser.add_argument(
        "--skip-tests",
        action="store_true",
        help="Skip Feature and Unit test generation.",
    )
    parser.add_argument(
        "--overwrite",
        action="store_true",
        help="Overwrite existing files (default: skip).",
    )
    parser.add_argument(
        "--base-dir",
        default=".",
        help="AIAS project root directory (default: current directory).",
    )
    parser.add_argument(
        "--verbose", "-v",
        action="store_true",
        help="Enable DEBUG-level logging.",
    )
    return parser.parse_args()


def main() -> None:
    args   = parse_args()
    global log
    log    = setup_logger(args.verbose)

    raw = args.module.strip()
    if not re.match(r"^[A-Za-z][A-Za-z0-9]*$", raw):
        log.error(color(
            f"Invalid module name '{raw}'. "
            "Must be PascalCase letters/digits only (e.g. AuditStandard, RiskAssessment).",
            RED,
        ))
        sys.exit(1)

    fields: list[FieldSpec] = []
    if args.fields.strip():
        for spec in args.fields.split(","):
            spec = spec.strip()
            if spec:
                try:
                    fields.append(FieldSpec.parse(spec))
                except Exception as exc:
                    log.error(color(f"Invalid field spec '{spec}': {exc}", RED))
                    sys.exit(1)

    base_dir = Path(args.base_dir).resolve()
    if not base_dir.is_dir():
        log.error(color(f"Base directory '{base_dir}' does not exist.", RED))
        sys.exit(1)

    cfg = GeneratorConfig(
        module_name = raw,
        fields      = fields,
        is_central  = args.central,
        dry_run     = args.dry_run,
        skip_tests  = args.skip_tests,
        overwrite   = args.overwrite,
        base_dir    = base_dir,
    )

    generator = ModuleGenerator(cfg)
    success   = generator.run()

    sys.exit(0 if success else 1)


if __name__ == "__main__":
    main()
