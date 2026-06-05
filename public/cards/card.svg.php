<?php
/**
 * Toxic Market — Dynamic Card Image Generator
 */
header('Content-Type: image/svg+xml');
header('Cache-Control: public, max-age=86400');

$id = intval($_GET['id'] ?? 1);
$gen = intval($_GET['gen'] ?? 1);
$name = $_GET['name'] ?? 'Unknown';
$holo = filter_var($_GET['holo'] ?? '0', FILTER_VALIDATE_BOOLEAN);

$colors = [
    1 => ['bg' => '#0a1628', 'accent' => '#00ff88', 'label' => 'GENESIS 2025', 'gradient' => ['#001a0d', '#0a1628']],
    2 => ['bg' => '#1a0a28', 'accent' => '#c44dff', 'label' => 'ZITADELLE 2026', 'gradient' => ['#0d001a', '#1a0a28']],
    3 => ['bg' => '#0a1a28', 'accent' => '#6bcfff', 'label' => 'REMAKE EN', 'gradient' => ['#001a2e', '#0a1a28']],
];
$c = $colors[$gen] ?? $colors[1];

$holo_positions_str = $_GET['holo_positions'] ?? '[]';
$holo_positions = json_decode($holo_positions_str, true) ?: [];

$cardNumber = (($id - 1) % 21) + 1;
$total = $gen === 3 ? 35 : 210;

$holoDisplay = '';
if (!empty($holo_positions)) {
    $positions = implode(', ', $holo_positions);
    $holoDisplay = "Holo: {$positions}/{$total}";
}

$accent = $c['accent'];
$bg = $c['bg'];
$label = $c['label'];
$g0 = $c['gradient'][0];
$g1 = $c['gradient'][1];

$strokeColor = $holo ? 'url(#holoGrad)' : $accent;
$strokeWidth = $holo ? '3' : '1.5';
$holoFilterAttr = $holo ? ' filter="url(#holo)"' : '';

$safeName = htmlspecialchars($name, ENT_XML1, 'UTF-8');
$safeLabel = htmlspecialchars($label, ENT_XML1, 'UTF-8');

// Build SVG as single string
$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="280" height="380" viewBox="0 0 280 380">
<defs>
<linearGradient id="cardBg" x1="0%" y1="0%" x2="100%" y2="100%">
<stop offset="0%" style="stop-color:' . $g0 . '"/>
<stop offset="100%" style="stop-color:' . $g1 . '"/>
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
<stop offset="50%" style="stop-color:' . $accent . '"/>
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

<rect width="280" height="380" rx="16" fill="url(#cardBg)" stroke="' . $strokeColor . '" stroke-width="' . $strokeWidth . '"/>
<rect x="20" y="16" width="240" height="1" fill="url(#accentGrad)" opacity="0.6"/>

<rect x="16" y="24" width="120" height="20" rx="10" fill="' . $accent . '" opacity="0.2"/>
<text x="76" y="38" text-anchor="middle" fill="' . $accent . '" font-family="monospace" font-size="9" font-weight="700" letter-spacing="1">' . $safeLabel . '</text>

<text x="264" y="38" text-anchor="end" fill="' . $accent . '" font-family="monospace" font-size="11" opacity="0.6">#' . $cardNumber . '</text>

<rect x="20" y="56" width="240" height="200" rx="12" fill="' . $bg . '" stroke="' . $accent . '" stroke-width="0.5" opacity="0.8"/>

<g transform="translate(100, 100)" opacity="0.9"' . $holoFilterAttr . '>
<path d="M 20,0 L 55,0 L 55,60 L 45,80 L 30,80 L 20,60 Z" fill="none" stroke="' . $accent . '" stroke-width="2"/>
<path d="M 24,40 L 52,40 L 52,58 L 44,76 L 31,76 L 24,58 Z" fill="' . $accent . '" opacity="0.3"/>
<circle cx="35" cy="50" r="3" fill="' . $accent . '" opacity="0.5"/>
<circle cx="42" cy="58" r="2" fill="' . $accent . '" opacity="0.4"/>
<circle cx="38" cy="45" r="1.5" fill="' . $accent . '" opacity="0.6"/>
<path d="M 32,-5 Q 37,-20 42,-5" fill="none" stroke="' . $accent . '" stroke-width="1" opacity="0.4"/>
<path d="M 36,-10 Q 41,-25 46,-10" fill="none" stroke="' . $accent . '" stroke-width="1" opacity="0.3"/>
</g>

<text x="140" y="280" text-anchor="middle" fill="#e8e8f8" font-family="Arial,Helvetica,sans-serif" font-size="14" font-weight="700">' . $safeName . '</text>

<text x="140" y="298" text-anchor="middle" fill="#7878a8" font-family="monospace" font-size="10">' . $total . ' Edition · #' . $cardNumber . '</text>';

if ($holoDisplay) {
    $svg .= '
<text x="140" y="185" text-anchor="middle" fill="url(#holoGrad)" font-family="monospace" font-size="9" font-weight="700">' . $holoDisplay . '</text>';
}

$svg .= '

<rect x="20" y="348" width="240" height="1" fill="url(#accentGrad)" opacity="0.6"/>

<text x="16" y="368" fill="#4a4a6a" font-family="monospace" font-size="8">TOXIC MARKET</text>
<text x="264" y="368" text-anchor="end" fill="#4a4a6a" font-family="monospace" font-size="8">MX12ART</text>
</svg>';

echo $svg;
