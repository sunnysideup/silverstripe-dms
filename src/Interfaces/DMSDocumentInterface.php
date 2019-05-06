<?php

namespace Sunnysideup\DMS\Interfaces;

/**
 * Interface for a DMSDocument used in the Document Management System. A DMSDocument is create by storing a File
 * object in an instance of the DMSInterface. All write operations on the DMSDocument create a new relation, so we
 * never need to explicitly call the write() method on the DMSDocument DataObject
 */
interface DMSDocumentInterface
{
    /**
     * Returns a link to download this DMSDocument from the DMS store
     * @return String
     */
    public function getLink();

    /**
     * Return the extension of the file associated with the document
     */
    public function getExtension();

    /**
     * Returns the size of the file type in an appropriate format.
     *
     * @return string
     */
    public function getSize();

    /**
     * Return the size of the file associated with the document, in bytes.
     *
     * @return int
     */
    public function getAbsoluteSize();


    /**
     * Takes a File object or a String (path to a file) and copies it into the DMS, replacing the original document file
     * but keeping the rest of the document unchanged.
     * @param $file File object, or String that is path to a file to store
     * @return DMSDocumentInstance Document object that we replaced the file in
     */
    public function replaceDocument($file);



}
