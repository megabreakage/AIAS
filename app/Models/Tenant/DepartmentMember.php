<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\BaseModel;
use App\Models\Concerns\TenantConnection;
use App\Models\User;
use Database\Factories\DepartmentMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class DepartmentMember extends BaseModel
{
    /** @use HasFactory<DepartmentMemberFactory> */
    use HasFactory;

    use SoftDeletes;
    use TenantConnection;

    /** @var list<string> */
    protected $fillable = [
        'department_id',
        'user_id',
    ];

    /** @return array<string, string|class-string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'department_id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    protected static function newFactory(): DepartmentMemberFactory
    {
        return DepartmentMemberFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'identifier';
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<User, $this> */
    public function memberUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
