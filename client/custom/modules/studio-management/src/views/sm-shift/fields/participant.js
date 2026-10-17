define(['views/fields/link'], function (LinkFieldView) {
    return class extends LinkFieldView {
        data() {
            const data = super.data();
            const isManager = this.getUser().isAdmin() || this.getUser().get('smAccountType') === 'ProducerAdmin';
            return {...data, profileLinkAllowed: isManager};
        }

        detailTemplate = 'studio-management:sm-shift/fields/participant'
        listTemplate = 'studio-management:sm-shift/fields/participant'
    };
});
