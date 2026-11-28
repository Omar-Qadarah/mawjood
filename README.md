# Mawjood Admin Dashboard

Mawjood connects customers with pharmacies that have medicine in stock. This Laravel 12 and React dashboard is the administrative part of the Orange Coding Academy Masterpiece project.

## Run locally

1. Install the Composer and npm dependencies with `composer install` and `npm ci`.
2. Copy `.env.example` to `.env` and run `php artisan key:generate`.
3. Create a MySQL or MariaDB database. Set `DB_CONNECTION=mysql` and the database name, host, port, and credentials in `.env`.
4. Run `php artisan migrate`.
5. Set `ADMIN_NAME`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD` in your local `.env`. Then run these seeders in order.

   ```sh
   php artisan db:seed --class=AdminUserSeeder
   php artisan db:seed --class=DemoMedicineSeeder
   php artisan db:seed --class=DemoInventorySeeder
   ```

6. Run `npm run build` and `php artisan serve`. Open [the admin sign-in](http://127.0.0.1:8000/admin) and use the admin credentials you set above.

Customer signup is available at `/signup`. API clients calling `POST /api/v1/auth/register` must send `password_confirmation` matching `password`.

Admin password reset links expire after 10 minutes. Set `APP_URL` to your browser-facing Mawjood URL. Configure a mail transport to deliver reset links to an inbox. With the local `MAIL_MAILER=log` setting, use Laravel's log to find the test email.

On XAMPP, use `C:\xampp\php\php.exe` in place of `php` if PHP is missing from your PATH.

## Dashboard modules

| Section | Admin actions |
| --- | --- |
| Overview | See pharmacy status, customer and owner counts, recent pharmacy changes, and sites on the Jordan map. |
| Pharmacies | Search and filter, create, view, edit, approve, and delete eligible records. |
| Customers | Search, filter, create, view, edit, and delete eligible customer records. |
| Medicines | Search and manage medicine packages, active ingredients, and strengths. |
| Inventory | Search and filter listings, add a medicine to a pharmacy, edit price or visibility, and delete unused listings. Inspect batches and record stock receipts and adjustments. |
| Reservations | Filter by status, inspect allocations, and confirm, reject, cancel, or complete reservations. |

The React dashboard uses the session-authenticated Laravel APIs in `routes/api.php`. Inventory listings with batch or reservation history can be unlisted. Their relationships preserve the historical records.

## Test on MySQL

Create a dedicated empty test database first. Laravel tests refresh the schema, so use a database reserved for tests.

```powershell
$env:DB_CONNECTION = 'mysql'
$env:DB_DATABASE = 'mawjood_test'
php artisan test
Remove-Item Env:DB_CONNECTION, Env:DB_DATABASE
```

For a short demo, sign in, show the overview, approve a pending pharmacy, search the medicine catalog, and open a pharmacy’s inventory batches. Sign out from the sidebar.
