define(['views/role/record/table'], function (RoleTableView) {
    return class extends RoleTableView {
        removeAllRestrictions() {
            const data = {};
            this.tableDataList.forEach(item => {
                if (!item) return;
                if (item.type === 'boolean') {
                    data[item.name] = true;
                    return;
                }
                const scopeData = {};
                item.list.forEach(action => {
                    if (action.levelList?.length) scopeData[action.action] = action.levelList[0];
                });
                data[item.name] = scopeData;
            });
            this.model.set({data, fieldData: {}});
        }
    };
});
