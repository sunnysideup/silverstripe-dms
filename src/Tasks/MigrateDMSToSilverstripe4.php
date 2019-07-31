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

use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Control\Director;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;


use Sunnysideup\DMS\Model\DMSDocument;
use Sunnysideup\DMS\Model\DMSDocumentSet;
use Sunnysideup\MigrateData\Tasks\MigrateDataTask;

class MigrateDMSToSilverstripe4 extends MigrateDataTask implements Flushable
{

    /**
     * list of tables => fields that need migrating
     * 'MyPageLongFormDocument' =>  'DownloadFile'
     * these tables also need to have a OriginalDMSDocumentID[TableName] field
     *@var array
     */
    private static $my_table_and_field_for_post_queries = [];

    public static function flush()
    {
        if(! empty($_GET['rundmsmigration'])) {
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

    protected $title = 'Upgrade DMS to SS4';

    protected $description = 'Migration tool for upgrading from DMS from SS3 to SS4. CAREFUL: this removes a ton of functionality from the module.';

    protected $enabled = true;


    private $_folderCache = [];

    protected function performMigration()
    {

        self::flush();

        $this->runSQLQueries($this->getPreQueries(), 'PRE');

        $this->setMainDMSFolder();
        $baseDir = Director::baseFolder();
        $baseDirWithPublic = $baseDir . '/public/';
        $baseURL = Director::absoluteBaseURL();
        self::flush();
        $rows = DB::query('SELECT * FROM "_obsolete_DMSDocument";');
        $this->flushNow('... selecting '.DB::affected_rows().' rows from _obsolete_DMSDocument');
        foreach ($rows as $row) {
            //new file system doesn't like using _ or 0 for folder names
            $oldFolderName = '/dmsassets/'. $row['Folder'] .'';
            $folderNumber = $row['Folder'] ? $row['Folder'] : 'zero';
            $newFolderName = 'dmsassets/'.$folderNumber.'';
            if (!isset($this->_folderCache[$newFolderName])) {
                $this->_folderCache[$newFolderName] = Folder::find_or_make($newFolderName);
            }
            if ($this->_folderCache[$newFolderName] instanceof Folder) {
                $myFolder = $this->_folderCache[$newFolderName];
                $exists = File::get()->filter(['OriginalDMSDocumentIDFile' => $row['ID']])->count() ? true : false;
                if ($exists) {
                    $this->flushNow('Skipping File with ID '.$row['ID']);
                    //do nothing
                } else {
                    $this->flushNow('Doing: '.print_r($row, 1));
                    $fullLocationFromAssets = $oldFolderName.'/'.$row['Filename'];
                    $fullLocationFromBase = ASSETS_PATH . $fullLocationFromAssets;


                    if (file_exists($fullLocationFromBase)) {
                        $newFile = File::find($fullLocationFromAssets);
                        if ($newFile) {
                            $newFile->ClassName = DMSDocument::class;
                            $newFile->write();
                        } else {
                            $newFile = DMSDocument::create();
                        }


                        //File Values
                        $newFile->Created = $row['Created'];
                        $newFile->LastEdited = $row['LastEdited'];
                        $newFile->Name = $row['Filename'];
                        $newFile->Title = $row['Title'];
                        $newFile->CanViewType = $row['CanViewType'];
                        $newFile->CanEditType = $row['CanEditType'];

                        //DMSDocument Values
                        $newFile->Description = $row['Description'];
                        $newFile->ISBN = $row['ISBN'];
                        $newFile->ISSN = $row['ISSN'];
                        $newFile->CoverImageID = $row['CoverImageID'];
                        $newFile->CreatedByID = $row['CreatedByID'];
                        $newFile->LastEditedByID = $row['LastEditedByID'];
                        $newFile->write();

                        //in the obsolete one we already have a version 1. Above we write a file
                        //so we end up with version 1
                        $sql = 'DELETE FROM "File_Versions" WHERE "RecordID" = '.$newFile->ID.' AND "Version" = 1;';
                        $this->flushNow('... ... ... running - '.$sql);
                        DB::query($sql);

                        $sql = 'DELETE FROM "DMSDocument_Versions" WHERE "RecordID" = '.$newFile->ID.' AND "Version" = 1;';
                        $this->flushNow('... ... ... running -'.$sql);
                        DB::query($sql);

                        if (! $this->tableExists('_obsolete_DMSDocument_versions')) {
                            user_error('Table _obsolete_DMSDocument_versions does not exist. Error', E_USER_ERROR);
                        }
                        $versionRows = DB::query('SELECT "ID" FROM "_obsolete_DMSDocument_versions" WHERE "DocumentID" = '.$row['ID']);
                        foreach ($versionRows as $versionRow) {
                            $this->flushNow('... adding row for DocumentID = '.$row['ID']);
                            $sql = '
                                INSERT
                                INTO "File_Versions" (
                                    "RecordID",        "Version",           "Created",   "LastEdited", "Name",     "Title", "CanViewType", "CanEditType", "OriginalDMSDocumentIDFile",                  "ClassName"
                                )
                                SELECT
                                    '.$newFile->ID .', "VersionCounter",    "Created",   "LastEdited", "Filename", "Title", "CanViewType", "CanEditType", '.$row['ID'].' AS OriginalDMSDocumentIDFile, \'Sunnysideup\\\\DMS\\\\Model\\\\DMSDocument\' as ClassNameInsert
                                FROM "_obsolete_DMSDocument_versions"
                                WHERE "ID" = '.$versionRow['ID'].';';
                            echo $sql;
                            DB::query($sql);
                            $id = DB::query('SELECT LAST_INSERT_ID();')->value();
                            if (! $id) {
                                user_error('Could not find an ID from the last insert.');
                            }
                            $testVersionCountForDMSDocumentTable = DB::query('SELECT COUNT("ID") FROM "DMSDocument_Versions" WHERE "ID" = '.$id);
                            if (! $testVersionCountForDMSDocumentTable) {
                                user_error('ID already exists in DMSDocument_Versions: '.$testVersionCountForDMSDocumentTable);
                            }
                            $sql = '
                                INSERT
                                INTO "DMSDocument_Versions" (
                                    "ID",  "RecordID", "Version",  "Description", "ISBN", "ISSN", "CoverImageID", "CreatedByID", "LastEditedByID"
                                )
                                SELECT
                                    '.$id.' AS ID, '.$newFile->ID .', "VersionCounter", "Description", "ISBN", "ISSN", '.$newFile->CoverImageID.', '.$newFile->CreatedByID.', "VersionAuthorID"
                                FROM "_obsolete_DMSDocument_versions"
                                WHERE "ID" = '.$versionRow['ID'].';';
                            DB::query($sql);
                        }

                        //attach File
                        $newFile->generateFilename();
                        $newFile->setFromLocalFile($fullLocationFromBase);
                        $newFile->ParentID = $myFolder->ID;
                        $id = $newFile->write();


                        //remove duplicate file and folder from the root of the assets dir
                        $oldLocalDir = ASSETS_PATH . '/' . substr($newFile->Hash, 0, 10);
                        $oldLocalFile = $oldLocalDir . '/' . $row['Filename'];
                        unlink($oldLocalFile);
                        rmdir($oldLocalDir);

                        $newFile->doPublish();
                        //run test 1
                        $testFilter1 = [
                            'Name' => $row['Filename'],
                            'ParentID' => $myFolder->ID
                        ];
                        $testCount1 = DMSDocument::get()
                            ->filter(['ID' => $id])
                            ->count();
                        if ($testCount1 !== 1) {
                            $this->flushNow('error in migration for row TEST 1 - could not find File Record, number of files found: '.$testCount1, 'error');
                            $this->flushNow('-----------------------------');
                            $this->flushNow('STOPPED');
                            $this->flushNow('-----------------------------');
                            die();
                        }

                        $link = $newFile->AbsoluteLink();
                        if ($link) {
                            echo $link;

                            $testLocation = str_replace($baseURL, $baseDirWithPublic, $link);
                            if (! file_exists($testLocation)) {
                                $this->flushNow('error in migration for row - could not find Actual File ('.$testLocation.')', 'error');
                                $this->flushNow('-----------------------------');
                                $this->flushNow('STOPPED');
                                $this->flushNow('-----------------------------');
                                die();
                            }
                            //mark as complete
                            $this->flushNow('... Marking as complete');
                            $newFile->OriginalDMSDocumentIDFile = $row['ID'];
                            $newFile->write();
                        } else {
                            $this->flushNow('ERROR: could not link to document ... ID = '.$newFile->ID.' we looked at '.$fullLocationFromAssets);
                            die();
                        }
                    } else {
                        $this->flushNow('Could not find: '.$fullLocationFromBase, 'error');
                    }
                }
            } else {
                die('Could not create folder: '.$newFolderName);
            }
        }

        $this->runPublishClasses([DMSDocument::class]);

        $this->runSQLQueries($this->getPostQueries(), 'POST');
    }


    protected function getPreQueries()
    {
        return [
            // 'ALTER TABLE "DMSDocument" CHANGE "ClassName" "ClassName" VARCHAR(255);',
            // 'ALTER TABLE "DMSDocument_versions" CHANGE "ClassName" "ClassName" VARCHAR(255);',
            // 'UPDATE "DMSDocument" SET "ClassName" = \'\'Sunnysideup\\DMS\\Model\\DMSDocument\'\' WHERE "ClassName" = \'\'DMSDocument\'\';',
            // 'UPDATE "DMSDocument_versions" SET "ClassName" = \'\'Sunnysideup\\DMS\\Model\\DMSDocument\'\' WHERE "ClassName" = \'\'DMSDocument_versions\'\';',
        ];
    }

    protected function getPostQueries()
    {
        $queries = [];

        $tablesAndFields = $this->Config()->my_table_and_field_for_post_queries;
        foreach ($tablesAndFields as $table => $field) {
            $queries = array_merge(
                $queries,
                $this->getPostQueriesBuilder($table, $field)
            );
        }

        return $queries;
    }

    protected function getPostQueriesBuilder($table, $relation)
    {
        $field = $relation.'ID';
        if (!$this->tableExists($table)) {
            $this->flushNow('Error: could not find the following table: '.$table);
            die('');
        }
        //clean table
        $baseTable = $table;
        $baseTable = str_replace('_Live', '', $baseTable);
        $baseTable = str_replace('_Versions', '', $baseTable);
        $originalDocumentIDField = 'OriginalDMSDocumentID'.$baseTable;
        if (! $this->fieldExists($table, $originalDocumentIDField)) {
            $this->flushNow('Error: could not find the following field: '.$originalDocumentIDField.' in '.$table);
            die('');
        }
        if (! $this->fieldExists($table, $field)) {
            $this->flushNow('Error: could not find the following field: '.$table.'.'.$field.'');
            die('');
        }
        $queries = [];
        foreach (['', '_Live', '_Versions'] as $tableExtensions) {
            $fullTable = $table.$tableExtensions;
            if ($this->tableExists($fullTable)) {
                $queries[] =

                    //set the OriginalDMSDocumentID if that has not been set yet ...
                    '
                        UPDATE "'.$fullTable.'"
                        SET "'.$fullTable.'"."'.$originalDocumentIDField.'" = "'.$fullTable.'"."'.$field.'"
                        WHERE
                            (
                                "'.$fullTable.'"."'.$originalDocumentIDField.'" = 0 OR
                                "'.$fullTable.'"."'.$originalDocumentIDField.'" IS NULL
                            ) AND (
                                "'.$fullTable.'"."'.$field.'" > 0 AND
                                "'.$fullTable.'"."'.$field.'" IS NOT NULL
                            );
                    ';
                $queries[] =

                    //set the field to zero in case there is a DMS Link
                    //but the DMS link can not be made
                    '
                        UPDATE "'.$fullTable.'"
                        LEFT JOIN "File"
                            ON "File"."OriginalDMSDocumentIDFile" = "'.$fullTable.'"."'.$originalDocumentIDField.'"
                        SET "'.$fullTable.'"."'.$field.'" = 0
                        WHERE
                            "'.$fullTable.'"."'.$originalDocumentIDField.'" > 0 AND
                            "'.$fullTable.'"."'.$originalDocumentIDField.'" IS NOT NULL AND

                            "File"."ID" IS NULL;
                    ';
                $queries[] =

                    //update to new value ... where there is a DMSDocument connection
                    '
                        UPDATE "'.$fullTable.'"
                            INNER JOIN "File"
                                ON "File"."OriginalDMSDocumentIDFile" = "'.$fullTable.'"."'.$originalDocumentIDField.'"
                        SET "'.$fullTable.'"."'.$field.'" = "File"."ID"
                        WHERE
                            "'.$fullTable.'"."'.$originalDocumentIDField.'" > 0 AND
                            "'.$fullTable.'"."'.$originalDocumentIDField.'" IS NOT NULL;
                    ';
            } else {
                $this->flushNow('Skipping '.$fullTable.' as this table does not exist.');
            }
        }

        return $queries;
    }

    public function setMainDMSFolder()
    {
        $siteConfig = SiteConfig::current_site_config();
        if (! $siteConfig->DMSFolderID) {
            $folder = Folder::find_or_make('dmsassets');
            $siteConfig->DMSFolderID = $folder->ID;
            $siteConfig->write();
        }
    }
}
