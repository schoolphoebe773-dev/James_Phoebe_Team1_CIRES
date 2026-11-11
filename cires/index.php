<?php
// Load shared session, database and page functions.
require __DIR__ . '/app/bootstrap.php';

// Keep submitted values when the form needs a correction.
$values = ['category' => '', 'cell' => '', 'location' => '', 'description' => ''];
// Start with an empty error message.
$error = '';

// Handle sign-in, sign-out and report submissions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify that the submitted form belongs to this session.
    checkToken();
    // Read which button submitted the form.
    $action = field($_POST, 'action');

    // Process the sign-out button.
    if ($action === 'logout') {
        // Clear the signed-in account and rotate the session identifier.
        $_SESSION = [];
        // Replace the old session identifier.
        session_regenerate_id(true);
        // Return to the entry page.
        go('index.php');
    }

    if ($action === 'login' && empty($_SESSION['user'])) {
        // Find the demo account and verify its stored password hash.
        $account = query('SELECT * FROM user_account WHERE login_name = ?', [field($_POST, 'login')])->fetch();
        if ($account && password_verify(field($_POST, 'password'), $account['password_hash'])) {
            // Keep the role and cell assignment in the session.
            unset($account['password_hash']);
            // Replace the old session identifier.
            session_regenerate_id(true);
            $_SESSION['user'] = $account;
            $_SESSION['token'] = bin2hex(random_bytes(32));
            go($account['role'] === 'cell_executive' ? 'office.php' : 'index.php');
        }
        // Give the same message for a wrong username or password.
        $error = 'The username or password is incorrect.';
    }

    if ($action === 'report') {
        // Only citizens can save a new report.
        $citizen = requireRole('citizen');
        // Read each field that the report accepts.
        foreach ($values as $name => $unused) {
            // Keep this value for validation and redisplay.
            $values[$name] = field($_POST, $name);
        }
        // Check lengths and verify both selected options against MySQL.
        if (!ctype_digit($values['category']) || !ctype_digit($values['cell'])
            || !query('SELECT 1 FROM category WHERE category_id = ?', [$values['category']])->fetchColumn()
            || !query('SELECT 1 FROM cell WHERE cell_id = ?', [$values['cell']])->fetchColumn()
            || $values['location'] === '' || textLength($values['location']) > 180
            || $values['description'] === '' || textLength($values['description']) > 1500) {
            $error = 'Choose a category and cell. Enter a location and a short description.';
        } else {
            // Generate a reference and save the report and its first update together.
            $reference = 'CIRES-' . strtoupper(bin2hex(random_bytes(6)));
            // Use the same connection for both inserts.
            $database = db();
            // Start a transaction so both writes succeed together.
            $database->beginTransaction();
            try {
                query('INSERT INTO issue (reference_code, citizen_id, cell_id, category_id, location_details, description, follow_up_due_at)
                    VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 3 DAY))',
                    [$reference, $citizen['user_id'], $values['cell'], $values['category'], $values['location'], $values['description']]);
                // Get the ID assigned to the new issue.
                $issueId = $database->lastInsertId();
                // Add the initial Received event to the history.
                query("INSERT INTO status_update (issue_id, updated_by, status) VALUES (?, ?, 'Received')", [$issueId, $citizen['user_id']]);
                // Make the report and first update permanent.
                $database->commit();
                // Save a confirmation for the next page.
                $_SESSION['notice'] = 'Report submitted. Save your reference number to track it later.';
                // Open tracking with the new reference already filled in.
                go('track.php?ref=' . rawurlencode($reference));
            } catch (PDOException $exception) {
                // Undo both writes if either one fails.
                $database->rollBack();
                // Show a short error and keep the entered details.
                $error = 'We could not save your report. Please try again.';
            }
        }
    }
}

// Route the executive to the office instead of the citizen form.
$role = $_SESSION['user']['role'] ?? '';
if ($role === 'cell_executive') {
    go('office.php');
}

// Load form choices before sending any HTML.
if ($role === 'citizen') {
    $categories = query('SELECT category_id, category_name FROM category ORDER BY category_name')->fetchAll();
    // Load cell and sector labels for the dropdown.
    $cells = query('SELECT cell.cell_id, cell.cell_name, sector.sector_name FROM cell JOIN sector USING (sector_id) ORDER BY cell_name')->fetchAll();
}
page($role === 'citizen' ? 'Report an issue' : 'Sign in', 'report');
?>
<?php if ($role === ''): ?>
    <!-- Keep sign-in on the same page as the citizen entry point. -->
    <section class="login-grid">
        <div class="hero">
            <p class="eyebrow">CIRES</p>
            <h1>Report and follow up on local issues</h1>
            <p>Send a report to your cell office and see what happens next.</p>
        </div>
        <div class="card">
            <h2>Sign in</h2>
            <?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>
            <form method="post" class="stack">
                <?php tokenField(); ?>
                <label>Username<input name="login" autocomplete="username" maxlength="80" required></label>
                <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
                <button name="action" value="login">Sign in</button>
            </form>
            <details><summary>Demo sign-in details</summary>
                <p>Citizen: <code>citizen</code><br>Cell Executive: <code>cell</code><br>Password: <code>CIRES-demo-2026</code></p>
            </details>
        </div>
    </section>
<?php else: ?>
    <!-- Display the same four report fields as the Figma prototype. -->
    <div class="heading"><p class="eyebrow">Citizen portal</p><h1>Report an issue</h1><p>Tell your cell office what needs attention.</p></div>
    <section class="card form-card">
        <?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <form method="post" class="stack">
            <?php tokenField(); ?>
            <label>Category
                <select name="category" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['category_id'] ?>" <?= $values['category'] === (string) $category['category_id'] ? 'selected' : '' ?>><?= e($category['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Cell
                <select name="cell" required>
                    <option value="">Select a cell</option>
                    <?php foreach ($cells as $cell): ?>
                        <option value="<?= (int) $cell['cell_id'] ?>" <?= $values['cell'] === (string) $cell['cell_id'] ? 'selected' : '' ?>><?= e($cell['cell_name'] . ' · ' . $cell['sector_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Location details<input name="location" value="<?= e($values['location']) ?>" maxlength="180" placeholder="Street or nearby landmark" required></label>
            <label>Describe the issue<textarea name="description" maxlength="1500" rows="4" placeholder="What happened?" required><?= e($values['description']) ?></textarea></label>
            <button name="action" value="report">Submit report</button>
        </form>
    </section>
<?php endif; ?>
<?php endPage(); ?>
