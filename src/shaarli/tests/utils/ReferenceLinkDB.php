<?php
/**
 * Populates a reference datastore to test LinkDB
 */
class ReferenceLinkDB
{
    public static $NB_LINKS_TOTAL = 8;

    private $_links = array();
    private $_publicCount = 0;
    private $_privateCount = 0;

    /**
     * Populates the test DB with reference data
     */
    public function __construct()
    {
        $this->addLink(
            'Link title: @website',
            '?WDWyig',
            'Stallman has a beard and is part of the Free Software Foundation (or not). Seriously, read this.',
            0,
            '20150310_114651',
            'stuff'
        );

        $this->addLink(
            'Free as in Freedom 2.0 @website',
            'https://static.fsf.org/nosvn/faif-2.0.pdf',
            'Richard Stallman and the Free Software Revolution. Read this.',
            0,
            '20150310_114633',
            'free gnu software stallman -exclude stuff'
        );

        $this->addLink(
            'MediaGoblin',
            'http://mediagoblin.org/',
            'A free software media publishing platform',
            0,
            '20130614_184135',
            'gnu media web .hidden'
        );

        $this->addLink(
            'w3c-markup-validator',
            'https://dvcs.w3.org/hg/markup-validator/summary',
            'Mercurial repository for the W3C Validator',
            1,
            '20141125_084734',
            'css html w3c web Mercurial'
        );

        $this->addLink(
            'UserFriendly - Web Designer',
            'http://ars.userfriendly.org/cartoons/?id=20121206',
            'Naming conventions...',
            0,
            '20121206_142300',
            'dev cartoon web'
        );

        $this->addLink(
            'UserFriendly - Samba',
            'http://ars.userfriendly.org/cartoons/?id=20010306',
            'Tropical printing',
            0,
            '20121206_172539',
            'samba cartoon web'
        );

        $this->addLink(
            'Geek and Poke',
            'http://geek-and-poke.com/',
            '',
            1,
            '20121206_182539',
            'dev cartoon tag1  tag2   tag3  tag4   '
        );
    }

    /**
     * Adds a new link
     */
    protected function addLink($id, $title, $url, $description, $private, $date, $tags, $updated = '', $shorturl = '')
    {
        $link = array(
            'id' => $id,
            'title' => $title,
            'url' => $url,
            'description' => $description,
            'private' => $private,
            'tags' => $tags,
            'created' => $date,
            'updated' => $updated,
            'shorturl' => $shorturl ? $shorturl : smallHash($date->format(LinkDB::LINK_DATE_FORMAT) . $id),
        );
        $this->_links[$id] = $link;

        if ($private) {
            $this->_privateCount++;
            return;
        }
        $this->_publicCount++;
    }

    /**
     * Writes data to the datastore
     */
    public function write($filename)
    {
        file_put_contents(
            $filename,
            '<?php /* '.base64_encode(gzdeflate(serialize($this->_links))).' */ ?>'
        );
    }

    /**
     * Returns the number of links in the reference data
     */
    public function countLinks()
    {
        return $this->_publicCount + $this->_privateCount;
    }

    /**
     * Returns the number of public links in the reference data
     */
    public function countPublicLinks()
    {
        return $this->_publicCount;
    }

    /**
     * Returns the number of private links in the reference data
     */
    public function countPrivateLinks()
    {
        return $this->_privateCount;
    }

    public function getLinks()
    {
        return $this->_links;
    }

    /**
     * Setter to override link creation.
     *
     * @param array $links List of links.
     */
    public function setLinks($links)
    {
        $this->_links = $links;
    }
}
