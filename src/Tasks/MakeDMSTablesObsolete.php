<?php

//1. check if DMSDocument Exists
//2. foreach DMSDocument as item
//3. create file
//4. copy fields across
//5, foreach DMS Document check that everything has been brough across to file
//6. delete DMS Document fields, keeping the ones that need to be kept.
//7. move DMS Document to its own location
//

namespace Sunnysideup\DMS\Tasks;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;


use Sunnysideup\DMS\Model\DMSDocument;
use Sunnysideup\MigrateData\Tasks\MigrateDataTask;


if (!class_exists(MigrateDataTask::class)) {
    return;
}

class MakeDMSTablesObsolete extends MigrateDataTask
{

    protected $title = 'Make DMS Tables Obsolete';

    protected $description = 'Make DMS Tables Obsolete - need to run this before the first dev/build in order to avoid errors.';

    protected $enabled = true;


    private $_folderCache = [];

    protected function performMigration()
    {

        $oldFolder = ASSETS_PATH . '/_dmsassets';
        $newFolder = ASSETS_PATH . '/dmsassets';
        if (file_exists($oldFolder) && ! file_exists($newFolder)) {
            rename($oldFolder, $newFolder);
        } elseif (file_exists($oldFolder) && file_exists($newFolder)) {
            user_error($oldFolder.' AND '.$newFolder.' exist! Please review ...', E_USER_NOTICE);
        }

        $obj = Injector::inst()->get('Sunnysideup\\MigrateData\\Tasks\\MigrateDataTask');

        if ($obj->tableExists('DMSDocument_versions')) {
            if ($obj->fieldExists('DMSDocument_versions', 'Created') &&
                $obj->fieldExists('DMSDocument_versions', 'LastEdited')
            ) {
                $obj->makeTableObsolete('DMSDocument_versions');
            }
        }

        if ($obj->tableExists('DMSDocument')) {
            if ($obj->fieldExists('DMSDocument', 'Created') &&
                $obj->fieldExists('DMSDocument', 'LastEdited')
            ) {
                $obj->makeTableObsolete('DMSDocument');
            }
        }


    }

}
