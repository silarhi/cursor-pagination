<?php

declare(strict_types=1);

/*
 * This file is part of the Cursor Pagination package.
 *
 * (c) SILARHI <dev@silarhi.fr>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silarhi\CursorPagination\Tests;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;

use function extension_loaded;

use PHPUnit\Framework\TestCase;
use Silarhi\CursorPagination\Tests\Fixtures\UserDataLoader;

abstract class DoctrineTestCase extends TestCase
{
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Extension pdo_sqlite is required.');
        }

        $connection = DriverManager::getConnection(
            [
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ]
        );

        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/Entity'],
            isDevMode: true,
        );
        $config->setEntityNamespaces(['CursorPagination' => 'Silarhi\CursorPagination\Tests\Entity']);
        $entityManager = new EntityManager($connection, $config);

        $schemaTool = new SchemaTool($entityManager);
        $metadataFactory = $entityManager->getMetadataFactory();
        $metadata = $metadataFactory->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $loader = new Loader();
        $loader->addFixture(new UserDataLoader());

        $executor = new ORMExecutor($entityManager, new ORMPurger());
        $executor->execute($loader->getFixtures());

        $this->entityManager = $entityManager;
    }
}
