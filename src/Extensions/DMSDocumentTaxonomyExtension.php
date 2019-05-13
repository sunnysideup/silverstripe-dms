<?php

namespace Sunnysideup\DMS\Extensions;

use SilverStripe\Taxonomy\TaxonomyTerm;

use SilverStripe\Core\Config\Config;
use Sunnysideup\DMS\Extensions\DMSTaxonomyTypeExtension;
use SilverStripe\ORM\DataExtension;


class DMSDocumentTaxonomyExtension extends DataExtension
{
    private static $db = array(
        'OriginalDMSDocumentID' => 'Int'
    );

    private static $many_many = array(
        'Tags' => TaxonomyTerm::class
    );

    /**
     * Return an array of all the available tags that a document can use. Will return a list containing a taxonomy
     * term's entire hierarchy, e.g. "Photo > Attribute > Density > High"
     *
     * @return array
     */
    public function getAllTagsMap()
    {
        $tags = TaxonomyTerm::get()->filter(
            'Type.Name:ExactMatch',
            Config::inst()->get(DMSTaxonomyTypeExtension::class, 'default_record_name')
        );

        $map = [];
        foreach ($tags as $tag) {
            $nameParts = array($tag->Name);
            $currentTag = $tag;

            while ($currentTag->Parent() && $currentTag->Parent()->exists()) {
                array_unshift($nameParts, $currentTag->Parent()->Name);
                $currentTag = $currentTag->Parent();
            }

            $map[$tag->ID] = implode(' > ', $nameParts);
        }
        return $map;
    }
}
