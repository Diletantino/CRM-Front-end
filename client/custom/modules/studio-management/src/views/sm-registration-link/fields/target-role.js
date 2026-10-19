define(['views/fields/link'], function (LinkFieldView) {
    return class extends LinkFieldView {
        setup() {
            // Load suggestions for an empty input, so the control behaves like a dropdown.
            this.autocompleteOnEmpty = true;

            super.setup();
        }

        afterRender() {
            super.afterRender();

            if (!this.isEditMode()) {
                return;
            }

            // Keep the input focused while its dropdown button is pressed. Otherwise
            // the base link field restores the old value before suggestions arrive.
            this.$el.find('[data-action="selectLink"]')
                .on('mousedown.targetRoleDropdown', event => event.preventDefault());
        }

        actionSelect() {
            if (!this.isEditMode()) {
                return super.actionSelect();
            }

            const wasFocused = this.$elementName.is(':focus');
            const hadValue = Boolean(this.$elementName.val());

            this.$elementName.trigger('focus');
            this.$elementName.val('');

            // Focusing an already empty field performs the lookup itself. If the field
            // was focused or had a value, explicitly reopen the complete suggestion list.
            if (wasFocused || hadValue) {
                this.$elementName.autocomplete('onValueChange');
            }
        }
    };
});
