<?php

namespace App\Repositories\Configuration;

use App\Repositories\Repository;
use App\Jobs\Configuration\MigrateAffiliates;
use App\Jobs\Configuration\MigratePackages;
use Exception;

class ConfigurationRepository extends Repository
{
    /**
     * @param  array $data
     * @return void
     *
     * @throws Exception
     */
    public function migratePackages($data): void
    {
        dispatch(job: new MigratePackages(data: $data));
    }

    /**
     * @param  array $data
     * @return void
     *
     * @throws Exception
     */
    public function migrateAffiliates($data): void
    {
        dispatch(job: new MigrateAffiliates(data: $data));
    }
}
