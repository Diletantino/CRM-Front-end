define(['views/site/navbar'], function (Navbar) {
    return class extends Navbar {
        getTabList() {
            const isModel = !this.getUser().isAdmin() && this.getUser().get('smAccountType') === 'Model';
            const filter = items => items.filter(item =>
                !(isModel && ['User', 'Team'].includes(item) && this.getAcl().getLevel(item, 'read') !== 'all')
            ).map(item => item?.itemList ? {...item, itemList: filter(item.itemList)} : item);
            const list = filter(super.getTabList());
            if (this.getAcl().check('SmAnalytics') ||
                !['Operator', 'Model'].includes(this.getUser().get('smAccountType'))) return list;
            const personal = {type: 'url', url: '#SmAnalytics',
                text: this.translate('Personal Analytics', 'labels', 'SmAnalytics')};
            const replace = items => items.map(item => {
                if (item === 'SmAnalytics') return personal;
                if (item?.itemList) return {...item, itemList: replace(item.itemList)};
                return item;
            });
            return replace(list);
        }
    };
});
