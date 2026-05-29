<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\ContactType;
use App\Models\BaseModel;
use App\Models\Concerns\TenantConnection;
use App\Models\User;
use Database\Factories\CompanyContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class CompanyContact extends BaseModel
{
    /** @use HasFactory<CompanyContactFactory> */
    use HasFactory;

    use SoftDeletes;
    use TenantConnection;

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'user_id',
        'contact_type',
    ];

    /** @return array<string, string|class-string> */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'company_id' => 'integer',
            'user_id' => 'integer',
            'contact_type' => ContactType::class,
        ];
    }

    protected static function newFactory(): CompanyContactFactory
    {
        return CompanyContactFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'identifier';
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function contactUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
