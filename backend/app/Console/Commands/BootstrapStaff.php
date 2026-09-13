<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Party;
use App\Models\User;
use Database\Seeders\StaffRolesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The first staff account on a fresh installation (docs/11-deployment.md).
 *
 * Every other staff account is granted by an existing one through the admin panel (P?-staff
 * access), which leaves the very first with nowhere to come from. The demo seeder's
 * admin@handyman.cm / password is exactly what must NOT exist on a production database, so this
 * creates one real person: a superadmin with a generated password printed ONCE, who is then made
 * to enrol 2FA by the panel before seeing anything (P1-09). Idempotent on the email — running it
 * again for an existing account only makes sure the role is there.
 */
final class BootstrapStaff extends Command
{
    protected $signature = 'staff:bootstrap
        {email : The person\'s email — their admin login}
        {phone : Their phone in E.164 (+237…), which is also how the app would know them}
        {--name= : Display name (defaults to the email\'s local part)}';

    protected $description = 'Create the first superadmin on a fresh installation, with a one-time password';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $phone = trim((string) $this->argument('phone'));
        $name = (string) ($this->option('name') ?: Str::before($email, '@'));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! preg_match('/^\+[1-9]\d{6,14}$/', $phone)) {
            $this->error('Give a valid email and an E.164 phone (+237…).');

            return self::INVALID;
        }

        $this->call(StaffRolesSeeder::class);

        $password = null;
        $user = DB::transaction(function () use ($email, $phone, $name, &$password): User {
            $existing = User::query()->where('email', $email)->first();
            if ($existing !== null) {
                return $existing;
            }

            $password = Str::password(20);

            return User::query()->create([
                'party_id' => Party::query()->create(['kind' => Party::KIND_INDIVIDUAL, 'display_name' => $name, 'status' => 'active'])->id,
                'email' => $email,
                'phone_e164' => $phone,
                'password_hash' => $password,
                'locale' => 'en',
                'comms_locale' => 'en',
                'status' => 'active',
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
            ]);
        });

        $user->assignRole('superadmin');

        $this->info("superadmin: {$user->email}");
        if ($password !== null) {
            $this->newLine();
            $this->line("  One-time password: <fg=yellow>{$password}</>");
            $this->line('  Shown once, never stored in the clear. Sign in at /admin and enrol 2FA — the panel will insist.');
        } else {
            $this->line('  Account existed; the role is in place.');
        }

        return self::SUCCESS;
    }
}
