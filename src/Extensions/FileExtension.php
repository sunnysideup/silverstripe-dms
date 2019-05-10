<?php

namespace Sunnysideup\DMS\Extensions;


use SilverStripe\Assets\Folder;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataExtension;

use Sunnysideup\DMS\Model\DMSDocument;
/**
 * Creates default taxonomy type records if they don't exist already
 */

class FileExtension extends DataExtension
{

    private static $db = [
        'OriginalDMSDocumentID' => 'Int'
    ];

    private static $indexes = [
        'OriginalDMSDocumentID' => true
    ];


    /**
     * Event handler called before writing to the database.
     */
    public function onBeforePublish()
    {
        $dmsFolder = Folder::find_or_make('dmsassets');
        if($this->owner->ParentID === $dmsFolder->ID){
            $this->owner->ClassName = DMSDocument::class;
            $this->owner->write();
        }
    }
}
