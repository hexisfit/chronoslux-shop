<?php
/**
 * CHRONOS LUX — Адмін-панель
 * Управління каталогом годинників та журналом
 */

session_start();

// ══════ НАЛАШТУВАННЯ ══════
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'ChronosLux2026!'); // ЗМІНІТЬ ПАРОЛЬ!
define('DATA_DIR', __DIR__ . '/../data/');
define('PHOTOS_DIR', __DIR__ . '/../photos/');
define('ARTICLES_DIR', __DIR__ . '/../articles/');

// Створити папки якщо не існують
foreach ([DATA_DIR, PHOTOS_DIR, ARTICLES_DIR] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

// ══════ АВТОРИЗАЦІЯ ══════
if (isset($_POST['login'])) {
    if ($_POST['user'] === ADMIN_USER && $_POST['pass'] === ADMIN_PASS) {
        $_SESSION['admin'] = true;
    } else {
        $login_error = 'Невірний логін або пароль';
    }
}
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['admin'])) {
    showLogin($login_error ?? '');
    exit;
}

// ══════ ОБРОБКА ДІЙ ══════
$msg = '';

// -- Каталог --
if (isset($_POST['save_watch'])) {
    $msg = saveWatch($_POST, $_FILES);
}
if (isset($_POST['delete_watch'])) {
    $msg = deleteWatch((int)$_POST['delete_watch']);
}
if (isset($_POST['toggle_sold'])) {
    $msg = toggleSold((int)$_POST['toggle_sold']);
}

// -- Журнал --
if (isset($_POST['save_article'])) {
    $msg = saveArticle($_POST, $_FILES);
}
if (isset($_POST['delete_article'])) {
    $msg = deleteArticle((int)$_POST['delete_article']);
}

// -- Завантажити дані --
$catalog = loadJSON('catalog.json');
$articles = loadJSON('articles.json');

// Сортування
usort($catalog, fn($a, $b) => ($a['order'] ?? 999) - ($b['order'] ?? 999));
usort($articles, fn($a, $b) => ($a['order'] ?? 999) - ($b['order'] ?? 999));

$tab = $_GET['tab'] ?? 'catalog';

// ══════ ФУНКЦІЇ ══════
function loadJSON($file) {
    $path = DATA_DIR . $file;
    if (!file_exists($path)) return [];
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function saveJSON($file, $data) {
    file_put_contents(DATA_DIR . $file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function nextId($items) {
    $max = 0;
    foreach ($items as $item) {
        if (($item['id'] ?? 0) > $max) $max = $item['id'];
    }
    return $max + 1;
}

function uploadImage($file, $prefix = '') {
    if (empty($file['name']) || $file['error'] !== 0) return '';
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) return '';
    // Чистимо назву
    $name = $prefix ? $prefix : pathinfo($file['name'], PATHINFO_FILENAME);
    $name = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $name);
    $name = preg_replace('/-+/', '-', trim($name, '-'));
    $filename = $name . '.' . $ext;
    move_uploaded_file($file['tmp_name'], PHOTOS_DIR . $filename);
    return $filename;
}

function saveWatch($post, $files) {
    $catalog = loadJSON('catalog.json');
    $id = !empty($post['watch_id']) ? (int)$post['watch_id'] : 0;

    // Завантажити фото
    $image = '';
    if (!empty($files['watch_image']['name']) && $files['watch_image']['error'] === 0) {
        $prefix = strtolower(trim($post['brand'] . '-' . $post['watch_name']));
        $image = uploadImage($files['watch_image'], $prefix);
    }

    // Специфікації з рядка
    $specs = array_filter(array_map('trim', explode(',', $post['specs'] ?? '')));

    $entry = [
        'id' => $id ?: nextId($catalog),
        'brand' => trim($post['brand'] ?? ''),
        'name' => trim($post['watch_name'] ?? ''),
        'ref' => trim($post['ref'] ?? ''),
        'specs' => $specs,
        'price' => trim($post['price'] ?? ''),
        'badge' => $post['badge'] ?? '',
        'badge_text' => trim($post['badge_text'] ?? ''),
        'condition' => $post['condition'] ?? '',
        'has_documents' => !empty($post['has_documents']),
        'has_box' => !empty($post['has_box']),
        'sold' => !empty($post['sold']),
        'image' => '',
        'telegram_text' => trim($post['telegram_text'] ?? ''),
        'order' => (int)($post['watch_order'] ?? 999),
    ];

    if ($id) {
        foreach ($catalog as &$item) {
            if ($item['id'] === $id) {
                $entry['image'] = $image ?: ($item['image'] ?? '');
                $entry['sold'] = $item['sold']; // зберегти поточний статус
                if (!empty($post['sold'])) $entry['sold'] = true;
                if (isset($post['unsold'])) $entry['sold'] = false;
                $item = $entry;
                break;
            }
        }
    } else {
        $entry['image'] = $image;
        $catalog[] = $entry;
    }

    saveJSON('catalog.json', $catalog);
    return '✅ Годинник збережено';
}

function deleteWatch($id) {
    $catalog = loadJSON('catalog.json');
    $catalog = array_values(array_filter($catalog, fn($w) => $w['id'] !== $id));
    saveJSON('catalog.json', $catalog);
    return '✅ Годинник видалено';
}

function toggleSold($id) {
    $catalog = loadJSON('catalog.json');
    foreach ($catalog as &$item) {
        if ($item['id'] === $id) {
            $item['sold'] = !($item['sold'] ?? false);
            break;
        }
    }
    saveJSON('catalog.json', $catalog);
    return '✅ Статус оновлено';
}

function saveArticle($post, $files) {
    $articles = loadJSON('articles.json');
    $id = !empty($post['article_id']) ? (int)$post['article_id'] : 0;

    $image = '';
    if (!empty($files['article_image']['name']) && $files['article_image']['error'] === 0) {
        $prefix = strtolower(trim($post['article_file'] ?? 'article'));
        $prefix = str_replace('.html', '', $prefix);
        $image = uploadImage($files['article_image'], $prefix . '-cover');
    }

    $entry = [
        'id' => $id ?: nextId($articles),
        'title' => trim($post['article_title'] ?? ''),
        'excerpt' => trim($post['article_excerpt'] ?? ''),
        'tag' => trim($post['article_tag'] ?? ''),
        'date' => trim($post['article_date'] ?? ''),
        'file' => trim($post['article_file'] ?? ''),
        'image' => '',
        'order' => (int)($post['article_order'] ?? 999),
    ];

    if ($id) {
        foreach ($articles as &$item) {
            if ($item['id'] === $id) {
                $entry['image'] = $image ?: ($item['image'] ?? '');
                $item = $entry;
                break;
            }
        }
    } else {
        $entry['image'] = $image;
        $articles[] = $entry;
    }

    saveJSON('articles.json', $articles);
    return '✅ Статтю збережено';
}

function deleteArticle($id) {
    $articles = loadJSON('articles.json');
    $articles = array_values(array_filter($articles, fn($a) => $a['id'] !== $id));
    saveJSON('articles.json', $articles);
    return '✅ Статтю видалено';
}

function showLogin($error) {
?>
<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="../favicon.png">
<link rel="apple-touch-icon" href="../favicon-180.png">
<title>CHRONOS LUX — Вхід</title>
<style>
  :root { --gold:#C9A96E; --black:#0A0A0A; --dark:#111; --white:#F5F0E8; --dim:#B8B0A2; --border:rgba(201,169,110,0.15); }
  * { margin:0; padding:0; box-sizing:border-box; }
  body {
    background:var(--black); color:var(--white); font-family:'Segoe UI',system-ui,sans-serif;
    min-height:100vh; display:flex; align-items:center; justify-content:center;
    position:relative; overflow:hidden;
  }
  body::before {
    content:'';
    position:fixed; inset:0;
    background: url('bg-mechanism.svg') center/800px 800px repeat;
    pointer-events:none;
    z-index:0;
  }
  body::after {
    content:'';
    position:fixed; inset:0;
    background:radial-gradient(ellipse at 30% 50%, rgba(201,169,110,0.05) 0%, transparent 70%);
    pointer-events:none;
    z-index:0;
  }
  .login-box { background:var(--dark); border:1px solid var(--border); padding:3rem; width:360px; text-align:center; position:relative; z-index:1; }
  .login-box h1 { color:var(--gold); font-size:1.5rem; letter-spacing:0.15em; margin-bottom:0.5rem; }
  .login-box p { color:var(--dim); font-size:0.8rem; margin-bottom:2rem; }
  .login-box input { width:100%; padding:0.8rem 1rem; background:#1a1a1a; border:1px solid var(--border); color:var(--white); font-size:0.9rem; margin-bottom:1rem; outline:none; }
  .login-box input:focus { border-color:var(--gold); }
  .login-box button { width:100%; padding:0.8rem; background:var(--gold); color:var(--black); border:none; font-size:0.85rem; font-weight:600; letter-spacing:0.1em; text-transform:uppercase; cursor:pointer; }
  .login-box button:hover { background:#E8D5A8; }
  .error { color:#e05830; font-size:0.8rem; margin-bottom:1rem; }
</style>
</head>
<body>
<div class="login-box">
  <h1>CHRONOS LUX</h1>
  <p>Адмін-панель</p>
  <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
  <form method="POST">
    <input type="text" name="user" placeholder="Логін" required>
    <input type="password" name="pass" placeholder="Пароль" required>
    <button type="submit" name="login" value="1">Увійти</button>
  </form>
</div>
</body>
</html>
<?php
}

// ══════ ГОЛОВНА ПАНЕЛЬ ══════
?>
<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="../favicon.png">
<link rel="apple-touch-icon" href="../favicon-180.png">
<title>CHRONOS LUX — Адмін-панель</title>
<style>
  :root { --gold:#C9A96E; --gold-light:#E8D5A8; --gold-dark:#9A7B4F; --black:#0A0A0A; --dark:#111; --card:#171717; --white:#F5F0E8; --dim:#B8B0A2; --border:rgba(201,169,110,0.15); --green:#4CAF50; --red:#e05830; }
  * { margin:0; padding:0; box-sizing:border-box; }
  body {
    background:var(--black); color:var(--white); font-family:'Segoe UI',system-ui,sans-serif; font-size:14px; line-height:1.5;
    position:relative;
  }
  body::before {
    content:'';
    position:fixed; inset:0;
    background: url('bg-mechanism.svg') center/800px 800px repeat;
    pointer-events:none;
    z-index:0;
  }
  body::after {
    content:'';
    position:fixed; inset:0;
    background:
      radial-gradient(ellipse at 20% 80%, rgba(201,169,110,0.04) 0%, transparent 60%),
      radial-gradient(ellipse at 80% 20%, rgba(201,169,110,0.03) 0%, transparent 60%);
    pointer-events:none;
    z-index:0;
  }

  /* NAV */
  .admin-nav { background:rgba(17,17,17,0.95); backdrop-filter:blur(10px); border-bottom:1px solid var(--border); padding:1rem 2rem; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:100; }
  .admin-nav h1 { color:var(--gold); font-size:1.1rem; letter-spacing:0.15em; }
  .admin-nav a { color:var(--dim); text-decoration:none; font-size:0.8rem; }
  .admin-nav a:hover { color:var(--gold); }

  /* TABS */
  .tabs { display:flex; gap:0; border-bottom:1px solid var(--border); background:rgba(17,17,17,0.9); backdrop-filter:blur(10px); padding:0 2rem; position:relative; z-index:10; }
  .tabs a { padding:1rem 1.5rem; color:var(--dim); text-decoration:none; font-size:0.85rem; font-weight:500; border-bottom:2px solid transparent; transition:all 0.2s; }
  .tabs a:hover { color:var(--white); }
  .tabs a.active { color:var(--gold); border-bottom-color:var(--gold); }

  /* CONTENT */
  .content { max-width:1200px; margin:0 auto; padding:2rem; position:relative; z-index:1; }

  /* MESSAGE */
  .msg { background:rgba(76,175,80,0.1); border:1px solid rgba(76,175,80,0.3); color:var(--green); padding:0.8rem 1.2rem; margin-bottom:1.5rem; font-size:0.85rem; }

  /* FORM */
  .form-card { background:var(--card); border:1px solid var(--border); padding:2rem; margin-bottom:2rem; }
  .form-card h2 { color:var(--gold); font-size:1.1rem; margin-bottom:1.5rem; font-weight:500; }
  .form-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem; }
  .form-row.full { grid-template-columns:1fr; }
  .form-group { display:flex; flex-direction:column; gap:0.3rem; }
  .form-group label { color:var(--dim); font-size:0.75rem; text-transform:uppercase; letter-spacing:0.08em; }
  .form-group input, .form-group select, .form-group textarea {
    background:#1a1a1a; border:1px solid var(--border); color:var(--white); padding:0.7rem 0.8rem; font-size:0.9rem; outline:none; font-family:inherit;
  }
  .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color:var(--gold); }
  .form-group textarea { resize:vertical; min-height:80px; }
  .form-group select { appearance:none; cursor:pointer; }
  .form-group input[type="file"] { padding:0.5rem; font-size:0.8rem; }
  .form-group input[type="file"]::file-selector-button {
    background:var(--gold); color:var(--black); border:none; padding:0.4rem 1rem; font-size:0.75rem; font-weight:600; cursor:pointer; margin-right:0.8rem;
  }

  .form-actions { display:flex; gap:1rem; margin-top:1.5rem; }
  .btn { padding:0.7rem 1.5rem; font-size:0.8rem; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; border:none; cursor:pointer; font-family:inherit; transition:all 0.2s; }
  .btn-gold { background:var(--gold); color:var(--black); }
  .btn-gold:hover { background:var(--gold-light); }
  .btn-outline { background:none; border:1px solid var(--border); color:var(--dim); }
  .btn-outline:hover { border-color:var(--gold); color:var(--gold); }
  .btn-red { background:rgba(224,88,48,0.15); border:1px solid rgba(224,88,48,0.3); color:var(--red); }
  .btn-red:hover { background:rgba(224,88,48,0.25); }
  .btn-green { background:rgba(76,175,80,0.15); border:1px solid rgba(76,175,80,0.3); color:var(--green); }
  .btn-sm { padding:0.4rem 0.8rem; font-size:0.7rem; }

  /* TABLE */
  .data-table { width:100%; border-collapse:collapse; margin-top:1rem; }
  .data-table th { background:rgba(201,169,110,0.08); color:var(--gold); font-size:0.7rem; font-weight:500; letter-spacing:0.1em; text-transform:uppercase; padding:0.8rem 1rem; text-align:left; border-bottom:1px solid var(--border); }
  .data-table td { padding:0.8rem 1rem; border-bottom:1px solid var(--border); font-size:0.85rem; vertical-align:middle; }
  .data-table tr:hover td { background:rgba(201,169,110,0.03); }
  .data-table .brand { color:var(--gold); font-size:0.7rem; letter-spacing:0.1em; text-transform:uppercase; }
  .data-table .name { font-weight:500; }
  .data-table .price { color:var(--gold); font-weight:500; }
  .data-table .thumb { width:50px; height:50px; object-fit:cover; border:1px solid var(--border); }
  .data-table .emoji-thumb { width:50px; height:50px; display:flex; align-items:center; justify-content:center; background:var(--card); border:1px solid var(--border); font-size:1.5rem; }

  /* BADGES */
  .badge { display:inline-block; font-size:0.65rem; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; padding:0.2rem 0.6rem; }
  .badge-sold { background:rgba(224,88,48,0.2); color:var(--red); border:1px solid rgba(224,88,48,0.3); }
  .badge-new { background:rgba(76,175,80,0.2); color:var(--green); border:1px solid rgba(76,175,80,0.3); }
  .badge-available { background:rgba(201,169,110,0.15); color:var(--gold); border:1px solid rgba(201,169,110,0.3); }
  .badge-condition { background:rgba(100,149,237,0.12); color:#7BA7E8; border:1px solid rgba(100,149,237,0.25); margin-top:3px; }
  .badge-docs { background:rgba(76,175,80,0.1); color:var(--green); border:1px solid rgba(76,175,80,0.2); font-size:0.6rem; margin-top:3px; margin-right:3px; }
  .badge-nodocs { background:rgba(224,88,48,0.1); color:var(--red); border:1px solid rgba(224,88,48,0.15); font-size:0.6rem; margin-top:3px; margin-right:3px; }

  .checkbox-row { display:flex; gap:1.5rem; margin-top:0.5rem; }
  .checkbox-label { display:flex; align-items:center; gap:0.4rem; color:var(--dim); font-size:0.85rem; cursor:pointer; }
  .checkbox-label input[type="checkbox"] { width:16px; height:16px; accent-color:var(--gold); cursor:pointer; }

  .actions-cell { display:flex; gap:0.5rem; flex-wrap:wrap; }
  .actions-cell form { display:inline; }

  /* HELP */
  .help-text { color:var(--dim); font-size:0.75rem; margin-top:0.3rem; font-style:italic; }

  @media (max-width:768px) {
    .form-row { grid-template-columns:1fr; }
    .content { padding:1rem; }
    .tabs { overflow-x:auto; padding:0 1rem; }
    .tabs a { white-space:nowrap; padding:0.8rem 1rem; font-size:0.8rem; }
    .data-table { font-size:0.8rem; }
    .data-table td, .data-table th { padding:0.5rem; }
  }
</style>
</head>
<body>

<!-- NAV -->
<div class="admin-nav">
  <h1>CHRONOS LUX — АДМІН</h1>
  <div>
    <a href="../index.php" target="_blank" style="margin-right:1.5rem;">👁 Переглянути сайт</a>
    <a href="?logout=1">Вийти ↗</a>
  </div>
</div>

<!-- TABS -->
<div class="tabs">
  <a href="?tab=catalog" class="<?= $tab === 'catalog' ? 'active' : '' ?>">⌚ Каталог</a>
  <a href="?tab=articles" class="<?= $tab === 'articles' ? 'active' : '' ?>">📰 Журнал</a>
</div>

<!-- CONTENT -->
<div class="content">

<?php if ($msg): ?><div class="msg"><?= $msg ?></div><?php endif; ?>

<?php if ($tab === 'catalog'): ?>
<!-- ══════════════ КАТАЛОГ ══════════════ -->

<!-- Форма додавання -->
<div class="form-card">
  <h2>➕ Додати / редагувати годинник</h2>
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="watch_id" id="watch_id" value="">

    <div class="form-row">
      <div class="form-group">
        <label>Бренд *</label>
        <input type="text" name="brand" id="f_brand" required placeholder="Rolex">
      </div>
      <div class="form-group">
        <label>Назва моделі *</label>
        <input type="text" name="watch_name" id="f_name" required placeholder="Submariner Date">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Референс</label>
        <input type="text" name="ref" id="f_ref" placeholder="Ref. 126610LN • 41mm">
      </div>
      <div class="form-group">
        <label>Ціна *</label>
        <input type="text" name="price" id="f_price" required placeholder="$14 500">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Характеристики</label>
        <input type="text" name="specs" id="f_specs" placeholder="Сталь Oystersteel, Автоматичний, 300м WR">
        <span class="help-text">Через кому</span>
      </div>
      <div class="form-group">
        <label>Telegram повідомлення</label>
        <input type="text" name="telegram_text" id="f_telegram" placeholder="Вітаю! Цікавить Rolex Submariner">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Значок</label>
        <select name="badge" id="f_badge">
          <option value="">Без значка</option>
          <option value="new">Новинка</option>
          <option value="available">В наявності</option>
        </select>
      </div>
      <div class="form-group">
        <label>Текст значка</label>
        <input type="text" name="badge_text" id="f_badge_text" placeholder="Новинка / В наявності">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Стан годинника</label>
        <select name="condition" id="f_condition">
          <option value="">Не вказано</option>
          <option value="new">Новий</option>
          <option value="unworn">Неношений</option>
          <option value="used">Має сліди використання</option>
          <option value="repair">Потребує відновлення / ремонту</option>
        </select>
      </div>
      <div class="form-group">
        <label>Комплектація</label>
        <div class="checkbox-row">
          <label class="checkbox-label">
            <input type="checkbox" name="has_documents" id="f_docs" value="1">
            <span>📋 Документи</span>
          </label>
          <label class="checkbox-label">
            <input type="checkbox" name="has_box" id="f_box" value="1">
            <span>📦 Оригінальний бокс</span>
          </label>
        </div>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Фото годинника</label>
        <input type="file" name="watch_image" accept="image/*">
        <span class="help-text">JPG/PNG/WebP — буде збережено у photos/</span>
      </div>
      <div class="form-group">
        <label>Порядок (число)</label>
        <input type="number" name="watch_order" id="f_order" value="999" min="1">
        <span class="help-text">1 = перший у списку</span>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" name="save_watch" class="btn btn-gold">Зберегти годинник</button>
      <button type="button" class="btn btn-outline" onclick="clearWatchForm()">Скинути</button>
    </div>
  </form>
</div>

<!-- Таблиця каталогу -->
<div class="form-card">
  <h2>Каталог годинників (<?= count($catalog) ?>)</h2>
  <table class="data-table">
    <tr>
      <th>Фото</th>
      <th>#</th>
      <th>Бренд / Модель</th>
      <th>Ціна</th>
      <th>Стан / Комплект</th>
      <th>Статус</th>
      <th>Дії</th>
    </tr>
    <?php
    $condition_labels = ['new'=>'Новий','unworn'=>'Неношений','used'=>'Сліди використання','repair'=>'Потребує ремонту'];
    foreach ($catalog as $w): ?>
    <tr>
      <td>
        <?php if (!empty($w['image'])): ?>
          <img src="../photos/<?= htmlspecialchars($w['image']) ?>" class="thumb" alt="">
        <?php else: ?>
          <div class="emoji-thumb">⌚</div>
        <?php endif; ?>
      </td>
      <td><?= $w['order'] ?? '-' ?></td>
      <td>
        <div class="brand"><?= htmlspecialchars($w['brand']) ?></div>
        <div class="name"><?= htmlspecialchars($w['name']) ?></div>
        <div style="color:var(--dim);font-size:0.75rem;"><?= htmlspecialchars($w['ref']) ?></div>
      </td>
      <td class="price"><?= htmlspecialchars($w['price']) ?></td>
      <td>
        <?php if (!empty($w['condition'])): ?>
          <span class="badge badge-condition"><?= $condition_labels[$w['condition']] ?? $w['condition'] ?></span><br>
        <?php endif; ?>
        <?php if (!empty($w['has_documents'])): ?>
          <span class="badge badge-docs">📋 Документи</span>
        <?php else: ?>
          <span class="badge badge-nodocs">📋 Без документів</span>
        <?php endif; ?>
        <?php if (!empty($w['has_box'])): ?>
          <span class="badge badge-docs">📦 Бокс</span>
        <?php else: ?>
          <span class="badge badge-nodocs">📦 Без боксу</span>
        <?php endif; ?>
      </td>
      <td>
        <?php if ($w['sold'] ?? false): ?>
          <span class="badge badge-sold">ПРОДАНО</span>
        <?php elseif ($w['badge'] === 'new'): ?>
          <span class="badge badge-new"><?= htmlspecialchars($w['badge_text'] ?: 'Новинка') ?></span>
        <?php elseif ($w['badge'] === 'available'): ?>
          <span class="badge badge-available"><?= htmlspecialchars($w['badge_text'] ?: 'В наявності') ?></span>
        <?php endif; ?>
      </td>
      <td class="actions-cell">
        <button class="btn btn-outline btn-sm" onclick="editWatch(<?= htmlspecialchars(json_encode($w, JSON_UNESCAPED_UNICODE)) ?>)">✏️ Змінити</button>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Позначити як <?= ($w['sold'] ?? false) ? 'доступний' : 'ПРОДАНО' ?>?')">
          <input type="hidden" name="toggle_sold" value="<?= $w['id'] ?>">
          <button class="btn <?= ($w['sold'] ?? false) ? 'btn-green' : 'btn-red' ?> btn-sm">
            <?= ($w['sold'] ?? false) ? '✅ Доступний' : '🏷 Продано' ?>
          </button>
        </form>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Видалити годинник?')">
          <input type="hidden" name="delete_watch" value="<?= $w['id'] ?>">
          <button class="btn btn-red btn-sm">🗑</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php elseif ($tab === 'articles'): ?>
<!-- ══════════════ ЖУРНАЛ ══════════════ -->

<!-- Форма додавання -->
<div class="form-card">
  <h2>➕ Додати / редагувати статтю</h2>
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="article_id" id="a_id" value="">

    <div class="form-row full">
      <div class="form-group">
        <label>Заголовок *</label>
        <input type="text" name="article_title" id="a_title" required placeholder="Назва статті">
      </div>
    </div>

    <div class="form-row full">
      <div class="form-group">
        <label>Короткий опис *</label>
        <textarea name="article_excerpt" id="a_excerpt" required placeholder="1-2 речення для картки на головній"></textarea>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Категорія (тег) *</label>
        <input type="text" name="article_tag" id="a_tag" required placeholder="Огляд / Гід покупця / Інвестиції">
      </div>
      <div class="form-group">
        <label>Дата *</label>
        <input type="text" name="article_date" id="a_date" required placeholder="Березень 2026">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Файл статті *</label>
        <input type="text" name="article_file" id="a_file" required placeholder="royal-oak-guide.html">
        <span class="help-text">Назва HTML файлу в папці articles/</span>
      </div>
      <div class="form-group">
        <label>Порядок (число) *</label>
        <input type="number" name="article_order" id="a_order" value="999" min="1">
        <span class="help-text">1 = перша стаття</span>
      </div>
    </div>

    <div class="form-row full">
      <div class="form-group">
        <label>Фото для обкладинки</label>
        <input type="file" name="article_image" accept="image/*">
        <span class="help-text">JPG/PNG/WebP — обкладинка картки у журналі. Без фото буде емодзі.</span>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" name="save_article" class="btn btn-gold">Зберегти статтю</button>
      <button type="button" class="btn btn-outline" onclick="clearArticleForm()">Скинути</button>
    </div>
  </form>
</div>

<!-- Таблиця статей -->
<div class="form-card">
  <h2>Статті журналу (<?= count($articles) ?>)</h2>
  <table class="data-table">
    <tr>
      <th>Фото</th>
      <th>#</th>
      <th>Заголовок</th>
      <th>Тег</th>
      <th>Файл</th>
      <th>Дії</th>
    </tr>
    <?php foreach ($articles as $a): ?>
    <tr>
      <td>
        <?php if (!empty($a['image'])): ?>
          <img src="../photos/<?= htmlspecialchars($a['image']) ?>" class="thumb" alt="">
        <?php else: ?>
          <div class="emoji-thumb">📝</div>
        <?php endif; ?>
      </td>
      <td><?= $a['order'] ?? '-' ?></td>
      <td>
        <div class="name"><?= htmlspecialchars($a['title']) ?></div>
        <div style="color:var(--dim);font-size:0.75rem;"><?= htmlspecialchars(mb_substr($a['excerpt'], 0, 80)) ?>...</div>
      </td>
      <td><span class="badge badge-available"><?= htmlspecialchars($a['tag']) ?></span></td>
      <td style="font-size:0.75rem;color:var(--dim);"><?= htmlspecialchars($a['file']) ?></td>
      <td class="actions-cell">
        <button class="btn btn-outline btn-sm" onclick="editArticle(<?= htmlspecialchars(json_encode($a, JSON_UNESCAPED_UNICODE)) ?>)">✏️ Змінити</button>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Видалити статтю?')">
          <input type="hidden" name="delete_article" value="<?= $a['id'] ?>">
          <button class="btn btn-red btn-sm">🗑</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php endif; ?>

</div><!-- /content -->

<script>
function editWatch(w) {
  document.getElementById('watch_id').value = w.id;
  document.getElementById('f_brand').value = w.brand || '';
  document.getElementById('f_name').value = w.name || '';
  document.getElementById('f_ref').value = w.ref || '';
  document.getElementById('f_price').value = w.price || '';
  document.getElementById('f_specs').value = (w.specs || []).join(', ');
  document.getElementById('f_telegram').value = w.telegram_text || '';
  document.getElementById('f_badge').value = w.badge || '';
  document.getElementById('f_badge_text').value = w.badge_text || '';
  document.getElementById('f_condition').value = w.condition || '';
  document.getElementById('f_docs').checked = !!w.has_documents;
  document.getElementById('f_box').checked = !!w.has_box;
  document.getElementById('f_order').value = w.order || 999;
  window.scrollTo({top: 0, behavior: 'smooth'});
}

function clearWatchForm() {
  document.getElementById('watch_id').value = '';
  document.getElementById('f_brand').value = '';
  document.getElementById('f_name').value = '';
  document.getElementById('f_ref').value = '';
  document.getElementById('f_price').value = '';
  document.getElementById('f_specs').value = '';
  document.getElementById('f_telegram').value = '';
  document.getElementById('f_badge').value = '';
  document.getElementById('f_badge_text').value = '';
  document.getElementById('f_condition').value = '';
  document.getElementById('f_docs').checked = false;
  document.getElementById('f_box').checked = false;
  document.getElementById('f_order').value = 999;
}

function editArticle(a) {
  document.getElementById('a_id').value = a.id;
  document.getElementById('a_title').value = a.title || '';
  document.getElementById('a_excerpt').value = a.excerpt || '';
  document.getElementById('a_tag').value = a.tag || '';
  document.getElementById('a_date').value = a.date || '';
  document.getElementById('a_file').value = a.file || '';
  document.getElementById('a_order').value = a.order || 999;
  window.scrollTo({top: 0, behavior: 'smooth'});
}

function clearArticleForm() {
  document.getElementById('a_id').value = '';
  document.getElementById('a_title').value = '';
  document.getElementById('a_excerpt').value = '';
  document.getElementById('a_tag').value = '';
  document.getElementById('a_date').value = '';
  document.getElementById('a_file').value = '';
  document.getElementById('a_order').value = 999;
}
</script>

</body>
</html>