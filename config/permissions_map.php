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

    'central' => [
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
        'tenants' => [
            'view', 'create', 'edit', 'delete', 'restore',
            'activate', 'suspend', 'verify', 'view-statistics',
        ],

        'settings' => [
            'view', 'edit', 'manage-system-defaults', 'manage-maintenance',
        ],

        'audit' => [
            'view-logs', 'view-login-history', 'export-logs',
        ],
    ],

    'tenants' => [

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

        'users' => [
            'view', 'create', 'edit', 'delete', 'restore',
            'activate', 'deactivate', 'assign-roles', 'reset-password', 'verify-email', 'manage-mfa',
        ],

        // System-level developer permissions
        'system' => [
            'manage-tenants', 'create-tenant', 'edit-tenant', 'delete-tenant',
            'suspend-tenant', 'reactivate-tenant', 'view-all-tenants',
            'manage-system-settings', 'run-maintenance',
        ],

        'groups' => ['view', 'create', 'edit', 'delete'],

        'continents' => ['view', 'create', 'edit', 'delete'],

        'countries' => ['view', 'create', 'edit', 'delete'],

        'section_styles' => ['view', 'create', 'edit', 'delete', 'restore'],

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

        'checklist-types' => ['view', 'create', 'update', 'delete', 'restore'],

        'section-styles' => ['view', 'create', 'update', 'delete', 'restore'],

    ],

];
