<?php
declare(strict_types=1);

/**
 * Back-office: list of bookings, newest first, with filters by date and status.
 * Protected: visitors who are not signed in are sent to login.php.
 *
 * Filters travel in the URL (GET), so a filtered view can be bookmarked or
 * shared between staff. Every value is checked before it reaches SQL.
 */

require __DIR__ . '/../../src/bootstrap.php';

Auth::requireAdmin();

const PER_PAGE = 20;

$repo = new ReservationRepository(Database::connection());

// --- Read and check the filters (shared with export.php) ---
[$filters, $filterNotice] = read_list_filters($_GET);
['date' => $date, 'status' => $status] = $filters;

// --- Pagination ---
$total = $repo->count($filters);
$pageCount = max(1, (int) ceil($total / PER_PAGE));
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 1]]);
$page = min($page, $pageCount);

$bookings = $repo->search($filters, PER_PAGE, ($page - 1) * PER_PAGE);
$summary = $date !== '' ? $repo->slotSummary($date) : null;

$capacity = (int) config('booking.slot_capacity');
$upcomingSunday = (new DateTimeImmutable('today'))->modify('sunday this week')->format('Y-m-d');

// One-time message from status.php ("SB-000031 confirmed.")
$flash = take_flash();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="../assets/img/favicon.svg" type="image/svg+xml">
    <title>Bookings | Sunset Brunch back-office</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-page">
    <header class="admin-bar">
        <!-- Back to the full list, filters cleared -->
        <a class="admin-bar__brand" href="index.php">Sunset Brunch <span>back-office</span></a>
        <div class="admin-bar__user">
            <span>Signed in as <strong><?= e(Auth::username()) ?></strong></span>
            <form method="post" action="logout.php">
                <?= Csrf::field() ?>
                <button class="button button--quiet" type="submit">Sign out</button>
            </form>
        </div>
    </header>

    <main class="admin-main">
        <h1 class="admin-title">Bookings</h1>

        <?php if ($flash !== null): ?>
            <p class="notice notice--<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></p>
        <?php endif; ?>

        <!-- Filters (GET: read-only, so no CSRF token needed) -->
        <form class="filters" method="get" action="index.php">
            <div class="field">
                <label for="filter-date">Date</label>
                <input id="filter-date" name="date" type="date" value="<?= e($date) ?>">
            </div>
            <div class="field">
                <label for="filter-status">Status</label>
                <select id="filter-status" name="status">
                    <option value="">All statuses</option>
                    <?php foreach (ReservationRepository::STATUSES as $option): ?>
                        <option value="<?= e($option) ?>"<?= $option === $status ? ' selected' : '' ?>><?= e(ucfirst($option)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filters__actions">
                <button class="button button--dark" type="submit">Show bookings</button>
                <a class="filters__link" href="<?= e(admin_list_url(['date' => $upcomingSunday, 'status' => ''])) ?>">This Sunday</a>
                <?php if ($date !== '' || $status !== ''): ?>
                    <a class="filters__link" href="index.php">Clear filters</a>
                <?php endif; ?>
                <?php if ($total > 0): ?>
                    <!-- Same filters as the list; "download" asks the browser to save the file -->
                    <a class="filters__link filters__export" href="<?= e(str_replace('index.php', 'export.php', admin_list_url($filters))) ?>" download>Export CSV (<?= $total ?>)</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($filterNotice !== ''): ?>
            <p class="notice notice--error" role="alert"><?= e($filterNotice) ?></p>
        <?php endif; ?>

        <?php if ($summary !== null): ?>
            <!-- Occupancy per sitting for the chosen date (new + confirmed bookings) -->
            <section class="occupancy" aria-labelledby="occupancy-title">
                <h2 class="occupancy__title" id="occupancy-title"><?= e(format_day($date)) ?></h2>
                <ul class="occupancy__list" role="list">
                    <?php foreach ($summary as $slot => $info):
                        // full = no seats left, busy = 75% or more taken
                        $level = $info['guests'] >= $capacity ? 'full' : ($info['guests'] >= $capacity * 0.75 ? 'busy' : 'open');
                    ?>
                        <li class="occupancy__slot occupancy__slot--<?= $level ?>">
                            <p class="occupancy__time"><?= e($slot) ?></p>
                            <p class="occupancy__guests"><strong><?= $info['guests'] ?></strong> / <?= $capacity ?> guests</p>
                            <progress class="occupancy__bar" max="<?= $capacity ?>" value="<?= min($info['guests'], $capacity) ?>" aria-hidden="true"></progress>
                            <p class="occupancy__bookings">
                                <?= $info['bookings'] ?> <?= $info['bookings'] === 1 ? 'booking' : 'bookings' ?>,
                                <?= $info['guests'] >= $capacity ? 'full' : ($capacity - $info['guests']) . ' seats left' ?>
                            </p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <p class="results-count" role="status">
            <?php if ($total === 0): ?>
                No bookings match these filters.
            <?php else: ?>
                Showing <?= ($page - 1) * PER_PAGE + 1 ?>–<?= min($page * PER_PAGE, $total) ?> of <?= $total ?> <?= $total === 1 ? 'booking' : 'bookings' ?>, newest first.
            <?php endif; ?>
        </p>

        <?php if ($bookings !== []): ?>
            <div class="table-wrap">
                <table class="bookings">
                    <thead>
                        <tr>
                            <th scope="col">Reference</th>
                            <th scope="col">Guest</th>
                            <th scope="col">Brunch</th>
                            <th scope="col">Guests</th>
                            <th scope="col">Message</th>
                            <th scope="col">Status</th>
                            <th scope="col">Received</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b): $ref = booking_reference($b['id']); ?>
                            <tr>
                                <td data-label="Reference"><?= e($ref) ?></td>
                                <td data-label="Guest">
                                    <div>
                                        <strong><?= e($b['full_name']) ?></strong>
                                        <a href="tel:<?= e($b['phone']) ?>"><?= e($b['phone']) ?></a>
                                        <a href="mailto:<?= e($b['email']) ?>"><?= e($b['email']) ?></a>
                                    </div>
                                </td>
                                <td data-label="Brunch"><div><?= e(format_day($b['booking_date'])) ?><br><?= e($b['time_slot']) ?></div></td>
                                <td data-label="Guests"><?= e($b['guests']) ?></td>
                                <td data-label="Message" class="bookings__message">
                                    <div><?= $b['message'] === null ? '<span class="muted">None</span>' : nl2br(e($b['message'])) ?></div>
                                </td>
                                <td data-label="Status"><span class="badge badge--<?= e($b['status']) ?>"><?= e(ucfirst($b['status'])) ?></span></td>
                                <td data-label="Received"><?= e((new DateTimeImmutable($b['created_at']))->format('j M, H:i')) ?></td>
                                <td data-label="Actions">
                                    <div class="row-actions">
                                        <?php foreach (['confirmed' => 'Confirm', 'cancelled' => 'Cancel booking'] as $target => $label): ?>
                                            <?php if ($b['status'] !== $target): ?>
                                                <!-- POST + CSRF token: a link on another site cannot change a booking -->
                                                <form method="post" action="status.php">
                                                    <?= Csrf::field() ?>
                                                    <input type="hidden" name="id" value="<?= e($b['id']) ?>">
                                                    <input type="hidden" name="new_status" value="<?= e($target) ?>">
                                                    <input type="hidden" name="filter_date" value="<?= e($date) ?>">
                                                    <input type="hidden" name="filter_status" value="<?= e($status) ?>">
                                                    <input type="hidden" name="page" value="<?= e($page) ?>">
                                                    <button class="button button--small button--<?= $target === 'confirmed' ? 'confirm' : 'cancel' ?>"
                                                            type="submit" aria-label="<?= e("{$label} {$ref}") ?>"><?= e($label) ?></button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($pageCount > 1): ?>
            <nav class="pagination" aria-label="Pages">
                <?php if ($page > 1): ?>
                    <a class="button button--quiet-dark" href="<?= e(admin_list_url($filters, ['page' => $page - 1])) ?>">Previous</a>
                <?php endif; ?>
                <span>Page <?= $page ?> of <?= $pageCount ?></span>
                <?php if ($page < $pageCount): ?>
                    <a class="button button--quiet-dark" href="<?= e(admin_list_url($filters, ['page' => $page + 1])) ?>">Next</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </main>
</body>
</html>
