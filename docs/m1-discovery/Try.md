# CIRES Milestone 4 — Local Testing

## Setup

Start Apache and MySQL in AMPPS, then open http://localhost/cires/.

## Test the citizen and Cell Executive workflows

1. Sign in as a Citizen and submit a test report. Save its reference number.
2. Look up that reference and verify that its status is Received.
3. Sign out and sign in as the Cell Executive. Open the same report in the queue.
4. Mark the report In Progress, then resolve it with a completion note.
5. Sign back in as the Citizen and look up the same reference.
6. Verify that the status is Resolved and the completion note is visible.
