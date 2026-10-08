<?php
// Intermediate Web — Project Tracker. PHP / MySQL / HTML / CSS, no framework.
require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';
session_start();
if (!isset($_SESSION['form_token'])) $_SESSION['form_token'] = bin2hex(random_bytes(16));
$errors = [];
$message = '';
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);
    $db->set_charset('utf8mb4');
} catch (Throwable $e) {
    http_response_code(500);
    exit('Database unavailable. Import database.sql and check config.php.');
}
$editing = null;
$form = ['title'=>'','repository'=>'','public_url'=>'','description'=>'','next_step'=>'','started_on'=>date('Y-m-d')];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['form_token'], (string)($_POST['form_token'] ?? ''))) {
        $errors[] = 'Your form session expired. Refresh and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if ($action === 'delete') {
            if (!$id) $errors[] = 'Invalid project ID.';
            else {
                $statement = $db->prepare('DELETE FROM projects WHERE id = ?');
                $statement->bind_param('i', $id);
                $statement->execute();
                header('Location: index.php?notice=deleted'); exit;
            }
        } elseif ($action === 'save') {
            [$form, $errors] = validateProject($_POST);
            if (!empty($_POST['id']) && !$id) $errors[] = 'Invalid project ID.';
            if (!$errors) {
                if ($id) {
                    $statement = $db->prepare('UPDATE projects SET title=?, repository=?, public_url=?, description=?, next_step=?, started_on=?, updated_at=NOW() WHERE id=?');
                    $statement->bind_param('ssssssi', $form['title'], $form['repository'], $form['public_url'], $form['description'], $form['next_step'], $form['started_on'], $id);
                } else {
                    $statement = $db->prepare('INSERT INTO projects (title, repository, public_url, description, next_step, started_on) VALUES (?, ?, ?, ?, ?, ?)');
                    $statement->bind_param('ssssss', $form['title'], $form['repository'], $form['public_url'], $form['description'], $form['next_step'], $form['started_on']);
                }
                $statement->execute();
                header('Location: index.php?notice=saved'); exit;
            }
            if ($id) $editing = $id;
        } else $errors[] = 'Unrecognized form action.';
    }
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit'])) {
    $id = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if ($id) {
        $statement = $db->prepare('SELECT * FROM projects WHERE id=?');
        $statement->bind_param('i', $id);
        $statement->execute();
        $existing = $statement->get_result()->fetch_assoc();
        if ($existing) { $editing = $id; $form = $existing; }
        else $errors[] = 'Project not found.';
    }
}
$search = trim((string)($_GET['search'] ?? ''));
$searchIn = (string)($_GET['search_in'] ?? 'all');
$searchColumns = ['title'=>'title', 'repository'=>'repository', 'public_url'=>'public_url', 'description'=>'description'];
if (!isset($searchColumns[$searchIn]) && $searchIn !== 'all') $searchIn = 'all';
$filter = (string)($_GET['filter'] ?? 'all');
if (!in_array($filter, ['all','public','no_public','has_repo','needs_step'], true)) $filter = 'all';
$sort = (string)($_GET['sort'] ?? 'updated_desc');
$sortOptions = [
 'updated_desc'=>'updated_at DESC, id DESC', 'updated_asc'=>'updated_at ASC, id ASC',
 'title_asc'=>'title ASC', 'title_desc'=>'title DESC',
 'repo_asc'=>'repository ASC', 'repo_desc'=>'repository DESC',
 'started_desc'=>'started_on DESC', 'started_asc'=>'started_on ASC',
 'created_desc'=>'created_at DESC', 'created_asc'=>'created_at ASC'
];
if (!isset($sortOptions[$sort])) $sort = 'updated_desc';
$conditions = [];
$parameters = [];
if ($search !== '') {
    $columns = $searchIn === 'all' ? array_values($searchColumns) : [$searchColumns[$searchIn]];
    $conditions[] = '(' . implode(' OR ', array_map(fn($column) => "$column LIKE ?", $columns)) . ')';
    foreach ($columns as $column) $parameters[] = '%' . $search . '%';
}
if ($filter === 'public') $conditions[] = "public_url <> ''";
if ($filter === 'no_public') $conditions[] = "public_url = ''";
if ($filter === 'has_repo') $conditions[] = "repository <> ''";
if ($filter === 'needs_step') $conditions[] = "next_step <> ''";
$sql = 'SELECT * FROM projects' . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '') . ' ORDER BY ' . $sortOptions[$sort];
$statement = $db->prepare($sql);
if ($parameters) $statement->bind_param(str_repeat('s', count($parameters)), ...$parameters);
$statement->execute();
$projects = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
$total = (int)$db->query('SELECT COUNT(*) AS n FROM projects')->fetch_assoc()['n'];
$notices = ['saved'=>'Project saved.', 'deleted'=>'Project deleted.'];
$message = $notices[(string)($_GET['notice'] ?? '')] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Project Tracker</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main>
<header><div><h1>Project Tracker</h1><p>One place for what I am building and what's next.</p></div><div class="count"><?= $total ?> total projects</div></header>
<?php if ($message): ?><p class="success" role="status"><?= escapeText($message) ?></p><?php endif; ?>
<?php if ($errors): ?><div class="error" role="alert"><?php foreach ($errors as $error): ?><p><?= escapeText($error) ?></p><?php endforeach; ?></div><?php endif; ?>
<section class="panel" id="editor">
<h2><?= $editing ? 'Edit project' : 'Add a project' ?></h2>
<form method="post" action="index.php#editor">
<input type="hidden" name="action" value="save">
<input type="hidden" name="id" value="<?= escapeText($editing ?? '') ?>">
<input type="hidden" name="form_token" value="<?= escapeText($_SESSION['form_token']) ?>">
<div class="formgrid">
<label>Project title <span class="required">*</span><input name="title" maxlength="160" value="<?= escapeText($form['title']) ?>" required placeholder="e.g. Uphill Battle"></label>
<label>GitHub repository<input name="repository" maxlength="255" value="<?= escapeText($form['repository']) ?>" placeholder="owner/repo or https://github.com/owner/repo"></label>
<label>Public working link (optional)<input type="url" name="public_url" maxlength="500" value="<?= escapeText($form['public_url']) ?>" placeholder="https://example.com"></label>
<label>Project started on<input type="date" name="started_on" value="<?= escapeText($form['started_on']) ?>" required></label>
<label class="wide">Short description<textarea name="description" rows="3" placeholder="What is it?"><?= escapeText($form['description']) ?></textarea></label>
<label class="wide">Waiting on / my next step<textarea name="next_step" rows="3" placeholder="What needs to happen next?"><?= escapeText($form['next_step']) ?></textarea></label>
</div>
<div class="actions"><button type="submit"><?= $editing ? 'Save changes' : 'Add project' ?></button><?php if ($editing): ?><a href="index.php#editor">Cancel editing</a><?php endif; ?></div>
</form>
</section>
<section class="panel">
<h2>Projects</h2>
<form method="get" action="index.php" class="toolbar">
<label>Search<input name="search" value="<?= escapeText($search) ?>" placeholder="Search project text..."></label>
<label>Search in<select name="search_in">
<?php foreach (['all'=>'All text fields','title'=>'Name','repository'=>'Repository','public_url'=>'Public link','description'=>'Description'] as $key=>$value): ?>
<option value="<?= escapeText($key) ?>" <?= $searchIn === $key ? 'selected' : '' ?>><?= escapeText($value) ?></option><?php endforeach; ?>
</select></label>
<label>Filter<select name="filter">
<?php foreach (['all'=>'All projects','public'=>'Has public link','no_public'=>'No public link','has_repo'=>'Has repository','needs_step'=>'Has next step'] as $key=>$value): ?>
<option value="<?= escapeText($key) ?>" <?= $filter === $key ? 'selected' : '' ?>><?= escapeText($value) ?></option><?php endforeach; ?>
</select></label>
<label>Sort<select name="sort">
<?php foreach (['updated_desc'=>'Recently updated','updated_asc'=>'Least recently updated','title_asc'=>'Name A–Z','title_desc'=>'Name Z–A','repo_asc'=>'Repository A–Z','repo_desc'=>'Repository Z–A','started_desc'=>'Newest start','started_asc'=>'Oldest start','created_desc'=>'Recently added','created_asc'=>'Oldest added'] as $key=>$value): ?>
<option value="<?= escapeText($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= escapeText($value) ?></option><?php endforeach; ?>
</select></label>
<div class="filteractions"><button type="submit">Apply</button><a href="index.php">Clear</a></div>
</form>
<p class="results">Showing <?= count($projects) ?> of <?= $total ?> projects</p>
<div class="tablewrap"><table>
<thead><tr><th>Project</th><th>Repository</th><th>Public link</th><th>Description</th><th>Waiting on / next step</th><th>Started</th><th>Last updated</th><th>Actions</th></tr></thead>
<tbody>
<?php if (!$projects): ?><tr><td colspan="8" class="empty">No matching projects. Add your first one above, or change the filters.</td></tr><?php endif; ?>
<?php foreach ($projects as $project): ?>
<tr>
<td class="projectname"><?= escapeText($project['title']) ?></td>
<td><?php $repoLink=repositoryUrl($project['repository']); if ($repoLink): ?><a href="<?= escapeText($repoLink) ?>" target="_blank" rel="noopener noreferrer"><?= escapeText($project['repository']) ?></a><?php else: ?><?= escapeText($project['repository'] ?: '—') ?><?php endif; ?></td>
<td><?php if ($project['public_url']): ?><a href="<?= escapeText($project['public_url']) ?>" target="_blank" rel="noopener noreferrer">Visit site ↗</a><?php else: ?>—<?php endif; ?></td>
<td class="bodycell"><?= nl2br(escapeText($project['description'] ?: '—')) ?></td>
<td class="bodycell"><?= nl2br(escapeText($project['next_step'] ?: '—')) ?></td>
<td class="datecell"><?= escapeText($project['started_on']) ?></td>
<td class="datecell"><?= escapeText($project['updated_at']) ?></td>
<td><div class="rowactions"><a href="index.php?edit=<?= (int)$project['id'] ?>#editor">Edit</a><form method="post" action="index.php" onsubmit="return confirm('Delete this project?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$project['id'] ?>"><input type="hidden" name="form_token" value="<?= escapeText($_SESSION['form_token']) ?>"><button type="submit" class="delete">Delete</button></form></div></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</section>
<footer>Course-constrained experiment · PHP + MySQL + HTML + CSS · Local XAMPP</footer>
</main>
</body>
</html>
