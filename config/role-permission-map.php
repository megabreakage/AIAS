<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Role-Permission Map
|--------------------------------------------------------------------------
|
| Defines all permissions for each module and maps them to tenant roles.
| Consumed by DefaultTenantRoleSeeder to create and assign permissions.
|
| Format:
|   'module-slug' => [
|       'permissions' => ['module.view', 'module.create', 'module.edit', 'module.delete'],
|       'roles'       => [
|           'role-name' => ['module.view', 'module.create', ...],
|       ],
|   ],
|
*/

return [

    'priority-levels' => [
        'permissions' => [
            'priority-levels.view',
            'priority-levels.create',
            'priority-levels.edit',
            'priority-levels.delete',
        ],
        'roles' => [
            'tenant-admin' => [
                'priority-levels.view',
                'priority-levels.create',
                'priority-levels.edit',
                'priority-levels.delete',
            ],
            'auditor' => [
                'priority-levels.view',
                'priority-levels.create',
                'priority-levels.edit',
            ],
            'viewer' => [
                'priority-levels.view',
            ],
        ],
    ],

];
