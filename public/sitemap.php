<?php
/**
 * Toxic Market — Dynamic Sitemap
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/toxic-market/includes/db.php';

header('Content-Type: application/xml; charset=utf-8');

$db = getDB();
$cards = $db->query('SELECT id, generation FROM card_templates ORDER BY id')->fetchAll();
$listings = $db->query('SELECT id, created_at FROM listings WHERE is_sold = 0 ORDER BY created_at DESC')->fetchAll();
$auctions = $db->query('SELECT id, ends_at FROM auctions WHERE status = \'active\' ORDER BY ends_at ASC')->fetchAll();
$users = $db->query('SELECT id FROM users ORDER BY id')->fetchAll();

$baseUrl = 'https://ml-bets.com/toxic-market';
$now = date('c');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Main pages
$pages = ['', '/#cards', '/#listings', '/#auctions', '/set-builder', '/llms.txt', '/robots.txt'];
foreach ($pages as $p) {
    echo "  <url><loc>{$baseUrl}{$p}</loc><lastmod>{$now}</lastmod><changefreq>daily</changefreq><priority>1.0</priority></url>\n";
}

// Card detail pages
foreach ($cards as $c) {
    echo "  <url><loc>{$baseUrl}/card/{$c['id']}</loc><changefreq>weekly</changefreq><priority>0.8</priority></url>\n";
}

// Listings
foreach ($listings as $l) {
    $date = date('c', strtotime($l['created_at']));
    echo "  <url><loc>{$baseUrl}/listing/{$l['id']}</loc><lastmod>{$date}</lastmod><changefreq>daily</changefreq><priority>0.9</priority></url>\n";
}

// Auctions
foreach ($auctions as $a) {
    $date = date('c', strtotime($a['ends_at']));
    echo "  <url><loc>{$baseUrl}/auction/{$a['id']}</loc><lastmod>{$date}</lastmod><changefreq>hourly</changefreq><priority>0.9</priority></url>\n";
}

// Seller profiles
foreach ($users as $u) {
    echo "  <url><loc>{$baseUrl}/seller/{$u['id']}</loc><changefreq>weekly</changefreq><priority>0.5</priority></url>\n";
}

echo '</urlset>';