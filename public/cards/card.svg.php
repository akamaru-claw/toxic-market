<?php
/**
 * Toxic Market — Dynamic Card Image Generator
 * Generates SVG card placeholders with generation-specific designs
 */
header('Content-Type: image/svg+xml');
header('Cache-Control: public, max-age=86400');

$id = intval($_GET['id'] ?? 1);
$gen = intval($_GET['gen'] ?? 1);
$name = $_GET['name'] ?? 'Unknown';
$serial = $_GET['serial'] ?? '';
$holo = filter_var($_GET['holo'] ?? '0', FILTER_VALIDATE_BOOLEAN);

// Generation colors & icons
$colors = [
    1 => ['bg' => '#0a1628', 'accent' => '#00ff88', 'label' => 'GENESIS 2025', 'gradient' => ['#001a0d', '#0a1628'], 'icon' => 'gen1'],
    2 => ['bg' => '#1a0a28', 'accent' => '#c44dff', 'label' => 'ZITADELLE 2026', 'gradient' => ['#0d001a', '#1a0a28'], 'icon' => 'gen2'],
    3 => ['bg' => '#0a1a28', 'accent' => '#6bcfff', 'label' => 'REMAKE EN', 'gradient' => ['#001a2e', '#0a1a28'], 'icon' => 'gen3'],
];
$c = $colors[$gen] ?? $colors[1];

// Holo positions from URL param
$holo_positions_str = $_GET['holo_positions'] ?? '[]';
$holo_positions = json_decode($holo_positions_str, true) ?: [];

// Card number within generation (1-21)
$cardNumber = (($id - 1) % 21) + 1;
$total = $gen === 3 ? 35 : 210;

// Holo display text
$holoBadge = '';
if (!empty($holo_positions)) {
    $positions = implode(', ', $holo_positions);
    $holoBadge = "<text x='140' y='185' text-anchor='middle' fill='url(#holoGrad)' font-family='monospace' font-size='9' font-weight='700'>Holo: {$positions}/{$total}</text>";
}

// Holo effect
$holoFilter = $holo ? ' filter="url(#holo)"' : '';
$holoBorder = $holo ? 'stroke="url(#holoGrad)" stroke-width="3"' : 'stroke="' . $c['accent'] . '" stroke-width="1.5"';

// Generation-specific center icons
$iconGen1 = '<g transform="translate(100, 100)" opacity="0.9"' . $holoFilter . '>
    <!-- Toxic flask -->
    <path d="M 20,0 L 55,0 L 55,55 L 50,80 L 25,80 L 20,55 Z" fill="none" stroke="' . $c['accent'] . '" stroke-width="2.5"/>
    <path d="M 24,35 L 52,35 L 52,53 L 48,76 L 27,76 L 24,53 Z" fill="' . $c['accent'] . '" opacity="0.25"/>
    <circle cx="35" cy="48" r="4" fill="' . $c['accent'] . '" opacity="0.5"/>
    <circle cx="44" cy="56" r="3" fill="' . $c['accent'] . '" opacity="0.4"/>
    <circle cx="39" cy="42" r="2" fill="' . $c['accent'] . '" opacity="0.6"/>
    <path d="M 32,-5 Q 37,-22 42,-5" fill="none" stroke="' . $c['accent'] . '" stroke-width="1.2" opacity="0.5"/>
    <path d="M 36,-10 Q 41,-28 46,-10" fill="none" stroke="' . $c['accent'] . '" stroke-width="1" opacity="0.4"/>
</g>';

$iconGen2 = '<g transform="translate(96, 88)" opacity="0.9"' . $holoFilter . '>
    <!-- Citadel tower -->
    <rect x="10" y="10" width="60" height="80" rx="4" fill="none" stroke="' . $c['accent'] . '" stroke-width="2"/>
    <rect x="20" y="0" width="40" height="15" rx="2" fill="' . $c['accent'] . '" opacity="0.3"/>
    <rect x="35" y="-5" width="10" height="8" rx="1" fill="' . $c['accent'] . '" opacity="0.5"/>
    <line x1="20" y1="30" x2="30" y2="30" stroke="' . $c['accent'] . '" stroke-width="1.5" opacity="0.4"/>
    <line x1="20" y1="45" x2="30" y2="45" stroke="' . $c['accent'] . '" stroke-width="1.5" opacity="0.4"/>
    <line x1="20" y1="60" x2="30" y2="60" stroke="' . $c['accent'] . '" stroke-width="1.5" opacity="0.4"/>
    <line x1="50" y1="30" x2="60" y2="30" stroke="' . $c['accent'] . '" stroke-width="1.5" opacity="0.4"/>
    <line x1="50" y1="45" x2="60" y2="45" stroke="' . $c['accent'] . '" stroke-width="1.5" opacity="0.4"/>
    <line x1="50" y1="60" x2="60" y2="60" stroke="' . $c['accent'] . '" stroke-width="1.5" opacity="0.4"/>
    <circle cx="40" cy="50" r="12" fill="none" stroke="' . $c['accent'] . '" stroke-width="1" opacity="0.3"/>
    <text x="40" y="54" text-anchor="middle" fill="' . $c['accent'] . '" font-family="monospace" font-size="14" opacity="0.6">₿</text>
</g>';

$iconGen3 = '<g transform="translate(100, 100)" opacity="0.9"' . $holoFilter . '>
    <!-- Globe/world icon for EN remakes -->
    <circle cx="40" cy="40" r="35" fill="none" stroke="' . $c['accent'] . '" stroke-width="2"/>
    <ellipse cx="40" cy="40" rx="18" ry="35" fill="none" stroke="' . $c['accent'] . '" stroke-width="1.2" opacity="0.5"/>
    <line x1="5" y1="40" x2="75" y2="40" stroke="' . $c['accent'] . '" stroke-width="1" opacity="0.5"/>
    <line x1="40" y1="5" x2="40" y2="75" stroke="' . $c['accent'] . '" stroke-width="1" opacity="0.5"/>
    <text x="40" y="46" text-anchor="middle" fill="' . $c['accent'] . '" font-family="Arial,sans-serif" font-size="20" font-weight="700" opacity="0.7">EN</text>
</g>';

$iconSvg = $gen === 1 ? $iconGen1 : ($gen === 2 ? $iconGen2 : $iconGen3);

// Background pattern (subtle hex grid)
$pattern = '';
for ($py = 0; $py < 380; $py += 30) {
    for ($px = 0; $px < 280; $px += 30) {
        $opacity = 0.02 + (rand(0, 5) * 0.005);
        $pattern .= "<circle cx='{$px}' cy='{$py}' r='1' fill='" . $c['accent'] . "' opacity='{$opacity}'/>";
    }
}

echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="280" height="380" viewBox="0 0 280 380">
  <defs>
    <linearGradient id="cardBg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:{$c['gradient'][0]}"/>
      <stop offset="100%" style="stop-color:{$c['gradient'][1]}"/>
    </linearGradient>
    <linearGradient id="holoGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#ff6b9d"/>
      <stop offset="25%" style="stop-color:#c44dff"/>
      <stop offset="50%" style="stop-color:#6bcfff"/>
      <stop offset="75%" style="stop-color:#c44dff"/>
      <stop offset="100%" style="stop-color:#ff6b9d"/>
    </linearGradient>
    <linearGradient id="accentGrad" x1="0%" y1="0%" x2="100%" y2="0%">
      <stop offset="0%" style="stop-color:transparent"/>
      <stop offset="50%" style="stop-color:{$c['accent']}"/>
      <stop offset="100%" style="stop-color:transparent"/>
    </linearGradient>
    <filter id="glow">
      <feGaussianBlur stdDeviation="3" result="blur"/>
      <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
    </filter>
    <filter id="holo">
      <feGaussianBlur stdDeviation="2" result="blur"/>
      <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
    </filter>
  </defs>

  <!-- Card background -->
  <rect width="280" height="380" rx="16" fill="url(#cardBg)" {$holoBorder}/>

  <!-- Subtle dot pattern -->
  {$pattern}

  <!-- Top accent line -->
  <rect x="20" y="16" width="240" height="1" fill="url(#accentGrad)" opacity="0.6"/>

  <!-- Generation badge -->
  <rect x="16" y="24" width="120" height="20" rx="10" fill="{$c['accent']}" opacity="0.2"/>
  <text x="76" y="38" text-anchor="middle" fill="{$c['accent']}" font-family="monospace" font-size="9" font-weight="700" letter-spacing="1">{$c['label']}</text>

  <!-- Card number -->
  <text x="264" y="38" text-anchor="end" fill="{$c['accent']}" font-family="monospace" font-size="11" opacity="0.6">#{$cardNumber}</text>

  <!-- Main illustration area -->
  <rect x="20" y="56" width="240" height="200" rx="12" fill="{$c['bg']}" stroke="{$c['accent']}" stroke-width="0.5" opacity="0.8"/>

  <!-- Generation-specific icon -->
  {$iconSvg}

  <!-- Card name -->
  <text x="140" y="280" text-anchor="middle" fill="#e8e8f8" font-family="Arial,Helvetica,sans-serif" font-size="14" font-weight="700">{$name}</text>

  <!-- Edition info -->
  <text x="140" y="298" text-anchor="middle" fill="#7878a8" font-family="monospace" font-size="10">{$total} Edition · #{$cardNumber}</text>

  {$holoBadge}

  <!-- Bottom accent line -->
  <rect x="20" y="348" width="240" height="1" fill="url(#accentGrad)" opacity="0.6"/>

  <!-- Bottom logos -->
  <text x="16" y="368" fill="#4a4a6a" font-family="monospace" font-size="8">TOXIC MARKET</text>
  <text x="264" y="368" text-anchor="end" fill="#4a4a6a" font-family="monospace" font-size="8">MX12ART</text>
</svg>
SVG;