define(['views/user/record/edit'], function (UserEditView) {
    return class extends UserEditView {
        layoutName = 'studioProfile'

        fetch() {
            const data = super.fetch();

            // EspoCRM removes userName from non-admin User writes before custom
            // input filters run. The server validates and restores this alias only
            // for a role that has real User create/edit access.
            data.smDelegatedUserName = data.userName;

            return data;
        }
    };
});
