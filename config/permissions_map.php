<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | PERMISSIONS (grouped)
    |--------------------------------------------------------------------------
    | These are the raw permissions for the system grouped by module.
    | Roles reference these groups when assigning permissions.
    |--------------------------------------------------------------------------
    */

    'permissions' => [

        'dashboard' => ['view'],

        'clients' => ['view', 'create', 'edit', 'delete', 'force-delete', 'restore', 'deactivate', 'manage-notes', 'override-id'],

        'prospects' => ['view', 'create', 'edit', 'delete', 'convert-to-client'],

        'matters' => ['view', 'create', 'update', 'delete', 'force-delete', 'restore', 'assign-attorney', 'view-documents', 'manage-custom-fields'],

        'tasks' => ['view', 'create', 'edit', 'delete', 'restore', 'assign', 'complete'],

        'calendar' => ['view', 'create', 'update', 'delete', 'restore', 'manage-integrations', 'sync'],

        'billing' => [
            'view', 'view-dashboard', 'view-invoices',
            'create-invoice', 'edit-invoice', 'delete-invoice',
            'send-invoice', 'record-payment', 'edit-payment', 'delete-payment',
        ],

        'reports' => [
            'view', 'view-financial', 'view-productivity', 'view-client-matter',
            'view-operational', 'view-compliance', 'schedule', 'export', 'create-custom',
        ],

        'settings-firm' => ['view', 'edit-profile', 'manage-offices', 'manage-branding', 'edit-billing-plan'],

        'settings-users' => ['view', 'invite', 'edit', 'deactivate', 'assign-roles', 'manage-groups'],

        'security' => ['view-audit-logs', 'view-login-history', 'impersonate'],

        'roles' => ['view', 'create', 'edit', 'delete', 'assign-permissions', 'assign-to-user'],

        'users' => ['view', 'create', 'edit', 'delete', 'update-status', 'assign-practice-areas'],

        // System-level developer permissions
        'system' => [
            'manage-tenants', 'create-tenant', 'edit-tenant', 'delete-tenant',
            'suspend-tenant', 'reactivate-tenant', 'view-all-tenants',
            'manage-system-settings', 'run-maintenance',
        ],

        'groups' => ['view', 'create', 'edit', 'delete'],

        'continents' => ['view', 'create', 'edit', 'delete'],

        'countries' => ['view', 'create', 'edit', 'delete'],

        'contacts' => ['view', 'create', 'edit', 'delete'],

        'billing_types' => ['view', 'create', 'edit', 'delete'],

        'billing_type_details' => ['view', 'create', 'edit', 'delete'],

        'billing_rates' => ['view', 'create', 'edit', 'delete'],

        'pipeline_stages' => ['view', 'create', 'edit', 'delete'],

        'task_categories' => ['view', 'create', 'edit', 'delete'],

        'task_priority_levels' => ['view', 'create', 'edit', 'delete'],
        'task_status_levels' => ['view', 'create', 'edit', 'delete'],
        'task_status_track_reminders' => ['view', 'create', 'edit', 'delete'],
        'entities' => ['view', 'create', 'edit', 'delete'],

        'workflows' => ['view', 'create', 'edit', 'delete'],

        'workflow_stages' => ['view', 'create', 'edit', 'delete'],

        'leave_types' => ['view', 'create', 'edit', 'delete'],

        'document_types' => ['view', 'create', 'edit', 'delete'],

        'documents' => ['view', 'create', 'edit', 'delete', 'restore', 'verify'],

        'proforma_invoices' => ['view', 'create', 'edit', 'delete'],

        'invoices' => ['view', 'create', 'edit', 'delete'],

        'expense_categories' => ['view', 'create', 'edit', 'delete'],

        'expenses' => ['view', 'create', 'edit', 'delete'],

        'payment_methods' => ['view', 'create', 'edit', 'delete'],

        'payments' => ['view', 'create', 'edit', 'delete'],

        'bank_accounts' => ['view', 'create', 'edit', 'delete'],

        'numbering_types' => ['view', 'create', 'edit', 'delete', 'restore'],

        'numbering_formats' => ['view', 'create', 'edit', 'delete', 'restore'],

        'numbering' => ['view', 'create', 'update', 'reset'],

        'overridden_numbers' => ['view', 'delete'],

        'activity_codes' => ['view', 'create', 'edit', 'delete'],

        'task_codes' => ['view', 'create', 'edit', 'delete'],

        'tax_types' => ['view', 'create', 'edit', 'delete'],

        'tax_informations' => ['view', 'create', 'edit', 'delete'],

        'billing_metrics' => ['view'],

        'modules' => ['view', 'create', 'edit', 'delete'],

        'feedback' => ['view', 'create', 'edit', 'delete'],

        'firm_settings' => ['view', 'create', 'edit', 'delete'],

        'office_locations' => ['view', 'create', 'edit', 'delete'],

        'mfa' => ['view-status', 'setup', 'enable', 'disable', 'regenerate-backup-codes', 'update-method'],

        // API Key Management (Central Database - system-admin only)
        'api-keys' => [
            'view', 'create', 'regenerate', 'revoke', 'delete', 'update-permissions',
            'view-usage', 'manage-tracking', 'set-expiration', 'manage-rate-limits',
            'manage-ip-whitelist', 'view-security-logs',
        ],

        'service-users' => [
            'view', 'create', 'edit', 'delete', 'activate', 'deactivate',
            'assign-permissions', 'manage-api-keys',
        ],

        // Aias Administration (Central Database - admin/user)
        'admin-tenants' => [
            'view', 'create', 'edit', 'delete', 'restore',
            'activate', 'suspend', 'verify', 'view-statistics',
        ],

        'admin-users' => [
            'view', 'create', 'edit', 'delete', 'restore',
            'activate', 'deactivate', 'assign-roles', 'reset-password',
        ],

        'admin-settings' => [
            'view', 'edit', 'manage-system-defaults', 'manage-maintenance',
        ],

        'admin-audit' => [
            'view-logs', 'view-login-history', 'export-logs',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | DEFAULT ROLES → PERMISSIONS
    |--------------------------------------------------------------------------
    | Assign permissions to roles here.
    | Wildcards (*) expand based on the permission groups above.
    |--------------------------------------------------------------------------
    */

    'roles' => [

        // SUPER ADMIN — full access
        'super-admin' => ['*'],

        // TENANT ADMIN
        'tenant-admin' => [
            'dashboard.*',
            'clients.*',
            'prospects.*',
            'matters.*',
            'tasks.*',
            'calendar.*',
            'ai.*',
            'time-tracking.*',
            'billing.*',
            'trust.*',
            'reports.*',
            'hr-departments.*',
            'hr-users.*',
            'hr-leave.*',
            'hr-policies.*',
            'hr-documents.*',
            'hr-performance.*',
            'training.*',
            'settings-firm.*',
            'settings-users.*',
            'settings-invoicing.*',
            'settings-client-matter.*',
            'settings-timekeeping.*',
            'settings-workflows.*',
            'settings-other.*',
            'roles.*',
            'security.*',
            'users.*',
            'groups.*',
            'practice_areas.*',
            'form_input_types.*',
            'case_stages.*',
            'lead_sources.*',
            'lead_statuses.*',
            'communication_channels.*',
            'continents.*',
            'countries.*',
            'contacts.*',
            'billing_types.*',
            'billing_type_details.*',
            'billing_rates.*',
            'task_categories.*',
            'task_priority_levels.*',
            'task_status_levels.*',
            'task_status_track_reminders.*',
            'entity_types.*',
            'workflows.*',
            'workflow_stages.*',
            'leave_types.*',
            'document_types.*',
            'documents.*',
            'chart_of_accounts.*',
            'proforma_invoices.*',
            'invoices.*',
            'expense_categories.*',
            'expenses.*',
            'payment_methods.*',
            'payments.*',
            'bank_accounts.*',
            'trust_refunds.*',
            'trust_reports.*',
            'trust_report_columns.*',
            'trust_account_reconciliations.*',
            'reconciliation_adjustments.*',
            'numbering_types.*',
            'numbering_formats.*',
            'numbering.*',
            'overridden_numbers.*',
            'activity_codes.*',
            'task_codes.*',
            'time_tracking.*',
            'tax_types.*',
            'tax_informations.*',
            'trust_account_types.*',
            'trust_configurations.*',
            'invoice_payment_settings.*',
            'modules.*',
            'feedback.*',
            'firm_settings.*',
            'office_locations.*',
            'mfa.*',
        ],

        // TENANT USER — restricted
        'tenant-user' => [
            'dashboard.view',

            'clients.*',
            'prospects.*',
            'matters.*',
            'tasks.*',
            'calendar.*',
            'ai.*',
            'time-tracking.*',

            'reports.*',

            'training.*',

            'hr-leave.view',
            'hr-leave.apply',

            'hr-policies.view',

            'hr-documents.*',

            'hr-performance.view',

            'hr-departments.view',

            'users.view', 'users.edit',
            'groups.*',
            'practice_areas.*',
            'form_input_types.*',
            'case_stages.*',
            'lead_sources.*',
            'lead_statuses.*',
            'communication_channels.*',
            'continents.*',
            'countries.*',
            'contacts.*',
            'billing_types.*',
            'billing_type_details.*',
            'billing_rates.*',
            'pipeline_stages.*',
            'task_categories.*',
            'task_priority_levels.*',
            'task_status_levels.*',
            'task_status_track_reminders.*',
            'entity_types.*',
            'workflows.*',
            'workflow_stages.*',
            'leave_types.*',
            'document_types.*',
            'documents.*',
            'chart_of_accounts.*',
            'proforma_invoices.*',
            'invoices.*',
            'expense_categories.*',
            'expenses.*',
            'payment_methods.*',
            'payments.*',
            'bank_accounts.*',
            'trust_refunds.*',
            'trust_reports.*',
            'trust_report_columns.*',
            'trust_account_reconciliations.*',
            'reconciliation_adjustments.*',
            'numbering_types.*',
            'numbering_formats.*',
            'numbering.*',
            'overridden_numbers.*',
            'activity_codes.*',
            'task_codes.*',
            'time_tracking.*',
            'tax_types.*',
            'tax_informations.*',
            'trust_account_types.*',
            'trust_configurations.*',
            'invoice_payment_settings.*',
            'modules.*',
            'feedback.*',
            'firm_settings.*',
            'office_locations.*',
            'mfa.*',
            'api-keys.view',
            'api-keys.view-usage',
            'service-users.view',
        ],

        // TENANT HR — HR management, settings, user management (no tenant-admin/Aias role assignment)
        'tenant-hr' => [
            'dashboard.*',
            'hr-departments.*',
            'hr-users.*',
            'hr-leave.*',
            'hr-policies.*',
            'hr-documents.*',
            'hr-performance.*',
            'training.*',
            'leave_types.*',
            'leave_requests.*',
            'pipeline_stages.*',
            'settings-workflows.*',
            'settings-users.*',
            'roles.*',
            'tax_types.*',
            'tax_informations.*',
            'firm_settings.*',
            'office_locations.*',
            'users.*',
            'groups.*',
            'mfa.*',
        ],

        // TENANT IT — all permissions except HR modules; own leave requests only
        'tenant-it' => [
            'dashboard.*',
            'clients.*',
            'prospects.*',
            'matters.*',
            'tasks.*',
            'calendar.*',
            'ai.*',
            'time-tracking.*',
            'billing.*',
            'trust.*',
            'reports.*',
            'training.*',
            'settings-firm.*',
            'settings-users.*',
            'settings-invoicing.*',
            'settings-client-matter.*',
            'settings-timekeeping.*',
            'settings-workflows.*',
            'settings-other.*',
            'roles.*',
            'security.*',
            'users.*',
            'groups.*',
            'practice_areas.*',
            'form_input_types.*',
            'case_stages.*',
            'lead_sources.*',
            'lead_statuses.*',
            'communication_channels.*',
            'continents.*',
            'countries.*',
            'contacts.*',
            'billing_types.*',
            'billing_type_details.*',
            'billing_rates.*',
            'pipeline_stages.*',
            'task_categories.*',
            'task_priority_levels.*',
            'task_status_levels.*',
            'task_status_track_reminders.*',
            'entity_types.*',
            'workflows.*',
            'workflow_stages.*',
            'leave_requests.*',
            'document_types.*',
            'documents.*',
            'chart_of_accounts.*',
            'proforma_invoices.*',
            'invoices.*',
            'expense_categories.*',
            'expenses.*',
            'payment_methods.*',
            'payments.*',
            'bank_accounts.*',
            'trust_refunds.*',
            'trust_reports.*',
            'trust_report_columns.*',
            'trust_account_reconciliations.*',
            'reconciliation_adjustments.*',
            'numbering_types.*',
            'numbering_formats.*',
            'numbering.*',
            'overridden_numbers.*',
            'activity_codes.*',
            'task_codes.*',
            'time_tracking.*',
            'tax_types.*',
            'tax_informations.*',
            'trust_account_types.*',
            'trust_configurations.*',
            'invoice_payment_settings.*',
            'modules.*',
            'feedback.*',
            'firm_settings.*',
            'office_locations.*',
            'mfa.*',
        ],

        // API SERVICE — specialized role for API integrations
        'api-service' => [
            // API services have minimal, specific permissions based on integration needs
            // These are typically assigned per-service when creating API keys
            // Default permissions - can be extended per service account
            'dashboard.view',
            'clients.view',
            'matters.view',
        ],

        // Aias ADMIN — full control over central administration
        'admin' => [
            'admin-tenants.*',
            'admin-users.*',
            'admin-settings.*',
            'admin-audit.*',
            'system.*',
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'users.update-status',
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.assign-permissions',
            'roles.assign-to-user',
            'security.*',
            'mfa.*',
            'api-keys.*',
            'service-users.*',
        ],

        // Aias USER — read-only admin with limited write access
        'user' => [
            'admin-tenants.view',
            'admin-tenants.view-statistics',
            'admin-users.view',
            'admin-settings.view',
            'admin-audit.view-logs',
            'admin-audit.view-login-history',
            'users.view',
            'roles.view',
            'mfa.view-status',
            'api-keys.view',
            'api-keys.view-usage',
            'api-keys.view-security-logs',
            'service-users.view',
        ],

    ],

];
