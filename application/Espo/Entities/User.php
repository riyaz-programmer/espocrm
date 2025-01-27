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

namespace Espo\Entities;

use Espo\Core\Entities\Person;

class User extends Person
{
    public function isActive() : bool
    {
        return (bool) $this->get('isActive');
    }

    public function isAdmin() : bool
    {
        return $this->get('type') === 'admin' || $this->isSystem() || $this->isSuperAdmin();
    }

    public function isPortal() : bool
    {
        return $this->get('type') === 'portal';
    }

    public function isPortalUser() : bool
    {
        return $this->isPortal();
    }

    public function isRegular() : bool
    {
        return $this->get('type') === 'regular' || ($this->has('type') && !$this->get('type'));
    }

    public function isApi() : bool
    {
        return $this->get('type') === 'api';
    }

    public function isSystem() : bool
    {
        return $this->get('type') === 'system';
    }

    public function isSuperAdmin() : bool
    {
        return $this->get('type') === 'super-admin';
    }

    public function getTeamIdList() : array
    {
        return $this->getLinkMultipleIdList('teams');
    }

    public function loadAccountField()
    {
        if ($this->get('contactId')) {
            $contact = $this->getEntityManager()->getEntity('Contact', $this->get('contactId'));

            if ($contact && $contact->get('accountId')) {
                $this->set('accountId', $contact->get('accountId'));
                $this->set('accountName', $contact->get('accountName'));
            }
        }
    }

    protected function _getName()
    {
        if (!$this->hasInContainer('name') || !$this->getFromContainer('name')) {
            if ($this->get('userName')) {
                return $this->get('userName');
            }
        }

        return $this->getFromContainer('name');
    }

    protected function _hasName()
    {
        if ($this->hasInContainer('name')) {
            return true;
        }

        if ($this->has('userName')) {
            return true;
        }

        return false;
    }
}
