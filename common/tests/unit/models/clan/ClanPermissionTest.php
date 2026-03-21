<?php

namespace common\tests\unit\models\clan;

use common\models\clan\ClanPermission;

/**
 * Unit tests for ClanPermission model
 */
class ClanPermissionTest extends \Codeception\Test\Unit
{
    public function testGetDefaultPermissions()
    {
        $permissions = ClanPermission::getDefaultPermissions();
        verify($permissions)->array();
        verify(count($permissions))->greaterThan(0);
    }

    public function testFindByKey()
    {
        $permission = ClanPermission::findByKey('invite');
        verify($permission)->notEmpty();
        verify($permission->key)->equals('invite');

        $permission = ClanPermission::findByKey('nonexistent');
        verify($permission)->empty();
    }

    public function testPermissionExists()
    {
        $permission = ClanPermission::findByKey('invite');
        verify($permission)->notEmpty();
        verify($permission->name)->notEmpty();
    }
}

