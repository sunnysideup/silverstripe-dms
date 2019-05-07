<?php

namespace Sunnysideup\DMS\Extensions;


use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataExtension;

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

}
