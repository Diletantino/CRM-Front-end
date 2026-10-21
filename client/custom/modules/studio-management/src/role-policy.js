define([], function () {
    const level = (view, permission, legacy = 'no') => {
        if (view.getUser().isAdmin()) {
            return 'all';
        }
        const value = view.getAcl().getPermissionLevel(permission);
        return value === 'not-set' ? legacy : value;
    };
    return {
        level,
        canOperate(view) {
            const legacy = ['Operator', 'ProducerAdmin'].includes(view.getUser().get('smAccountType')) ? 'own' : 'no';
            const value = level(view, 'smShiftOperate', legacy);
            return value === 'all' || (value === 'own' && view.model.get('operatorId') === view.getUser().id);
        },
        canPrepare(view) {
            const value = level(view, 'smShiftPrepare', 'own');
            return value === 'all' || (value === 'own' &&
                [view.model.get('operatorId'), view.model.get('producerId')].includes(view.getUser().id));
        },
        canRevise(view) {
            const legacy = view.getUser().get('smAccountType') === 'ProducerAdmin' ? 'team' : 'no';
            const value = level(view, 'smShiftRevise', legacy);
            return value === 'all' || (value === 'team' && view.model.get('producerId') === view.getUser().id);
        },
        canViewStaff(view, scope) {
            const legacy = view.getUser().get('smAccountType') === 'ProducerAdmin' ? 'team' : 'no';
            return level(view, 'smStaffAccess', legacy) !== 'no' &&
                view.getAcl().checkScope(scope) && view.getAcl().checkScope('User', 'read');
        },
    };
});
