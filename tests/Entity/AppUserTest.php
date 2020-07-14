<?php

namespace App\Tests\Entity;

use App\Entity\AppUser;
use PHPUnit\Framework\TestCase;

class AppUserTest extends TestCase
{
    public function testHasRoleAddingExplicitRoles()
    {
        $role = 'ROLE_TESTING';

        $user = new AppUser('abc123');

        // New user doesn't have test role
        $this->assertFalse($user->hasRoleExplicit($role));

        // Still doesn't have even after adding an unrelated role
        $user->addRole('ROLE_FOO');
        $this->assertFalse($user->hasRoleExplicit($role));

        // Has test role after adding it
        $user->addRole($role);
        $this->assertTrue($user->hasRoleExplicit($role));

        // No test role after removing it
        $user->removeRole($role);
        $this->assertFalse($user->hasRoleExplicit($role));
    }
}
