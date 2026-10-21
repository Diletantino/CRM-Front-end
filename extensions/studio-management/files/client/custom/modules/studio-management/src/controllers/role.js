define(['controllers/role'], function (RoleController) {
    return class extends RoleController {
        checkAccess() {
            return this.getUser().isAdmin() || this.getAcl().checkScope('SmRoleAdministration');
        }
    };
});
