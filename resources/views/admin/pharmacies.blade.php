<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pharmacy administration · Mawjood</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <script src="{{ asset('js/admin.js') }}" defer></script>
</head>
<body class="admin-body">
    <main id="admin-app" class="admin-shell" data-api-base="{{ url('/api/v1') }}">
        <header class="admin-header">
            <div>
                <p class="admin-eyebrow">MAWJOOD · ADMIN</p>
                <h1>Pharmacy management</h1>
                <p class="admin-subtitle">Review and manage pharmacies in the directory.</p>
            </div>
            <button id="logout-button" class="button button-secondary" type="button" hidden>Log out</button>
        </header>

        <p id="notice" class="notice" role="status" aria-live="polite" hidden></p>

        <section id="login-panel" class="panel login-panel" hidden>
            <h2>Administrator sign in</h2>
            <p>Use your Mawjood administrator account to continue.</p>
            <form id="login-form" class="form-grid login-form">
                <label>Email
                    <input name="email" type="email" autocomplete="username" required>
                </label>
                <label>Password
                    <input name="password" type="password" autocomplete="current-password" required>
                </label>
                <button class="button button-primary" type="submit">Sign in</button>
            </form>
        </section>

        <section id="pharmacy-panel" hidden>
            <div class="toolbar">
                <label class="search-field">Search
                    <input id="search-input" type="search" placeholder="Name, license, or city">
                </label>
                <label>Status
                    <select id="status-filter">
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </label>
                <button id="create-button" class="button button-primary" type="button">Add pharmacy</button>
            </div>

            <div class="panel table-panel">
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Pharmacy</th>
                                <th>License</th>
                                <th>City</th>
                                <th>Status</th>
                                <th>Members</th>
                                <th><span class="visually-hidden">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody id="pharmacy-rows"></tbody>
                    </table>
                </div>
                <div class="pagination">
                    <button id="previous-page" class="button button-secondary" type="button">Previous</button>
                    <span id="page-label"></span>
                    <button id="next-page" class="button button-secondary" type="button">Next</button>
                </div>
            </div>
        </section>

        <dialog id="pharmacy-dialog" class="pharmacy-dialog">
            <form id="pharmacy-form" class="form-grid">
                <div class="dialog-heading">
                    <div>
                        <p class="admin-eyebrow">PHARMACY RECORD</p>
                        <h2 id="form-title">Add pharmacy</h2>
                    </div>
                    <button id="close-dialog" class="icon-button" type="button" aria-label="Close">×</button>
                </div>
                <input name="id" type="hidden">
                <label>Arabic name<input name="name_ar" maxlength="150" required></label>
                <label>English name<input name="name_en" maxlength="150" required></label>
                <label>License number<input name="license_no" maxlength="100" required></label>
                <label>Phone<input name="phone" maxlength="20"></label>
                <label>Email<input name="email" type="email" maxlength="190"></label>
                <label>City<input name="city" maxlength="100" required></label>
                <label class="field-wide">Address<input name="address" maxlength="255" required></label>
                <label>Latitude<input name="latitude" type="number" min="29" max="33.5" step="any" required></label>
                <label>Longitude<input name="longitude" type="number" min="34.9" max="39.4" step="any" required></label>
                <label>Status
                    <select name="status">
                        <option value="pending">Pending</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </label>
                <label class="field-wide">Opening hours (JSON)
                    <textarea name="opening_hours" rows="3" placeholder='{"sunday":"09:00-17:00"}'></textarea>
                </label>
                <div class="dialog-actions field-wide">
                    <button class="button button-secondary" id="cancel-dialog" type="button">Cancel</button>
                    <button class="button button-primary" id="save-pharmacy" type="submit">Save pharmacy</button>
                </div>
            </form>
        </dialog>
    </main>
</body>
</html>

