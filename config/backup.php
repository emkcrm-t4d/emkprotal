<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Backup Storage Disk & Directory
    |--------------------------------------------------------------------------
    |
    | Destination filesystem disk and storage path where encrypted platform
    | archives and SHA-256 integrity manifests are stored.
    |
    */

    'disk' => env('BACKUP_DISK', 'local'),

    'path' => storage_path('app/backups'),

    /*
    |--------------------------------------------------------------------------
    | Archive Encryption Settings (T-10-10)
    |--------------------------------------------------------------------------
    |
    | Symmetric encryption algorithm and secret key used to encrypt the
    | raw ZIP archive containing the database dump and private documents.
    |
    */

    'cipher' => env('BACKUP_CIPHER', 'AES-256-CBC'),

    'encryption_key' => env('BACKUP_ENCRYPTION_KEY', env('APP_KEY')),

    /*
    |--------------------------------------------------------------------------
    | Operational SLOs & Retention Policy (GAP-P1-06)
    |--------------------------------------------------------------------------
    |
    | Rolling snapshot retention in days (default 30 days) and atomic
    | concurrency lock timeout in seconds (default 10 minutes = 600s).
    |
    */

    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),

    'timeout_seconds' => (int) env('BACKUP_TIMEOUT_SECONDS', 600),

    'concurrency_lock_key' => 'platform_backup_lock',

    'rpo_target_hours' => 1,

    'rto_target_hours' => 4,

    /*
    |--------------------------------------------------------------------------
    | Private Documents Path
    |--------------------------------------------------------------------------
    |
    | The source directory containing private applicant and member attachments
    | bundled into full platform archives.
    |
    */

    'documents_disk' => 'private',

    /*
    |--------------------------------------------------------------------------
    | Database Tables Included (DASH-05)
    |--------------------------------------------------------------------------
    |
    | Relational tables to be dumped in dependency order. Core CRM entities,
    | membership records, invoices, audit trails, and platform configurations.
    |
    */

    'tables' => [
        'workspaces',
        'spaces',
        'users',
        'membership_terms',
        'member_id_sequences',
        'leads',
        'lead_attachments',
        'lead_verifications',
        'members',
        'invoices',
        'id_cards',
        'member_change_requests',
        'audit_logs',
        'platform_settings',
        'email_messages',
        'email_threads',
        'email_templates',
        'workflow_rules',
        'forms',
        'form_submissions',
        'roles',
        'permissions',
        'model_has_roles',
        'model_has_permissions',
        'role_has_permissions',
    ],

];
