<?php

namespace Sunnysideup\DMS\Extensions;

use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataExtension;

use Sunnysideup\DMS\Model\DMSDocument;
/**
 * Creates default taxonomy type records if they don't exist already
 */

class FileExtension extends DataExtension
{

    private static $db = [
        'OriginalDMSDocumentIDFile' => 'Int'
    ];

    private static $indexes = [
        'OriginalDMSDocumentIDFile' => true
    ];

}
