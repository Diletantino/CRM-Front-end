<?php

namespace Espo\Modules\StudioManagement\Tools;

use Espo\Core\Acl\Cache\Clearer;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/** Studio slots are business assignments, not Espo permission roles. */
final class TeamComposition
{
    public const SLOTS = ['smProducer' => 'ProducerAdmin', 'smOperator' => 'Operator', 'smModel' => 'Model'];
    private const LABELS = ['smProducer' => 'продюсер', 'smOperator' => 'оператор', 'smModel' => 'модель'];

    public function __construct(private EntityManager $em, private AccountTypeResolver $types, private Clearer $aclCache) {}

    public function participant(?string $id, string $slot): User
    {
        $user = $id ? $this->em->getEntityById('User', $id) : null;
        return $this->validateParticipant($user, $slot);
    }

    private function validateParticipant(?User $user, string $slot): User
    {
        $allowed = $slot === 'smOperator' ? ['Operator', 'ProducerAdmin'] : [self::SLOTS[$slot]];
        if (!$user instanceof User || !$user->isActive() || $user->isPortal() || $user->isSystem() ||
            !in_array($this->types->resolve($user), $allowed, true)) {
            throw WorkflowError::badRequest('В группе не выбран активный ' . self::LABELS[$slot] .
                '. Проверьте состав группы и поле «Тип аккаунта студии» в профиле пользователя.');
        }
        $user->set('smAccountType', $this->types->resolve($user));
        return $user;
    }

    /** Fill legacy groups from native membership without guessing among multiple candidates. */
    public function resolve(Entity $team, bool $complete = false): array
    {
        $members = $team->has('usersIds')
            ? $this->em->getRDBRepository('User')->where(['id' => $team->get('usersIds') ?: []])->find()
            : ($team->isNew() ? [] : $this->em->getRDBRepository('Team')->getRelation($team, 'users')->find());
        $result = [];
        foreach (self::SLOTS as $slot => $type) {
            $id = $team->get($slot . 'Id');
            if (!$id && !($team->has($slot . 'Id') && $team->isAttributeChanged($slot . 'Id'))) {
                $candidates = [];
                foreach ($members as $member) {
                    if ($member instanceof User && $member->isActive() && $this->types->resolve($member) === $type) {
                        $candidates[$member->getId()] = $member;
                    }
                }
                if (count($candidates) > 1) {
                    throw WorkflowError::badRequest('В группе несколько пользователей для слота «' . self::LABELS[$slot] .
                        '». Выберите одного в карточке группы.');
                }
                $id = $candidates ? (string) array_key_first($candidates) : null;
            }
            if ($id || $complete) {
                $result[$slot] = $this->participant($id, $slot);
            }
        }
        return $result;
    }

    public function prepare(Entity $team): void
    {
        foreach ($this->resolve($team) as $slot => $user) {
            $team->set($slot . 'Id', $user->getId());
        }
    }

    public function syncMembership(Entity $team): void
    {
        $relation = $this->em->getRDBRepository('Team')->getRelation($team, 'users');
        $ids = [];
        foreach (self::SLOTS as $slot => $_) {
            if ($id = $team->get($slot . 'Id')) {
                $ids[$id] = true;
                $relation->relateById($id);
            }
        }
        foreach (self::SLOTS as $slot => $_) {
            $oldId = $team->getFetched($slot . 'Id');
            if ($oldId && !isset($ids[$oldId]) && $team->isAttributeChanged($slot . 'Id')) {
                $relation->unrelateById($oldId);
            }
        }
        foreach (self::SLOTS as $slot => $_) {
            if (!$team->isNew() && !$team->isAttributeChanged($slot . 'Id')) {
                continue;
            }
            foreach (array_unique(array_filter([$team->get($slot . 'Id'), $team->getFetched($slot . 'Id')])) as $id) {
                $user = $this->em->getEntityById('User', $id);
                if ($user instanceof User) {
                    $this->aclCache->clearForUser($user);
                }
            }
        }
    }

    public function checkMember(Entity $team, User $user): ?string
    {
        $type = $this->types->resolve($user);
        $slot = array_search($type, self::SLOTS, true);
        if (!$slot) {
            return null; // Preserve ordinary Espo ACL-only membership.
        }
        $this->validateParticipant($user, $slot);
        $composition = $this->resolve($team);
        if (isset($composition[$slot]) && $composition[$slot]->getId() !== $user->getId()) {
            throw WorkflowError::badRequest('В группе уже выбран ' . self::LABELS[$slot] .
                '. Для замены измените соответствующий слот в карточке группы.');
        }
        return $slot;
    }

    public function memberChanged(Entity $team, User $user, bool $removed = false): void
    {
        $changed = false;
        foreach (self::SLOTS as $slot => $type) {
            if ($removed && $team->get($slot . 'Id') === $user->getId()) {
                $team->set($slot . 'Id', null);
                $changed = true;
            } elseif (!$removed && $this->types->resolve($user) === $type && !$team->get($slot . 'Id')) {
                $team->set($slot . 'Id', $user->getId());
                $changed = true;
            }
        }
        if ($changed) {
            $this->em->saveEntity($team, ['skipHooks' => true]);
        }
    }

    public function applyToDraft(Entity $shift): void
    {
        $teamId = $shift->get('teamId');
        $team = $teamId ? $this->em->getEntityById('Team', $teamId) : null;
        if (!$team) {
            throw WorkflowError::badRequest('Выберите существующую группу для смены.');
        }
        $members = $this->resolve($team, true);
        foreach (['smProducer' => 'producer', 'smOperator' => 'operator', 'smModel' => 'model'] as $slot => $field) {
            $shift->set($field . 'Id', $members[$slot]->getId());
            $shift->set($field . 'Name', $members[$slot]->get('name'));
        }
    }
}
