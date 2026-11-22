define(['views/fields/link', 'studio-management:role-policy'], function (LinkFieldView, RolePolicy) {
    return class extends LinkFieldView {
        data() {
            const data = super.data();
            return {...data, profileLinkAllowed: RolePolicy.level(this, 'smProfileAccess') !== 'no'};
        }

        detailTemplate = 'studio-management:sm-shift/fields/participant'
        listTemplate = 'studio-management:sm-shift/fields/participant'
    };
});
