define(['views/fields/base'], function (BaseFieldView) {
    return class extends BaseFieldView {
        detailTemplate = 'studio-management:sm-shift/fields/workflow-footer/footer'
        editTemplate = 'studio-management:sm-shift/fields/workflow-footer/footer'

        data() {
            const data = super.data();
            const status = this.model.get('status');
            const accountType = this.getUser().get('smAccountType');
            const isAdmin = this.getUser().isAdmin();
            const isAssignedOperator = accountType === 'Operator' &&
                this.model.get('operatorId') === this.getUser().id;
            const isManager = isAdmin || accountType === 'ProducerAdmin';

            return {
                ...data,
                showFinish: this.mode === 'detail' && status === 'Open' &&
                    (isAssignedOperator || isAdmin),
                showComplete: this.mode === 'edit' && status === 'Counting' &&
                    (isAssignedOperator || isAdmin),
                showRevision: this.mode === 'edit' && status === 'Closed' && isManager,
            };
        }
    };
});
