<?php

namespace App\Entity;

use App\Repository\PostRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PostRepository::class)
 */
class Post
{
    const STATUS_CHECKED = 'checked';
    const STATUS_NO_VALID_SAUCES = 'no_valid_sauces';
    const STATUS_WYKOP_ERROR = 'wykop_error';
    const STATUS_COMMENTED = 'commented';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $entryId;

    /**
     * @ORM\Column(type="string", length=20)
     */
    private $status;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $sauces = [];

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $message;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $rawSauces = [];

    /**
     * @ORM\Column(type="datetime")
     */
    private $time;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $author;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $embed;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntryId(): ?int
    {
        return $this->entryId;
    }

    public function setEntryId(int $entryId): self
    {
        $this->entryId = $entryId;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getSauces(): ?array
    {
        return $this->sauces;
    }

    public function setSauces(?array $sauces): self
    {
        $this->sauces = $sauces;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getRawSauces(): ?array
    {
        return $this->rawSauces;
    }

    public function setRawSauces(?array $rawSauces): self
    {
        $this->rawSauces = $rawSauces;

        return $this;
    }

    public function getTime(): ?\DateTimeInterface
    {
        return $this->time;
    }

    public function setTime(\DateTimeInterface $time): self
    {
        $this->time = $time;

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(string $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getEmbed(): ?string
    {
        return $this->embed;
    }

    public function setEmbed(?string $embed): self
    {
        $this->embed = $embed;

        return $this;
    }
}
