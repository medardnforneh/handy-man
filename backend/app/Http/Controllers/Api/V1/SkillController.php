<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SkillResource;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Skills catalog (build plan P1-07). Public — discovery must work without the app (doc 08). Search
 * uses the FTS dictionary that matches the query language (P1-07b).
 */
final class SkillController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $categories = Skill::query()
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('name_fr')
            ->get();

        return SkillResource::collection($categories);
    }

    public function search(Request $request): AnonymousResourceCollection
    {
        $term = trim((string) $request->query('q', ''));
        $locale = in_array($request->query('locale'), ['fr', 'en'], true)
            ? (string) $request->query('locale')
            : (string) config('app.locale');

        $results = $term === ''
            ? collect()
            : Skill::query()->where('is_leaf', true)->search($term, $locale)->limit(20)->get();

        return SkillResource::collection($results);
    }
}
