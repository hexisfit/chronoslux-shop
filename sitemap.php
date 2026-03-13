<?php
/**
 * CHRONOS LUX — Динамічна карта сайту (sitemap.xml)
 * Автоматично генерує список всіх сторінок та статей
 */
header('Content-Type: application/xml; charset=UTF-8');

$domain = 'https://chronoslux.shop';
$today = date('Y-m-d');

// Завантажити статті
$articles = [];
$articles_file = __DIR__ . '/data/articles.json';
if (file_exists($articles_file)) {
    $articles = json_decode(file_get_contents($articles_file), true) ?: [];
}

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>

<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">

  <!-- Головна сторінка -->
  <url>
    <loc><?= $domain ?>/</loc>
    <lastmod><?= $today ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
  </url>

  <!-- Статті журналу -->
<?php foreach ($articles as $a):
  if (empty($a['file'])) continue;
  $img = !empty($a['image']) ? $domain . '/photos/' . $a['image'] : '';
?>
  <url>
    <loc><?= $domain ?>/articles/<?= htmlspecialchars($a['file']) ?></loc>
    <lastmod><?= $today ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
<?php if ($img): ?>
    <image:image>
      <image:loc><?= htmlspecialchars($img) ?></image:loc>
      <image:title><?= htmlspecialchars($a['title'] ?? '') ?></image:title>
    </image:image>
<?php endif; ?>
  </url>
<?php endforeach; ?>

</urlset>
