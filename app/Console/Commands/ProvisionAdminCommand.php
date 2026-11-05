<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Audit\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ProvisionAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:provision:admin {--email= : Admin email address} {--name= : Admin full name} {--role=superadmin : Role (superadmin|admin)} {--password= : Optional password (prompted securely if omitted)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provision or update a named Superadmin or Global Admin account with strong security constraints.';

    /**
     * Known disposable email domains rejected per security requirements.
     *
     * @var list<string>
     */
    protected array $disposableDomains = [
        'mailinator.com',
        'tempmail.com',
        '10minutemail.com',
        'guerrillamail.com',
        'sharklasers.com',
        'throwawaymail.com',
        'yopmail.com',
        'trashmail.com',
        'dispostable.com',
        'getairmail.com',
        'temp-mail.org',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Admin email address');
        $name = $this->option('name') ?: $this->ask('Admin full name');
        $role = strtolower((string) ($this->option('role') ?: 'superadmin'));
        $password = $this->option('password') ?: $this->secret('Admin password (minimum 12 chars, upper, lower, number, symbol)');

        // 1. Validate email address
        if (empty($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Validation error: A valid email address is required.');

            return self::FAILURE;
        }

        $emailDomain = strtolower((string) substr(strrchr((string) $email, '@') ?: '', 1));
        if (in_array($emailDomain, $this->disposableDomains, true)) {
            $this->error("Security policy violation: Disposable email domain [{$emailDomain}] is prohibited.");

            return self::FAILURE;
        }

        // 2. Validate admin name
        if (empty($name) || strlen(trim((string) $name)) < 2) {
            $this->error('Validation error: Admin name must be at least 2 characters.');

            return self::FAILURE;
        }

        // 3. Validate administrative role
        $validRoles = ['superadmin', 'admin', 'global_admin'];
        if (! in_array($role, $validRoles, true)) {
            $this->error("Validation error: Invalid role [{$role}]. Allowed roles: superadmin, admin.");

            return self::FAILURE;
        }

        // 4. Validate password complexity
        $passwordValidation = $this->validatePasswordComplexity((string) $password);
        if ($passwordValidation !== null) {
            $this->error("Password complexity violation: {$passwordValidation}");

            return self::FAILURE;
        }

        // 5. Create or update user
        $user = User::where('email', $email)->first();
        $isNew = is_null($user);

        if ($isNew) {
            $user = new User();
            $user->email = (string) $email;
            $user->guard = 'web';
        }

        $user->name = (string) $name;
        $user->password = Hash::make((string) $password);
        $user->is_active = true;
        $user->must_reset_password = false;
        $user->save();

        // 6. Assign role
        $roleToAssign = ($role === 'admin') ? 'global_admin' : $role;
        Role::findOrCreate($roleToAssign, 'web');
        $user->assignRole($roleToAssign);
        if ($role === 'admin') {
            Role::findOrCreate('admin', 'web');
            $user->assignRole('admin');
        }

        // 7. Record immutable audit log per DEC-11 / T-12-16 / T-12-20
        $auditEventType = $isNew ? AuditLog::EVENT_ADMIN_PROVISIONED : AuditLog::EVENT_PASSWORD_ROTATED;
        AuditLog::create([
            'workspace_id' => $user->workspace_id,
            'actor_id' => null,
            'actor_guard' => 'cli',
            'event_type' => $auditEventType,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'old_values' => null,
            'new_values' => [
                'email' => $user->email,
                'name' => $user->name,
                'role' => $role,
                'assigned_role' => $roleToAssign,
                'action' => $isNew ? 'admin_provisioned' : 'password_rotated',
                'cli_actor' => get_current_user() ?: 'cli',
            ],
            'reason' => $isNew
                ? "Named admin [{$user->email}] provisioned via CLI"
                : "Password rotated for admin [{$user->email}] via CLI",
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Artisan CLI / crm:provision:admin',
        ]);

        $this->info(sprintf(
            '✓ Admin account [%s] successfully %s.',
            $user->email,
            $isNew ? 'provisioned' : 'updated'
        ));

        $this->table(
            ['Property', 'Value'],
            [
                ['User ID', (string) $user->id],
                ['Name', $user->name],
                ['Email', $user->email],
                ['Assigned Role', $roleToAssign],
                ['Status', 'Active'],
                ['Audit Event', $auditEventType],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Validate password complexity requirements:
     * - Minimum 12 characters
     * - Uppercase letter
     * - Lowercase letter
     * - Digit
     * - Special symbol
     */
    protected function validatePasswordComplexity(string $password): ?string
    {
        if (strlen($password) < 12) {
            return 'Password must be at least 12 characters in length.';
        }

        if (! preg_match('/[A-Z]/', $password)) {
            return 'Password must contain at least one uppercase letter (A-Z).';
        }

        if (! preg_match('/[a-z]/', $password)) {
            return 'Password must contain at least one lowercase letter (a-z).';
        }

        if (! preg_match('/[0-9]/', $password)) {
            return 'Password must contain at least one number (0-9).';
        }

        if (! preg_match('/[^A-Za-z0-9]/', $password)) {
            return 'Password must contain at least one special character/symbol.';
        }

        return null;
    }
}
