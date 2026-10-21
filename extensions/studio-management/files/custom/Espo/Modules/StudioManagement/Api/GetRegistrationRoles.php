<?php

namespace Espo\Modules\StudioManagement\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\Modules\StudioManagement\Tools\RolePolicy;
use Espo\ORM\EntityManager;

/** Only a paged id/name catalog; this does not grant Role CRUD or expose the ACL matrix. */
final class GetRegistrationRoles implements Action
{
    public function __construct(private EntityManager $em, private User $user,
        private RolePolicy $policy, private Acl $acl) {}

    public function process(Request $request): Response
    {
        if (!$this->policy->canInvite($this->user) ||
            !$this->acl->checkScope('SmRegistrationLink', 'create')) {
            throw new Forbidden();
        }
        $offset = max(0, (int) ($request->getQueryParam('offset') ?? 0));
        $maxSize = max(1, min(100, (int) ($request->getQueryParam('maxSize') ?? 20)));
        $query = (string) ($request->getQueryParam('q') ?? '');
        // getQueryParam accepts strings only; bracket-encoded browser filters are arrays.
        $params = $request->getQueryParams();
        $filters = $params['whereGroup'] ?? $params['where'] ?? [];
        if (is_string($filters)) {
            $filters = json_decode($filters, true);
        }
        if (!is_array($filters)) {
            throw new BadRequest('Invalid role search.');
        }
        foreach ($filters as $filter) {
            $filter = (array) $filter;
            if (($filter['type'] ?? '') === 'textFilter' ||
                (($filter['type'] ?? '') === 'contains' && ($filter['attribute'] ?? '') === 'name')) {
                $query = (string) ($filter['value'] ?? '');
            }
        }
        if (mb_strlen($query) > 150) {
            throw new BadRequest('Role search is too long.');
        }
        // ORM LIKE values need explicit wildcards; escape user wildcard characters.
        $where = $query === '' ? [] : ['name*' => '%' . addcslashes($query, '%_\\') . '%'];
        $repo = $this->em->getRDBRepository('Role');
        $total = $repo->where($where)->count();
        $roles = $repo->select(['id', 'name'])->where($where)->order('name')->limit($offset, $maxSize)->find();
        $list = [];
        foreach ($roles as $role) {
            $list[] = ['id' => $role->getId(), 'name' => $role->get('name')];
        }
        return ResponseComposer::json(['total' => $total, 'list' => $list]);
    }
}
