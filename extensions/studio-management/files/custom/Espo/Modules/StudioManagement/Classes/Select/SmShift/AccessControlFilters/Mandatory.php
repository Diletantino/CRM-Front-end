<?php

namespace Espo\Modules\StudioManagement\Classes\Select\SmShift\AccessControlFilters;

use Espo\Core\Select\AccessControl\Filter;
use Espo\Entities\User;
use Espo\ORM\Query\Part\Condition as C;
use Espo\ORM\Query\Part\Expression as E;
use Espo\ORM\Query\SelectBuilder;
use Espo\Modules\StudioManagement\Tools\RolePolicy;

final class Mandatory implements Filter
{
    public function __construct(private User $user, private RolePolicy $policy) {}

    public function apply(SelectBuilder $queryBuilder): void
    {
        if ($this->user->isAdmin() || $this->user->get('smAccountType') !== 'Operator') return;
        $level = $this->policy->level($this->user, 'smShiftRevise');
        if ($level === 'all') return;
        // Operators keep their own history in the table, including Closed rows.
        // Opening Closed records remains separately restricted by the entity ACL.
        $visible = C::equal(E::column('operatorId'), E::value($this->user->getId()));
        if ($level === 'team') {
            $teams = SelectBuilder::create()->from('Team')->select('id')->where([
                'deleted' => false, 'smProducerId' => $this->user->getId(),
            ])->build();
            $visible = C::or($visible,
                C::equal(E::column('producerId'), E::value($this->user->getId())),
                C::and(C::equal(E::column('producerId'), E::value(null)), C::in(E::column('teamId'), $teams)));
        }
        $queryBuilder->where($visible);
    }
}
