<?php
session_start();
require_once 'db.php';
$pdo = getDb();

$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

$stmt = $pdo->prepare("
    SELECT e.*, COALESCE(SUM(b.seats), 0) AS seats_booked
    FROM events e
    LEFT JOIN bookings b ON b.event_id = e.id
    WHERE e.id = ?
    GROUP BY e.id
");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    header("Location: index.php");
    exit;
}

$remaining = $event['capacity'] - $event['seats_booked'];
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['guest_name']  ?? '');
    $email = trim($_POST['guest_email'] ?? '');
    $seats = (int)($_POST['seats']      ?? 1);

    if ($name === '')                              $errors[] = "Please enter your full name.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Please enter a valid email address.";
    if ($seats < 1)                                $errors[] = "Please book at least 1 seat.";
    if ($seats > $remaining)                       $errors[] = "Only $remaining seat(s) left for this event.";

    if (empty($errors)) {
        // double-check capacity before committing
        $check = $pdo->prepare("
            SELECT e.capacity, COALESCE(SUM(b.seats),0) AS booked
            FROM events e LEFT JOIN bookings b ON b.event_id = e.id
            WHERE e.id = ? GROUP BY e.id
        ");
        $check->execute([$eventId]);
        $current = $check->fetch();

        if (($current['booked'] + $seats) > $current['capacity']) {
            $errors[] = "Sorry, this event just filled up. Please reduce the number of seats.";
        } else {
            $pdo->prepare("INSERT INTO bookings (event_id, guest_name, guest_email, seats) VALUES (?, ?, ?, ?)")
                ->execute([$eventId, $name, $email, $seats]);

            $price = (float)$event['price'];
            $total = $price * $seats;
            $totalStr = $price > 0 ? ' — Total: €'.number_format($total, 2) : '';
            $_SESSION['flash'] = "🎉 You're booked! {$seats} seat(s) for \"{$event['title']}\"{$totalStr}. Details sent to {$email}.";
            header("Location: index.php");
            exit;
        }
    }
}

$price    = (float)$event['price'];
$pct      = ($event['capacity'] > 0) ? ($event['seats_booked'] / $event['capacity']) * 100 : 0;
$catSlug  = strtolower($event['category']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book — <?= e($event['title']) ?></title>
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
  --green: #16A34A;
  --red: #DC2626;
}

body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); }

nav {
  background: #0F0E0C;
  color: #fff;
  padding: 0 28px;
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.nav-brand { display: flex; align-items: center; gap: 8px; font-family: 'Sora', sans-serif; font-size: 18px; font-weight: 800; }
.nav-brand-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--accent); }
.back-link { font-size: 13px; color: rgba(255,255,255,.65); text-decoration: none; transition: color .15s; }
.back-link:hover { color: #fff; }

.wrap { max-width: 640px; margin: 0 auto; padding: 36px 22px 80px; }

/* event summary card */
.event-summary {
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  margin-bottom: 22px;
}

.summary-banner {
  height: 90px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 40px;
  background: linear-gradient(135deg, #1E3A8A, #2563EB);
}

.banner-design    { background: linear-gradient(135deg, #7C2D12, #EA580C); }
.banner-business  { background: linear-gradient(135deg, #14532D, #16A34A); }
.banner-networking{ background: linear-gradient(135deg, #4C1D95, #7C3AED); }
.banner-general   { background: linear-gradient(135deg, #1C1917, #44403C); }

.summary-body { padding: 18px 20px; }
.summary-title { font-family: 'Sora', sans-serif; font-size: 18px; font-weight: 800; margin-bottom: 8px; }
.summary-meta { display: flex; flex-direction: column; gap: 5px; }
.summary-meta-row { display: flex; align-items: center; gap: 7px; font-size: 13px; color: var(--text2); }

.cap-track { height: 6px; background: #F0EDE8; border-radius: 100px; overflow: hidden; margin-top: 14px; }
.cap-fill  { height: 100%; border-radius: 100px; background: var(--accent); }

/* booking form */
.form-card {
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 24px;
}

.form-title { font-family: 'Sora', sans-serif; font-size: 18px; font-weight: 800; margin-bottom: 20px; }

.field { margin-bottom: 16px; }
.field label { display: block; font-size: 13px; font-weight: 600; color: var(--text2); margin-bottom: 7px; }

.field input, .field select {
  width: 100%;
  padding: 11px 14px;
  border: 1.5px solid var(--border);
  border-radius: 9px;
  font-size: 14px;
  font-family: inherit;
  outline: none;
  background: #FAFAF8;
  color: var(--text);
  transition: border-color .15s, background .15s;
}

.field input:focus, .field select:focus {
  border-color: var(--accent);
  background: #fff;
}

.field-hint { font-size: 11px; color: var(--text3); margin-top: 6px; }

/* order summary */
.order-box {
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 14px 16px;
  margin-bottom: 18px;
}

.order-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  margin-bottom: 8px;
}

.order-row:last-child { margin-bottom: 0; }
.order-total { font-weight: 700; font-size: 15px; padding-top: 8px; border-top: 1px solid var(--border); margin-top: 8px; }

/* errors */
.errors {
  background: #FEF2F2;
  border: 1px solid #FECACA;
  color: #991B1B;
  border-radius: 10px;
  padding: 14px 16px;
  font-size: 13px;
  margin-bottom: 18px;
}

.errors ul { margin: 0; padding-left: 16px; }
.errors li { margin-bottom: 4px; }

/* submit */
.submit-btn {
  width: 100%;
  padding: 14px;
  background: var(--accent);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
  transition: background .15s, transform .1s;
}

.submit-btn:hover { background: #1D4ED8; transform: translateY(-1px); }
.submit-btn:active { transform: scale(.98); }

.secure-note {
  text-align: center;
  font-size: 12px;
  color: var(--text3);
  margin-top: 12px;
}
</style>
</head>
<body>

<nav>
  <div class="nav-brand">
    <div class="nav-brand-dot"></div>
    Eventful
  </div>
  <a class="back-link" href="index.php">← All events</a>
</nav>

<div class="wrap">

  <!-- Event summary -->
  <div class="event-summary">
    <div class="summary-banner banner-<?= e($catSlug) ?>">
      <?= e($event['image_emoji']) ?>
    </div>
    <div class="summary-body">
      <div class="summary-title"><?= e($event['title']) ?></div>
      <div class="summary-meta">
        <div class="summary-meta-row">📅 <?= date("l, d M Y", strtotime($event['event_date'])) ?> at <?= date("H:i", strtotime($event['event_time'])) ?></div>
        <div class="summary-meta-row">📍 <?= e($event['location']) ?></div>
        <div class="summary-meta-row">🎟️ <?= $remaining ?> of <?= (int)$event['capacity'] ?> seats remaining</div>
      </div>
      <div class="cap-track">
        <div class="cap-fill" style="width:<?= min(100, round($pct)) ?>%"></div>
      </div>
    </div>
  </div>

  <!-- Booking form -->
  <div class="form-card">
    <div class="form-title">Complete your booking</div>

    <?php if (!empty($errors)): ?>
      <div class="errors">
        <ul>
          <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" action="book.php?event_id=<?= (int)$eventId ?>" id="bookingForm">

      <div class="field">
        <label for="guest_name">Full name</label>
        <input
          type="text"
          id="guest_name"
          name="guest_name"
          value="<?= e($_POST['guest_name'] ?? '') ?>"
          placeholder="Your full name"
          required
          autocomplete="name"
        >
      </div>

      <div class="field">
        <label for="guest_email">Email address</label>
        <input
          type="email"
          id="guest_email"
          name="guest_email"
          value="<?= e($_POST['guest_email'] ?? '') ?>"
          placeholder="you@example.com"
          required
          autocomplete="email"
        >
        <div class="field-hint">Booking confirmation will be sent to this address.</div>
      </div>

      <div class="field">
        <label for="seats">Number of seats</label>
        <select id="seats" name="seats">
          <?php for ($i = 1; $i <= min($remaining, 8); $i++): ?>
            <option value="<?= $i ?>" <?= (int)($_POST['seats'] ?? 1) === $i ? 'selected' : '' ?>>
              <?= $i ?> seat<?= $i > 1 ? 's' : '' ?><?= $price > 0 ? ' — €'.number_format($price * $i, 2) : '' ?>
            </option>
          <?php endfor; ?>
        </select>
        <div class="field-hint">Maximum <?= $remaining ?> seats per booking.</div>
      </div>

      <?php if ($price > 0): ?>
      <div class="order-box" id="orderBox">
        <div class="order-row"><span>Price per seat</span><span>€<?= number_format($price, 2) ?></span></div>
        <div class="order-row"><span>Seats</span><span id="seatCount">1</span></div>
        <div class="order-row order-total"><span>Total</span><span id="orderTotal">€<?= number_format($price, 2) ?></span></div>
      </div>
      <?php endif; ?>

      <button type="submit" class="submit-btn">
        Confirm booking<?= $price > 0 ? ' & pay' : '' ?> →
      </button>
    </form>

    <div class="secure-note">🔒 Your details are kept private and never shared.</div>
  </div>

</div>

<?php if ($price > 0): ?>
<script>
const pricePerSeat = <?= $price ?>;
const seatsSelect  = document.getElementById('seats');
const totalEl      = document.getElementById('orderTotal');
const countEl      = document.getElementById('seatCount');

seatsSelect.addEventListener('change', function() {
  const n = parseInt(this.value);
  countEl.textContent = n;
  totalEl.textContent = '€' + (pricePerSeat * n).toFixed(2);
});
</script>
<?php endif; ?>
</body>
</html>
