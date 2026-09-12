<?php

declare(strict_types=1);

/*
 * The cities a customer can name when requesting a quote from the public site, with a centroid
 * for each.
 *
 * The app places an on-site job with the device's own GPS fix. The website cannot — it must work
 * with no JavaScript on a throttled connection — and an on-site job without coordinates cannot
 * exist (the address requirement is a DB CHECK, doc 06). A city centroid is the honest substitute:
 * provider matching is a service-radius query, and a provider's radius covers their city, so a
 * request placed at the centre of Douala reaches the same providers a fix from anywhere in Douala
 * would. The customer's own line and quarter travel on the address as text, for the provider.
 *
 * Keys are the values the form submits; labels are per locale.
 */
return [
    'yaounde' => ['fr' => 'Yaoundé', 'en' => 'Yaoundé', 'lat' => 3.8480, 'lng' => 11.5021, 'region' => 'Centre'],
    'douala' => ['fr' => 'Douala', 'en' => 'Douala', 'lat' => 4.0511, 'lng' => 9.7679, 'region' => 'Littoral'],
    'bafoussam' => ['fr' => 'Bafoussam', 'en' => 'Bafoussam', 'lat' => 5.4737, 'lng' => 10.4179, 'region' => 'Ouest'],
    'bamenda' => ['fr' => 'Bamenda', 'en' => 'Bamenda', 'lat' => 5.9631, 'lng' => 10.1591, 'region' => 'Nord-Ouest'],
    'garoua' => ['fr' => 'Garoua', 'en' => 'Garoua', 'lat' => 9.3017, 'lng' => 13.3921, 'region' => 'Nord'],
    'maroua' => ['fr' => 'Maroua', 'en' => 'Maroua', 'lat' => 10.5910, 'lng' => 14.3159, 'region' => 'Extrême-Nord'],
    'ngaoundere' => ['fr' => 'Ngaoundéré', 'en' => 'Ngaoundéré', 'lat' => 7.3167, 'lng' => 13.5833, 'region' => 'Adamaoua'],
    'bertoua' => ['fr' => 'Bertoua', 'en' => 'Bertoua', 'lat' => 4.5772, 'lng' => 13.6847, 'region' => 'Est'],
    'buea' => ['fr' => 'Buéa', 'en' => 'Buea', 'lat' => 4.1527, 'lng' => 9.2410, 'region' => 'Sud-Ouest'],
    'limbe' => ['fr' => 'Limbé', 'en' => 'Limbe', 'lat' => 4.0186, 'lng' => 9.2054, 'region' => 'Sud-Ouest'],
    'kumba' => ['fr' => 'Kumba', 'en' => 'Kumba', 'lat' => 4.6363, 'lng' => 9.4469, 'region' => 'Sud-Ouest'],
    'nkongsamba' => ['fr' => 'Nkongsamba', 'en' => 'Nkongsamba', 'lat' => 4.9547, 'lng' => 9.9404, 'region' => 'Littoral'],
    'edea' => ['fr' => 'Édéa', 'en' => 'Edéa', 'lat' => 3.8000, 'lng' => 10.1333, 'region' => 'Littoral'],
    'kribi' => ['fr' => 'Kribi', 'en' => 'Kribi', 'lat' => 2.9375, 'lng' => 9.9097, 'region' => 'Sud'],
    'ebolowa' => ['fr' => 'Ebolowa', 'en' => 'Ebolowa', 'lat' => 2.9000, 'lng' => 11.1500, 'region' => 'Sud'],
];
