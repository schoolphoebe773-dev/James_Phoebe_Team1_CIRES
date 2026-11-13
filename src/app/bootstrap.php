<?php
// Require explicit types for function arguments.
declare(strict_types=1);

// Start a session that keeps the user signed in between pages.
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'use_strict_mode' => true]);

// Load database settings from the local environment file.
$configPath = dirname(__DIR__) . '/.env';

// Create a request token for forms if this browser has none yet.
$_SESSION['token'] ??= bin2hex(random_bytes(32));

// Escape text before inserting it into HTML.
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Open and reuse one database connection per request.
function db(): PDO
{
    static $connection = null;
    // Reuse the connection after the first query.
    if ($connection instanceof PDO) {
        return $connection;
    }
    // Use the local settings file and keep database errors out of the UI.
    global $configPath;
    try {
        // Read raw values so passwords are handled as text.
        $settings = is_file($configPath) ? parse_ini_file($configPath, false, INI_SCANNER_RAW) : [];
        // Build the MySQL connection address from the local configuration.
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $settings['DB_HOST'] ?? '127.0.0.1', $settings['DB_PORT'] ?? '3306', $settings['DB_NAME'] ?? 'cires_m4');
        // Throw on query errors and fetch results by column name.
        $connection = new PDO($dsn, $settings['DB_USER'] ?? 'root', $settings['DB_PASSWORD'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $connection;
    } catch (PDOException $error) {
        // Show setup instructions without printing credentials.
        http_response_code(503);
        page('Database unavailable');
        echo '<section class="card"><h1>Database unavailable</h1><p>Start MySQL in AMPPS, import database/cires.sql and check the credentials in .env.</p></section>';
        endPage();
        exit;
    }
}

// Pass user data as parameters so it is never interpreted as SQL.
function query(string $sql, array $values = []): PDOStatement
{
    // Prepare the SQL and then bind its values.
    $statement = db()->prepare($sql);
    $statement->execute($values);
    // Return the result for reading rows or checking changes.
    return $statement;
}

// Redirect after POST so a browser refresh does not repeat it.
function go(string $url): void
{
    header('Location: ' . $url, true, 303);
    exit;
}

// Read a form or URL value safely, including when an array was submitted.
function field(array $source, string $key): string
{
    return is_string($source[$key] ?? null) ? trim($source[$key]) : '';
}

// Count UTF-8 characters to match the database field limits.
function textLength(string $value): int
{
    // Invalid UTF-8 should fail the length checks.
    $length = preg_match_all('/./us', $value);
    return $length === false ? PHP_INT_MAX : $length;
}

// Check that a POST came from a form opened by this session.
function checkToken(): void
{
    if (!hash_equals($_SESSION['token'], field($_POST, 'token'))) {
        http_response_code(400);
        exit('This form expired. Go back, reload the page and try again.');
    }
}

// Print the hidden request token in a form.
function tokenField(): void
{
    echo '<input type="hidden" name="token" value="' . e($_SESSION['token']) . '">';
}

// Send people to the sign-in page if their role cannot open the page.
function requireRole(string $role): array
{
    $user = $_SESSION['user'] ?? [];
    if (($user['role'] ?? '') !== $role) {
        go('index.php');
    }
    return $user;
}

// Read a notice once so it disappears when the page reloads.
function notice(): void
{
    if (isset($_SESSION['notice'])) {
        echo '<p class="notice" role="status">' . e($_SESSION['notice']) . '</p>';
        unset($_SESSION['notice']);
    }
}

// Draw the common header and open the content area.
function page(string $title, string $active = ''): void
{
    $role = $_SESSION['user']['role'] ?? '';
    ?>
<!doctype html>
<html lang="en">
<head>
    <!-- Keep text and layouts readable on phones. -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | CIRES</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<header class="top">
    <div class="top-inner">
        <a class="brand" href="index.php"><span class="brand-mark" aria-hidden="true">C</span> CIRES</a>
        <?php if ($role !== ''): ?>
            <!-- Show links that belong to the signed-in role. -->
            <nav aria-label="Main">
                <?php if ($role === 'citizen'): ?>
                    <a href="index.php" <?= $active === 'report' ? 'aria-current="page"' : '' ?>>Report an issue</a>
                    <a href="track.php" <?= $active === 'track' ? 'aria-current="page"' : '' ?>>Track my issue</a>
                <?php else: ?>
                    <a href="office.php" <?= $active === 'office' ? 'aria-current="page"' : '' ?>>Cell queue</a>
                <?php endif; ?>
                <form method="post" action="index.php">
                    <?php tokenField(); ?>
                    <button class="text-button" name="action" value="logout">Sign out</button>
                </form>
            </nav>
        <?php endif; ?>
    </div>
</header>
<main id="main" class="shell">
<?php
}

// Close the common content area and page.
function endPage(): void
{
    echo '</main><footer class="footer">CIRES · Citizen Issue Reporting and Escalation System</footer></body></html>';
}

// Show workflow states as readable labels with a visual cue.
function statusBadge(string $status): string
{
    $class = $status === 'Resolved' ? 'resolved' : ($status === 'In Progress' ? 'progress' : 'received');
    return '<span class="badge ' . $class . '">' . e($status) . '</span>';
}
