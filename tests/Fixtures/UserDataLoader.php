<?php

declare(strict_types=1);

/*
 * This file is part of the Cursor Pagination package.
 *
 * (c) SILARHI <dev@silarhi.fr>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silarhi\CursorPagination\Tests\Fixtures;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;
use Silarhi\CursorPagination\Tests\Entity\Post;
use Silarhi\CursorPagination\Tests\Entity\User;

use function sprintf;

final class UserDataLoader implements FixtureInterface
{
    /** Tenant ids of users 1 to 10 */
    public const TENANT_IDS = [2, 2, 2, 12, 12, 12, 2, 2, 2, 12];

    /** Authors of posts 1 to 10, by user id */
    public const POST_AUTHORS = [1, 1, 1, 3, 3, 4, 7, null, null, 10];

    #[Override]
    public function load(ObjectManager $manager): void
    {
        $users = [];
        for ($i = 1; $i <= 10; ++$i) {
            $user = new User();
            $user->setNumber($i);
            $user->setTenantId(self::TENANT_IDS[$i - 1]);
            $user->setUsername(sprintf('User %d', $i));
            $user->setFirstName(sprintf('First name %d', $i));
            $user->setLastName(sprintf('Last name %d', $i));

            $manager->persist($user);
            $users[$i] = $user;
        }

        foreach (self::POST_AUTHORS as $index => $authorId) {
            $manager->persist(new Post(sprintf('Post %d', $index + 1), null === $authorId ? null : $users[$authorId]));
        }

        $manager->flush();
    }
}
