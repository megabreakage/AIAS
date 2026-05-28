<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\AuditScope;
use App\Models\BaseModel;
use App\Models\Concerns\TenantConnection;
use App\Models\User;
use Database\Factories\AuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Audit extends BaseModel
{
    /** @use HasFactory<AuditFactory> */
    use HasFactory;

    use SoftDeletes;
    use TenantConnection;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'reference_number',
        'name',
        'checklist_id',
        'task_type_id',
        'scope',
        'department_id',
        'audit_start_date',
        'audit_end_date',
        'lead_auditor_id',
        'quality_manager_id',
        'add_appendix',
        'description',
        'is_featured',
        'created_by',
        'updated_by',
    ];

    /** @return array<string, string|class-string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'checklist_id' => 'integer',
            'task_type_id' => 'integer',
            'scope' => AuditScope::class,
            'department_id' => 'integer',
            'audit_start_date' => 'datetime',
            'audit_end_date' => 'datetime',
            'lead_auditor_id' => 'integer',
            'quality_manager_id' => 'integer',
            'add_appendix' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function generateReferenceNumber(): string
    {
        return 'AUD-'.now()->year.$this->id.'-'.now()->timestamp;
    }

    protected static function booted(): void
    {
        parent::booted();

        self::created(function (self $audit): void {
            $audit->updateQuietly([
                'reference_number' => $audit->generateReferenceNumber(),
            ]);
        });
    }

    protected static function newFactory(): AuditFactory
    {
        return AuditFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'identifier';
    }

    /** @return HasMany<AuditStatusStage, $this> */
    public function statusStages(): HasMany
    {
        return $this->hasMany(AuditStatusStage::class)->orderBy('changed_at', 'asc');
    }

    /** @return HasOne<AuditStatusStage, $this> */
    public function latestStatus(): HasOne
    {
        return $this->hasOne(AuditStatusStage::class)->latestOfMany('changed_at');
    }

    /** @return BelongsTo<User, $this> */
    public function leadAuditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_auditor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function qualityManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quality_manager_id');
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /** @return BelongsTo<Checklist, $this> */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class, 'checklist_id');
    }
}
