define(['views/fields/base', 'studio-management:role-policy'], function (BaseFieldView, RolePolicy) {
    return class extends BaseFieldView {
        listTemplate = 'studio-management:sm-shift/fields/workflow-actions/list'
        detailTemplate = 'studio-management:sm-shift/fields/workflow-actions/list'

        data() {
            const data = super.data();
            const status = this.model.get('status');
            const accountType = this.getUser().get('smAccountType');
            const canOperate = RolePolicy.canOperate(this);
            const isManager = status === 'Draft' ? RolePolicy.canPrepare(this) :
                status === 'Review' ? RolePolicy.canReview(this) : RolePolicy.canRevise(this);
            const canEdit = this.getAcl().checkModel(this.model, 'edit');

            return {
                ...data,
                id: this.model.id,
                showStart: canEdit && canOperate && status === 'Draft',
                showFinish: canEdit && canOperate && status === 'Open',
                showCalculation: canEdit && canOperate && status === 'Counting',
                showEdit: canEdit && isManager && ['Draft', 'Review', 'Closed'].includes(status),
                editMode: status === 'Closed' ? 'revise' : status === 'Review' ? 'review' : '',
                operatorCompleted: accountType === 'Operator' && ['Review', 'Closed'].includes(status) && !isManager,
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
