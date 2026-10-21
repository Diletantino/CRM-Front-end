define(['views/fields/link', 'studio-management:role-policy'], function (LinkFieldView, RolePolicy) {
    return class extends LinkFieldView {
        data() {
            const data = super.data();
            const scope = this.name === 'model' ? 'SmModels' : 'SmOperators';
            const isManager = RolePolicy.canViewStaff(this, scope);
            return {...data, profileLinkAllowed: isManager};
        }

        detailTemplate = 'studio-management:sm-shift/fields/participant'
        listTemplate = 'studio-management:sm-shift/fields/participant'
    };
});
