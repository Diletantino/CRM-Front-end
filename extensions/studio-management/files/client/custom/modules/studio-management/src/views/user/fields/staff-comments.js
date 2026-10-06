define(['views/fields/base'], function (BaseFieldView) {
    return class extends BaseFieldView {
        detailTemplate = 'studio-management:user/fields/staff-comments/detail'
        editTemplate = 'studio-management:user/fields/staff-comments/detail'

        setup() {
            super.setup();
            this.addHandler('click', '[data-action="addComment"]', () => this.addComment());
        }

        afterRender() {
            if (!this.getStaffType()) {
                this.$el.empty();
                return;
            }

            this.renderComments(this.model.get('smProfileComments') || []);
        }

        getStaffType() {
            if (this.model.get('smAccountType') === 'Model') {
                return 'model';
            }

            if (this.model.get('smAccountType') === 'Operator') {
                return 'operator';
            }

            return null;
        }

        renderComments(comments) {
            const $list = this.$el.find('.staff-comments-list').empty();

            if (!Array.isArray(comments) || !comments.length) {
                $('<p>')
                    .addClass('text-muted')
                    .text(this.translate('noStaffComments', 'messages', 'User'))
                    .appendTo($list);

                return;
            }

            [...comments].reverse().forEach(comment => {
                const $item = $('<div>')
                    .addClass('well well-sm')
                    .css({marginBottom: '8px', whiteSpace: 'pre-wrap'});
                $('<div>').text(comment.text || '').appendTo($item);
                $('<small>')
                    .addClass('text-muted')
                    .text(`${comment.createdByName || '—'} · ${this.formatDateTime(comment.createdAt)}`)
                    .appendTo($item);
                $item.appendTo($list);
            });
        }

        formatDateTime(value) {
            return value ? this.getDateTime().toDisplay(value) : '—';
        }

        async addComment() {
            const staffType = this.getStaffType();
            const $input = this.$el.find('[name="staffComment"]');
            const text = String($input.val() || '').trim();

            if (!staffType || !this.model.id) {
                this.$el.find('.staff-comment-status')
                    .text(this.translate('staffProfileUnavailable', 'messages', 'User'));
                return;
            }

            if (!text) {
                this.$el.find('.staff-comment-status')
                    .text(this.translate('commentRequired', 'messages', 'User'));
                return;
            }

            const $button = this.$el.find('[data-action="addComment"]').prop('disabled', true);
            const $status = this.$el.find('.staff-comment-status').text('');

            try {
                const response = await Espo.Ajax.postRequest(
                    `StudioManagement/staff/${staffType}/${encodeURIComponent(this.model.id)}/comment`,
                    {text}
                );
                this.model.set('smProfileComments', response.comments || [], {silent: true});
                $input.val('');
                this.renderComments(response.comments || []);
            } catch (error) {
                $status.text(
                    error?.xhr?.getResponseHeader('X-Status-Reason') ||
                    this.translate('commentSaveFailed', 'messages', 'User')
                );
            } finally {
                $button.prop('disabled', false);
            }
        }
    };
});
