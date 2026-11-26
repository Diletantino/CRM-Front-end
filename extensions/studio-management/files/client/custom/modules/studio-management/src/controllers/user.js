define(['controllers/user', 'studio-management:role-policy', 'exceptions'], function (UserController, RolePolicy, Exceptions) {
    return class extends UserController {
        actionView(options) {
            const level = RolePolicy.level(this, 'smProfileAccess');
            if (level === 'no') {
                throw new Exceptions.AccessDenied();
            }
            if (level !== 'all') {
                this.main('studio-management:views/user/simple-profile', {profileId: options.id});
                return;
            }
            return super.actionView(options);
        }

        actionEdit(options) {
            if (RolePolicy.level(this, 'smProfileAccess') !== 'all') {
                throw new Exceptions.AccessDenied();
            }
            return super.actionEdit(options);
        }
    };
});
