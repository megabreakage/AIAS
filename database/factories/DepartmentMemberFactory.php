<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant\DepartmentMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DepartmentMember>
 */
class DepartmentMemberFactory extends Factory
{
    protected $model = DepartmentMember::class;

    public function definition(): array
    {
        return [
            'department_id' => null,
            'user_id' => null,
        ];
    }
}
