<?php


namespace Sunnysideup\DMS\Tasks;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Control\Director;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;


use Sunnysideup\DMS\Model\DMSDocument;
use Sunnysideup\DMS\Model\DMSDocumentSet;
use Sunnysideup\MigrateData\Tasks\MigrateDataTask;


if (!class_exists(MigrateDataTask::class)) {
    return;
}

class MigrateDMSToSilverstripe4HTMLShortCodeFix extends MigrateDataTask
{

    protected $title = 'Upgrade DMS to SS4 Short Codes only';

    protected $description = 'Run this after MigrateDMSToSilverstripe4';

    protected $enabled = true;

    protected function performMigration()
    {
        $count = DMSDocument::get()->count();
        $this->flushNow('DOING '.$count.' DMS DOCUMENTS');
        $idList = DMSDocument::get()->map('ID', 'OriginalDMSDocumentIDFile');
        foreach($idList as $newID => $oldID) {
            if($oldID) {
                $this->flushNow('');
                $this->flushNow('');
                $this->flushNow('Searching for DMS Document with ID = '.$newID.' and OldID = '.$oldID);
                $this->replaceShortCode($oldID, $newID);
            }
        }

    }

    /**
     * @var String Regex matching a {@link DBField} class name which is shortcode capable.
     *
     * This should really look for implementors of a ShortCodeParseable interface,
     * but we can't extend the core Text and HTMLText class
     * on existing like SiteTree.Content for this.
     */
    protected $fieldSpecRegex = '/^(HTMLText)/';


    /**
     * @param int $oldID
     * @param int $newID
     */
    protected function replaceShortCode($oldID, $newID)
    {
        $fields = $this->getShortCodeFields(SiteTree::class);
        foreach ($fields as $className => $fieldNames) {
            $tableName = $this->getSchemaForDataObject()->tableName($className);
            $this->flushNow('... searching in '.$tableName);
            if($this->tableExists($tableName)) {
                foreach ($fieldNames as $fieldName => $fieldSpecs) {
                    if($this->fieldExists($tableName, $fieldName)) {
                        $oldPhrase = '[dms_document_link,id='.$oldID.']';
                        $newPhrase = '[file_link,id='.$newID.']';
                        $this->flushNow('... ... replacing '.$oldPhrase.' to '.$newPhrase.' in '.$tableName.'.'.$fieldName);
                        $sql = '
                            UPDATE "'.$tableName.'"
                            SET
                            "'.$tableName.'"."'.$fieldName.'" = REPLACE(
                                "'.$tableName.'"."'.$fieldName.'",
                                \''.$oldPhrase.'\',
                                \''.$newPhrase.'\'
                            )
                            WHERE "'.$tableName.'"."'.$fieldName.'" LIKE \'%'.$oldPhrase.'%\';
                        ';
                        // $this->flushNow($sql);
                        DB::query($sql);
                        $this->flushNow('... ... ... DONE updated '.DB::affected_rows().' rows');
                    } else {
                        $this->flushNow('... ... skipping '.$tableName.'.'.$fieldName.' (does not exist)');
                    }
                }
            } else {
                $this->flushNow('... skipping: '.$tableName.' (does not exist)');
            }
        }
    }

    private $_classFieldCache = [];

    /**
     * Returns a filtered list of fields which could contain shortcodes.
     *
     * @param String
     * @return Array Map of class names to an array of field names on these classes.
     */
    protected function getShortcodeFields($class)
    {
        if(! isset($this->_classFieldCache[$class])) {
            $fields = [];
            $ancestry = array_values(ClassInfo::dataClassesFor($class));

            foreach ($ancestry as $ancestor) {
                if (ClassInfo::classImplements($ancestor, 'TestOnly')) {
                    continue;
                }

                $ancFields = $this->getSchemaForDataObject()->databaseFields($ancestor);
                if ($ancFields) {
                    foreach ($ancFields as $ancFieldName => $ancFieldSpec) {
                        if (preg_match($this->fieldSpecRegex, $ancFieldSpec)) {
                            if (!@$fields[$ancestor]) {
                                $fields[$ancestor] = [];
                            }
                            $fields[$ancestor][$ancFieldName] = $ancFieldSpec;
                        }
                    }
                }
            }

            $this->_classFieldCache[$class] = $fields;
        }

        return $this->_classFieldCache[$class];
    }

}
