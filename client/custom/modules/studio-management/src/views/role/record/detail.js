define(['views/role/record/detail'], function (RoleDetailView) {
    return class extends RoleDetailView {
        layoutName = 'studioRole'
        tableView = 'studio-management:views/role/record/table'
    };
});
