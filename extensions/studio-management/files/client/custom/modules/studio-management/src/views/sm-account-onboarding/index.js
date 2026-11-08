define(['view'], function (View) {
    const endpoint = 'StudioManagement/onboarding';
    const fieldTypes = {
        smSharePercentOverride: 'number',
        smOperatorSharePercentOverride: 'number',
        smProducerSharePercentOverride: 'number',
        smTechnicalDirectorSharePercentOverride: 'number',
        smBirthDate: 'date',
        smStartDate: 'date',
        smContact: 'text',
    };

    return class extends View {
        template = 'studio-management:sm-account-onboarding/index'

        setup() {
            this.offset = 0;
            this.list = [];
            this.addHandler('click', '[data-onboarding-open]', (event, target) => this.run(async () => {
                this.profile = await Espo.Ajax.getRequest(endpoint + '/' + target.dataset.onboardingOpen);
                await this.reRender();
            }));
            this.addHandler('click', '[data-onboarding-back]', () => this.run(() => this.loadList(this.offset)));
            this.addHandler('click', '[data-onboarding-page]', (event, target) => this.run(() =>
                this.loadList(Math.max(0, this.offset + Number(target.dataset.onboardingPage) * this.pageSize))));
            this.addHandler('submit', 'form', event => {
                event.preventDefault();
                this.save(false);
            });
            this.addHandler('click', '[data-onboarding-complete]', async () => {
                if (!this.$el.find('form')[0].reportValidity()) return;
                try { await this.confirm(this.message('confirmComplete')); } catch { return; }
                await this.save(true);
            });
            this.addHandler('change', '[name="profilePhoto"]', (event, target) => this.upload(target.files[0]));
            this.addHandler('click', '[data-onboarding-preview]', (event, target) => this.run(async () => {
                const photo = await Espo.Ajax.getRequest(this.profileUrl() + '/photos/' + target.dataset.onboardingPreview);
                this.$el.find('[data-photo-preview]').attr('src', photo.dataUrl).show();
            }));
            this.addHandler('click', '[data-onboarding-remove]', async (event, target) => {
                try { await this.confirm(this.message('confirmRemovePhoto')); } catch { return; }
                await this.run(async () => {
                    const draft = this.readForm();
                    const profile = await Espo.Ajax.deleteRequest(this.profileUrl() + '/photos/' + target.dataset.onboardingRemove);
                    this.profile = {...profile, ...draft};
                    await this.reRender();
                });
            });
        }

        afterRender() {
            if (this.started) return;
            this.started = true;
            this.run(() => this.loadList(0));
        }

        message(key) {
            return this.translate(key, 'messages', 'SmAccountOnboarding');
        }

        data() {
            const accountType = value => value ? this.getLanguage().translateOption(value, 'smAccountType', 'User') : '—';
            const fields = Object.entries(fieldTypes).map(([name, type]) => ({
                name, type, numeric: type === 'number', value: this.profile?.[name] ?? '',
                label: this.translate(name, 'fields', 'User'),
            }));
            return {
                profile: this.profile ? {...this.profile, typeLabel: accountType(this.profile.accountType)} : null,
                fields,
                list: this.list.map(account => ({
                    ...account, typeLabel: accountType(account.accountType),
                    receivedAt: account.createdAt ? this.getDateTime().toDisplay(account.createdAt) : '—',
                })),
                hasPrevious: this.offset > 0,
                hasMore: this.hasMore,
            };
        }

        async loadList(offset) {
            const result = await Espo.Ajax.getRequest(endpoint, {offset});
            this.offset = offset;
            this.list = result.list;
            this.hasMore = result.hasMore;
            this.pageSize = result.pageSize;
            this.profile = null;
            await this.reRender();
        }

        profileUrl() {
            return endpoint + '/' + this.profile.id;
        }

        readForm() {
            const fields = [...Object.keys(fieldTypes), 'smProfileBio'];
            return Object.fromEntries(fields.map(name => [name, this.$el.find(`[name="${name}"]`).val()]));
        }

        save(complete) {
            return this.run(async () => {
                const data = this.readForm();
                if (complete) {
                    const {id} = await Espo.Ajax.postRequest(this.profileUrl() + '/complete', data);
                    this.list = this.list.filter(account => account.id !== id);
                    this.profile = null;
                    await this.reRender();
                    Espo.Ui.success(this.message('accountCompleted'));
                    return;
                }
                this.profile = await Espo.Ajax.putRequest(this.profileUrl(), data);
                await this.reRender();
                Espo.Ui.success(this.message('profileSaved'));
            });
        }

        upload(file) {
            if (!file) return;
            return this.run(async () => {
                if (file.size > 5 * 1024 * 1024) {
                    throw new Error(this.message('photoTooLarge'));
                }
                const draft = this.readForm();
                const content = await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result.split(',', 2)[1]);
                    reader.onerror = () => reject(reader.error);
                    reader.readAsDataURL(file);
                });
                const profile = await Espo.Ajax.postRequest(this.profileUrl() + '/photos', {name: file.name, content});
                this.profile = {...profile, ...draft};
                await this.reRender();
            });
        }

        async run(task) {
            if (this.busy) return;
            this.busy = true;
            this.$el.find('button, input, textarea').prop('disabled', true);
            this.$el.find('[data-feedback]').text('');
            try {
                await task();
            } catch (error) {
                this.$el.find('[data-feedback]').text(
                    error?.xhr?.getResponseHeader('X-Status-Reason') || error?.message || this.message('requestFailed')
                );
                this.$el.find('[name="profilePhoto"]').val('');
            } finally {
                this.busy = false;
                this.$el.find('button, input, textarea').prop('disabled', false);
            }
        }
    };
});
