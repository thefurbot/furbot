<?php

namespace App\Entity;

class MirkoComment
{
    public $entryId;
    public $content;
    public $embed;

    /**
     * Creates new comment for Mirkoblog entry
     *
     * @param int $parentId Entry ID from Mirkoblog
     * @param string $message Body of the comment
     */
    public function __construct($parentId, $message)
    {
        $this->entryId = $parentId;
        $this->content = $message;
        return $this;
    }

    /**
     * Adds file from URL
     *
     * @param string $filename URL of the file to attach (video/image)
     */
    public function file($filename)
    {
        $this->embed = $filename;
        return $this;
    }

    /**
     * Sets body of the comment
     *
     * @param string $message Body of the comment
     */
    public function message($message)
    {
        $this->content = $message;
        return $this;
    }
}
