<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\MediaBundle\Model;

use Sonata\MediaBundle\Exception\NoDriverException;

/**
 * @internal
 *
 * @author Andrey F. Mindubaev <covex.mobile@gmail.com>
 */
final class NoDriverMediaManager implements MediaManagerInterface
{
    public function getClass(): string
    {
        throw new NoDriverException();
    }

    public function findAll(): array
    {
        throw new NoDriverException();
    }

    public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null): array
    {
        throw new NoDriverException();
    }

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object
    {
        throw new NoDriverException();
    }

    public function find($id): ?object
    {
        throw new NoDriverException();
    }

    public function create(): object
    {
        throw new NoDriverException();
    }

    public function save($entity, $andFlush = true): void
    {
        throw new NoDriverException();
    }

    public function delete($entity, $andFlush = true): void
    {
        throw new NoDriverException();
    }

    public function getTableName(): string
    {
        throw new NoDriverException();
    }

    /**
     * Do not add return typehint to this method, it forces a dependency with
     * Doctrine DBAL that we do not want here. This method will probably be
     * deprecated on sonata-project/doctrine-extensions because it is only for
     * Doctrine ORM.
     */
    public function getConnection()
    {
        throw new NoDriverException();
    }
}
