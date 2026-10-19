define(['views/fields/link'], function (LinkFieldView) {
    return class extends LinkFieldView {
        setup() {
            super.setup();

            this.listenTo(this.model, 'change:accountType', () => {
                // Never carry a role (and its permissions) into another account type.
                if (!this.model.isNew()) {
                    return;
                }

                this.model.set({targetRoleId: null, targetRoleName: null});
            });
        }

        getSelectFilters() {
            if (!this.isEditMode()) {
                return super.getSelectFilters();
            }

            return {
                ...super.getSelectFilters(),
                smAccountType: {
                    type: 'in',
                    attribute: 'smAccountType',
                    value: [this.model.get('accountType')],
                    data: {type: 'anyOf', valueList: [this.model.get('accountType')]},
                },
            };
        }

        getCreateAttributes() {
            return {
                ...super.getCreateAttributes(),
                smAccountType: this.model.get('accountType'),
            };
        }
    };
});
