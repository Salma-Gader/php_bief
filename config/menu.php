<?php
/**
 * The six brunch formulas shown on the landing page.
 *
 * Descriptions only mention what the photos show.
 * Prices in Moroccan dirhams (MAD). null = price not provided yet: the page
 * then shows "Price coming soon" and the SEO schema leaves the price out.
 *
 * 'image' is the base name of the photo in public/assets/img/
 * (the page uses its -480 and -800 WebP versions).
 */

return [
    [
        'name'        => 'Continental breakfast',
        'description' => 'Croissants and bread rolls, an omelette with green salad, yogurt, fresh orange juice and a hot drink.',
        'price'       => null,
        'image'       => 'bf-continentale',
    ],
    [
        'name'        => 'Marrakech breakfast',
        'description' => 'Fried eggs with khlii, Moroccan pancakes with honey and butter, olives, and mint tea poured from a silver teapot.',
        'price'       => null,
        'image'       => 'bf-marrakech',
    ],
    [
        'name'        => 'Mediterranean breakfast',
        'description' => 'Cucumber, cherry tomatoes and white cheese dressed with olive oil and herbs, served with black olives.',
        'price'       => null,
        'image'       => 'bf-mediterranee',
    ],
    [
        'name'        => 'Brunch buffet',
        'description' => 'A generous buffet of fresh salads, savoury dishes, pastries and sweet bites to enjoy at your own pace.',
        'price'       => null,
        'image'       => 'bf-buffet',
    ],
    [
        'name'        => 'Fresh juice bar',
        'description' => 'A row of freshly pressed juices, from classic orange to green and red blends.',
        'price'       => null,
        'image'       => 'jus',
    ],
    [
        'name'        => 'Yogurts and fruit verrines',
        'description' => 'Little glasses of fresh fruit and creamy yogurt, prepared for the brunch table.',
        'price'       => null,
        'image'       => 'verrines',
    ],
];
