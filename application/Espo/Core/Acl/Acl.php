<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2021 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\Core\Acl;

use Espo\Entities\User;
use Espo\ORM\Entity;

use Espo\Core\{
    Acl\Table,
    ORM\EntityManager,
    AclManager,
    Utils\Config,
    Utils\DateTime as DateTimeUtil,
};

/**
 * An implementation for access checking for entities. Can be overridden in `Acl` namespace.
 */
class Acl implements ScopeAcl, EntityAcl
{
    protected $scope;

    protected $ownerUserIdAttribute = null;

    protected $allowDeleteCreatedThresholdPeriod = '24 hours';

    protected $entityManager;

    protected $aclManager;

    protected $config;

    public function __construct(
        EntityManager $entityManager,
        AclManager $aclManager,
        Config $config,
        string $scope = null
    ) {
        $this->entityManager = $entityManager;
        $this->aclManager = $aclManager;
        $this->config = $config;

        $this->scope = $scope;
    }

    public function checkReadOnlyTeam(User $user, ScopeData $data) : bool
    {
        return $data->getRead() === Table::LEVEL_TEAM;
    }

    public function checkReadNo(User $user, ScopeData $data) : bool
    {
        return $data->getRead() === Table::LEVEL_NO;
    }

    public function checkReadOnlyOwn(User $user, ScopeData $data) : bool
    {
        return $data->getRead() === Table::LEVEL_OWN;
    }

    public function checkEntity(
        User $user,
        Entity $entity,
        ScopeData $data,
        string $action = Table::ACTION_READ
    ) : bool {

        return $this->checkScopeInternal($user, $data, $action, $entity);
    }

    public function checkScope(User $user, ScopeData $data, ?string $action = null) : bool
    {
        return $this->checkScopeInternal($user, $data, $action);
    }

    protected function checkScopeInternal(
        User $user,
        ScopeData $data,
        ?string $action = null,
        ?Entity $entity = null,
        array $entityAccessData = []
    ) : bool {

        if ($data->isFalse()) {
            return false;
        }

        if ($data->isTrue()) {
            return true;
        }

        $isOwner = null;

        if (isset($entityAccessData['isOwner'])) {
            $isOwner = $entityAccessData['isOwner'];
        }

        $inTeam = null;

        if (isset($entityAccessData['inTeam'])) {
            $inTeam = $entityAccessData['inTeam'];
        }

        if (is_null($action)) {
            if ($data->hasNotNo()) {
                return true;
            }

            return false;
        }

        $value = $data->get($action);

        if ($value === Table::LEVEL_ALL || $value === Table::LEVEL_YES) {
            return true;
        }

        if ($value === Table::LEVEL_NO) {
            return false;
        }

        if (is_null($isOwner)) {
            if ($entity) {
                $isOwner = $this->checkIsOwner($user, $entity);
            }
            else {
                return true;
            }
        }

        if ($isOwner) {
            if ($value === Table::LEVEL_OWN || $value === Table::LEVEL_TEAM) {
                return true;
            }
        }

        if (is_null($inTeam) && $entity) {
            $inTeam = $this->checkInTeam($user, $entity);
        }

        if ($inTeam) {
            if ($value === Table::LEVEL_TEAM) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function checkIsOwner(User $user, Entity $entity)
    {
        if ($entity->hasAttribute('assignedUserId')) {
            if ($entity->has('assignedUserId') && $user->id === $entity->get('assignedUserId')) {
                return true;
            }
        }
        else if ($entity->hasAttribute('createdById')) {
            if ($entity->has('createdById') && $user->id === $entity->get('createdById')) {
                return true;
            }
        }

        if ($entity->hasLinkMultipleField('assignedUsers')) {
            if ($entity->hasLinkMultipleId('assignedUsers', $user->id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function checkInTeam(User $user, Entity $entity)
    {
        $userTeamIdList = $user->getLinkMultipleIdList('teams');

        if (!$entity->hasRelation('teams') || !$entity->hasAttribute('teamsIds')) {
            return false;
        }

        $entityTeamIdList = $entity->getLinkMultipleIdList('teams');

        if (empty($entityTeamIdList)) {
            return false;
        }

        foreach ($userTeamIdList as $id) {
            if (in_array($id, $entityTeamIdList)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function checkEntityDelete(User $user, Entity $entity, $data)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->checkEntity($user, $entity, $data, Table::ACTION_DELETE)) {
            return true;
        }

        if (!is_object($data)) {
            return false;
        }

        if ($data->edit === Table::LEVEL_NO && $data->create === Table::LEVEL_NO) {
            return false;
        }

        if (
            !$this->config->get('aclAllowDeleteCreated') ||
            !$entity->has('createdById') ||
            !$entity->get('createdById') !== $user->getId()
        ) {
            return false;
        }

        $isDeletedAllowed = false;

        if (!$entity->has('assignedUserId')) {
            $isDeletedAllowed = true;
        }
        else {
            if (!$entity->get('assignedUserId')) {
                $isDeletedAllowed = true;
            }
            else if ($entity->get('assignedUserId') === $entity->get('createdById')) {
                $isDeletedAllowed = true;
            }
        }

        if (!$isDeletedAllowed) {
            return false;
        }

        $createdAt = $entity->get('createdAt');

        if (!$createdAt) {
            return true;
        }

        $deleteThresholdPeriod = $this->config->get(
            'aclAllowDeleteCreatedThresholdPeriod',
            $this->allowDeleteCreatedThresholdPeriod
        );

        if (DateTimeUtil::isAfterThreshold($createdAt, $deleteThresholdPeriod)) {
            return false;
        }

        return true;
    }

    public function getOwnerUserIdAttribute(Entity $entity) : ?string
    {
        if ($this->ownerUserIdAttribute) {
            return $this->ownerUserIdAttribute;
        }

        if ($entity->hasLinkMultipleField('assignedUsers')) {
            return 'assignedUsersIds';
        }

        if ($entity->hasAttribute('assignedUserId')) {
            return 'assignedUserId';
        }

        if ($entity->hasAttribute('createdById')) {
            return 'createdById';
        }

        return null;
    }

    /**
     * @deprecated Use `$this->config`.
     */
    protected function getConfig() : Config
    {
        return $this->config;
    }

    /**
     * @deprecated Use `$this->entityManager`.
     */
    protected function getEntityManager() : EntityManager
    {
        return $this->entityManager;
    }

    /**
     * @deprecated Use `$this->aclManager`.
     */
    protected function getAclManager() : AclManager
    {
        return $this->aclManager;
    }
}
