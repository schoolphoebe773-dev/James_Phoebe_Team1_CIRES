# CIRES · Milestone 4 local demo

Citizen Issue Reporting and Escalation System. The demo uses PHP, PDO and MySQL on AMPPS. It has three entry pages and two working journeys: citizen reporting and tracking, and Cell Executive progress and resolution.

## Run it in AMPPS

1. Extract the ZIP. Copy the extracted `cires` folder into AMPPS's `www` directory. Check that `www/cires/index.php` exists.
2. Start Apache and MySQL in AMPPS. Use PHP 7.4 or newer with the `pdo_mysql` extension enabled.
3. Open phpMyAdmin from AMPPS. From its home screen, choose **Import** and select `cires/database/cires.sql`. The script creates the `cires_m4` database and adds the demo accounts. If your MySQL account cannot create databases, use an account with permission to create `cires_m4` and its tables.
4. In the `cires` folder, copy `.env.example` as `.env`. On Windows, check that the result is named exactly `.env` with no `.txt` suffix. Set `DB_USER`, `DB_PASSWORD` and `DB_PORT` to the values for your local MySQL installation. The AMPPS example uses port 4001 and the default `root` / `mysql` credentials; change them if your MySQL configuration differs. The phpMyAdmin login and app settings must both match your local MySQL configuration.
5. Visit [localhost/cires/](http://localhost/cires/). If AMPPS Apache uses another port, include it in the URL. In VS Code, choose **File → Open Folder** and select `www/cires`. Load PHP pages through Apache.

## Demo accounts

| Role | Username | Password |
| --- | --- | --- |
| Citizen | citizen | CIRES-demo-2026 |
| Cell Executive | cell | CIRES-demo-2026 |

These are fictional accounts for the local demo. The sign-in page displays these credentials for demonstration. Enter only fictional issue details. The MySQL password is independent of these demo login passwords.

## Demonstrate both journeys with one report

1. Sign in as `citizen`. Select **Drainage** and **Demo Cell**. Enter **Near the market** as the location and **The roadside drain is blocked** as the description. Select **Submit report**. The tracking page shows **Received** and a unique reference. Save the reference.
2. Sign out and sign in as `cell`. Open that reference in the queue. Select **Mark in progress**. Then enter **The drain was cleared and water is flowing again** as the completion note and select **Resolve issue**.
3. Sign back in as `citizen`. Open **Track my issue**, enter the same reference and check for **Received → In Progress → Resolved** and the final reply. Reload the page to confirm the history is still present.

## Read the code in VS Code

| File | Purpose |
| --- | --- |
| `index.php` | Checks sign-in and saves the citizen report and its initial status together. |
| `track.php` | Finds a report owned by the signed-in citizen and shows its history. |
| `office.php` | Lists reports for the executive's cell and records authorized status changes. |
| `app/bootstrap.php` | Shares the PDO connection, sessions, form protection and page layout. |
| `database/cires.sql` | Creates the seven tables from the M3 ER diagram and seeds the local demo. |
| `assets/style.css` | Styles the responsive forms, navigation, status labels and queue. |

The three PHP pages handle both the UI and server-side requests. The current status comes from the last row in `status_update`. The schema includes the sector, follow-up deadline and escalation relationships. The two-journey demo stops at cell-level resolution. A new issue receives a three-day example deadline for future escalation work.
