define(['views/record/row-actions/default'], function (DefaultRowActionsView) {
    return class extends DefaultRowActionsView {
        getActionList() {
            const status = this.model.get('status');
            const accountType = this.getUser().get('smAccountType');
            const isAdmin = this.getUser().isAdmin();
            const isAssignedOperator = accountType === 'Operator' &&
                this.model.get('operatorId') === this.getUser().id;
            const isManager = isAdmin || accountType === 'ProducerAdmin';
            const list = [{
                label: 'View',
                data: {id: this.model.id},
                link: `#SmShift/view/${this.model.id}`,
                groupIndex: 0,
                iconClass: 'fas fa-expand',
            }];

            if ((isAssignedOperator || isAdmin) && status === 'Draft' && this.options.acl.edit) {
                list.push({
                    label: 'Start Shift',
                    data: {id: this.model.id},
                    link: `#SmShift/edit/${this.model.id}/start`,
                    groupIndex: 1,
                    iconClass: 'fas fa-play',
                });
            }

            if ((isAssignedOperator || isAdmin) && status === 'Open' && this.options.acl.edit) {
                list.push({
                    action: 'finishShift',
                    label: 'Finish Shift',
                    data: {id: this.model.id},
                    groupIndex: 1,
                    iconClass: 'fas fa-stop',
                });
            }

            if ((isAssignedOperator || isAdmin) && status === 'Counting' && this.options.acl.edit) {
                list.push({
                    label: 'Complete Calculation',
                    data: {id: this.model.id},
                    link: `#SmShift/edit/${this.model.id}/count`,
                    groupIndex: 1,
                    iconClass: 'fas fa-calculator',
                });
            }

            if (isManager && ['Draft', 'Closed'].includes(status) && this.options.acl.edit) {
                list.push({
                    label: 'Edit',
                    data: {id: this.model.id},
                    link: status === 'Closed' ?
                        `#SmShift/edit/${this.model.id}/revise` :
                        `#SmShift/edit/${this.model.id}`,
                    groupIndex: 1,
                    iconClass: 'fas fa-pen-to-square',
                });
            }

            return list;
        }
    };
});
