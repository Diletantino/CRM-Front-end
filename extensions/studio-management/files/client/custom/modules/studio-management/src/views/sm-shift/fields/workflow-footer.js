define(['views/fields/base'], function (BaseFieldView) {
    return class extends BaseFieldView {
        detailTemplate = 'studio-management:sm-shift/fields/workflow-footer/footer'
        editTemplate = 'studio-management:sm-shift/fields/workflow-footer/footer'

        setup() {
            super.setup();
            this.addHandler('click', '[data-action="workflowSave"]', event => this.runWorkflow(event, 'actionSave'));
            this.addHandler('click', '[data-action="workflowFinish"]', event => this.runWorkflow(event, 'actionFinishShift'));
        }

        async runWorkflow(event, method) {
            event.preventDefault();
            event.stopPropagation();
            let record = this.getParentView();
            while (record && typeof record[method] !== 'function') {
                record = record.getParentView();
            }
            if (!record) {
                return;
            }
            const button = event.currentTarget;
            button.disabled = true;
            try {
                await record[method]();
            } catch (error) {
                // The record view/API displays validation and request errors.
            } finally {
                button.disabled = false;
            }
        }

        data() {
            const data = super.data();
            const status = this.model.get('status');
            const accountType = this.getUser().get('smAccountType');
            const isAdmin = this.getUser().isAdmin();
            const isAssignedOperator = ['Operator', 'ProducerAdmin'].includes(accountType) &&
                this.model.get('operatorId') === this.getUser().id;
            const isManager = isAdmin || accountType === 'ProducerAdmin';

            return {
                ...data,
                showStart: this.mode === 'edit' && !this.model.isNew() && status === 'Draft' &&
                    this.model.studioWorkflowMode === 'start' &&
                    (isAssignedOperator || isAdmin),
                showFinish: this.mode === 'detail' && status === 'Open' &&
                    (isAssignedOperator || isAdmin),
                showComplete: this.mode === 'edit' && status === 'Counting' &&
                    (isAssignedOperator || isAdmin),
                showRevision: this.mode === 'edit' && status === 'Closed' && isManager,
            };
        }
    };
});
