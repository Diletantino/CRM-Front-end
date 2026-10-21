define(['views/record/detail', 'studio-management:role-policy'], function (DetailRecordView, RolePolicy) {
    return class extends DetailRecordView {
        editModeDisabled = true

        setupActionItems() {
            super.setupActionItems();

            this.buttonList = this.buttonList.filter(item => item.name !== 'edit');

            const status = this.model.get('status');
            const canEdit = this.getAcl().checkModel(this.model, 'edit');
            const canOperate = canEdit && RolePolicy.canOperate(this);
            const isManager = canEdit && (status === 'Draft' ? RolePolicy.canPrepare(this) : RolePolicy.canRevise(this));

            if (canOperate && status === 'Draft') {
                this.buttonList.push({name: 'prepareStart', label: 'Start Shift', style: 'success'});
            }

            if (canOperate && status === 'Open') {
                this.buttonList.push({name: 'finishShift', label: 'Finish Shift', style: 'danger'});
            }

            if (canOperate && status === 'Counting') {
                this.buttonList.push({
                    name: 'continueCalculation',
                    label: 'Complete Calculation',
                    style: 'success',
                });
            }

            if (isManager && ['Draft', 'Closed'].includes(status)) {
                this.buttonList.push({name: 'managerEdit', label: 'Edit', style: 'default'});
            }

            if (!this.getAcl().checkModel(this.model, 'delete')) {
                this.removeActionItem('delete');
            }
        }

        actionPrepareStart() {
            this.navigateToEdit('start');
        }

        async actionFinishShift() {
            await this.confirm(this.translate('finishShiftConfirmation', 'messages', 'SmShift'));
            const response = await Espo.Ajax.postRequest(`SmShift/${this.model.id}/finish`);
            this.model.set(response);
            this.navigateToEdit('count');
        }

        actionContinueCalculation() {
            this.navigateToEdit('count');
        }

        actionManagerEdit() {
            this.navigateToEdit(this.model.get('status') === 'Closed' ? 'revise' : null);
        }

        navigateToEdit(mode) {
            const suffix = mode ? `/${mode}` : '';
            this.getRouter().navigate(`#SmShift/edit/${this.model.id}${suffix}`, {trigger: true});
        }
    };
});
