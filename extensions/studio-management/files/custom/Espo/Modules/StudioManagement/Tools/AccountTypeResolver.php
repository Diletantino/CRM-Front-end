<?php

namespace Espo\Modules\StudioManagement\Tools;

use Espo\Entities\User;
use Espo\ORM\EntityManager;

final class AccountTypeResolver
{
    public function __construct(private EntityManager $entityManager) {}

    public function resolve(User $user): string
    {
        if ($user->get('smAccountType')) {
            return (string) $user->get('smAccountType');
        }

        $roles = $user->has('rolesIds')
            ? $this->entityManager->getRDBRepository('Role')->where(['id' => $user->get('rolesIds') ?: []])->find()
            : ($user->isNew() ? [] : $this->entityManager->getRDBRepository('User')->getRelation($user, 'roles')->find());

        $types = [];
        foreach ($roles as $role) {
            $type = $role->get('smAccountType') ?: $this->fromLabel((string) $role->get('name'));
            if ($type) {
                $types[$type] = true;
            }
        }

        // A producer can also be an operator; keep their primary studio type.
        if (isset($types['ProducerAdmin'])) {
            return 'ProducerAdmin';
        }

        return count($types) === 1 ? (string) array_key_first($types) :
            ($types === [] ? $this->fromLabel((string) $user->get('title')) : '');
    }

    private function fromLabel(string $label): string
    {
        return match (mb_strtolower(trim($label))) {
            'model', 'модель' => 'Model',
            'operator', 'оператор' => 'Operator',
            'producer', 'продюсер', 'продюссер', 'продюсер / админ' => 'ProducerAdmin',
            'technical director', 'технический директор', 'техдиректор' => 'TechnicalDirector',
            'recruiter', 'рекрутер', 'рекрутёр' => 'Recruiter',
            default => '',
        };
    }

    public function syncExisting(): int
    {
        $count = 0;
        $users = $this->entityManager->getRDBRepository('User')
            ->where(['OR' => [['smAccountType' => null], ['smAccountType' => '']]])->find();
        foreach ($users as $user) {
            if (!$user instanceof User) {
                continue;
            }
            $type = $this->resolve($user);
            if (!$type) {
                continue;
            }
            $user->set('smAccountType', $type);
            $this->entityManager->saveEntity($user);
            $count++;
        }
        return $count;
    }
}
