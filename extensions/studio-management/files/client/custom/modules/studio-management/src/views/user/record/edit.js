define(['views/user/record/edit'], function (UserEditView) {
    return class extends UserEditView {
        layoutName = 'studioProfile'

        fetch() {
            const data = super.fetch();
            data.smDelegatedUserName = data.userName;
            return data;
        }
    };
});
