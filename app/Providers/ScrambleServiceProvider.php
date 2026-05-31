<?php

declare(strict_types=1);

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\ServiceProvider;

class ScrambleServiceProvider extends ServiceProvider
{
    /**
     * Map controller class substrings to OpenAPI tag names.
     * Order matters: more specific patterns must come before broader ones.
     *
     * @var array<string, string>
     */
    private const CONTROLLER_TAG_MAP = [
        'MfaController'          => 'Multi-Factor Authentication',
        'AuthController'         => 'Authentication',
        'TenantUserController'   => 'Tenant User Provisioning',
        'TenantController'       => 'Tenants',
        'CentralUserController'  => 'Users',
        'Tenant\UserController'  => 'Users',
        'ContinentController'    => 'Continents',
        'CountryController'      => 'Countries',
        'HealthController'       => 'System',
        'PriorityLevelController' => 'Priority Levels',
        'AuditController'        => 'Audits',
        'ChecklistTypeController' => 'Checklist Types',
        'ChecklistController'    => 'Checklists',
        'CompanyController'      => 'Companies',
        'DepartmentController'   => 'Departments',
        'PreambleController'     => 'Preambles',
        'SectionStyleController' => 'Section Styles',
    ];

    public function register(): void {}

    public function boot(): void
    {
        Scramble::configure()
            ->afterOpenApiGenerated(function (OpenApi $openApi): void {
                $openApi->secure(
                    SecurityScheme::http('bearer', 'Passport Personal Access Token')
                        ->as('BearerAuth')
                );
            });

        Scramble::resolveTagsUsing(function (RouteInfo $routeInfo): array {
            $controller = $routeInfo->className() ?? '';

            foreach (self::CONTROLLER_TAG_MAP as $fragment => $tag) {
                if (str_contains($controller, $fragment)) {
                    return [$tag];
                }
            }

            return ['General'];
        });
    }
}
