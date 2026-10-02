define(['view'], function (View) {
    return class extends View {
        template = 'studio-management:registration'

        data() {
            return {
                notFound: this.options.notFound,
                accountType: this.options.invitation?.accountType || '',
                roleName: this.options.invitation?.roleName || '',
                expiresAt: this.options.invitation?.expiresAt || '',
            };
        }

        setup() {
            this.addHandler('click', '[data-action="register"]', () => this.submit());
            this.addHandler('keydown', 'input', event => {
                if (event.key === 'Enter') {
                    this.submit();
                }
            });
        }

        async submit() {
            const fullName = this.$el.find('[name="fullName"]').val().trim();
            const email = this.$el.find('[name="email"]').val().trim();
            const password = this.$el.find('[name="password"]').val();
            const passwordConfirm = this.$el.find('[name="passwordConfirm"]').val();
            const $message = this.$el.find('.registration-message');

            $message.removeClass('text-success').addClass('text-danger').text('');

            if (!fullName || !email || !password) {
                $message.text(this.translate('allFieldsRequired', 'messages', 'SmRegistrationLink'));
                return;
            }

            if (password !== passwordConfirm) {
                $message.text(this.translate('passwordsDoNotMatch', 'messages', 'SmRegistrationLink'));
                return;
            }

            const $button = this.$el.find('[data-action="register"]').prop('disabled', true);

            try {
                await Espo.Ajax.postRequest('StudioManagement/register', {
                    token: this.options.token,
                    fullName,
                    email,
                    password,
                });

                this.$el.find('.registration-form').addClass('hidden');
                $message
                    .removeClass('text-danger')
                    .addClass('text-success')
                    .text(this.translate('registrationComplete', 'messages', 'SmRegistrationLink'));
                this.$el.find('.login-link').removeClass('hidden');
            } catch (e) {
                $message.text(
                    e?.xhr?.getResponseHeader('X-Status-Reason') ||
                    this.translate('registrationFailed', 'messages', 'SmRegistrationLink')
                );
                $button.prop('disabled', false);
            }
        }
    };
});
