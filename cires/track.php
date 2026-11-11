<?php
// Load the shared functions and restrict tracking to signed-in citizens.
require __DIR__ . '/app/bootstrap.php';
// Get the signed-in citizen or redirect to sign-in.
$citizen = requireRole('citizen');

// Look up the entered reference only among this citizen's reports.
$reference = strtoupper(field($_GET, 'ref'));
// Assume there is no matching report until the query runs.
$issue = false;
// Start with an empty status history.
$updates = [];
// Query only after a reference has been entered.
if ($reference !== '') {
    $issue = query('SELECT issue.issue_id, issue.reference_code, issue.location_details, cell.cell_name, category.category_name
        FROM issue JOIN cell USING (cell_id) JOIN category USING (category_id)
        WHERE issue.reference_code = ? AND issue.citizen_id = ?', [$reference, $citizen['user_id']])->fetch();
    if ($issue) {
        // Read updates in the same order they were recorded.
        $updates = query('SELECT status, updated_at, completion_note FROM status_update WHERE issue_id = ? ORDER BY update_id',
            [$issue['issue_id']])->fetchAll();
    }
}
// Derive the current state and final reply from the last update.
$latest = $updates ? $updates[count($updates) - 1] : null;
// Print the shared header with Tracking selected.
page('Track my issue', 'track');
?>
<div class="heading"><p class="eyebrow">Citizen portal</p><h1>Track my issue</h1><p>Check the progress and read the cell office’s reply.</p></div>
<?php notice(); ?>
<!-- A reference can be entered again after signing back in. -->
<section class="card form-card">
    <form class="lookup" method="get">
        <label>Reference number<input name="ref" maxlength="24" value="<?= e($reference) ?>" placeholder="CIRES-…" required></label>
        <button>Check status</button>
    </form>
    <?php if ($reference !== '' && !$issue): ?>
        <p class="error" role="alert">We couldn’t find that report in your account. Check the reference number and try again.</p>
    <?php endif; ?>
</section>
<?php if ($issue && $latest): ?>
    <!-- Show the current state, history and completion note together. -->
    <section class="card form-card">
        <div class="row"><h2><?= e($issue['reference_code']) ?></h2><?= statusBadge($latest['status']) ?></div>
        <p><?= e($issue['category_name'] . ' · ' . $issue['location_details']) ?></p>
        <p>Responsible office <strong><?= e($issue['cell_name']) ?></strong></p>
        <h3>Progress history</h3>
        <ol class="timeline">
            <?php foreach ($updates as $update): ?>
                <li><strong><?= e($update['status']) ?></strong><time><?= e($update['updated_at']) ?></time></li>
            <?php endforeach; ?>
        </ol>
        <h3>Final reply</h3>
        <p class="reply"><?= e($latest['completion_note'] ?? 'The cell office will post its reply here when the issue is resolved.') ?></p>
        <a class="secondary" href="index.php">Report another issue</a>
    </section>
<?php endif; ?>
<?php endPage(); ?>
