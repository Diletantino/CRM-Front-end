define(['views/site/navbar', 'studio-management:role-policy'], function (Navbar, RolePolicy) {
    return class extends Navbar {
        setupMenu() {
            super.setupMenu();
            if (RolePolicy.level(this, 'smProfileAccess') === 'no') {
                this.menuDataList = this.menuDataList.filter(item => !item.link?.startsWith('#User/'));
            }
        }

        getTabList() {
            const profile = RolePolicy.level(this, 'smProfileAccess');
            const personal = ['yes', 'all'].includes(RolePolicy.level(this, 'smPersonalAnalytics'));
            const onboarding = RolePolicy.canAccessOnboarding(this);
            const studio = RolePolicy.level(this, 'smAnalyticsAccess', 'legacy') !== 'no';
            const filter = items => items.filter(item => {
                const scope = typeof item === 'string' ? item : item?.url?.replace(/^#/, '');
                return !['SmCredits', 'Stream', 'GlobalStream', 'Note'].includes(scope) &&
                    !(profile === 'no' && scope === 'User') &&
                    !(scope === 'SmPersonalAnalytics' && !personal) &&
                    !(scope === 'SmAnalytics' && !studio) &&
                    !(scope === 'SmAccountOnboarding' && !onboarding);
            }).map(item => item?.itemList ? {...item, itemList: filter(item.itemList)} : item);
            const list = filter(super.getTabList());
            const add = (scope, text) => {
                if (!list.some(item => item === scope || item?.url === '#' + scope)) {
                    list.push({type: 'url', url: '#' + scope, text});
                }
            };
            if (onboarding) {
                add('SmAccountOnboarding', this.translate('Accounts to complete', 'labels', 'SmAccountOnboarding'));
            }
            if (personal) {
                add('SmPersonalAnalytics', this.translate('Personal Analytics', 'labels', 'SmAnalytics'));
            }
            return list;
        }
    };
});
