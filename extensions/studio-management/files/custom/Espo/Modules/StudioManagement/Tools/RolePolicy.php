<?php

namespace Espo\Modules\StudioManagement\Tools;

use Espo\Core\AclManager;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Business account types determine payroll/slots, not authorization.
 * "not-set" preserves existing installations; an explicit role level overrides it.
 * Native record/field ACLs are still required by the API before these workflow checks.
 */
final class RolePolicy
{
    public function __construct(private AclManager $aclManager, private EntityManager $em) {}

    public function level(User $user, string $permission, string $legacy = 'no'): string
    {
        if ($user->isAdmin()) {
            return 'all';
        }
        $level = $this->aclManager->getPermissionLevel($user, $permission);
        return $level === 'not-set' ? $legacy : $level;
    }

    public function canOperate(User $user, Entity $shift): bool
    {
        $legacy = in_array($user->get('smAccountType'), ['Operator', 'ProducerAdmin'], true) ? 'own' : 'no';
        $level = $this->level($user, 'smShiftOperate', $legacy);
        return $level === 'all' || ($level === 'own' && $shift->get('operatorId') === $user->getId());
    }

    public function canPrepare(User $user, Entity $shift): bool
    {
        $level = $this->level($user, 'smShiftPrepare', 'own');
        return $level === 'all' || ($level === 'own' && in_array($user->getId(),
            [$shift->get('operatorId'), $shift->get('producerId')], true));
    }

    public function canRevise(User $user, Entity $shift): bool
    {
        $legacy = $user->get('smAccountType') === 'ProducerAdmin' ? 'team' : 'no';
        $level = $this->level($user, 'smShiftRevise', $legacy);
        if ($level === 'all') {
            return true;
        }
        if ($level !== 'team') {
            return false;
        }
        $teamId = $shift->get('teamId');
        $team = $teamId ? $this->em->getEntityById('Team', $teamId) : null;
        return $team && $team->get('smProducerId') === $user->getId();
    }

    public function staffLevel(User $user): string
    {
        return $this->level($user, 'smStaffAccess',
            $user->get('smAccountType') === 'ProducerAdmin' ? 'team' : 'no');
    }

    public function canInvite(User $user): bool
    {
        return in_array($this->level($user, 'smInvitations'), ['yes', 'all'], true);
    }
}
