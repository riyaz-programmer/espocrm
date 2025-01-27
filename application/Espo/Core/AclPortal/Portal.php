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

namespace Espo\Core\AclPortal;

use Espo\Entities\User;
use Espo\ORM\Entity;

trait Portal
{
    public function checkScope(User $user, $data, $action = null, Entity $entity = null, $entityAccessData = [])
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (is_null($data)) {
            return false;
        }

        if ($data === false) {
            return false;
        }

        if ($data === true) {
            return true;
        }

        if (is_string($data)) {
            return true;
        }

        $isOwner = null;

        if (isset($entityAccessData['isOwner'])) {
            $isOwner = $entityAccessData['isOwner'];
        }

        $inAccount = null;

        if (isset($entityAccessData['inAccount'])) {
            $inAccount = $entityAccessData['inAccount'];
        }

        $isOwnContact = null;

        if (isset($entityAccessData['isOwnContact'])) {
            $isOwnContact = $entityAccessData['isOwnContact'];
        }

        if (is_null($action)) {
            return true;
        }

        if (!isset($data->$action)) {
            return false;
        }

        $value = $data->$action;

        if ($value === Table::LEVEL_ALL || $value === Table::LEVEL_YES || $value === true) {
            return true;
        }

        if (!$value || $value === Table::LEVEL_NO) {
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
            if ($value === Table::LEVEL_OWN || $value === Table::LEVEL_ACCOUNT || $value === Table::LEVEL_CONTACT) {
                return true;
            }
        }

        if ($value === Table::LEVEL_ACCOUNT) {
            if (is_null($inAccount) && $entity) {
                $inAccount = $this->checkInAccount($user, $entity);
            }

            if ($inAccount) {
                return true;
            }
            else {
                if (is_null($isOwnContact) && $entity) {
                    $isOwnContact = $this->checkIsOwnContact($user, $entity);
                }

                if ($isOwnContact) {
                    return true;
                }
            }
        }

        if ($value === Table::LEVEL_CONTACT) {
            if (is_null($isOwnContact) && $entity) {
                $isOwnContact = $this->checkIsOwnContact($user, $entity);
            }

            if ($isOwnContact) {
                return true;
            }
        }

        return false;
    }

    public function checkReadOnlyAccount(User $user, $data)
    {
        if (empty($data) || !is_object($data) || !isset($data->read)) {
            return false;
        }

        return $data->read === Table::LEVEL_ACCOUNT;
    }

    public function checkReadOnlyContact(User $user, $data)
    {
        if (empty($data) || !is_object($data) || !isset($data->read)) {
            return false;
        }

        return $data->read === Table::LEVEL_CONTACT;
    }

    public function checkIsOwner(User $user, Entity $entity)
    {
        if ($entity->hasAttribute('createdById')) {
            if ($entity->has('createdById') && $user->id === $entity->get('createdById')) {
                return true;
            }
        }

        return false;
    }

    public function checkInAccount(User $user, Entity $entity)
    {
        $accountIdList = $user->getLinkMultipleIdList('accounts');

        if (!count($accountIdList)) {
            return false;
        }

        if ($entity->hasAttribute('accountId') && $entity->getRelationParam('account', 'entity') === 'Account') {
            if (in_array($entity->get('accountId'), $accountIdList)) {
                return true;
            }
        }

        if ($entity->hasRelation('accounts') && $entity->getRelationParam('accounts', 'entity') === 'Account') {
            $repository = $this->getEntityManager()->getRepository($entity->getEntityType());

            foreach ($accountIdList as $accountId) {
                if ($repository->isRelated($entity, 'accounts', $accountId)) {
                    return true;
                }
            }
        }

        if ($entity->hasAttribute('parentId') && $entity->hasRelation('parent')) {
            if (
                $entity->get('parentType') === 'Account' &&
                in_array($entity->get('parentId'), $accountIdList)
            ) {
                return true;
            }
        }

        return false;
    }

    public function checkIsOwnContact(User $user, Entity $entity)
    {
        $contactId = $user->get('contactId');

        if (!$contactId) {
            return false;
        }

        if ($entity->hasAttribute('contactId') && $entity->getRelationParam('contact', 'entity') === 'Contact') {
            if ($entity->get('contactId') === $contactId) {
                return true;
            }
        }

        if ($entity->hasRelation('contacts') && $entity->getRelationParam('contacts', 'entity') === 'Contact') {
            $repository = $this->getEntityManager()->getRepository($entity->getEntityType());

            if ($repository->isRelated($entity, 'contacts', $contactId)) {
                return true;
            }
        }

        if ($entity->hasAttribute('parentId') && $entity->hasRelation('parent')) {
            if ($entity->get('parentType') === 'Contact' && $entity->get('parentId') === $contactId) {
                return true;
            }
        }

        return false;
    }
}
