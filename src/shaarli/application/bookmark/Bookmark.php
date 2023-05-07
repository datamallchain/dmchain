<?php

declare(strict_types=1);

namespace Shaarli\Bookmark;

use DateTime;
use DateTimeInterface;
use Shaarli\Bookmark\Exception\InvalidBookmarkException;

/**
 * Class Bookmark
 *
 * This class represent a single Bookmark with all its attributes.
 * Every bookmark should manipulated using this, before being formatted.
 *
 * @package Shaarli\Bookmark
 */
class Bookmark
{
    /** @var string Date format used in string (former ID format) */
    const LINK_DATE_FORMAT = 'Ymd_His';

    /** @var int Bookmark ID */
    protected $id;

    /** @var string Permalink identifier */
    protected $shortUrl;

    /** @var string Bookmark's URL - $shortUrl prefixed with `?` for notes */
    protected $url;

    /** @var string Bookmark's title */
    protected $title;

    /** @var string Raw bookmark's description */
    protected $description;

    /** @var array List of bookmark's tags */
    protected $tags;

    /** @var string|bool|null Thumbnail's URL - initialized at null, false if no thumbnail could be found */
    protected $thumbnail;

    /** @var bool Set to true if the bookmark is set as sticky */
    protected $sticky;

    /** @var DateTimeInterface Creation datetime */
    protected $created;

    /** @var DateTimeInterface datetime */
    protected $updated;

    /** @var bool True if the bookmark can only be seen while logged in */
    protected $private;

    /**
     * Initialize a link from array data. Especially useful to create a Bookmark from former link storage format.
     *
     * @param array $data
     *
     * @return $this
     */
    public function fromArray(array $data): Bookmark
    {
        $this->id = $data['id'] ?? null;
        $this->shortUrl = $data['shorturl'] ?? null;
        $this->url = $data['url'] ?? null;
        $this->title = $data['title'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->thumbnail = $data['thumbnail'] ?? null;
        $this->sticky = $data['sticky'] ?? false;
        $this->created = $data['created'] ?? null;
        if (is_array($data['tags'])) {
            $this->tags = $data['tags'];
        } else {
            $this->tags = preg_split('/\s+/', $data['tags'] ?? '', -1, PREG_SPLIT_NO_EMPTY);
        }
        if (! empty($data['updated'])) {
            $this->updated = $data['updated'];
        }
        $this->private = ($data['private'] ?? false) ? true : false;

        return $this;
    }

    /**
     * Make sure that the current instance of Bookmark is valid and can be saved into the data store.
     * A valid link requires:
     *   - an integer ID
     *   - a short URL (for permalinks)
     *   - a creation date
     *
     * This function also initialize optional empty fields:
     *   - the URL with the permalink
     *   - the title with the URL
     *
     * @throws InvalidBookmarkException
     */
    public function validate(): void
    {
        if ($this->id === null
            || ! is_int($this->id)
            || empty($this->shortUrl)
            || empty($this->created)
        ) {
            throw new InvalidBookmarkException($this);
        }
        if (empty($this->url)) {
            $this->url = '/shaare/'. $this->shortUrl;
        }
        if (empty($this->title)) {
            $this->title = $this->url;
        }
    }

    /**
     * Set the Id.
     * If they're not already initialized, this function also set:
     *   - created: with the current datetime
     *   - shortUrl: with a generated small hash from the date and the given ID
     *
     * @param int|null $id
     *
     * @return Bookmark
     */
    public function setId(?int $id): Bookmark
    {
        $this->id = $id;
        if (empty($this->created)) {
            $this->created = new DateTime();
        }
        if (empty($this->shortUrl)) {
            $this->shortUrl = link_small_hash($this->created, $this->id);
        }

        return $this;
    }

    /**
     * Get the Id.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get the ShortUrl.
     *
     * @return string|null
     */
    public function getShortUrl(): ?string
    {
        return $this->shortUrl;
    }

    /**
     * Get the Url.
     *
     * @return string|null
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Get the Title.
     *
     * @return string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Get the Description.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return ! empty($this->description) ? $this->description : '';
    }

    /**
     * Get the Created.
     *
     * @return DateTimeInterface
     */
    public function getCreated(): ?DateTimeInterface
    {
        return $this->created;
    }

    /**
     * Get the Updated.
     *
     * @return DateTimeInterface
     */
    public function getUpdated(): ?DateTimeInterface
    {
        return $this->updated;
    }

    /**
     * Set the ShortUrl.
     *
     * @param string|null $shortUrl
     *
     * @return Bookmark
     */
    public function setShortUrl(?string $shortUrl): Bookmark
    {
        $this->shortUrl = $shortUrl;

        return $this;
    }

    /**
     * Set the Url.
     *
     * @param string|null $url
     * @param string[]    $allowedProtocols
     *
     * @return Bookmark
     */
    public function setUrl(?string $url, array $allowedProtocols = []): Bookmark
    {
        $url = $url !== null ? trim($url) : '';
        if (! empty($url)) {
            $url = whitelist_protocols($url, $allowedProtocols);
        }
        $this->url = $url;

        return $this;
    }

    /**
     * Set the Title.
     *
     * @param string|null $title
     *
     * @return Bookmark
     */
    public function setTitle(?string $title): Bookmark
    {
        $this->title = $title !== null ? trim($title) : '';

        return $this;
    }

    /**
     * Set the Description.
     *
     * @param string|null $description
     *
     * @return Bookmark
     */
    public function setDescription(?string $description): Bookmark
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set the Created.
     * Note: you shouldn't set this manually except for special cases (like bookmark import)
     *
     * @param DateTimeInterface|null $created
     *
     * @return Bookmark
     */
    public function setCreated(?DateTimeInterface $created): Bookmark
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Set the Updated.
     *
     * @param DateTimeInterface|null $updated
     *
     * @return Bookmark
     */
    public function setUpdated(?DateTimeInterface $updated): Bookmark
    {
        $this->updated = $updated;

        return $this;
    }

    /**
     * Get the Private.
     *
     * @return bool
     */
    public function isPrivate(): bool
    {
        return $this->private ? true : false;
    }

    /**
     * Set the Private.
     *
     * @param bool|null $private
     *
     * @return Bookmark
     */
    public function setPrivate(?bool $private): Bookmark
    {
        $this->private = $private ? true : false;

        return $this;
    }

    /**
     * Get the Tags.
     *
     * @return string[]
     */
    public function getTags(): array
    {
        return is_array($this->tags) ? $this->tags : [];
    }

    /**
     * Set the Tags.
     *
     * @param string[]|null $tags
     *
     * @return Bookmark
     */
    public function setTags(?array $tags): Bookmark
    {
        $this->setTagsString(implode(' ', $tags ?? []));

        return $this;
    }

    /**
     * Get the Thumbnail.
     *
     * @return string|bool|null Thumbnail's URL - initialized at null, false if no thumbnail could be found
     */
    public function getThumbnail()
    {
        return !$this->isNote() ? $this->thumbnail : false;
    }

    /**
     * Set the Thumbnail.
     *
     * @param string|bool|null $thumbnail Thumbnail's URL - false if no thumbnail could be found
     *
     * @return Bookmark
     */
    public function setThumbnail($thumbnail): Bookmark
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }

    /**
     * Get the Sticky.
     *
     * @return bool
     */
    public function isSticky(): bool
    {
        return $this->sticky ? true : false;
    }

    /**
     * Set the Sticky.
     *
     * @param bool|null $sticky
     *
     * @return Bookmark
     */
    public function setSticky(?bool $sticky): Bookmark
    {
        $this->sticky = $sticky ? true : false;

        return $this;
    }

    /**
     * @return string Bookmark's tags as a string, separated by a space
     */
    public function getTagsString(): string
    {
        return implode(' ', $this->getTags());
    }

    /**
     * @return bool
     */
    public function isNote(): bool
    {
        // We check empty value to get a valid result if the link has not been saved yet
        return empty($this->url) || startsWith($this->url, '/shaare/') || $this->url[0] === '?';
    }

    /**
     * Set tags from a string.
     * Note:
     *   - tags must be separated whether by a space or a comma
     *   - multiple spaces will be removed
     *   - trailing dash in tags will be removed
     *
     * @param string|null $tags
     *
     * @return $this
     */
    public function setTagsString(?string $tags): Bookmark
    {
        // Remove first '-' char in tags.
        $tags = preg_replace('/(^| )\-/', '$1', $tags ?? '');
        // Explode all tags separted by spaces or commas
        $tags = preg_split('/[\s,]+/', $tags);
        // Remove eventual empty values
        $tags = array_values(array_filter($tags));

        $this->tags = $tags;

        return $this;
    }

    /**
     * Rename a tag in tags list.
     *
     * @param string $fromTag
     * @param string $toTag
     */
    public function renameTag(string $fromTag, string $toTag): void
    {
        if (($pos = array_search($fromTag, $this->tags)) !== false) {
            $this->tags[$pos] = trim($toTag);
        }
    }

    /**
     * Delete a tag from tags list.
     *
     * @param string $tag
     */
    public function deleteTag(string $tag): void
    {
        if (($pos = array_search($tag, $this->tags)) !== false) {
            unset($this->tags[$pos]);
            $this->tags = array_values($this->tags);
        }
    }
}
