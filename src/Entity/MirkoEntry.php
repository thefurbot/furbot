<?php

namespace App\Entity;


class MirkoEntry
{
    public $content;
    public $embed;
    public $adult = false;

    /**
     * Creates Mikroblog entry
     *
     * @param string $content Body of the entry
     */
    public function __construct($content)
    {
        $this->content = $content;
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
     * Sets entry as adult content or not
     *
     * @param bool $adult Is the content +18?
     */
    public function adult($adult)
    {
        $this->adult = $adult;
        return $this;
    }

    /**
     * Sets body of the entry
     *
     * @param string $message Body of the entry
     */
    public function message($message)
    {
        $this->content = $message;
        return $this;
    }
}
