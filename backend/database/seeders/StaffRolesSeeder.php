<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Access\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Seeds the GLOBAL staff/admin roles (team_id null). Organization-internal roles are created
 * per-organization when a company is onboarded (P1/P2), not seeded here.
 */
final class StaffRolesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Role::staffRoles() as $role) {
            SpatieRole::findOrCreate($role->value, 'web');
        }
    }
}
