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

namespace Espo\Acl;

use Espo\Entities\User as EntityUser;

use Espo\ORM\Entity;

use Espo\Core\Acl\Acl;

use Exception;
use DateTime;

class Note extends Acl
{
    protected $deleteThresholdPeriod = '1 month';

    protected $editThresholdPeriod = '7 days';

    public function checkIsOwner(EntityUser $user, Entity $entity)
    {
        if ($entity->get('type') === 'Post' && $user->id === $entity->get('createdById')) {
            return true;
        }

        return false;
    }

    public function checkEntityCreate(EntityUser $user, Entity $entity, $data)
    {
        if (!$entity->get('parentId') || !$entity->get('parentType')) {
            return true;
        }

        $parent = $this->entityManager->getEntity($entity->get('parentType'), $entity->get('parentId'));

        if ($parent && $this->getAclManager()->checkEntity($user, $parent, 'stream')) {
            return true;
        }

        return false;
    }

    public function checkEntityEdit(EntityUser $user, Entity $entity, $data)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (!$this->checkEntity($user, $entity, $data, 'edit')) {
            return false;
        }

        if (!$this->checkIsOwner($user, $entity)) {
            return true;
        }

        $createdAt = $entity->get('createdAt');

        if (!$createdAt) {
            return true;
        }

        $noteEditThresholdPeriod =
            '-' .  $this->config->get('noteEditThresholdPeriod', $this->editThresholdPeriod);

        $dt = new DateTime();

        $dt->modify($noteEditThresholdPeriod);

        try {
            if ($dt->format('U') > (new DateTime($createdAt))->format('U')) {
                return false;
            }
        }
        catch (Exception $e) {
            return false;
        }

        return true;
    }

    public function checkEntityDelete(EntityUser $user, Entity $entity, $data)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (!$this->checkEntity($user, $entity, $data, 'delete')) {
            return false;
        }

        if (!$this->checkIsOwner($user, $entity)) {
            return true;
        }

        $createdAt = $entity->get('createdAt');

        if (!$createdAt) {
            return true;
        }

        $deleteThresholdPeriod =
            '-' . $this->getConfig()->get('noteDeleteThresholdPeriod', $this->deleteThresholdPeriod);

        $dt = new DateTime();

        $dt->modify($deleteThresholdPeriod);

        try {
            if ($dt->format('U') > (new DateTime($createdAt))->format('U')) {
                return false;
            }
        }
        catch (Exception $e) {
            return false;
        }

        return true;
    }
}
