<?php

/**
 * This class is in charge of building the final page.
 * (This is basically a wrapper around RainTPL which pre-fills some fields.)
 * $p = new PageBuilder();
 * $p->assign('myfield','myvalue');
 * $p->renderPage('mytemplate');
 */
class PageBuilder
{
    /**
     * @var RainTPL RainTPL instance.
     */
    private $tpl;

    /**
     * @var ConfigManager $conf Configuration Manager instance.
     */
    protected $conf;

    /**
     * PageBuilder constructor.
     * $tpl is initialized at false for lazy loading.
     *
     * @param ConfigManager $conf Configuration Manager instance (reference).
     */
    function __construct(&$conf)
    {
        $this->tpl = false;
        $this->conf = $conf;
    }

    /**
     * Initialize all default tpl tags.
     */
    private function initialize()
    {
        $this->tpl = new RainTPL();

        try {
            $version = ApplicationUtils::checkUpdate(
                shaarli_version,
                $this->conf->get('path.update_check'),
                $this->conf->get('general.check_updates_interval'),
                $this->conf->get('general.check_updates'),
                isLoggedIn(),
                $this->conf->get('general.check_updates_branch')
            );
            $this->tpl->assign('newVersion', escape($version));
            $this->tpl->assign('versionError', '');

        } catch (Exception $exc) {
            logm($this->conf->get('path.log'), $_SERVER['REMOTE_ADDR'], $exc->getMessage());
            $this->tpl->assign('newVersion', '');
            $this->tpl->assign('versionError', escape($exc->getMessage()));
        }

        $this->tpl->assign('feedurl', escape(index_url($_SERVER)));
        $searchcrits = ''; // Search criteria
        if (!empty($_GET['searchtags'])) {
            $searchcrits .= '&searchtags=' . urlencode($_GET['searchtags']);
        }
        if (!empty($_GET['searchterm'])) {
            $searchcrits .= '&searchterm=' . urlencode($_GET['searchterm']);
        }
        $this->tpl->assign('searchcrits', $searchcrits);
        $this->tpl->assign('source', index_url($_SERVER));
        $this->tpl->assign('version', shaarli_version);
        $this->tpl->assign('scripturl', index_url($_SERVER));
        $this->tpl->assign('pagetitle', 'Shaarli');
        $this->tpl->assign('privateonly', !empty($_SESSION['privateonly'])); // Show only private links?
        if ($this->conf->exists('general.title')) {
            $this->tpl->assign('pagetitle', $this->conf->get('general.title'));
        }
        if ($this->conf->exists('general.header_link')) {
            $this->tpl->assign('titleLink', $this->conf->get('general.header_link'));
        }
        if ($this->conf->exists('pagetitle')) {
            $this->tpl->assign('pagetitle', $this->conf->get('pagetitle'));
        }
        $this->tpl->assign('shaarlititle', $this->conf->get('title', 'Shaarli'));
        $this->tpl->assign('openshaarli', $this->conf->get('extras.open_shaarli', false));
        $this->tpl->assign('showatom', $this->conf->get('extras.show_atom', false));
        $this->tpl->assign('hide_timestamps', $this->conf->get('extras.hide_timestamps', false));
        if (!empty($GLOBALS['plugin_errors'])) {
            $this->tpl->assign('plugin_errors', $GLOBALS['plugin_errors']);
        }
    }

    /**
     * The following assign() method is basically the same as RainTPL (except lazy loading)
     *
     * @param string $placeholder Template placeholder.
     * @param mixed  $value       Value to assign.
     */
    public function assign($placeholder, $value)
    {
        if ($this->tpl === false) {
            $this->initialize();
        }
        $this->tpl->assign($placeholder, $value);
    }

    /**
     * Assign an array of data to the template builder.
     *
     * @param array $data Data to assign.
     *
     * @return false if invalid data.
     */
    public function assignAll($data)
    {
        if ($this->tpl === false) {
            $this->initialize();
        }

        if (empty($data) || !is_array($data)){
            return false;
        }

        foreach ($data as $key => $value) {
            $this->assign($key, $value);
        }
        return true;
    }

    /**
     * Render a specific page (using a template file).
     * e.g. $pb->renderPage('picwall');
     *
     * @param string $page Template filename (without extension).
     */
    public function renderPage($page)
    {
        if ($this->tpl === false) {
            $this->initialize();
        }

        $this->tpl->draw($page);
    }

    /**
     * Render a 404 page (uses the template : tpl/404.tpl)
     * usage : $PAGE->render404('The link was deleted')
     *
     * @param string $message A messate to display what is not found
     */
    public function render404($message = 'The page you are trying to reach does not exist or has been deleted.')
    {
        header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
        $this->tpl->assign('error_message', $message);
        $this->renderPage('404');
    }
}
