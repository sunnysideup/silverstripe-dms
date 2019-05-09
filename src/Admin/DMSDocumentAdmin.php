<?php

namespace Sunnysideup\DMS\Admin;

use SilverStripe\Admin\ModelAdmin;
use Sunnysideup\DMS\Model\DMSDocument;

class DMSDocumentAdmin extends ModelAdmin
{
    private static $url_segment = "dms-documents";
    private static $menu_title = 'DMS Documents';
    private static $managed_models = array(DMSDocument::class);
}
