<?php

declare(strict_types=1);

use App\Models\Skill;
use Database\Seeders\SkillsSeeder;

it('seeds ~40 bilingual leaf skills across ~10 categories', function () {
    $this->seed(SkillsSeeder::class);

    $categories = Skill::whereNull('parent_id')->count();
    $leaves = Skill::where('is_leaf', true)->count();

    expect($categories)->toBeGreaterThanOrEqual(10)
        ->and($leaves)->toBeGreaterThanOrEqual(40);

    // Every skill has BOTH languages populated (no half-translated catalog).
    $untranslated = Skill::query()
        ->where(fn ($q) => $q->whereNull('name_fr')->orWhere('name_fr', '')->orWhereNull('name_en')->orWhere('name_en', ''))
        ->count();
    expect($untranslated)->toBe(0);
});

it('searches with the French dictionary for a French query', function () {
    $this->seed(SkillsSeeder::class);

    $results = Skill::query()->where('is_leaf', true)->search('électricité', 'fr')->get();

    // "Dépannage électrique" and "Installation électrique" stem to "électr" in French.
    expect($results)->not->toBeEmpty()
        ->and($results->pluck('name_fr')->implode('|'))->toContain('électrique');
});

it('searches with the English dictionary for an English query', function () {
    $this->seed(SkillsSeeder::class);

    $results = Skill::query()->where('is_leaf', true)->search('repair', 'en')->get();

    // "repair" stems across several leaves (Leak repair, Engine repair, …).
    expect($results->pluck('name_en'))->toContain('Leak repair');
});

it('uses the matching config so an English query does not match French-only stems', function () {
    $this->seed(SkillsSeeder::class);

    // "menuiserie" is French; searching the ENGLISH column/config for it finds nothing.
    $enResults = Skill::query()->where('is_leaf', true)->search('menuiserie', 'en')->get();
    expect($enResults)->toBeEmpty();

    // The French config finds the French skills.
    $frResults = Skill::query()->where('is_leaf', true)->search('meubles', 'fr')->get();
    expect($frResults)->not->toBeEmpty();
});

it('exposes the catalog publicly, localized', function () {
    $this->seed(SkillsSeeder::class);

    // Public — no auth.
    $this->getJson('/api/v1/skills?locale=en')
        ->assertOk()
        ->assertJsonPath('data.0.children.0.name', fn ($name) => is_string($name));

    $this->getJson('/api/v1/skills/search?q=carrelage&locale=fr')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Carrelage');
});

it('carries risk tiers that drive on-site verification (doc 10)', function () {
    $this->seed(SkillsSeeder::class);

    // Electrical installation is high-risk and licensed.
    $electrical = Skill::where('slug', 'electrical-installation')->firstOrFail();
    expect($electrical->risk_tier)->toBe(3)
        ->and($electrical->requires_license)->toBeTrue();
});
