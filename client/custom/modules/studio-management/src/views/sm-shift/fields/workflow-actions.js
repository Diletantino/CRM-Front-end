define(['views/fields/base'], function (BaseFieldView) {
    return class extends BaseFieldView {
        listTemplate = 'studio-management:sm-shift/fields/workflow-actions/list'
        detailTemplate = 'studio-management:sm-shift/fields/workflow-actions/list'

        data() {
            const data = super.data();
            const status = this.model.get('status');
            const accountType = this.getUser().get('smAccountType');
            const isAdmin = this.getUser().isAdmin();
            const isAssignedOperator = accountType === 'Operator' &&
                this.model.get('operatorId') === this.getUser().id;
            const isManager = isAdmin || accountType === 'ProducerAdmin';
            const canEdit = this.getAcl().checkModel(this.model, 'edit');

            return {
                ...data,
                id: this.model.id,
                showStart: canEdit && (isAssignedOperator || isAdmin) && status === 'Draft',
                showFinish: canEdit && (isAssignedOperator || isAdmin) && status === 'Open',
                showCalculation: canEdit && (isAssignedOperator || isAdmin) && status === 'Counting',
                showEdit: canEdit && isManager && ['Draft', 'Closed'].includes(status),
                editMode: status === 'Closed' ? 'revise' : '',
                operatorCompleted: accountType === 'Operator' && status === 'Closed',
            };
        }

        afterRender() {
            super.afterRender();

            this.$el.off('.smWorkflow');
            this.$el.on('click.smWorkflow', '[data-action="finish-shift"]', () => this.finishShift());
        }

        async finishShift() {
            await this.confirm(this.translate('finishShiftConfirmation', 'messages', 'SmShift'));
            const response = await Espo.Ajax.postRequest(`SmShift/${this.model.id}/finish`);
            this.model.set(response);
            this.getRouter().navigate(`#SmShift/edit/${this.model.id}/count`, {trigger: true});
        }
    };
});
