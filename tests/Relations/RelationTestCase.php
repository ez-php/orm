<?php

declare(strict_types=1);

namespace Tests\Relations;

use Tests\RepositoryTestCase;

// RelationFixtures.php is not itself a *Test.php file, so PHPUnit's test
// discovery never requires it directly (unlike Tests\Entity\RepositoryTest,
// which declares its fixtures in the same file PHPUnit discovers). Require
// it explicitly so RelUserRepository et al. are defined.
require_once __DIR__ . '/RelationFixtures.php';

/**
 * Shared schema + repository wiring for isolated per-relation-class tests.
 *
 * @internal test fixtures
 */
abstract class RelationTestCase extends RepositoryTestCase
{
    protected RelUserRepository $users;

    protected RelPostRepository $posts;

    protected RelProfileRepository $profiles;

    protected RelTagRepository $tags;

    protected function setUp(): void
    {
        parent::setUp();

        $h = $this->hydrator;

        $this->users = new RelUserRepository($this->db, $h);
        $this->posts = new RelPostRepository($this->db, $h);
        $this->profiles = new RelProfileRepository($this->db, $h);
        $this->tags = new RelTagRepository($this->db, $h);

        $this->users->setPostRepo($this->posts);
        $this->users->setProfileRepo($this->profiles);
        $this->users->setTagRepo($this->tags);
        $this->posts->setUserRepo($this->users);
    }

    protected function setUpDatabase(): void
    {
        $this->exec('CREATE TABLE rel_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $this->exec('CREATE TABLE rel_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, title TEXT)');
        $this->exec('CREATE TABLE rel_profiles (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, bio TEXT)');
        $this->exec('CREATE TABLE rel_tags (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $this->exec('CREATE TABLE rel_user_tags (user_id INTEGER, tag_id INTEGER)');
    }
}
