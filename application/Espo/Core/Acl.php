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

namespace Espo\Core;

use Espo\Core\{
    Acl\Table,
};

use Espo\ORM\Entity;
use Espo\Entities\User;

use StdClass;

/**
 * A wrapper for AclManager. To check access for a current user.
 */
class Acl
{
    protected $user;

    protected $aclManager;

    public function __construct(AclManager $aclManager, User $user)
    {
        $this->aclManager = $aclManager;
        $this->user = $user;
    }

    public function getMap() : StdClass
    {
        return $this->aclManager->getMap($this->user);
    }

    /**
     * Get an access level for a specific scope and action.
     */
    public function getLevel(string $scope, string $action) : string
    {
        return $this->aclManager->getLevel($this->user, $scope, $action);
    }

    /**
     * Get a permission. E.g. 'assignment' permission.
     */
    public function get(string $permission) : ?string
    {
        return $this->aclManager->get($this->user, $permission);
    }

    /**
     * Whether there's no 'read' access for a specific scope.
     */
    public function checkReadNo(string $scope) : bool
    {
        return $this->aclManager->checkReadNo($this->user, $scope);
    }

    /**
     * Whether 'read' access is set to 'team' for a specific scope.
     */
    public function checkReadOnlyTeam(string $scope) : bool
    {
        return $this->aclManager->checkReadOnlyTeam($this->user, $scope);
    }

    /**
     * Whether 'read' access is set to 'own' for a specific scope.
     */
    public function checkReadOnlyOwn(string $scope) : bool
    {
        return $this->aclManager->checkReadOnlyOwn($this->user, $scope);
    }

    /**
     * Check a scope or entity. If $action is omitted, it will check whether a scope level is set to 'enabled'.
     */
    public function check($subject, ?string $action = null) : bool
    {
        return $this->aclManager->check($this->user, $subject, $action);
    }

    /**
     * Check access to scope. If $action is omitted, it will check whether a scope level is set to 'enabled'.
     */
    public function checkScope(string $scope, ?string $action = null) : bool
    {
        return $this->aclManager->checkScope($this->user, $scope, $action);
    }

    /**
     * Check access to a specific entity (record).
     */
    public function checkEntity(Entity $entity, string $action = Table::ACTION_READ) : bool
    {
        return $this->aclManager->checkEntity($this->user, $entity, $action);
    }

    /**
     * @deprecated
     */
    public function checkUser(string $permission, User $entity) : bool
    {
        return $this->aclManager->checkUser($this->user, $permission, $entity);
    }

    /**
     * Whether a user is owned of an entity (record). Usually 'assignedUser' field is used for checking.
     */
    public function checkIsOwner(Entity $entity) : bool
    {
        return $this->aclManager->checkIsOwner($this->user, $entity);
    }

    /**
     * Whether a user team list overlaps with teams set in an entity.
     */
    public function checkInTeam(Entity $entity) : bool
    {
        return $this->aclManager->checkInTeam($this->user, $entity);
    }

    /**
     * Get attributes forbidden for a user.
     */
    public function getScopeForbiddenAttributeList(
        string $scope, string $action = Table::ACTION_READ, string $thresholdLevel = Table::LEVEL_NO
    ) : array {

        return $this->aclManager
            ->getScopeForbiddenAttributeList($this->user, $scope, $action, $thresholdLevel);
    }

    /**
     * Get fields forbidden for a user.
     */
    public function getScopeForbiddenFieldList(
        string $scope, string $action = Table::ACTION_READ, string $thresholdLevel = Table::LEVEL_NO
    ) : array {

        return $this->aclManager
            ->getScopeForbiddenFieldList($this->user, $scope, $action, $thresholdLevel);
    }

    /**
     * Get links forbidden for a user.
     */
    public function getScopeForbiddenLinkList(
        string $scope, string $action = Table::ACTION_READ, string $thresholdLevel = Table::LEVEL_NO
    ) : array {

        return $this->aclManager->getScopeForbiddenLinkList($this->user, $scope, $action, $thresholdLevel);
    }

    /**
     * Whether a user has an access to another user over a specific permission.
     *
     * @param User|string $target User entity or user ID.
     */
    public function checkUserPermission($target, string $permissionType = 'user') : bool
    {
        return $this->aclManager->checkUserPermission($this->user, $target, $permissionType);
    }

    /**
     * Whether a user can assign to another user.
     *
     * @param User|string $target User entity or user ID.
     */
    public function checkAssignmentPermission($target) : bool
    {
        return $this->aclManager->checkAssignmentPermission($this->user, $target);
    }

    public function getScopeRestrictedFieldList(string $scope, $type) : array
    {
        return $this->aclManager->getScopeRestrictedFieldList($scope, $type);
    }

    public function getScopeRestrictedAttributeList(string $scope, $type) : array
    {
        return $this->aclManager->getScopeRestrictedAttributeList($scope, $type);
    }

    public function getScopeRestrictedLinkList(string $scope, $type) : array
    {
        return $this->aclManager->getScopeRestrictedLinkList($scope, $type);
    }
}
