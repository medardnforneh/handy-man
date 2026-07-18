<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Drop Postgres custom enum types on `migrate:fresh`. Our native enums (party_kind, …) are
     * types, not tables — without this, RefreshDatabase's fresh migrate fails with
     * "type already exists" on the second run.
     */
    protected bool $dropTypes = true;
}
