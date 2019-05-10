<?php

namespace Sunnysideup\DMS\Model;

use Exception;



use Sunnysideup\DMS\Model\DMSDocumentSet;
use SilverStripe\Assets\Image;
use SilverStripe\Security\Member;
use Sunnysideup\DMS\Model\DMSDocument;
use SilverStripe\Security\Group;
use SilverStripe\Security\Permission;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataExtension;
use SilverStripe\View\Parsers\URLSegmentFilter;
use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Versioned\Versioned;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use Sunnysideup\DMS\DMS;
use SilverStripe\View\Requirements;
use SilverStripe\Forms\FieldList;
use Sunnysideup\DMS\Tools\ShortCodeRelationFinder;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\OptionsetField;
use Sunnysideup\DMS\Cms\DMSUploadField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\Forms\GridField\GridField;
use Sunnysideup\DMS\Model\DMSDocument_versions;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\LiteralField;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Assets\File;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\DateField_Disabled;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use Sunnysideup\DMS\Cms\DMSGridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataObject;
use Sunnysideup\DMS\Interfaces\DMSDocumentInterface;
use SilverStripe\Core\Manifest\ModuleLoader;

/**
 * @package dms
 *
 * @property Text Description
 *
 * @method ManyManyList RelatedDocuments
 * @method ManyManyList ViewerGroups
 * @method ManyManyList EditorGroups
 *
 * @method Member CreatedBy
 * @property Int CreatedByID
 * @method Member LastEditedBy
 * @property Int LastEditedByID
 *
 */
class DMSDocument extends File implements DMSDocumentInterface
{


    private static $db = array(
        "Description" => 'Text'
    );

    private static $belongs_many_many = array(
        'Sets' => DMSDocumentSet::class
    );


    private static $table_name = 'DMSDocument';

    private static $has_one = array(
        'CoverImage' => Image::class,
        'CreatedBy' => Member::class,
        'LastEditedBy' => Member::class,
    );

    private static $many_many = array(
        'RelatedDocuments' => DMSDocument::class
    );

    private static $singular_name = 'Document';

    private static $plural_name = 'Documents';

    private static $summary_fields = array(
        'Name' => 'Filename',
        'Title' => 'Title',
        'getRelatedPages.count' => 'Page Use'
    );

    /**
     * @var string download|open
     * @config
     */
    private static $default_download_behaviour = 'download';


    /**
     * Return the type of file for the given extension
     * on the current file name.
     *
     * @param string $ext
     *
     * @return string
     */
    public static function get_file_type($ext)
    {
        $types = array(
            'gif' => 'GIF image - good for diagrams',
            'jpg' => 'JPEG image - good for photos',
            'jpeg' => 'JPEG image - good for photos',
            'png' => 'PNG image - good general-purpose format',
            'ico' => 'Icon image',
            'tiff' => 'Tagged image format',
            'doc' => 'Word document',
            'xls' => 'Excel spreadsheet',
            'zip' => 'ZIP compressed file',
            'gz' => 'GZIP compressed file',
            'dmg' => 'Apple disk image',
            'pdf' => 'Adobe Acrobat PDF file',
            'mp3' => 'MP3 audio file',
            'wav' => 'WAV audo file',
            'avi' => 'AVI video file',
            'mpg' => 'MPEG video file',
            'mpeg' => 'MPEG video file',
            'js' => 'Javascript file',
            'css' => 'CSS file',
            'html' => 'HTML file',
            'htm' => 'HTML file'
        );

        return isset($types[$ext]) ? $types[$ext] : $ext;
    }


    /**
     * Returns the Description field with HTML <br> tags added when there is a
     * line break.
     *
     * @return string
     */
    public function getDescriptionWithLineBreak()
    {
        return nl2br($this->getField('Description'));
    }

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {

        $fields = new FieldList();  //don't use the automatic scaffolding, it is slow and unnecessary here

        $extraTasks = '';   //additional text to inject into the list of tasks at the bottom of a DMSDocument CMSfield

        $fields->add(TextField::create('Title', _t('DMSDocument.TITLE', 'Title')));
        $fields->add(TextareaField::create('Description', _t('DMSDocument.DESCRIPTION', 'Description')));

        if($this->hasExtension('Sunnysideup\DMS\Extensions\DMSDocumentTaxonomyExtension')){
            $tags = $this->getAllTagsMap();
            $tagField = ListboxField::create('Tags', _t('DMSDocumentTaxonomyExtension.TAGS', 'Tags'))
                ->setSource($tags);

            if (empty($tags)) {
                $tagField->setAttribute('data-placeholder', _t('DMSDocumentTaxonomyExtension.NOTAGS', 'No tags found'));
            }

            $fields->add($tagField);
        }

        $coverImageField = UploadField::create('CoverImage', _t('DMSDocument.COVERIMAGE', 'Cover Image'));
        $coverImageField->getValidator()->setAllowedExtensions(array('jpg', 'jpeg', 'png', 'gif'));
        $coverImageField->setAllowedMaxFileNumber(1);
        $fields->add($coverImageField);



        //create upload field to replace document
        $uploadField = new UploadField('ReplaceFile', 'Replace file');
        $uploadField->setAllowedMaxFileNumber(1);
        $fields->add(HeaderField::create('ReplaceFileHeader', 'File Replacement'));
        $fields->add($uploadField);
        //$uploadField->setConfig('downloadTemplateName', 'ss-dmsuploadfield-downloadtemplate');
        //$uploadField->setRecord($this);




        $gridFieldConfig = GridFieldConfig::create()->addComponents(
            new GridFieldToolbarHeader(),
            new GridFieldSortableHeader(),
            new GridFieldDataColumns(),
            new GridFieldPaginator(30),
            //new GridFieldEditButton(),
            new GridFieldDetailForm()
        );

        $gridFieldConfig->getComponentByType(GridFieldDataColumns::class)
            ->setDisplayFields(array(
                'Title' => 'Title',
                'ClassName' => 'Page Type',
                'ID' => 'Page ID'
            ))
            ->setFieldFormatting(array(
                'Title' => sprintf(
                    '<a class=\"cms-panel-link\" href=\"%s/$ID\">$Title</a>',
                    singleton(CMSPageEditController::class)->Link('show')
                )
            ));

        $pagesGrid = GridField::create(
            'Pages',
            _t('DMSDocument.RelatedPages', 'Related Pages'),
            $this->getRelatedPages(),
            $gridFieldConfig
        );

        $fields->add(HeaderField::create('PagesHeader', 'Usage'));
        $fields->add($pagesGrid);



        if ($this->canEdit()) {
            $fields->add($this->getRelatedDocumentsGridField());

            $fields->add(HeaderField::create('PermissionsHeader', 'Permissions'));

            $versionsGridFieldConfig = GridFieldConfig::create()->addComponents(
                new GridFieldToolbarHeader(),
                new GridFieldSortableHeader(),
                new GridFieldDataColumns(),
                new GridFieldPaginator(30)
            );
            $versionsGridFieldConfig->getComponentByType(GridFieldDataColumns::class)
                ->setFieldFormatting(
                    array(
                        'FilenameWithoutID' => '<a target="_blank" class="file-url" href="$Link">'
                            . '$FilenameWithoutID</a>'
                    )
                );

            $versionsGrid =  GridField::create(
                'Versions',
                _t('DMSDocument.Versions', 'Versions'),
                Versioned::get_all_versions(DMSDocument::class, $this->ID),
                $versionsGridFieldConfig
            );

            $fields->add($versionsGrid);

            $fields->add($this->getPermissionsActionPanel());
        }



        $this->extend('updateCMSFields', $fields);
        return $fields;
    }

    /**
     * Adds permissions selection fields to a composite field and returns so it can be used in the "actions panel"
     *
     * @return CompositeField
     */
    public function getPermissionsActionPanel()
    {
        $fields = FieldList::create();
        $showFields = array(
            'CanViewType'  => '',
            'ViewerGroups' => 'hide',
            'CanEditType'  => '',
            'EditorGroups' => 'hide',
        );
        /** @var SiteTree $siteTree */
        $siteTree = singleton(SiteTree::class);
        $settingsFields = $siteTree->getSettingsFields();

        foreach ($showFields as $name => $extraCss) {
            $compositeName = "Root.Settings.$name";
            /** @var FormField $field */
            if ($field = $settingsFields->fieldByName($compositeName)) {
                $field->addExtraClass($extraCss);
                $title = str_replace('page', 'document', $field->Title());
                $field->setTitle($title);

                // Remove Inherited source option from DropdownField
                if ($field instanceof DropdownField) {
                    $options = $field->getSource();
                    unset($options['Inherit']);
                    $field->setSource($options);
                }
                $fields->push($field);
            }
        }

        $this->extend('updatePermissionsFields', $fields);

        return CompositeField::create($fields);
    }

    /**
     * Return a title to use on the frontend, preferably the "title", otherwise the filename without it's numeric ID
     *
     * @return string
     */
    public function getTitle()
    {
        if ($this->getField('Title')) {
            return $this->getField('Title');
        }
        return $this->FilenameWithoutID;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if (isset($this->Embargo)) {
            //set the embargo options from the OptionSetField created in the getCMSFields method
            //do not write after clearing the embargo (write happens automatically)
            $savedDate = $this->EmbargoedUntilDate;
            $this->clearEmbargo(false); // Clear all previous settings and re-apply them on save

            if ($this->Embargo == 'Published') {
                $this->embargoUntilPublished(false);
            }
            if ($this->Embargo == 'Indefinitely') {
                $this->embargoIndefinitely(false);
            }
            if ($this->Embargo == DBDate::class) {
                $this->embargoUntilDate($savedDate, false);
            }
        }

        if (isset($this->Expiry)) {
            if ($this->Expiry == DBDate::class) {
                $this->expireAtDate($this->ExpireAtDate, false);
            } else {
                $this->clearExpiry(false);
            } // Clear all previous settings
        }

        // Set user fields
        if ($currentUserID = Member::currentUserID()) {
            if (!$this->CreatedByID) {
                $this->CreatedByID = $currentUserID;
            }
            $this->LastEditedByID = $currentUserID;
        }
    }

    /**
     * Return the relative URL of an icon for the file type, based on the
     * {@link appCategory()} value.
     *
     * Images are searched for in "dms/images/app_icons/".
     *
     * @return string
     */
    public function Icon($ext)
    {
        if (!Director::fileExists(DMS_DIR."/images/app_icons/{$ext}_32.png")) {
            $ext = File::get_app_category($ext);
        }

        if (!Director::fileExists(DMS_DIR."/images/app_icons/{$ext}_32.png")) {
            $ext = "generic";
        }

        return DMS_DIR."/images/app_icons/{$ext}_32.png";
    }

    public function Link(){
        return $this->getLink();
    }

    /**
     * Returns a link to download this DMSDocument from the DMS store
     * @return String
     */
    public function getLink()
    {
        $linkID = $this->ID;
        if($this->OriginalDMSDocumentID){
            $linkID = $this->OriginalDMSDocumentID;
        }
        $urlSegment = sprintf('%d-%s', $linkID, URLSegmentFilter::create()->filter($this->getTitle()));
        $result = Controller::join_links(Director::baseURL(), 'dmsdocument/' . $urlSegment);
        if (!$this->canView()) {
            $result = sprintf("javascript:alert('%s')", $this->getPermissionDeniedReason());
        }

        $this->extend('updateGetLink', $result);

        return $result;
    }

    /**
     * Return the extension of the file associated with the document
     *
     * @return string
     */
    public function getExtension()
    {
        return strtolower(pathinfo($this->Filename, PATHINFO_EXTENSION));
    }

    /**
     * @return string
     */
    public function getSize()
    {
        $size = $this->getAbsoluteSize();
        return ($size) ? File::format_size($size) : false;
    }

    /**
     * Return the size of the file associated with the document.
     *
     * @return string
     */
    public function getAbsoluteSize()
    {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: ->getFullPath() (case sensitive)
  * NEW: ->getFilename() (COMPLEX)
  * EXP: You may need to add ASSETS_PATH."/" in front of this ...
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
        return file_exists($this->getFilename()) ? filesize($this->getFilename()) : null;
    }

    /**
     * An alias to DMSDocument::getSize()
     *
     * @return string
     */
    public function getFileSizeFormatted()
    {
        return $this->getSize();
    }


    /**
     * @return FieldList
     */
    protected function getFieldsForFile($relationListCount)
    {
        $extension = $this->getExtension();

        $previewField = new LiteralField(
            "ImageFull",
            "<img id='thumbnailImage' class='thumbnail-preview' src='{$this->Icon($extension)}?r="
            . rand(1, 100000) . "' alt='{$this->Title}' />\n"
        );

        //count the number of pages this document is published on
        $publishedOnCount = $this->getRelatedPages()->count();
        $publishedOnValue = "$publishedOnCount pages";
        if ($publishedOnCount == 1) {
            $publishedOnValue = "$publishedOnCount page";
        }

        $relationListCountValue = "$relationListCount pages";
        if ($relationListCount == 1) {
            $relationListCountValue = "$relationListCount page";
        }

        $fields = new FieldGroup(
            $filePreview = CompositeField::create(
                CompositeField::create(
                    $previewField
                )->setName("FilePreviewImage")->addExtraClass('cms-file-info-preview'),
                CompositeField::create(
                    CompositeField::create(
                        new ReadonlyField("ID", "ID number". ':', $this->ID),
                        new ReadonlyField(
                            "FileType",
                            _t('AssetTableField.TYPE', 'File type') . ':',
                            self::get_file_type($extension)
                        ),
                        new ReadonlyField(
                            "Size",
                            _t('AssetTableField.SIZE', 'File size') . ':',
                            $this->getFileSizeFormatted()
                        ),
                        $urlField = new ReadonlyField(
                            'ClickableURL',
                            _t('AssetTableField.URL', 'URL'),
                            sprintf(
                                '<a href="%s" target="_blank" class="file-url">%s</a>',
                                $this->getLink(),
                                $this->getLink()
                            )
                        ),
                        new ReadonlyField("FilenameWithoutIDField", "Filename". ':', $this->getFilenameWithoutID()),
                        new DateField_Disabled(
                            "Created",
                            _t('AssetTableField.CREATED', 'First uploaded') . ':',
                            $this->Created
                        ),
                        new DateField_Disabled(
                            "LastEdited",
                            _t('AssetTableField.LASTEDIT', 'Last changed') . ':',
                            $this->LastEdited
                        ),
                        new ReadonlyField("PublishedOn", "Published on". ':', $publishedOnValue),
                        new ReadonlyField("ReferencedOn", "Referenced on". ':', $relationListCountValue)
                    )->setName('FilePreviewDataFields')
                )->setName("FilePreviewData")->addExtraClass('cms-file-info-data')
            )->setName("FilePreview")->addExtraClass('cms-file-info')
        );

        $fields->addExtraClass('dmsdocument-documentdetails');

        /**
          * ### @@@@ START REPLACEMENT @@@@ ###
          * WHY: upgrade to SS4
          * OLD: ->dontEscape (case sensitive)
          * NEW: ->dontEscape (COMPLEX)
          * EXP: dontEscape is not longer in use for form fields, please use HTMLReadonlyField (or similar) instead.
          * ### @@@@ STOP REPLACEMENT @@@@ ###
          */
        $urlField->dontEscape = true;

        $this->extend('updateFieldsForFile', $fields);

        return $fields;
    }

    /**
     * Takes a file and adds it to the DMSDocument storage, replacing the
     * current file.
     *
     * @param File $file
     *
     * @return $this
     */
    public function ingestFile($file)
    {
        $this->replaceDocument($file);
        $file->delete();

        return $this;
    }

    /**
     * Get a data list of documents related to this document
     *
     * @return DataList
     */
    public function getRelatedDocuments()
    {
        $documents = $this->RelatedDocuments();

        $this->extend('updateRelatedDocuments', $documents);

        return $documents;
    }

    /**
     * Get a list of related pages for this document by going through the associated document sets
     *
     * @return ArrayList
     */
    public function getRelatedPages()
    {
        $pages = ArrayList::create();

        foreach ($this->Sets() as $documentSet) {
            /** @var DocumentSet $documentSet */
            $pages->add($documentSet->Page());
        }
        $pages->removeDuplicates();

        $this->extend('updateRelatedPages', $pages);

        return $pages;
    }

    /**
     * Get a GridField for managing related documents
     *
     * @return GridField
     */
    protected function getRelatedDocumentsGridField()
    {
        $gridField = GridField::create(
            'RelatedDocuments',
            _t('DMSDocument.RELATEDDOCUMENTS', 'Related Documents'),
            $this->RelatedDocuments(),
            new GridFieldConfig_RelationEditor
        );

        $gridFieldConfig = $gridField->getConfig();

        $gridField->getConfig()->removeComponentsByType(GridFieldAddNewButton::class);
        // Move the autocompleter to the left
        $gridField->getConfig()->removeComponentsByType(GridFieldAddExistingAutocompleter::class);
        $gridField->getConfig()->addComponent(
            $addExisting = new GridFieldAddExistingAutocompleter('buttons-before-left')
        );

        // Ensure that current document doesn't get returned in the autocompleter
        $addExisting->setSearchList($this->getRelatedDocumentsForAutocompleter());

        // Restrict search fields to specific fields only
        $addExisting->setSearchFields(array('Title:PartialMatch', 'Filename:PartialMatch'));
        $addExisting->setResultsFormat('$Filename');

        $this->extend('updateRelatedDocumentsGridField', $gridField);
        return $gridField;
    }

    /**
     * Get the list of documents to show in "related documents". This can be modified via the extension point, for
     * example if you wanted to exclude embargoed documents or something similar.
     *
     * @return DataList
     */
    protected function getRelatedDocumentsForAutocompleter()
    {
        $documents = DMSDocument::get()->exclude('ID', $this->ID);
        $this->extend('updateRelatedDocumentsForAutocompleter', $documents);
        return $documents;
    }

    /**
     * Checks at least one group is selected if CanViewType || CanEditType == 'OnlyTheseUsers'
     *
     * @return ValidationResult
     */
    public function validate()
    {
        $valid = parent::validate();

        if ($this->CanViewType == 'OnlyTheseUsers' && !$this->ViewerGroups()->count()) {
            $valid->error(
                _t(
                    'DMSDocument.VALIDATIONERROR_NOVIEWERSELECTED',
                    "Selecting 'Only these people' from a viewers list needs at least one group selected."
                )
            );
        }

        if ($this->CanEditType == 'OnlyTheseUsers' && !$this->EditorGroups()->count()) {
            $valid->error(
                _t(
                    'DMSDocument.VALIDATIONERROR_NOEDITORSELECTED',
                    "Selecting 'Only these people' from a editors list needs at least one group selected."
                )
            );
        }

        return $valid;
    }

    /**
     * Returns a reason as to why this document cannot be viewed.
     *
     * @return string
     */
    public function getPermissionDeniedReason()
    {
        $result = '';

        if ($this->CanViewType == 'LoggedInUsers') {
            $result = _t('DMSDocument.PERMISSIONDENIEDREASON_LOGINREQUIRED', 'Please log in to view this document');
        }

        if ($this->CanViewType == 'OnlyTheseUsers') {
            $result = _t(
                'DMSDocument.PERMISSIONDENIEDREASON_NOTAUTHORISED',
                'You are not authorised to view this document'
            );
        }

        return $result;
    }

    /**
     * Add an "action panel" task
     *
     * @param  string $panelKey
     * @param  string $title
     * @return $this
     */
    public function addActionPanelTask($panelKey, $title)
    {
        $this->actionTasks[$panelKey] = $title;
        return $this;
    }


    /**
     * Removes an "action panel" tasks
     *
     * @param  string $panelKey
     * @return $this
     */
    public function removeActionPanelTask($panelKey)
    {
        if (array_key_exists($panelKey, $this->actionTasks)) {
            unset($this->actionTasks[$panelKey]);
        }
        return $this;
    }

    /**
     * Takes a File object or a String (path to a file) and copies it into the DMS, replacing the original document file
     * but keeping the rest of the document unchanged.
     * @param $file File object, or String that is path to a file to store
     * @return DMSDocumentInstance Document object that we replaced the file in
     */
    public function replaceDocument($file) {
        return $file;
    }
}
