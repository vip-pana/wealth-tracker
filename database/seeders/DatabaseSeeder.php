<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Nothing to seed. The starting set of categories is created by the
     * `seed_default_categories` migration, because that is what actually runs
     * on an install — the Docker entrypoint and the README run `migrate` and
     * never `db:seed`. The goal and its milestones are the owner's own plan,
     * not a sensible default for anyone else, so a fresh install starts with
     * no goal and the user creates theirs.
     */
    public function run(): void {}
}
