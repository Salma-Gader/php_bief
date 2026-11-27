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

// Only prices that have been provided count (null = not provided yet).
$prices = array_values(array_filter(array_column($menu, 'price'), static fn ($price) => $price !== null));

// Restaurant structured data (schema.org) for search engines. Only facts the
// site really has: no street address, closing time or missing price is invented.
$restaurantSchema = [
    '@context'            => 'https://schema.org',
    '@type'               => 'Restaurant',
    'name'                => 'Sunset Brunch',
    'description'         => $pageDescription,
    'url'                 => app_url(),
    'image'               => $shareImage,
    'servesCuisine'       => ['Moroccan', 'Brunch'],
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
            'hasMenuItem' => array_map(static fn (array $dish): array => array_filter([
                '@type'       => 'MenuItem',
                'name'        => $dish['name'],
                'description' => $dish['description'],
                'offers'      => $dish['price'] === null ? null : ['@type' => 'Offer', 'price' => (string) $dish['price'], 'priceCurrency' => 'MAD'],
            ]), $menu),
        ],
    ],
];
if ($prices !== []) {
    $restaurantSchema['priceRange'] = min($prices) . '–' . max($prices) . ' MAD';
}

// Photos for the gallery strip: file base, available widths, size of the largest, description.
$gallery = [
    ['riad', [800, 1200], 1200, 800, 'Riad courtyard with white galleries, green plants and laid tables'],
    ['gal-01', [640, 1280], 1280, 852, 'Brunch table with eggs, pancakes, pastries and fresh juices'],
    ['the', [360, 455], 455, 595, 'Mint tea poured from high in the traditional way'],
    ['gal-04', [640, 1280], 1280, 852, 'Zellige table laid with a full Moroccan breakfast'],
    ['food-04', [640, 1280], 1280, 1920, 'Burrata with cherry tomatoes and a drizzle of balsamic'],
    ['patio', [480, 800], 800, 681, 'Patio corner with plants and a mosaic table'],
    ['oud', [360, 455], 455, 595, 'Musician playing the oud'],
    ['gal-06', [640, 1280], 1280, 852, 'Sharing plates with eggs, vegetables and avocado'],
    ['drink-13', [640, 1280], 1280, 1920, 'Frothy drink garnished with dried flowers'],
    ['terrasse', [360, 455], 455, 595, 'Terrace with lanterns and lounge seating'],
    ['jars', [480, 800], 800, 533, 'Jars of nuts and dried fruit on the buffet'],
];

/** srcset attribute for a photo available in several widths: "x-480.webp 480w, x-800.webp 800w". */
function srcset(string $base, array $widths): string
{
    return implode(', ', array_map(static fn (int $w): string => "assets/img/{$base}-{$w}.webp {$w}w", $widths));
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="theme-color" content="#3A0D0B">
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
    <meta property="og:image:alt" content="Sunset Brunch: a golden sky above warm clouds">
    <meta property="og:locale" content="en_GB">
    <meta name="twitter:card" content="summary_large_image">

    <!-- JSON_HEX_TAG turns < and > into < / >, so no value can close this <script> early -->
    <script type="application/ld+json"><?= json_encode($restaurantSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_PRETTY_PRINT) ?></script>

    <link rel="preload" href="assets/fonts/cormorant-garamond-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
    <!-- The sky picture is the largest thing on the first screen: fetch it early,
         at the size each screen uses (same 64rem breakpoint as site.css). -->
    <link rel="preload" href="assets/img/hero-sky-1200.webp" as="image" type="image/webp" media="(max-width: 63.99rem)">
    <link rel="preload" href="assets/img/hero-sky-2400.webp" as="image" type="image/webp" media="(min-width: 64rem)">
    <link rel="stylesheet" href="assets/css/site.css">
    <!-- type="module" runs the script after the HTML is parsed (like defer) and keeps its variables private. -->
    <script type="module" src="assets/js/booking.js"></script>
    <!-- Scroll motion (GSAP + Lenis, self-hosted): loads its libraries after the page, skipped for reduced motion -->
    <script type="module" src="assets/js/motion.js"></script>
</head>
<body>
    <a class="skip-link" href="#booking">Skip to the booking form</a>

    <!-- Floating pill navigation, wordmark in the middle -->
    <header class="topbar">
        <nav class="pill" aria-label="Main">
            <a class="pill__link" href="#menu">Menu</a>
            <a class="pill__link pill__link--wide" href="#gallery">Gallery</a>
            <a class="pill__brand" href="#top">Sunset <span>Brunch</span></a>
            <a class="pill__link pill__link--wide" href="#faq">Q&amp;A</a>
            <a class="pill__link pill__link--cta" href="#booking">Book</a>
        </nav>
    </header>

    <main id="top">
        <!-- 1. Hero: golden sky -->
        <section class="hero" aria-labelledby="hero-title">
            <!-- Sky, sun glow and clouds are one opaque picture (fast to draw), whose bottom
                 fades into the oxblood of the next section -->
            <div class="hero__sky" aria-hidden="true"></div>
            <!-- Doves flying across the sky, behind the title (filled by motion.js) -->
            <div class="doves hero__doves deco" data-flock="hero" aria-hidden="true"></div>

            <div class="hero__content">
                <p class="eyebrow">Every Sunday in Tanger</p>
                <!-- Each word sits in a mask so it can rise into view -->
                <h1 class="hero__title" id="hero-title">
                    <span class="hero__line"><span class="hero__thin">Sunset</span></span>
                    <span class="hero__line"><span class="hero__bold">Brunch</span></span>
                </h1>
                <p class="hero__tagline">Moroccan brunch classics, served slowly every Sunday in Tanger.</p>
                <p class="hero__times">Three sittings: 12:00, 13:30 and 15:00.</p>
                <div class="hero__actions">
                    <a class="pill-button" href="#booking">Book a table</a>
                    <a class="text-link" href="#menu">See the menu</a>
                </div>
            </div>
        </section>

        <!-- 2. Intro: the portal -->
        <section class="intro has-sky" aria-labelledby="intro-title">
            <!-- Decoration (drawn only when motion runs): painted clouds and a dove.
                 hero__bank: the clouds that rise over the hero title while scrolling.
                 They live here, after the hero, so they are never cut by its edge. -->
            <div class="hero__bank deco" aria-hidden="true"></div>
            <div class="cloud cloud--intro deco" aria-hidden="true"></div>
            <div class="doves deco" data-flock="intro" aria-hidden="true"></div>
            <div class="intro__text reveal">
                <h2 class="display" id="intro-title" data-split>A slow Sunday</h2>
                <p class="intro__lines" data-split>From the first coffee<br>to the last glass of mint tea,<br>brunch is never rushed.</p>
                <a class="pill-button pill-button--small" href="#menu">See the menu</a>
            </div>
            <figure class="portal">
                <img class="portal__img" src="assets/img/riad-800.webp"
                     srcset="<?= e(srcset('riad', [800, 1200, 1600])) ?>"
                     sizes="(min-width: 64rem) 34vw, 80vw"
                     width="1600" height="1067" loading="lazy" decoding="async"
                     alt="Riad courtyard with white galleries, green plants and laid tables">
            </figure>
        </section>

        <!-- 3. Menu -->
        <section class="section menu has-sky" id="menu" aria-labelledby="menu-title">
            <div class="cloud cloud--b cloud--menu deco" aria-hidden="true"></div>
            <div class="cloud cloud--menu-end deco" aria-hidden="true"></div>
            <div class="doves deco" data-flock="menu" aria-hidden="true"></div>
            <header class="section__head reveal">
                <p class="eyebrow">The Sunday menu</p>
                <h2 class="display" id="menu-title" data-split>Six ways to start a Sunday</h2>
                <p class="section__intro">Breakfasts and brunch favourites, made to share.</p>
            </header>

            <ul class="dish-grid" role="list">
                <?php foreach ($menu as $dish): ?>
                    <li class="dish">
                        <div class="dish__window">
                            <img src="assets/img/<?= e($dish['image']) ?>-480.webp"
                                 srcset="<?= e(srcset($dish['image'], [480, 800])) ?>"
                                 sizes="(min-width: 64rem) 30vw, (min-width: 40rem) 45vw, 90vw"
                                 width="800" height="450" loading="lazy" decoding="async"
                                 alt="<?= e($dish['name']) ?>">
                        </div>
                        <div class="dish__body">
                            <h3 class="dish__name"><?= e($dish['name']) ?></h3>
                            <p class="dish__description"><?= e($dish['description']) ?></p>
                            <?php if ($dish['price'] !== null): ?>
                                <p class="dish__price"><data value="<?= e($dish['price']) ?>"><?= e($dish['price']) ?> MAD</data></p>
                            <?php else: ?>
                                <p class="dish__price dish__price--pending">Price coming soon</p>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <!-- 4. Gallery: a horizontal strip of photos -->
        <section class="section gallery" id="gallery" aria-labelledby="gallery-title">
            <header class="section__head reveal">
                <p class="eyebrow">Gallery</p>
                <h2 class="display" id="gallery-title" data-split>Around the table</h2>
            </header>
            <!-- tabindex="0": keyboard users can focus the strip and scroll it with the arrow keys -->
            <div class="gallery__strip" tabindex="0" role="region" aria-label="Photo gallery, scroll sideways">
                <ul class="gallery__list" role="list">
                    <?php foreach ($gallery as [$base, $widths, $w, $h, $alt]): ?>
                        <li class="gallery__item">
                            <img src="assets/img/<?= e($base) ?>-<?= $widths[0] ?>.webp"
                                 srcset="<?= e(srcset($base, $widths)) ?>"
                                 sizes="(min-width: 64rem) 40vw, 80vw"
                                 width="<?= $w ?>" height="<?= $h ?>" loading="lazy" decoding="async"
                                 alt="<?= e($alt) ?>">
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <!-- 5. Q&A: practical answers, all taken from the booking rules -->
        <section class="section faq has-sky" id="faq" aria-labelledby="faq-title">
            <div class="doves deco" data-flock="faq" aria-hidden="true"></div>
            <!-- Dusk clouds hanging over the section (only drawn when motion runs) -->
            <div class="cloudbank cloudbank--hang deco" aria-hidden="true"></div>
            <h2 class="display reveal" id="faq-title" data-split>Q&amp;A</h2>
            <dl class="faq__list">
                <div class="faq__item">
                    <dt>When is brunch served?</dt>
                    <dd>Every Sunday, in three sittings: <?= e(implode(', ', array_slice($slots, 0, -1))) ?> and <?= e(end($slots)) ?>.</dd>
                </div>
                <div class="faq__item">
                    <dt>How many guests can I book for?</dt>
                    <dd>From <?= e(config('booking.min_guests')) ?> to <?= e(config('booking.max_guests')) ?> guests per booking.</dd>
                </div>
                <div class="faq__item">
                    <dt>How far ahead can I book?</dt>
                    <dd>Up to <?= e(config('booking.booking_window_days')) ?> days ahead. On a Sunday, you can still book for a sitting that has not started yet.</dd>
                </div>
                <div class="faq__item">
                    <dt>Is my table confirmed straight away?</dt>
                    <dd>You receive a booking reference as soon as you send the form. Our team then reviews your request and confirms your table.</dd>
                </div>
                <div class="faq__item">
                    <dt>Allergies, a high chair, a birthday?</dt>
                    <dd>Tell us in the message field of the booking form.</dd>
                </div>
            </dl>
        </section>

        <!-- 6. Booking -->
        <section class="section booking has-sky" id="booking" aria-labelledby="booking-title">
            <div class="doves deco" data-flock="booking" aria-hidden="true"></div>
            <div class="booking__layout">
                <header class="booking__info reveal">
                    <p class="eyebrow">Reservations</p>
                    <h2 class="display" id="booking-title" data-split>Book a table</h2>
                    <p class="section__intro">Choose a Sunday and a sitting. After you send your request, our team reviews it and confirms your table.</p>
                </header>

                <!--
                    novalidate: the browser's own error bubbles are switched off because
                    booking.js shows clearer messages. The server checks everything again.
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

                        <!--
                            Honeypot: spam bots fill every field they find in the HTML; people never see this one.
                            display:none + a name autofill does not recognise (not "website" or "url"),
                            so the browser's autofill never fills it for a real guest.
                        -->
                        <div class="hp" aria-hidden="true">
                            <label for="sb_extra">Leave this field empty</label>
                            <input id="sb_extra" name="sb_extra" type="text" tabindex="-1" autocomplete="off">
                        </div>
                    </div>

                    <button class="button button--primary booking-form__submit" type="submit">Send booking request</button>
                    <p class="form-status" id="form-status" role="status" aria-live="polite" tabindex="-1"></p>
                </form>
            </div>
            <div class="booking__glow deco" aria-hidden="true"></div>
        </section>
    </main>

    <footer class="site-footer">
        <p class="site-footer__brand">Sunset<span>Brunch</span></p>
        <p class="site-footer__meta">Sunday brunch in Tanger, Morocco</p>
        <p class="site-footer__meta">&copy; <?= date('Y') ?> Sunset Brunch</p>
    </footer>
</body>
</html>
