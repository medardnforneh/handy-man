<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Skill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * A real Cameroonian trade list (build plan P1-07). ~40 leaf skills across ~13 categories, every
 * name in both French and English. risk_tier / requires_license reflect on-site risk (doc 10):
 * electrical and gas work are tier 3 and licensed; most trades tier 2; low-contact or
 * remote-capable work tier 1.
 */
final class SkillsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->taxonomy() as $category) {
            $parent = Skill::query()->updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name_fr' => $category['fr'],
                    'name_en' => $category['en'],
                    'is_leaf' => false,
                    'parent_id' => null,
                    'risk_tier' => 1,
                ],
            );

            foreach ($category['leaves'] as $leaf) {
                Skill::query()->updateOrCreate(
                    ['slug' => $leaf['slug']],
                    [
                        'parent_id' => $parent->id,
                        'name_fr' => $leaf['fr'],
                        'name_en' => $leaf['en'],
                        'is_leaf' => true,
                        'requires_license' => $leaf['license'] ?? false,
                        'risk_tier' => $leaf['risk'],
                    ],
                );
            }
        }
    }

    /**
     * @return list<array{slug: string, fr: string, en: string, leaves: list<array{slug: string, fr: string, en: string, risk: int, license?: bool}>}>
     */
    private function taxonomy(): array
    {
        $data = [
            ['Plomberie', 'Plumbing', [
                ['Réparation de fuite', 'Leak repair', 2],
                ['Installation sanitaire', 'Sanitary installation', 2],
                ['Débouchage de canalisation', 'Drain unclogging', 2],
                ['Chauffe-eau', 'Water heater', 2],
            ]],
            ['Électricité', 'Electrical', [
                ['Installation électrique', 'Electrical installation', 3, true],
                ['Dépannage électrique', 'Electrical troubleshooting', 3],
                ['Éclairage', 'Lighting', 2],
                ['Groupe électrogène', 'Generator', 3, true],
            ]],
            ['Climatisation et froid', 'HVAC and refrigeration', [
                ['Installation de climatiseur', 'AC installation', 2],
                ['Entretien de climatiseur', 'AC maintenance', 2],
                ['Réparation de réfrigérateur', 'Refrigerator repair', 2],
            ]],
            ['Menuiserie', 'Carpentry', [
                ['Meubles sur mesure', 'Custom furniture', 2],
                ['Portes et fenêtres', 'Doors and windows', 2],
                ['Réparation de meubles', 'Furniture repair', 1],
            ]],
            ['Maçonnerie', 'Masonry', [
                ['Carrelage', 'Tiling', 2],
                ['Crépissage', 'Plastering', 2],
                ['Construction', 'Construction', 2],
            ]],
            ['Peinture', 'Painting', [
                ['Peinture intérieure', 'Interior painting', 1],
                ['Peinture extérieure', 'Exterior painting', 2],
                ['Peinture décorative', 'Decorative painting', 1],
            ]],
            ['Nettoyage', 'Cleaning', [
                ['Nettoyage de maison', 'House cleaning', 1],
                ['Nettoyage après chantier', 'Post-construction cleaning', 1],
                ['Nettoyage de vitres', 'Window cleaning', 1],
            ]],
            ['Jardinage', 'Gardening', [
                ['Entretien de jardin', 'Garden maintenance', 1],
                ['Élagage', 'Tree pruning', 2],
                ['Aménagement paysager', 'Landscaping', 1],
            ]],
            ['Mécanique auto', 'Auto mechanics', [
                ['Vidange', 'Oil change', 1],
                ['Diagnostic', 'Diagnostics', 2],
                ['Réparation de moteur', 'Engine repair', 2],
            ]],
            ['Coiffure et beauté', 'Hair and beauty', [
                ['Coiffure à domicile', 'Home hairdressing', 1],
                ['Manucure', 'Manicure', 1],
                ['Maquillage', 'Makeup', 1],
            ]],
            ['Informatique et réseaux', 'IT and networks', [
                ['Dépannage informatique', 'Computer repair', 1],
                ['Installation de réseau', 'Network setup', 1],
                ['Développement web', 'Web development', 1],
            ]],
            ['Cours particuliers', 'Private tutoring', [
                ['Mathématiques', 'Mathematics', 1],
                ['Langues', 'Languages', 1],
                ['Informatique', 'Computing', 1],
            ]],
            ['Couture', 'Tailoring', [
                ['Retouches', 'Alterations', 1],
                ['Confection sur mesure', 'Custom tailoring', 1],
                ['Broderie', 'Embroidery', 1],
            ]],
        ];

        return array_map(function (array $category): array {
            [$fr, $en, $leaves] = $category;

            return [
                'slug' => Str::slug($en),
                'fr' => $fr,
                'en' => $en,
                'leaves' => array_map(fn (array $leaf): array => [
                    'slug' => Str::slug($leaf[1]),
                    'fr' => $leaf[0],
                    'en' => $leaf[1],
                    'risk' => $leaf[2],
                    'license' => $leaf[3] ?? false,
                ], $leaves),
            ];
        }, $data);
    }
}
