define(['view'], function (View) {
    return class extends View {
        template = 'studio-management:user/simple-profile'

        setup() {
            this.wait(Espo.Ajax.getRequest('StudioManagement/profiles/' + encodeURIComponent(this.options.profileId))
                .then(profile => { this.profile = profile; }));
        }

        data() {
            return {
                userName: this.profile.userName,
                avatarLabel: this.profile.userName.slice(0, 2).toUpperCase(),
                avatarUrl: this.profile.avatarId
                    ? this.getBasePath() + '?entryPoint=image&id=' + encodeURIComponent(this.profile.avatarId)
                    : null,
            };
        }
    };
});
