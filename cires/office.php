<?php
// Check that the signed-in user is a Cell Executive.
require __DIR__ . '/app/bootstrap.php';
// Get the executive account and its assigned cell.
$executive = requireRole('cell_executive');

// Define which status can follow each current status.
$nextStatus = ['Received' => 'In Progress', 'In Progress' => 'Resolved'];
// Start with an empty error message.
$error = '';

// Save a status change from an authorized executive.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify the submitted form token.
    checkToken();
    // Read the report identifier.
    $issueId = field($_POST, 'id');
    // Read the new status requested by the button.
    $requested = field($_POST, 'status');
    // Read the completion note, when provided.
    $note = field($_POST, 'note');
    // Use one connection for the locked read and insert.
    $database = db();
    // Start a transaction to serialize updates to this report.
    $database->beginTransaction();
    try {
        // Lock this cell's report while checking and recording its next status.
        $owned = ctype_digit($issueId) && query('SELECT issue_id FROM issue WHERE issue_id = ? AND cell_id = ? FOR UPDATE',
            [$issueId, $executive['cell_id']])->fetchColumn();
        // Find the last status only if the report belongs to this cell.
        $current = $owned ? query('SELECT status FROM status_update WHERE issue_id = ? ORDER BY update_id DESC LIMIT 1', [$issueId])->fetchColumn() : '';
        if (!$owned || $requested === '' || ($nextStatus[$current] ?? '') !== $requested) {
            $error = 'This update is no longer available. Open the report and check its current status.';
        } elseif ($requested === 'Resolved' && ($note === '' || textLength($note) > 800)) {
            $error = 'Enter a short completion note before resolving the issue.';
        } else {
            // Append the new state and store a reply only at resolution.
            query('INSERT INTO status_update (issue_id, updated_by, status, completion_note) VALUES (?, ?, ?, ?)',
                [$issueId, $executive['user_id'], $requested, $requested === 'Resolved' ? $note : null]);
            // Save the appended status and release the lock.
            $database->commit();
            // Save a one-time confirmation for the page reload.
            $_SESSION['notice'] = 'Status updated to ' . $requested . '.';
            // Reload the same report through a GET request.
            go('office.php?id=' . rawurlencode($issueId));
        }
        // Release the lock without saving an invalid change.
        $database->rollBack();
    } catch (PDOException $exception) {
        $database->rollBack();
        $error = 'We couldn’t save that update. Please try again.';
    }
}

// List only the reports assigned to this executive's cell.
$reports = query('SELECT issue.issue_id, issue.reference_code, issue.location_details, issue.description, category.category_name,
    (SELECT status FROM status_update WHERE issue_id = issue.issue_id ORDER BY update_id DESC LIMIT 1) AS current_status
    FROM issue JOIN category USING (category_id) WHERE issue.cell_id = ? ORDER BY issue.issue_id DESC',
    [$executive['cell_id']])->fetchAll();

// Find the selected report within this already restricted queue.
$selected = null;
// Read the report selected in the queue.
$id = field($_GET, 'id');
// Search the cell queue for that report.
foreach ($reports as $report) {
    if ((string) $report['issue_id'] === $id) {
        // Use this row for the details panel.
        $selected = $report;
        // Stop after finding the selected row.
        break;
    }
}
// Choose the next action to display, if one is available.
$next = $selected ? ($nextStatus[$selected['current_status']] ?? '') : '';
// Print the shared header with Cell queue selected.
page('Cell queue', 'office');
?>
<div class="heading"><p class="eyebrow">Cell Executive</p><h1>Cell issue queue</h1><p>Open a report to record progress or send a final reply.</p></div>
<?php notice(); ?>
<?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>
<div class="office-grid">
    <!-- Keep the queue visible beside the report on wider screens. -->
    <section class="card">
        <h2>Reports <span class="count"><?= count($reports) ?></span></h2>
        <?php if (!$reports): ?><p>No reports yet. New citizen reports will appear here.</p><?php endif; ?>
        <?php foreach ($reports as $report): ?>
            <a class="queue-item" href="office.php?id=<?= (int) $report['issue_id'] ?>#report" <?= $id === (string) $report['issue_id'] ? 'aria-current="true"' : '' ?>>
                <strong><?= e($report['reference_code']) ?></strong>
                <span><?= e($report['category_name']) ?></span>
                <?= statusBadge($report['current_status']) ?>
            </a>
        <?php endforeach; ?>
    </section>
    <section class="card" id="report">
        <?php if ($selected): ?>
            <div class="row"><h2><?= e($selected['reference_code']) ?></h2><?= statusBadge($selected['current_status']) ?></div>
            <p><strong>Location</strong><br><?= e($selected['location_details']) ?></p>
            <p class="reply"><?= e($selected['description']) ?></p>
            <?php if ($next): ?>
                <!-- Offer the next valid action and require a reply at resolution. -->
                <form method="post" class="stack">
                    <?php tokenField(); ?>
                    <input type="hidden" name="id" value="<?= (int) $selected['issue_id'] ?>">
                    <?php if ($next === 'Resolved'): ?>
                        <label>Completion note<textarea name="note" rows="3" maxlength="800" placeholder="Tell the citizen what was done." required><?= e($error ? field($_POST, 'note') : '') ?></textarea></label>
                    <?php endif; ?>
                    <button name="status" value="<?= e($next) ?>"><?= $next === 'Resolved' ? 'Resolve issue' : 'Mark in progress' ?></button>
                </form>
            <?php else: ?>
                <p class="notice">Resolved. The citizen can see the completion note on the tracking page.</p>
            <?php endif; ?>
        <?php else: ?>
            <h2>Report details</h2><p>Select a report from the queue to see its details.</p>
        <?php endif; ?>
    </section>
</div>
<?php endPage(); ?>
