<?php

declare(strict_types=1);

namespace Tests\Relations;

use EzPhp\Orm\AbstractRepository;
use EzPhp\Orm\Entity;
use EzPhp\Orm\Relations\EntityBelongsTo;
use EzPhp\Orm\Relations\EntityBelongsToMany;
use EzPhp\Orm\Relations\EntityHasMany;
use EzPhp\Orm\Relations\EntityHasOne;

/**
 * Shared entity/repository fixtures for isolated per-relation-class tests.
 *
 * Schema: rel_users (1) -hasMany-> rel_posts, rel_users (1) -hasOne-> rel_profiles,
 * rel_users <-> rel_tags via rel_user_tags pivot.
 *
 * Repositories are wired together via setter injection, mirroring the
 * pattern in Tests\Entity\RepositoryTest — each is constructed with the
 * shared (db, hydrator) pair, then cross-references are set explicitly.
 *
 * @internal test fixtures
 */
final class RelUserEntity extends Entity
{
    protected static string $table = 'rel_users';

    protected static array $fillable = ['name'];
}

/**
 * @internal test fixtures
 */
final class RelPostEntity extends Entity
{
    protected static string $table = 'rel_posts';

    protected static array $fillable = ['user_id', 'title'];
}

/**
 * @internal test fixtures
 */
final class RelProfileEntity extends Entity
{
    protected static string $table = 'rel_profiles';

    protected static array $fillable = ['user_id', 'bio'];
}

/**
 * @internal test fixtures
 */
final class RelTagEntity extends Entity
{
    protected static string $table = 'rel_tags';

    protected static array $fillable = ['name'];
}

/**
 * @internal test fixtures
 *
 * @extends AbstractRepository<RelPostEntity>
 */
final class RelPostRepository extends AbstractRepository
{
    private ?RelUserRepository $userRepo = null;

    public function setUserRepo(RelUserRepository $repo): void
    {
        $this->userRepo = $repo;
    }

    /** @return EntityBelongsTo<RelUserEntity> */
    public function user(Entity $post): EntityBelongsTo
    {
        assert($this->userRepo !== null);

        return $this->belongsTo($this->userRepo, 'user_id', 'id', $post->getAttribute('user_id'));
    }

    protected function entityClass(): string
    {
        return RelPostEntity::class;
    }
}

/**
 * @internal test fixtures
 *
 * @extends AbstractRepository<RelProfileEntity>
 */
final class RelProfileRepository extends AbstractRepository
{
    protected function entityClass(): string
    {
        return RelProfileEntity::class;
    }
}

/**
 * @internal test fixtures
 *
 * @extends AbstractRepository<RelTagEntity>
 */
final class RelTagRepository extends AbstractRepository
{
    protected function entityClass(): string
    {
        return RelTagEntity::class;
    }
}

/**
 * @internal test fixtures
 *
 * @extends AbstractRepository<RelUserEntity>
 */
final class RelUserRepository extends AbstractRepository
{
    private ?RelPostRepository $postRepo = null;

    private ?RelProfileRepository $profileRepo = null;

    private ?RelTagRepository $tagRepo = null;

    public function setPostRepo(RelPostRepository $repo): void
    {
        $this->postRepo = $repo;
    }

    public function setProfileRepo(RelProfileRepository $repo): void
    {
        $this->profileRepo = $repo;
    }

    public function setTagRepo(RelTagRepository $repo): void
    {
        $this->tagRepo = $repo;
    }

    /** @return EntityHasMany<RelPostEntity> */
    public function posts(Entity $user): EntityHasMany
    {
        assert($this->postRepo !== null);

        return $this->hasMany($this->postRepo, 'user_id', 'id', $user->getAttribute('id'));
    }

    /** @return EntityHasOne<RelProfileEntity> */
    public function profile(Entity $user): EntityHasOne
    {
        assert($this->profileRepo !== null);

        return $this->hasOne($this->profileRepo, 'user_id', 'id', $user->getAttribute('id'));
    }

    /** @return EntityBelongsToMany<RelTagEntity> */
    public function tags(Entity $user): EntityBelongsToMany
    {
        assert($this->tagRepo !== null);

        return $this->belongsToMany(
            $this->tagRepo,
            'rel_tags',
            'rel_user_tags',
            'user_id',
            'tag_id',
            'id',
            'id',
            $user->getAttribute('id'),
        );
    }

    protected function entityClass(): string
    {
        return RelUserEntity::class;
    }
}
