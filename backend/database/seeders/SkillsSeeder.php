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
    /**
     * Trades that genuinely need doing again, and how often (P7-07's `maintenance_due`).
     *
     * Deliberately a SHORT list. Every trade absent from it schedules no maintenance nudge at all,
     * because a reminder to "service" a wardrobe or a haircut is noise, and noise is what trains
     * people to ignore the reminders that matter. Intervals are the honest service life of the work
     * in this climate — Douala humidity is hard on air conditioning, generators run often and long.
     *
     * @var array<string, int>
     */
    private const MAINTENANCE_INTERVAL_DAYS = [
        // Keys are the generated slugs, which come from the ENGLISH leaf name (see taxonomy()).
        'ac-maintenance' => 180,      // servicing — twice a year is the real cadence in this climate
        'ac-installation' => 180,     // a newly installed unit still wants its first service
        'generator' => 180,           // generators here are run hard and often
        'garden-maintenance' => 90,   // a garden in the rainy season does not wait a year
        'oil-change' => 180,
        'drain-unclogging' => 365,    // recurring for a household that has needed it once
        'house-cleaning' => 90,
    ];

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
                        'maintenance_interval_days' => self::MAINTENANCE_INTERVAL_DAYS[$leaf['slug']] ?? null,
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

            // ── Added because the first thirteen categories were a starting sample, not the trade
            // list of a Cameroonian city. A directory that cannot name solar, boreholes, welding,
            // roofing or generators is missing the work people here actually pay for, and every
            // trade absent from this list is a search that returns nothing and a provider who
            // cannot say what they do.
            ['Énergie solaire', 'Solar energy', [
                ['Installation de panneaux solaires', 'Solar panel installation', 3, true],
                ['Batteries et onduleurs', 'Batteries and inverters', 3, true],
                ['Entretien de système solaire', 'Solar system maintenance', 2],
            ]],
            ['Sécurité', 'Security', [
                ['Vidéosurveillance', 'CCTV installation', 2],
                ['Alarme et détection', 'Alarms and detection', 2],
                ['Portail automatique', 'Automatic gates', 3],
                ['Serrurerie', 'Locksmithing', 2],
            ]],
            ['Toiture', 'Roofing', [
                ['Pose de toiture', 'Roof installation', 3],
                ['Réparation de toiture', 'Roof repair', 3],
                ['Gouttières', 'Gutters', 2],
                ['Étanchéité', 'Waterproofing', 2],
            ]],
            ['Métallerie et aluminium', 'Metalwork and aluminium', [
                ['Soudure', 'Welding', 3],
                ['Portails et grilles', 'Gates and grilles', 2],
                ['Menuiserie aluminium', 'Aluminium joinery', 2],
                ['Vitrerie', 'Glazing', 2],
            ]],
            ['Électroménager', 'Appliance repair', [
                ['Machine à laver', 'Washing machine', 2],
                ['Cuisinière et four', 'Cooker and oven', 2],
                ['Télévision et audio', 'TV and audio', 1],
            ]],
            ['Téléphonie', 'Phone repair', [
                ['Écran de téléphone', 'Phone screen', 1],
                ['Batterie de téléphone', 'Phone battery', 1],
                ['Déblocage et logiciel', 'Unlocking and software', 1],
            ]],
            ['Eau et forage', 'Water and boreholes', [
                ['Forage', 'Borehole drilling', 3, true],
                ['Pompe à eau', 'Water pump', 2],
                ['Château d’eau et citerne', 'Water tank', 2],
                ['Filtration et traitement', 'Filtration and treatment', 2],
            ]],
            ['Déménagement et transport', 'Moving and transport', [
                ['Déménagement', 'House moving', 1],
                ['Livraison', 'Delivery', 1],
                ['Montage de meubles', 'Furniture assembly', 1],
                ['Évacuation de gravats', 'Rubble removal', 1],
            ]],
            ['Événementiel', 'Events', [
                ['Traiteur', 'Catering', 1],
                ['Décoration d’événement', 'Event decoration', 1],
                ['Sonorisation et lumière', 'Sound and lighting', 2],
                ['Photographie et vidéo', 'Photography and video', 1],
            ]],
            ['Décoration intérieure', 'Interior decoration', [
                ['Rideaux et stores', 'Curtains and blinds', 1],
                ['Tapisserie et ameublement', 'Upholstery', 1],
                ['Faux plafond', 'False ceiling', 2],
                ['Revêtement de sol', 'Flooring', 2],
            ]],
            ['Dératisation et désinsectisation', 'Pest control', [
                ['Traitement anti-nuisibles', 'Pest treatment', 2, true],
                ['Fumigation', 'Fumigation', 3, true],
            ]],
            ['Services professionnels', 'Professional services', [
                ['Comptabilité', 'Accounting', 1],
                ['Traduction', 'Translation', 1],
                ['Secrétariat et saisie', 'Secretarial and data entry', 1],
                ['Rédaction et administratif', 'Admin and paperwork', 1],
            ]],
            ['Design et communication', 'Design and communication', [
                ['Design graphique', 'Graphic design', 1],
                ['Community management', 'Community management', 1],
                ['Impression et signalétique', 'Printing and signage', 1],
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
