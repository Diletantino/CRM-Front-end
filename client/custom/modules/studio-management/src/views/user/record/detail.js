define(['views/user/record/detail', 'studio-management:role-policy'], function (UserDetailView, RolePolicy) {
    return class extends UserDetailView {
        layoutName = 'studioProfile'

        setup() {
            if (RolePolicy.level(this, 'smProfileAccess') !== 'all') {
                this.layoutName = 'studioSimpleProfile';
                this.sideDisabled = true;
                this.bottomDisabled = true;
                this.editDisabled = true;
                this.deleteDisabled = true;
            }
            super.setup();
        }
    };
});
