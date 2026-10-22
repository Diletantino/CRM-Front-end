define(['controllers/team'], function (TeamController) {
    return class extends TeamController {
        checkAccess(action) {
            // Native TeamController hard-codes create/edit to admin accounts.
            // Match the server's delegated CRUD matrix; per-record ACL is still
            // checked by the standard record views and API.
            return this.getUser().isAdmin() || this.getAcl().checkScope('Team', action);
        }
    };
});
