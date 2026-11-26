<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$menu    = require APP_ROOT . '/config/menu.php';
$slots   = config('booking.slots');
$minDate = BookingCalendar::firstBookableDate()->format('Y-m-d');
$maxDate = BookingCalendar::lastBookableDate()->format('Y-m-d');

// --- SEO ---
$pageTitle = 'Sunset Brunch, Tanger: book a table for Sunday brunch';
$pageDescription = 'Moroccan brunch classics every Sunday in Tanger. Three sittings at 12:00, 13:30 and 15:00. Book your table online.';
$shareImage = app_url('assets/img/og-image.jpg');
$prices = array_column($menu, 'price');

// Restaurant structured data (schema.org) for search engines. Only facts the
// site really has: no street address or closing time is invented.
$restaurantSchema = [
    '@context'            => 'https://schema.org',
    '@type'               => 'Restaurant',
    'name'                => 'Sunset Brunch',
    'description'         => $pageDescription,
    'url'                 => app_url(),
    'image'               => $shareImage,
    'servesCuisine'       => ['Moroccan', 'Brunch'],
    'priceRange'          => min($prices) . '–' . max($prices) . ' MAD',
    'acceptsReservations' => app_url('#booking'),
    'address'             => [
        '@type'           => 'PostalAddress',
        'addressLocality' => 'Tanger',
        'addressCountry'  => 'MA',
    ],
    'hasMenu' => [
        '@type'          => 'Menu',
        'name'           => 'The Sunday menu',
        'hasMenuSection' => [
            '@type'       => 'MenuSection',
            'name'        => 'Brunch',
            'hasMenuItem' => array_map(static fn (array $dish): array => [
                '@type'       => 'MenuItem',
                'name'        => $dish['name'],
                'description' => $dish['description'],
                'offers'      => ['@type' => 'Offer', 'price' => (string) $dish['price'], 'priceCurrency' => 'MAD'],
            ], $menu),
        ],
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="theme-color" content="#12355B">
    <link rel="canonical" href="<?= e(app_url()) ?>">
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">

    <!-- Open Graph / Twitter: the preview card shown when the link is shared -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Sunset Brunch">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:url" content="<?= e(app_url()) ?>">
    <meta property="og:image" content="<?= e($shareImage) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Illustration of the Strait of Gibraltar at sunset seen from the Tanger medina">
    <meta property="og:locale" content="en_GB">
    <meta name="twitter:card" content="summary_large_image">

    <!-- JSON_HEX_TAG turns < and > into < / >, so no value can close this <script> early -->
    <script type="application/ld+json"><?= json_encode($restaurantSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_PRETTY_PRINT) ?></script>

    <link rel="preload" href="assets/fonts/josefin-sans-latin-wght.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="assets/img/hero-strait.svg" as="image" type="image/svg+xml">
    <link rel="stylesheet" href="assets/css/main.css">
    <!-- type="module" runs the script after the HTML is parsed (like defer) and keeps its variables private. -->
    <script type="module" src="assets/js/booking.js"></script>
</head>
<body>
    <a class="skip-link" href="#booking">Skip to the booking form</a>

    <header class="site-header">
        <a class="brand" href="#top">Sunset Brunch</a>
        <nav aria-label="Main">
            <ul class="nav-list">
                <li class="nav-optional"><a href="#menu">Menu</a></li>
                <li><a href="#booking">Book a table</a></li>
            </ul>
        </nav>
    </header>

    <main id="top">
        <!-- 1. Hero -->
        <section class="hero" aria-labelledby="hero-title">
            <div class="hero__content">
                <h1 class="hero__title" id="hero-title">Sunset Brunch</h1>
                <p class="hero__tagline">Moroccan brunch classics, served slowly every Sunday in Tanger.</p>
                <p class="hero__times">Three sittings each Sunday: 12:00, 13:30 and 15:00.</p>
                <div class="hero__actions">
                    <a class="button button--primary" href="#booking">Book a table</a>
                    <a class="hero__link" href="#menu">See the menu</a>
                </div>
            </div>
        </section>

        <!-- 2. Menu -->
        <section class="section menu" id="menu" aria-labelledby="menu-title">
            <div class="container">
                <h2 class="section__title" id="menu-title">The Sunday menu</h2>
                <p class="section__intro">Six dishes from the Moroccan breakfast table, made to share.</p>

                <ul class="dish-grid" role="list">
                    <?php foreach ($menu as $dish): ?>
                        <li class="dish">
                            <div class="dish__window" aria-hidden="true"></div>
                            <div class="dish__body">
                                <h3 class="dish__name"><?= e($dish['name']) ?></h3>
                                <p class="dish__description"><?= e($dish['description']) ?></p>
                                <p class="dish__price"><data value="<?= e($dish['price']) ?>"><?= e($dish['price']) ?> MAD</data></p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <!-- 3. Booking -->
        <section class="section booking" id="booking" aria-labelledby="booking-title">
            <div class="container booking__layout">
                <div class="booking__info">
                    <h2 class="section__title" id="booking-title">Book a table</h2>
                    <p class="section__intro">Choose a Sunday and a sitting. After you send your request, our team reviews it and confirms your table.</p>
                    <dl class="facts">
                        <div>
                            <dt>Sittings</dt>
                            <dd><?= e(implode(', ', $slots)) ?></dd>
                        </div>
                        <div>
                            <dt>Group size</dt>
                            <dd><?= e(config('booking.min_guests')) ?> to <?= e(config('booking.max_guests')) ?> guests per booking</dd>
                        </div>
                        <div>
                            <dt>How far ahead</dt>
                            <dd>Up to <?= e(config('booking.booking_window_days')) ?> days</dd>
                        </div>
                    </dl>
                </div>

                <!--
                    novalidate: the browser's own error bubbles are switched off because
                    booking.js (Phase 3) shows clearer messages. The server checks everything again.
                    The data-* attributes pass the booking rules from PHP config to JavaScript.
                -->
                <form class="booking-form" id="booking-form" action="api/book.php" method="post" novalidate
                      data-slots="<?= e(json_encode($slots)) ?>"
                      data-open-weekday="<?= e(config('booking.open_weekday')) ?>"
                      data-min-guests="<?= e(config('booking.min_guests')) ?>"
                      data-max-guests="<?= e(config('booking.max_guests')) ?>"
                      data-window-days="<?= e(config('booking.booking_window_days')) ?>"
                      data-timezone="<?= e(config('app.timezone')) ?>">

                    <div class="form-grid">
                        <div class="field field--full">
                            <label for="full_name">Full name</label>
                            <input id="full_name" name="full_name" type="text" autocomplete="name"
                                   maxlength="100" required aria-describedby="full_name-error">
                            <p class="field__error" id="full_name-error"></p>
                        </div>

                        <div class="field">
                            <label for="phone">Phone</label>
                            <input id="phone" name="phone" type="tel" autocomplete="tel" inputmode="tel"
                                   maxlength="20" required placeholder="+212 6 12 34 56 78"
                                   aria-describedby="phone-hint phone-error">
                            <p class="field__hint" id="phone-hint">Moroccan or international number.</p>
                            <p class="field__error" id="phone-error"></p>
                        </div>

                        <div class="field">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" autocomplete="email"
                                   maxlength="190" required aria-describedby="email-error">
                            <p class="field__error" id="email-error"></p>
                        </div>

                        <div class="field">
                            <label for="booking_date">Date</label>
                            <input id="booking_date" name="booking_date" type="date" required
                                   min="<?= e($minDate) ?>" max="<?= e($maxDate) ?>" step="7"
                                   aria-describedby="booking_date-hint booking_date-error">
                            <p class="field__hint" id="booking_date-hint">Sundays only.</p>
                            <p class="field__error" id="booking_date-error"></p>
                        </div>

                        <div class="field">
                            <label for="guests">Guests</label>
                            <select id="guests" name="guests" required aria-describedby="guests-error">
                                <?php for ($n = config('booking.min_guests'); $n <= config('booking.max_guests'); $n++): ?>
                                    <option value="<?= $n ?>"<?= $n === 2 ? ' selected' : '' ?>><?= $n ?></option>
                                <?php endfor; ?>
                            </select>
                            <p class="field__error" id="guests-error"></p>
                        </div>

                        <fieldset class="field field--full slots" aria-describedby="time_slot-error">
                            <legend>Sitting</legend>
                            <div class="slots__options">
                                <?php foreach ($slots as $i => $slot): ?>
                                    <input class="slots__input" type="radio" name="time_slot"
                                           id="slot-<?= $i ?>" value="<?= e($slot) ?>" required>
                                    <label class="slots__label" for="slot-<?= $i ?>"><?= e($slot) ?></label>
                                <?php endforeach; ?>
                            </div>
                            <p class="field__error" id="time_slot-error"></p>
                        </fieldset>

                        <div class="field field--full">
                            <label for="message">Message <span class="field__optional">(optional)</span></label>
                            <textarea id="message" name="message" rows="4" maxlength="500"
                                      aria-describedby="message-hint message-error"></textarea>
                            <p class="field__hint" id="message-hint">Allergies, a high chair, a birthday: tell us here.</p>
                            <p class="field__error" id="message-error"></p>
                        </div>

                        <!-- Honeypot: hidden from people, but spam bots fill every field. -->
                        <div class="hp" aria-hidden="true">
                            <label for="website">Leave this field empty</label>
                            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                        </div>
                    </div>

                    <button class="button button--primary booking-form__submit" type="submit">Send booking request</button>
                    <p class="form-status" id="form-status" role="status" aria-live="polite" tabindex="-1"></p>
                </form>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container site-footer__inner">
            <p class="site-footer__brand">Sunset Brunch</p>
            <p>Sunday brunch in Tanger, Morocco.</p>
            <p>&copy; <?= date('Y') ?> Sunset Brunch</p>
        </div>
    </footer>
</body>
</html>
