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

use Composer\InstalledVersions;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;

use function extension_loaded;

use Override;
use PHPUnit\Framework\TestCase;
use Silarhi\CursorPagination\Tests\Fixtures\UserDataLoader;

abstract class DoctrineTestCase extends TestCase
{
    protected EntityManagerInterface $entityManager;

    protected QueryCounter $queryCounter;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Extension pdo_sqlite is required.');
        }

        $ormVersion = InstalledVersions::getVersion('doctrine/orm') ?? '0';
        // Native lazy objects need PHP 8.4 and doctrine/orm 3.4+; older ORM versions
        // (tested by the lowest-dependencies CI job) keep using proxy classes. doctrine/orm 4 always uses them.
        $nativeLazyObjects = \PHP_VERSION_ID >= 80400 && version_compare($ormVersion, '3.4.0', '>=');

        // createAttributeMetadataConfig() replaces createAttributeMetadataConfiguration() in doctrine/orm 3.5, which 4
        // removes. It configures no proxy directory: only use it with native lazy objects
        $config = $nativeLazyObjects && version_compare($ormVersion, '3.5.0', '>=')
            ? ORMSetup::createAttributeMetadataConfig(paths: [__DIR__ . '/Entity'], isDevMode: true)
            : ORMSetup::createAttributeMetadataConfiguration(paths: [__DIR__ . '/Entity'], isDevMode: true);
        $this->queryCounter = new QueryCounter();
        $config->setMiddlewares([new Middleware($this->queryCounter)]);

        $connection = DriverManager::getConnection(
            [
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ],
            $config,
        );
        $config->setEntityNamespaces(['CursorPagination' => 'Silarhi\CursorPagination\Tests\Entity']);
        if ($nativeLazyObjects && version_compare($ormVersion, '4.0.0-dev', '<')) {
            $config->enableNativeLazyObjects(true);
        }
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
