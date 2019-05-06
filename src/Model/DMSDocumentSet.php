<?php

namespace Sunnysideup\DMS\Model;

use UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows;
use Symbiote\GridFieldExtension\GridFieldOrderableRows;
use SilverStripe\CMS\Model\SiteTree;
use Sunnysideup\DMS\Model\DMSDocument;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use Sunnysideup\DMS\Cms\DMSGridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Control\Controller;
use SilverStripe\CMS\Controllers\CMSPageEditController;
use Sunnysideup\DMS\Cms\DMSGridFieldDetailForm_ItemRequest;
use SilverStripe\Forms\GridField\GridField;
use Sunnysideup\DMS\Cms\DMSGridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use Sunnysideup\DMS\DMS;
use SilverStripe\Forms\HiddenField;
use SilverStripe\View\Requirements;
use SilverStripe\Security\Member;
use SilverStripe\Forms\ListboxField;
use Sunnysideup\DMS\Forms\DMSJsonField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Permission;
use SilverStripe\ORM\DataObject;

/**
 * A document set is attached to Pages, and contains many DMSDocuments
 *
 * @property Varchar Title
 * @property  Text KeyValuePairs
 * @property  Enum SortBy
 * @property Enum SortByDirection
 */
class DMSDocumentSet extends DataObject
{

    private static $table_name = 'DMSDocumentSet';


    /**
      * ### @@@@ START REPLACEMENT @@@@ ###
      * WHY: upgrade to SS4
      * OLD: private static $db = (case sensitive)
      * NEW: private static $db = (COMPLEX)
      * EXP: Make sure to add a private static $table_name!
      * ### @@@@ STOP REPLACEMENT @@@@ ###
      */
    private static $db = array(
        'Title' => 'Varchar(255)',
        'KeyValuePairs' => 'Text',
        'SortBy' => "Enum('LastEdited,Created,Title')')",
        'SortByDirection' => "Enum('DESC,ASC')')",
    );


    /**
      * ### @@@@ START REPLACEMENT @@@@ ###
      * WHY: upgrade to SS4
      * OLD: private static $has_one = (case sensitive)
      * NEW: private static $has_one = (COMPLEX)
      * EXP: Make sure to add a private static $table_name!
      * ### @@@@ STOP REPLACEMENT @@@@ ###
      */
    private static $has_one = array(
        'Page' => SiteTree::class,
    );

    private static $many_many = array(
        'Documents' => DMSDocument::class,
    );

    private static $many_many_extraFields = array(
        'Documents' => array(
            // Flag indicating if a document was added directly to a set - in which case it is set - or added
            // via the query-builder.
            'ManuallyAdded' => 'Boolean(1)',
            'DocumentSort' => 'Int'
        ),
    );

    private static $summary_fields = array(
        'Title' => 'Title',
        'Documents.Count' => 'No. Documents'
    );

    /**
     * Retrieve a list of the documents in this set. An extension hook is provided before the result is returned.
     *
     * You can attach an extension to this event:
     *
     * <code>
     * public function updateDocuments($document)
     * {
     *     // do something
     * }
     * </code>
     *
     * @return DataList|null
     */
    public function getDocuments()
    {
        $documents = $this->Documents();
        $this->extend('updateDocuments', $documents);
        return $documents;
    }



    /**
     * Customise the display fields for the documents GridField
     *
     * @return array
     */
    public function getDocumentDisplayFields()
    {
        return array_merge(
            (array) DMSDocument::create()->config()->get('display_fields'),
            array('ManuallyAdded' => _t('DMSDocumentSet.ADDEDMETHOD', 'Added'))
        );
    }

    public function validate()
    {
        $result = parent::validate();

        if (!$this->getTitle()) {
            $result->addError(_t('DMSDocumentSet.VALIDATION_NO_TITLE', '\'Title\' is required.'));
        }
        return $result;
    }

    public function canView($member = null, $context = [])
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }
        return $this->getGlobalPermission($member);
    }

    public function canCreate($member = null, $context = [])
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }
        return $this->getGlobalPermission($member);
    }

    public function canEdit($member = null, $context = [])
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }
        return $this->getGlobalPermission($member);
    }

    public function canDelete($member = null, $context = [])
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }
        return $this->getGlobalPermission($member);
    }

    /**
     * Checks if a then given (or logged in) member is either an ADMIN, SITETREE_EDIT_ALL or has access
     * to the DMSDocumentAdmin module, in which case permissions is granted.
     *
     * @param Member $member
     * @return bool
     */
    public function getGlobalPermission(Member $member = null)
    {
        if (!$member || !(is_a($member, Member::class)) || is_numeric($member)) {
            $member = Member::currentUser();
        }

        $result = (
            $member &&
            Permission::checkMember(
                $member,
                array('ADMIN', 'SITETREE_EDIT_ALL', 'CMS_ACCESS_DMSDocumentAdmin')
            )
        );

        return (bool) $result;
    }
}
