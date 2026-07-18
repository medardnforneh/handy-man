<?php

declare(strict_types=1);

namespace App\Domain\Access;

/**
 * The ONLY legitimate roles in the system (doc 10). Spatie roles are scoped to exactly two jobs:
 *
 *   1. Organization-internal roles — team-scoped (per organization). A worker can't reassign a
 *      job; a dispatcher can. Real RBAC within a company provider.
 *   2. Staff/admin roles — global. Gate the Filament admin panel.
 *
 * There is deliberately **no `customer` or `provider` role**. The customer/provider section split
 * is NOT a permission — both sections are always open to every user (doc 10). You "become" a
 * provider by using the provider section, gated (where needed) by verified FACTS, not roles. If a
 * task asks for a role that gates the section split, it is wrong — reject it.
 */
enum Role: string
{
    // --- Organization-internal (team-scoped) ---
    case OrgOwner = 'org_owner';
    case OrgDispatcher = 'org_dispatcher';
    case OrgFinance = 'org_finance';
    case OrgWorker = 'org_worker';

    // --- Staff / admin (global) ---
    case SuperAdmin = 'superadmin';
    case Support = 'support';
    case Verifier = 'verifier';
    case FinanceAdmin = 'finance_admin';

    /**
     * @return array<int, self>
     */
    public static function organizationRoles(): array
    {
        return [self::OrgOwner, self::OrgDispatcher, self::OrgFinance, self::OrgWorker];
    }

    /**
     * @return array<int, self>
     */
    public static function staffRoles(): array
    {
        return [self::SuperAdmin, self::Support, self::Verifier, self::FinanceAdmin];
    }

    public function isOrganizationRole(): bool
    {
        return in_array($this, self::organizationRoles(), true);
    }

    public function isStaffRole(): bool
    {
        return in_array($this, self::staffRoles(), true);
    }
}
