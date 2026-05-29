<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\AuditStatusStageStatus;
use App\Models\BaseModel;
use App\Models\Concerns\TenantConnection;
use App\Models\User;
use Database\Factories\AuditStatusStageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class AuditStatusStage extends BaseModel
{
    /** Status constants for convenience */
    public const SCHEDULED = 'scheduled';

    public const IN_PROGRESS = 'in_progress';

    public const COMPLETED = 'completed';

    public const CLOSED = 'closed';

    /** @use HasFactory<AuditStatusStageFactory> */
    use HasFactory;

    use SoftDeletes;
    use TenantConnection;

    /** @var list<string> */
    protected $fillable = [
        'audit_id',
        'status',
        'changed_at',
        'changed_by',
        'notes',
    ];

    /** @return array<string, string|class-string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'audit_id' => 'integer',
            'status' => AuditStatusStageStatus::class,
            'changed_at' => 'datetime',
            'changed_by' => 'integer',
        ];
    }

    protected static function newFactory(): AuditStatusStageFactory
    {
        return AuditStatusStageFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'identifier';
    }

    /** @return BelongsTo<Audit, $this> */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
