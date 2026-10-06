define(['views/record/detail'], function (DetailRecordView) {
    return class extends DetailRecordView {
        editModeDisabled = true

        setupActionItems() {
            super.setupActionItems();

            this.buttonList = this.buttonList.filter(item => item.name !== 'edit');

            const status = this.model.get('status');
            const accountType = this.getUser().get('smAccountType');
            const isAdmin = this.getUser().isAdmin();
            const isAssignedOperator = accountType === 'Operator' &&
                this.model.get('operatorId') === this.getUser().id;
            const isManager = isAdmin || accountType === 'ProducerAdmin';

            if ((isAssignedOperator || isAdmin) && status === 'Draft') {
                this.buttonList.push({name: 'prepareStart', label: 'Start Shift', style: 'success'});
            }

            if ((isAssignedOperator || isAdmin) && status === 'Open') {
                this.buttonList.push({name: 'finishShift', label: 'Finish Shift', style: 'danger'});
            }

            if ((isAssignedOperator || isAdmin) && status === 'Counting') {
                this.buttonList.push({
                    name: 'continueCalculation',
                    label: 'Complete Calculation',
                    style: 'success',
                });
            }

            if (isManager && ['Draft', 'Closed'].includes(status)) {
                this.buttonList.push({name: 'managerEdit', label: 'Edit', style: 'default'});
            }

            if (!isManager) {
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
