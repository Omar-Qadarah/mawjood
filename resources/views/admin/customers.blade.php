<section id="customers" aria-labelledby="customers-heading" hidden>
    <h2 id="customers-heading">Customers</h2>
    <div class="toolbar">
        <label class="search-field">Search<input id="customer-search" type="search" placeholder="Name or email"></label>
        <label>Status
            <select id="customer-status">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </label>
        <button id="add-customer" class="button button-primary" type="button">Add customer</button>
    </div>
    <div class="panel table-panel">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Status</th><th>Memberships</th><th>Actions</th></tr>
                </thead>
                <tbody id="customer-rows"></tbody>
            </table>
        </div>
        <div class="pagination">
            <button id="customer-previous" class="button button-secondary" type="button">Previous</button>
            <span id="customer-page"></span>
            <button id="customer-next" class="button button-secondary" type="button">Next</button>
        </div>
    </div>

    <dialog id="customer-dialog" class="pharmacy-dialog" aria-labelledby="customer-title">
        <form id="customer-form" class="form-grid">
            <h2 class="field-wide" id="customer-title">Add customer</h2>
            <p id="customer-error" class="field-wide notice notice-error" role="alert" hidden></p>
            <label>Name<input name="name" maxlength="150" required></label>
            <label>Email<input name="email" type="email" maxlength="190" required></label>
            <label class="field-wide">Password<input name="password" type="password" minlength="10" autocomplete="new-password"></label>
            <label class="check-label"><input name="is_active" type="checkbox" checked> Active account</label>
            <div class="dialog-actions field-wide">
                <button id="cancel-customer" class="button button-secondary" type="button">Cancel</button>
                <button class="button button-primary" type="submit">Save customer</button>
            </div>
        </form>
    </dialog>
    <dialog id="customer-details" class="pharmacy-dialog" aria-labelledby="customer-details-title">
        <h2 id="customer-details-title"></h2>
        <dl id="customer-details-fields" class="details-list"></dl>
        <form method="dialog"><button class="button button-secondary">Close</button></form>
    </dialog>
</section>
