define(['controllers/user', 'studio-management:role-policy'], function (UserController, RolePolicy) {
    return class extends UserController {
        actionView(options) {
            const level = RolePolicy.level(this, 'smProfileAccess');
            if (level === 'no') {
                this.getRouter().dispatch('Error', 'forbidden');
                return;
            }
            if (level !== 'all') {
                this.main('studio-management:views/user/simple-profile', {profileId: options.id});
                return;
            }
            return super.actionView(options);
        }

        actionEdit(options) {
            if (RolePolicy.level(this, 'smProfileAccess') !== 'all') {
                this.getRouter().dispatch('Error', 'forbidden');
                return;
            }
            return super.actionEdit(options);
        }
    };
});
