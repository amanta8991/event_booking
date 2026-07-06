<?php
session_start();
require_once 'db.php';
$pdo = getDb();

$flash = null;
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

// category filter + search
$category = $_GET['cat'] ?? 'all';
$search   = trim($_GET['q'] ?? '');

$where  = ["1=1"];
$params = [];

if ($category !== 'all') {
    $where[]  = "e.category = ?";
    $params[] = $category;
}
if ($search !== '') {
    $where[]  = "(e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s]);
}

$whereStr = implode(' AND ', $where);

$events = $pdo->prepare("
    SELECT e.*,
           COALESCE(SUM(b.seats), 0) AS seats_booked
    FROM events e
    LEFT JOIN bookings b ON b.event_id = e.id
    WHERE $whereStr
    GROUP BY e.id
    ORDER BY e.event_date ASC
");
$events->execute($params);
$events = $events->fetchAll();

// get all categories for the filter bar
$categories = $pdo->query("SELECT DISTINCT category FROM events ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Eventful — Upcoming Events</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sora:wght@700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg: #F8F7F5;
  --white: #FFFFFF;
  --border: #E8E6E0;
  --text: #1A1714;
  --text2: #5C5751;
  --text3: #9C9690;
  --accent: #2563EB;
  --accent-soft: #EFF6FF;
  --green: #16A34A;
  --amber: #D97706;
  --red: #DC2626;
}

body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); }
a { text-decoration: none; color: inherit; }

/* ── NAV ── */
nav {
  background: #0F0E0C;
  color: #fff;
  padding: 0 28px;
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky;
  top: 0;
  z-index: 100;
}

.nav-brand {
  display: flex;
  align-items: center;
  gap: 8px;
  font-family: 'Sora', sans-serif;
  font-size: 18px;
  font-weight: 800;
  color: #fff;
}

.nav-brand-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--accent);
}

.nav-link {
  font-size: 13px;
  font-weight: 600;
  color: rgba(255,255,255,.65);
  padding: 6px 12px;
  border-radius: 7px;
  transition: background .15s, color .15s;
}

.nav-link:hover { background: rgba(255,255,255,.1); color: #fff; }

.nav-right { display: flex; align-items: center; gap: 8px; }

@media (max-width: 600px) {
  nav { padding: 0 16px; }
  .nav-link { display: none; }
}

/* ── HERO ── */
.hero {
  background: linear-gradient(135deg, #0F0E0C 0%, #1C1A17 60%, #2563EB22 100%);
  color: #fff;
  padding: 56px 28px 64px;
  text-align: center;
}

.hero h1 {
  font-family: 'Sora', sans-serif;
  font-size: clamp(28px, 5vw, 46px);
  font-weight: 800;
  margin-bottom: 12px;
  line-height: 1.15;
}

.hero h1 span { color: #60A5FA; }

.hero p {
  font-size: 16px;
  color: rgba(255,255,255,.65);
  margin-bottom: 32px;
  max-width: 500px;
  margin-left: auto;
  margin-right: auto;
}

.search-bar {
  max-width: 500px;
  margin: 0 auto;
  display: flex;
  gap: 10px;
}

.search-bar input {
  flex: 1;
  padding: 13px 18px;
  border-radius: 10px;
  border: 1px solid rgba(255,255,255,.15);
  background: rgba(255,255,255,.1);
  color: #fff;
  font-size: 14px;
  font-family: inherit;
  outline: none;
  transition: border-color .2s, background .2s;
}

.search-bar input::placeholder { color: rgba(255,255,255,.4); }
.search-bar input:focus { border-color: var(--accent); background: rgba(255,255,255,.15); }

.search-bar button {
  padding: 13px 22px;
  background: var(--accent);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  transition: background .15s;
  white-space: nowrap;
}

.search-bar button:hover { background: #1D4ED8; }

@media (max-width: 500px) { .search-bar { flex-direction: column; } }

/* ── PAGE BODY ── */
.wrap { max-width: 1100px; margin: 0 auto; padding: 32px 24px 80px; }
@media (max-width: 640px) { .wrap { padding: 20px 14px 60px; } }

/* ── FILTER BAR ── */
.filter-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 28px;
  flex-wrap: wrap;
}

.filter-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--text3);
  text-transform: uppercase;
  letter-spacing: .05em;
  margin-right: 4px;
}

.filter-chip {
  padding: 7px 16px;
  border-radius: 100px;
  border: 1.5px solid var(--border);
  background: var(--white);
  font-size: 13px;
  font-weight: 600;
  color: var(--text2);
  cursor: pointer;
  transition: all .15s;
  text-decoration: none;
}

.filter-chip:hover { border-color: var(--accent); color: var(--accent); }
.filter-chip.active { background: var(--accent); color: #fff; border-color: var(--accent); }

/* ── RESULTS HEADER ── */
.results-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  flex-wrap: wrap;
  gap: 8px;
}

.results-count { font-size: 13px; color: var(--text3); font-weight: 600; }

/* ── EVENTS GRID ── */
.events-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 20px;
}

@media (max-width: 700px) { .events-grid { grid-template-columns: 1fr; } }

/* ── EVENT CARD ── */
.event-card {
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  transition: transform .18s ease, box-shadow .18s ease;
  display: flex;
  flex-direction: column;
}

.event-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 36px rgba(0,0,0,.1);
}

.card-banner {
  height: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 52px;
  position: relative;
}

.card-category-badge {
  position: absolute;
  top: 12px;
  left: 12px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .05em;
  padding: 4px 10px;
  border-radius: 100px;
  background: rgba(255,255,255,.9);
  color: var(--text);
}

.card-price-badge {
  position: absolute;
  top: 12px;
  right: 12px;
  font-size: 12px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 100px;
  background: rgba(0,0,0,.55);
  color: #fff;
}

.card-body { padding: 18px 18px 14px; flex: 1; display: flex; flex-direction: column; }

.card-date-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.card-date {
  font-size: 12px;
  font-weight: 700;
  color: var(--accent);
  text-transform: uppercase;
  letter-spacing: .04em;
}

.card-time {
  font-size: 11px;
  color: var(--text3);
  font-weight: 600;
}

.card-title {
  font-family: 'Sora', sans-serif;
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 8px;
  line-height: 1.3;
}

.card-desc {
  font-size: 13px;
  color: var(--text2);
  line-height: 1.5;
  margin-bottom: 14px;
  flex: 1;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.card-location {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: var(--text3);
  margin-bottom: 14px;
}

/* capacity */
.cap-row {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  font-weight: 600;
  margin-bottom: 6px;
}

.cap-label { color: var(--text3); }
.cap-remaining { }
.cap-remaining.ok     { color: var(--green); }
.cap-remaining.warn   { color: var(--amber); }
.cap-remaining.danger { color: var(--red);   }

.cap-track {
  height: 6px;
  background: #F0EDE8;
  border-radius: 100px;
  overflow: hidden;
  margin-bottom: 14px;
}

.cap-fill {
  height: 100%;
  border-radius: 100px;
  transition: width .5s ease;
}

.cap-fill.ok     { background: var(--green); }
.cap-fill.warn   { background: var(--amber); }
.cap-fill.danger { background: var(--red);   }

/* card footer */
.card-footer {
  padding: 14px 18px;
  border-top: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.seats-info { font-size: 12px; color: var(--text3); }
.seats-info strong { color: var(--text); }

.book-btn {
  padding: 9px 20px;
  background: var(--accent);
  color: #fff;
  border: none;
  border-radius: 9px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  text-decoration: none;
  display: inline-block;
  transition: background .15s, transform .1s;
  font-family: inherit;
  white-space: nowrap;
}

.book-btn:hover { background: #1D4ED8; transform: translateY(-1px); }

.book-btn.full {
  background: var(--border);
  color: var(--text3);
  cursor: not-allowed;
  transform: none;
}

/* urgency badge */
.urgency {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 9px;
  border-radius: 100px;
  background: #FEF3CD;
  color: #92400E;
}

/* flash */
.flash {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #F0FDF4;
  border: 1px solid #BBF7D0;
  color: #166534;
  padding: 14px 18px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 500;
  margin-bottom: 24px;
}

/* empty state */
.empty {
  text-align: center;
  padding: 80px 20px;
  color: var(--text3);
  grid-column: 1 / -1;
}

.empty div { font-size: 48px; margin-bottom: 14px; }

/* banner color by category */
.banner-tech    { background: linear-gradient(135deg, #1E3A8A, #2563EB); }
.banner-design  { background: linear-gradient(135deg, #7C2D12, #EA580C); }
.banner-business{ background: linear-gradient(135deg, #14532D, #16A34A); }
.banner-networking { background: linear-gradient(135deg, #4C1D95, #7C3AED); }
.banner-general { background: linear-gradient(135deg, #1C1917, #44403C); }

/* stats strip */
.stats-strip {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
  margin-bottom: 32px;
}

@media (max-width: 640px) { .stats-strip { grid-template-columns: 1fr; gap: 10px; } }

.stat-card {
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 16px 20px;
}

.stat-card .s-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--text3); margin-bottom: 6px; }
.stat-card .s-val   { font-family: 'Sora', sans-serif; font-size: 24px; font-weight: 800; }
.stat-card .s-sub   { font-size: 11px; color: var(--text3); margin-top: 3px; }
</style>
</head>
<body>

<nav>
  <div class="nav-brand">
    <div class="nav-brand-dot"></div>
    Eventful
  </div>
  <div class="nav-right">
    <a class="nav-link" href="index.php">Events</a>
  </div>
</nav>

<div class="hero">
  <h1>Discover <span>events</span><br>happening near you</h1>
  <p>Tech meetups, workshops, conferences and more — book your seat in seconds.</p>
  <form class="search-bar" method="GET" action="index.php">
    <?php if ($category !== 'all'): ?>
      <input type="hidden" name="cat" value="<?= e($category) ?>">
    <?php endif; ?>
    <input
      type="text"
      name="q"
      placeholder="Search events, topics, locations..."
      value="<?= e($search) ?>"
    >
    <button type="submit">Search</button>
  </form>
</div>

<div class="wrap">

  <?php if ($flash): ?>
    <div class="flash">
      <span>✅</span>
      <?= e($flash) ?>
    </div>
  <?php endif; ?>

  <?php
  // stats
  $totalEvents  = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
  $totalBooked  = $pdo->query("SELECT COALESCE(SUM(seats),0) FROM bookings")->fetchColumn();
  $freeEvents   = $pdo->query("SELECT COUNT(*) FROM events WHERE price = 0")->fetchColumn();
  ?>

  <div class="stats-strip">
    <div class="stat-card">
      <div class="s-label">Upcoming events</div>
      <div class="s-val"><?= $totalEvents ?></div>
      <div class="s-sub">This month</div>
    </div>
    <div class="stat-card">
      <div class="s-label">Seats booked</div>
      <div class="s-val"><?= $totalBooked ?></div>
      <div class="s-sub">Across all events</div>
    </div>
    <div class="stat-card">
      <div class="s-label">Free events</div>
      <div class="s-val"><?= $freeEvents ?></div>
      <div class="s-sub">No ticket needed</div>
    </div>
  </div>

  <div class="filter-bar">
    <span class="filter-label">Filter:</span>
    <a href="index.php<?= $search ? '?q='.urlencode($search) : '' ?>" class="filter-chip <?= $category === 'all' ? 'active' : '' ?>">All</a>
    <?php foreach ($categories as $cat): ?>
      <a
        href="?cat=<?= urlencode($cat) ?><?= $search ? '&q='.urlencode($search) : '' ?>"
        class="filter-chip <?= $category === $cat ? 'active' : '' ?>"
      ><?= e($cat) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="results-header">
    <div class="results-count">
      <?= count($events) ?> event<?= count($events) !== 1 ? 's' : '' ?>
      <?= $search ? 'for "'.e($search).'"' : '' ?>
      <?= $category !== 'all' ? 'in '.e($category) : '' ?>
    </div>
    <?php if ($search || $category !== 'all'): ?>
      <a href="index.php" class="filter-chip">✕ Clear filters</a>
    <?php endif; ?>
  </div>

  <div class="events-grid">
    <?php if (empty($events)): ?>
      <div class="empty">
        <div>🔍</div>
        <p>No events found. Try a different search or category.</p>
      </div>
    <?php endif; ?>

    <?php foreach ($events as $ev):
      $remaining = $ev['capacity'] - $ev['seats_booked'];
      $pct       = ($ev['capacity'] > 0) ? ($ev['seats_booked'] / $ev['capacity']) * 100 : 0;
      $capClass  = $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warn' : 'ok');
      $isFull    = $remaining <= 0;
      $catSlug   = strtolower($ev['category']);
      $bannerClass = "banner-$catSlug";
      $price     = (float)$ev['price'];
      $priceLabel = $price > 0 ? '€'.number_format($price, 2) : 'Free';
    ?>
    <div class="event-card">
      <div class="card-banner <?= e($bannerClass) ?>">
        <?= e($ev['image_emoji']) ?>
        <span class="card-category-badge"><?= e($ev['category']) ?></span>
        <span class="card-price-badge"><?= $priceLabel ?></span>
      </div>

      <div class="card-body">
        <div class="card-date-row">
          <span class="card-date"><?= date("D, d M Y", strtotime($ev['event_date'])) ?></span>
          <span class="card-time">· <?= date("H:i", strtotime($ev['event_time'])) ?></span>
        </div>

        <div class="card-title"><?= e($ev['title']) ?></div>
        <div class="card-desc"><?= e($ev['description']) ?></div>

        <div class="card-location">
          <span>📍</span>
          <?= e($ev['location']) ?>
        </div>

        <div class="cap-row">
          <span class="cap-label"><?= (int)$ev['seats_booked'] ?> / <?= (int)$ev['capacity'] ?> booked</span>
          <span class="cap-remaining <?= $capClass ?>">
            <?php if ($isFull): ?>
              Fully booked
            <?php elseif ($remaining <= 5): ?>
              ⚡ <?= $remaining ?> left
            <?php else: ?>
              <?= $remaining ?> remaining
            <?php endif; ?>
          </span>
        </div>

        <div class="cap-track">
          <div class="cap-fill <?= $capClass ?>" style="width:<?= min(100, round($pct)) ?>%"></div>
        </div>
      </div>

      <div class="card-footer">
        <div class="seats-info">
          <?php if ($remaining <= 5 && !$isFull): ?>
            <span class="urgency">🔥 Almost full</span>
          <?php else: ?>
            <strong><?= (int)$ev['capacity'] ?></strong> total seats
          <?php endif; ?>
        </div>
        <?php if ($isFull): ?>
          <span class="book-btn full">Sold out</span>
        <?php else: ?>
          <a class="book-btn" href="book.php?event_id=<?= (int)$ev['id'] ?>">Book now →</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

</div>
</body>
</html>
