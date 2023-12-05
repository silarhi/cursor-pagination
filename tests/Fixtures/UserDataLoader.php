<?php

declare(strict_types=1);

/*
 * This file is part of the CFONB Parser package.
 *
 * (c) SILARHI <dev@silarhi.fr>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silarhi\CursorPagination\Tests\Fixtures;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Silarhi\CursorPagination\Tests\Entity\User;

class UserDataLoader implements FixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        for ($i = 1; $i <= 10; ++$i) {
            $user = new User();
            $user->setNumber($i);
            $user->setUsername(sprintf('User %d', $i));
            $user->setFirstName(sprintf('First name %d', $i));
            $user->setLastName(sprintf('Last name %d', $i));

            $manager->persist($user);
        }

        $manager->flush();
    }
}
