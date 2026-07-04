<?php
/**
 * forum.php — Ray Chad Forum
 * Single-file PHP + SQLite forum engine.
 *
 * No framework, no Composer, no external services. Drop this file
 * on any host with PHP 8+ and the pdo_sqlite extension enabled and
 * it will create its own database on first run.
 *
 * Companion to the Node.js/Docker stack described in INSTALL.md —
 * use this instead when you just want a single file you can upload
 * over FTP/cPanel or run with `php -S 0.0.0.0:8080 forum.php`.
 */

declare(strict_types=1);
session_start();

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------
const DB_PATH   = __DIR__ . '/forum.sqlite';
const SITE_NAME = 'Ray Chad Forum';

// ---------------------------------------------------------------------------
// Database
// ---------------------------------------------------------------------------
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $isNew = !file_exists(DB_PATH);
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');

    if ($isNew) {
        seed($pdo);
    }

    return $pdo;
}

function seed(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE boards (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            slug        TEXT UNIQUE NOT NULL,
            name        TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT ""
        )
    ');

    $pdo->exec('
        CREATE TABLE threads (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            board_id   INTEGER NOT NULL REFERENCES boards(id) ON DELETE CASCADE,
            title      TEXT NOT NULL,
            author     TEXT NOT NULL,
            body       TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime("now"))
        )
    ');

    $pdo->exec('
        CREATE TABLE replies (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            thread_id  INTEGER NOT NULL REFERENCES threads(id) ON DELETE CASCADE,
            author     TEXT NOT NULL,
            body       TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime("now"))
        )
    ');

    $boards = [
        ['meta',    'Meta',    'Forum itself: feedback, bugs, ideas.'],
        ['dev',     'Dev',     'Building on top of raychd and the forum.'],
        ['install', 'Install', 'Setup help across macOS, Linux, and Termux.'],
    ];
    $stmt = $pdo->prepare('INSERT INTO boards (slug, name, description) VALUES (?, ?, ?)');
    foreach ($boards as $b) {
        $stmt->execute($b);
    }
}

// ---------------------------------------------------------------------------
// Tiny helpers
// ---------------------------------------------------------------------------
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function current_user(): string
{
    return $_SESSION['user'] ?? '';
}

function require_user(): string
{
    $user = current_user();
    if ($user === '') {
        redirect('forum.php?action=login');
    }
    return $user;
}

// ---------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------
$action = $_GET['action'] ?? 'index';
$pdo    = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'login':
            $name = trim((string)($_POST['username'] ?? ''));
            if ($name === '' || !preg_match('/^[a-zA-Z0-9_\-]{2,24}$/', $name)) {
                $error = 'Username must be 2-24 characters: letters, numbers, - or _.';
                break;
            }
            $_SESSION['user'] = $name;
            redirect('forum.php');

        case 'logout':
            unset($_SESSION['user']);
            redirect('forum.php');

        case 'new_thread':
            $user = require_user();
            $boardSlug = (string)($_POST['board'] ?? '');
            $title = trim((string)($_POST['title'] ?? ''));
            $body  = trim((string)($_POST['body'] ?? ''));
            if ($title === '' || $body === '') {
                $error = 'Title and body are required.';
                break;
            }
            $board = $pdo->prepare('SELECT id FROM boards WHERE slug = ?');
            $board->execute([$boardSlug]);
            $boardId = $board->fetchColumn();
            if (!$boardId) {
                $error = 'Unknown board.';
                break;
            }
            $stmt = $pdo->prepare(
                'INSERT INTO threads (board_id, title, author, body) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$boardId, $title, $user, $body]);
            redirect('forum.php?action=thread&id=' . $pdo->lastInsertId());

        case 'reply':
            $user = require_user();
            $threadId = (int)($_POST['thread_id'] ?? 0);
            $body = trim((string)($_POST['body'] ?? ''));
            if ($body === '' || $threadId <= 0) {
                $error = 'Reply body is required.';
                break;
            }
            $stmt = $pdo->prepare(
                'INSERT INTO replies (thread_id, author, body) VALUES (?, ?, ?)'
            );
            $stmt->execute([$threadId, $user, $body]);
            redirect('forum.php?action=thread&id=' . $threadId);
    }
}

// ---------------------------------------------------------------------------
// Layout
// ---------------------------------------------------------------------------
function layout_start(string $title): void
{
    $user = current_user();
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . h($title) . ' — ' . SITE_NAME . '</title>';
    echo '<style>
        :root{--bg:#0d0f0d;--bg-raised:#141714;--line:#22271f;--text:#d7ddd2;
              --text-dim:#7a8574;--accent:#7fd66b;--accent-dim:#4f7a44;--amber:#e0b24a;}
        *{box-sizing:border-box;} body{margin:0;background:var(--bg);color:var(--text);
          font-family:ui-monospace,"JetBrains Mono",Menlo,Consolas,monospace;line-height:1.6;}
        a{color:var(--accent);text-decoration:none;} a:hover{text-decoration:underline;}
        .wrap{max-width:820px;margin:0 auto;padding:20px;}
        nav{border-bottom:1px solid var(--line);display:flex;justify-content:space-between;
            align-items:center;padding:14px 20px;}
        .brand{font-weight:700;} .navr{font-size:13px;color:var(--text-dim);}
        .navr a{color:var(--text-dim);margin-left:14px;} .navr a:hover{color:var(--accent);}
        .card{background:var(--bg-raised);border:1px solid var(--line);border-radius:4px;
              padding:16px 18px;margin-bottom:14px;}
        .board-row,.thread-row{display:flex;justify-content:space-between;gap:12px;
              padding:10px 0;border-bottom:1px solid var(--line);}
        .board-row:last-child,.thread-row:last-child{border-bottom:none;}
        .meta{color:var(--text-dim);font-size:12px;white-space:nowrap;}
        input,textarea,button{font-family:inherit;background:var(--bg);color:var(--text);
              border:1px solid var(--line);border-radius:3px;padding:9px 12px;font-size:13.5px;}
        textarea{width:100%;min-height:100px;resize:vertical;}
        button{background:var(--accent);color:#0d0f0d;font-weight:700;cursor:pointer;border:none;}
        button:hover{background:#93e082;}
        .error{color:var(--amber);margin-bottom:12px;font-size:13px;}
        .eyebrow{color:var(--accent-dim);font-size:12px;text-transform:uppercase;
              letter-spacing:0.08em;margin-bottom:10px;}
        .eyebrow::before{content:"// ";}
        .reply{border-left:2px solid var(--accent-dim);padding-left:12px;margin-top:12px;}
        form.inline{display:flex;flex-direction:column;gap:10px;margin-top:14px;}
    </style></head><body>';
    echo '<nav><div class="brand">ray#chad<span style="color:var(--text-dim)">/forum</span></div>';
    echo '<div class="navr">';
    if ($user !== '') {
        echo h($user) . ' · <a href="forum.php?action=logout" onclick="event.preventDefault();document.getElementById(\'logout-form\').submit();">Log out</a>';
        echo '<form id="logout-form" method="post" action="forum.php?action=logout" style="display:none;"></form>';
    } else {
        echo '<a href="forum.php?action=login">Log in</a>';
    }
    echo '</div></nav><div class="wrap">';
}

function layout_end(): void
{
    echo '</div></body></html>';
}

// ---------------------------------------------------------------------------
// Views
// ---------------------------------------------------------------------------
switch ($action) {
    case 'login':
        layout_start('Log in');
        if (!empty($error)) echo '<div class="error">' . h($error) . '</div>';
        echo '<div class="eyebrow">log in</div>';
        echo '<div class="card"><form class="inline" method="post" action="forum.php?action=login">';
        echo '<input name="username" placeholder="username" maxlength="24" required>';
        echo '<button type="submit">Continue</button>';
        echo '</form></div>';
        echo '<p style="color:var(--text-dim);font-size:12.5px;">No password yet — this is a lightweight demo login. '
           . 'Wire this up to your raychd IRC account system before running it for real users.</p>';
        layout_end();
        break;

    case 'thread':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare('
            SELECT threads.*, boards.name AS board_name, boards.slug AS board_slug
            FROM threads JOIN boards ON boards.id = threads.board_id
            WHERE threads.id = ?
        ');
        $stmt->execute([$id]);
        $thread = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$thread) {
            layout_start('Not found');
            echo '<p>Thread not found.</p><p><a href="forum.php">&larr; Back to boards</a></p>';
            layout_end();
            break;
        }

        $replies = $pdo->prepare('SELECT * FROM replies WHERE thread_id = ? ORDER BY created_at ASC');
        $replies->execute([$id]);

        layout_start($thread['title']);
        echo '<div class="eyebrow"><a href="forum.php?action=board&slug=' . h($thread['board_slug']) . '">' . h($thread['board_name']) . '</a></div>';
        echo '<h2 style="margin:0 0 6px;">' . h($thread['title']) . '</h2>';
        echo '<div class="meta" style="margin-bottom:14px;">' . h($thread['author']) . ' · ' . h($thread['created_at']) . '</div>';
        echo '<div class="card">' . nl2br(h($thread['body'])) . '</div>';

        foreach ($replies->fetchAll(PDO::FETCH_ASSOC) as $r) {
            echo '<div class="reply"><div class="meta">' . h($r['author']) . ' · ' . h($r['created_at']) . '</div>';
            echo '<div>' . nl2br(h($r['body'])) . '</div></div>';
        }

        if (current_user() !== '') {
            if (!empty($error)) echo '<div class="error">' . h($error) . '</div>';
            echo '<form class="inline" method="post" action="forum.php?action=reply">';
            echo '<input type="hidden" name="thread_id" value="' . (int)$id . '">';
            echo '<textarea name="body" placeholder="Write a reply..." required></textarea>';
            echo '<button type="submit">Reply</button>';
            echo '</form>';
        } else {
            echo '<p style="margin-top:16px;"><a href="forum.php?action=login">Log in</a> to reply.</p>';
        }
        layout_end();
        break;

    case 'board':
        $slug = (string)($_GET['slug'] ?? '');
        $board = $pdo->prepare('SELECT * FROM boards WHERE slug = ?');
        $board->execute([$slug]);
        $board = $board->fetch(PDO::FETCH_ASSOC);
        if (!$board) {
            layout_start('Not found');
            echo '<p>Board not found.</p><p><a href="forum.php">&larr; Back to boards</a></p>';
            layout_end();
            break;
        }

        $threads = $pdo->prepare('
            SELECT threads.*, (SELECT COUNT(*) FROM replies WHERE replies.thread_id = threads.id) AS reply_count
            FROM threads WHERE board_id = ? ORDER BY created_at DESC
        ');
        $threads->execute([$board['id']]);

        layout_start($board['name']);
        echo '<div class="eyebrow">' . h($board['name']) . '</div>';
        echo '<p style="color:var(--text-dim);margin-top:0;">' . h($board['description']) . '</p>';

        echo '<div class="card">';
        foreach ($threads->fetchAll(PDO::FETCH_ASSOC) as $t) {
            echo '<div class="thread-row">';
            echo '<a href="forum.php?action=thread&id=' . (int)$t['id'] . '">' . h($t['title']) . '</a>';
            echo '<span class="meta">' . h($t['author']) . ' · ' . (int)$t['reply_count'] . ' replies</span>';
            echo '</div>';
        }
        echo '</div>';

        if (current_user() !== '') {
            if (!empty($error)) echo '<div class="error">' . h($error) . '</div>';
            echo '<div class="eyebrow">new thread</div>';
            echo '<form class="inline" method="post" action="forum.php?action=new_thread">';
            echo '<input type="hidden" name="board" value="' . h($slug) . '">';
            echo '<input name="title" placeholder="Thread title" maxlength="200" required>';
            echo '<textarea name="body" placeholder="What do you want to say?" required></textarea>';
            echo '<button type="submit">Post thread</button>';
            echo '</form>';
        } else {
            echo '<p><a href="forum.php?action=login">Log in</a> to start a thread.</p>';
        }
        layout_end();
        break;

    case 'index':
    default:
        $boards = $pdo->query('
            SELECT boards.*,
                   (SELECT COUNT(*) FROM threads WHERE threads.board_id = boards.id) AS thread_count
            FROM boards ORDER BY boards.id ASC
        ');

        layout_start('Boards');
        echo '<div class="eyebrow">boards</div>';
        echo '<div class="card">';
        foreach ($boards->fetchAll(PDO::FETCH_ASSOC) as $b) {
            echo '<div class="board-row">';
            echo '<div><a href="forum.php?action=board&slug=' . h($b['slug']) . '">' . h($b['name']) . '</a>';
            echo '<div class="meta">' . h($b['description']) . '</div></div>';
            echo '<span class="meta">' . (int)$b['thread_count'] . ' threads</span>';
            echo '</div>';
        }
        echo '</div>';
        layout_end();
        break;
}
