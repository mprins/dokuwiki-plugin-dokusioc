<?php
/**
 * DokuSIOC - SIOC plugin for DokuWiki
 *
 * DokuSIOC integrates the SIOC ontology within DokuWiki and provides an
 * alternate RDF/XML views of the wiki documents.
 *
 * For DokuWiki we can't use the Triplify script because DokuWiki has not a RDBS
 * backend. But the wiki API provides enough methods to get the data out, so
 * DokuSIOC as a plugin uses the export hook to provide accessible data as
 * RDF/XML, using the SIOC ontology as vocabulary.
 * @copyright 2009 Michael Haschke
 * @copyright 2020 mprins
 * LICENCE
 *
 * This program is free software: you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation, version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * @link      http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License 2.0 (GPLv2)
 *
 */

class action_plugin_dokusioc extends DokuWiki_Action_Plugin
{

    private $agentlink = 'http://eye48.com/go/dokusioc?v=0.1.2';

    /**
     * Register it's handlers with the DokuWiki's event controller
     */
    public function register(Doku_Event_Handler $controller): void
    {
        //print_r(headers_list()); die();
        // test the requested action
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'checkAction', $controller);
    }

    /* -- Event handlers ---------------------------------------------------- */

    public function checkAction($action, $controller)
    {
        global $INFO;
        //print_r($INFO); die();
        //print_r(headers_list()); die();

        if ($action->data === 'export_siocxml') {
            // give back rdf
            $this->exportSioc();
        } elseif (($action->data === 'show' || $action->data === 'index') && $INFO['perm'] && !defined(
                'DOKU_MEDIADETAIL'
            ) && ($INFO['exists'] || getDwUserInfo($INFO['id'], $this)) && !isHiddenPage($INFO['id'])) {
            if ($this->isRdfXmlRequest()) {
                // forward to rdfxml document if requested
                // print_r(headers_list()); die();
                $location = $this->createRdfLink();
                if (function_exists('header_remove')) {
                    header_remove();
                }
                header('Location: ' . $location['href'], true, 303);
                exit();
            } else {
                // add meta link to html head
                $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'createRdfLink');
            }
        }
        /*
        else
        {
            print_r(array($action->data, $INFO['perm'], defined('DOKU_MEDIADETAIL'), $INFO['exists'],
                    getDwUserInfo($INFO['id'],$this), isHiddenPage($INFO['id'])));
            die();
        }
        */
    }

    public function exportSioc()
    {
        global $ID, $INFO;

        if (isHiddenPage($ID)) {
            $this->exit("HTTP/1.0 404 Not Found");
        }

        $sioc_type = $this->getContenttype();

        // Test for valid types
        if (!(($sioc_type == 'post' && $INFO['exists']) || $sioc_type == 'user' || $sioc_type == 'container')) {
            $this->exit("HTTP/1.0 404 Not Found");
        }

        // Test for permission
        if (!$INFO['perm']) {
            // not enough rights to see the wiki page
            $this->exit("HTTP/1.0 401 Unauthorized");
        }

        // Forward to URI with explicit type attribut
        if (!isset($_GET['type'])) {
            header('Location:' . $_SERVER['REQUEST_URI'] . '&type=' . $sioc_type, true, 302);
        }

        // Include SIOC libs
        require_once(__DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'sioc_inc.php');
        require_once(__DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'sioc_dokuwiki.php');

        // Create exporter

        $rdf              = new SIOCExporter();
        $rdf->profile_url = normalizeUri(
            getAbsUrl(
                exportlink(
                    $ID,
                    'siocxml',
                    array('type' => $sioc_type),
                    false,
                    '&'
                )
            )
        );
        $rdf->setURLParameters('type', 'id', 'page', false);

        // Create SIOC-RDF content

        switch ($sioc_type) {
            case 'container':
                $rdf = $this->exportContainercontent($rdf);
                break;

            case 'user':
                $rdf = $this->exportUsercontent($rdf);
                break;

            case 'post':
            default:
                $rdf = $this->exportPostcontent($rdf);
                break;
        }

        // export
        if ($this->getConf('noindx')) {
            header("X-Robots-Tag: noindex", true);
        }
        $rdf->export();

        //print_r(headers_list()); die();
        die();
    }

    private function exit($headermsg)
    {
        header($headermsg);
        die();
    }

    /* -- public class methods ---------------------------------------------- */

    private function getContenttype()
    {
        global $ID, $conf;

        // check for type if unknown
        if (!($_GET['type'] ?? "")) {
            $userinfo = getDwUserInfo($ID, $this);

            if ($userinfo) {
                $type = 'user';
            } elseif (isset($_GET['do']) && $_GET['do'] == 'index') {
                $type = 'container';
            } else {
                $type = 'post';
            }
        } else {
            $type = $_GET['type'];
        }

        return $type;
    }

    private function exportContainercontent($exporter)
    {
        global $ID, $INFO, $conf;

        if ($ID == $conf['start']) {
            $title = $conf['title'];
        } elseif (isset($INFO['meta']['title'])) {
            $title = $INFO['meta']['title'];
        } else {
            $title = $ID;
        }

        $exporter->setParameters(
            'Container: ' . $title,
            getAbsUrl(),
            getAbsUrl() . 'doku.php?',
            'utf-8',
            $this->agentlink
        );

        // create container object
        $wikicontainer = new SIOCDokuWikiContainer(
            $ID, normalizeUri($exporter->siocURL('container', $ID))
        );

        /* container is type=wiki */
        if ($ID == $conf['start']) {
            $wikicontainer->isWiki();
        }
        /* sioc:name              */
        if ($INFO['exists']) {
            $wikicontainer->addTitle($INFO['meta']['title']);
        }
        /* has_parent             */
        if ($INFO['namespace']) {
            $wikicontainer->addParent($INFO['namespace']);
        }

        // search next level entries (posts, sub containers) in container
        require_once(DOKU_INC . 'inc/search.php');
        $dir        = utf8_encodeFN(str_replace(':', '/', $ID));
        $entries    = array();
        $posts      = array();
        $containers = array();
        search($entries, $conf['datadir'], 'search_index', array('ns' => $ID), $dir);
        foreach ($entries as $entry) {
            if ($entry['type'] === 'f') {
                // wikisite
                $posts[] = $entry;
            } elseif ($entry['type'] === 'd') {
                // sub container
                $containers[] = $entry;
            }
        }

        // without sub content it can't be a container (so it does not exist as a container)
        if (count($posts) + count($containers) == 0) {
            $this->exit("HTTP/1.0 404 Not Found");
        }

        if (count($posts) > 0) {
            $wikicontainer->addArticles($posts);
        }
        if (count($containers) > 0) {
            $wikicontainer->addContainers($containers);
        }

        //print_r($containers);die();

        // add container to exporter
        $exporter->addObject($wikicontainer);

        return $exporter;
    }

    /* -- private helpers --------------------------------------------------- */

    private function exportUsercontent($exporter)
    {
        global $ID;

        // get user info
        $userinfo = getDwUserInfo($ID, $this);

        // no userinfo means there is no user space or user does not exists
        if ($userinfo === false) {
            $this->exit("HTTP/1.0 404 Not Found");
        }

        $exporter->setParameters(
            'Account: ' . $userinfo['name'],
            getAbsUrl(),
            getAbsUrl() . 'doku.php?',
            'utf-8',
            $this->agentlink
        );
        // create user object
        //print_r($userinfo); die();
        // $id, $url, $userid, $name, $email
        $wikiuser = new SIOCDokuWikiUser(
            $ID, normalizeUri($exporter->siocURL('user', $ID)), $userinfo['user'], $userinfo['name'], $userinfo['mail']
        );
        /* TODO: avatar (using Gravatar) */ /* TODO: creator_of */
        // add user to exporter
        $exporter->addObject($wikiuser);

        //print_r(headers_list());die();
        return $exporter;
    }

    private function exportPostcontent($exporter)
    {
        global $ID, $INFO, $REV, $conf;

        $exporter->setParameters(
            $INFO['meta']['title'] . ($REV ? ' (rev ' . $REV . ')' : ''),
            $this->getDokuUrl(),
            $this->getDokuUrl() . 'doku.php?',
            'utf-8',
            $this->agentlink
        );

        // create user object
        $dwuserpage_id = cleanID($this->getConf('userns')) . ($conf['useslash'] ? '/' : ':') . $INFO['editor'];
        // create wiki page object
        $wikipage = new SIOCDokuWikiArticle(
            $ID, // id
            normalizeUri(
                $exporter->siocURL(
                    'post',
                    $ID . ($REV ? $exporter->_urlseparator . 'rev' . $exporter->_urlequal . $REV : '')
                )
            ), // url
            $INFO['meta']['title'] . ($REV ? ' (rev ' . $REV . ')' : ''), // subject
            rawWiki($ID, $REV) // body (content)
        );
        /* encoded content   */
        $wikipage->addContentEncoded(p_cached_output(wikiFN($ID, $REV), 'xhtml'));
        /* created           */
        if (isset($INFO['meta']['date']['created'])) {
            $wikipage->addCreated(date('c', $INFO['meta']['date']['created']));
        }
        /* or modified       */
        if (isset($INFO['meta']['date']['modified'])) {
            $wikipage->addModified(date('c', $INFO['meta']['date']['modified']));
        }
        /* creator/modifier  */
        if ($INFO['editor'] && $this->getConf('userns')) {
            $wikipage->addCreator(array('foaf:maker' => '#' . $INFO['editor'], 'sioc:modifier' => $dwuserpage_id));
        }
        /* is creator        */
        if (isset($INFO['meta']['date']['created'])) {
            $wikipage->isCreator();
        }
        /* intern wiki links */
        $wikipage->addLinks($INFO['meta']['relation']['references']);

        // contributors - only for last revision b/c of wrong meta data for older revisions
        if (!$REV && $this->getConf('userns') && isset($INFO['meta']['contributor'])) {
            $cont_temp = array();
            $cont_ns   = $this->getConf('userns') . ($conf['useslash'] ? '/' : ':');
            foreach ($INFO['meta']['contributor'] as $cont_id => $cont_name) {
                $cont_temp[$cont_ns . $cont_id] = $cont_name;
            }
            $wikipage->addContributors($cont_temp);
        }

        // backlinks - only for last revision
        if (!$REV) {
            require_once(DOKU_INC . 'inc/fulltext.php');
            $backlinks = ft_backlinks($ID);
            if (count($backlinks) > 0) {
                $wikipage->addBacklinks($backlinks);
            }
        }

        // TODO: addLinksExtern

        /* previous and next revision */
        $changelog = new PageChangeLog($ID);
        $pagerevs  = $changelog->getRevisions(0, $conf['recent'] + 1);
        $prevrev   = false;
        $nextrev   = false;
        if (!$REV) {
            // latest revision, previous rev is on top in array
            $prevrev = 0;
        } else {
            // other revision
            $currentrev = array_search($REV, $pagerevs);
            if ($currentrev !== false) {
                $prevrev = $currentrev + 1;
                $nextrev = $currentrev - 1;
            }
        }
        if ($prevrev !== false && $prevrev > -1 && page_exists($ID, $pagerevs[$prevrev])) {
            /* previous revision*/
            $wikipage->addVersionPrevious($pagerevs[$prevrev]);
        }
        if ($nextrev !== false && $nextrev > -1 && page_exists($ID, $pagerevs[$nextrev])) {
            /* next revision*/
            $wikipage->addVersionNext($pagerevs[$nextrev]);
        }

        /* latest revision   */
        if ($REV) {
            $wikipage->addVersionLatest();
        }
        // TODO: topics
        /* has_container     */
        if ($INFO['namespace']) {
            $wikipage->addContainer($INFO['namespace']);
        }
        /* has_space         */
        if ($this->getConf('owners')) {
            $wikipage->addSite($this->getConf('owners'));
        }
        // TODO: dc:contributor / has_modifier
        // TODO: attachment (e.g. pictures in that dwns)

        // add wiki page to exporter
        $exporter->addObject($wikipage);
        //if ($INFO['editor'] && $this->getConf('userns')) $exporter->addObject($pageuser);

        return $exporter;
    }

    private function getDokuUrl($url = null)
    {
        return getAbsUrl($url);
    }

    public function isRdfXmlRequest(): bool
    {
        if (!isset($_SERVER['HTTP_ACCEPT']) return false;
        
        // get accepted types
        $http_accept = trim($_SERVER['HTTP_ACCEPT']);

        // save accepted types in array
        $accepted = explode(',', $http_accept);

        if ($this->getConf('softck') && strpos($_SERVER['HTTP_ACCEPT'], 'application/rdf+xml') !== false) {
            return true;
        }

        if (count($accepted) > 0) {
            // hard check, only serve RDF if it is requested first or equal to first type

            // extract accepting ratio
            $test_accept = array();
            foreach ($accepted as $format) {
                $formatspec = explode(';', $format);
                $k          = trim($formatspec[0]);
                if (count($formatspec) === 2) {
                    $test_accept[$k] = trim($formatspec[1]);
                } else {
                    $test_accept[$k] = 'q=1.0';
                }
            }

            // sort by ratio
            arsort($test_accept);
            $accepted_order = array_keys($test_accept);

            if ($accepted_order[0] === 'application/rdf+xml' || (array_key_exists(
                        'application/rdf+xml',
                        $test_accept
                    ) && $test_accept['application/rdf+xml'] === 'q=1.0')) {
                return true;
            }
        }

        // print_r($accepted_order);print_r($test_accept);die();

        return false;
    }

    /**
     */
    public function createRdfLink($event = null, $param = null)
    {
        global $ID, $INFO, $conf;

        // Test for hidden pages

        if (isHiddenPage($ID)) {
            return false;
        }

        // Get type of SIOC content

        $sioc_type = $this->getContenttype();

        // Test for valid types

        if (!(($sioc_type === 'post' && $INFO['exists']) || $sioc_type === 'user' || $sioc_type === 'container')) {
            return false;
        }

        // Test for permission

        if (!$INFO['perm']) {
            // not enough rights to see the wiki page
            return false;
        }

        $userinfo = getDwUserInfo($ID, $this);

        // Create attributes for meta link

        $metalink['type'] = 'application/rdf+xml';
        $metalink['rel']  = 'meta';

        switch ($sioc_type) {
            case 'container':
                $title     = htmlentities(
                    "Container '" . ($INFO['meta']['title'] ?? $ID) . "' (SIOC document as RDF/XML)"
                );
                $queryAttr = array('type' => 'container');
                break;

            case 'user':
                $title     = htmlentities($userinfo['name']);
                $queryAttr = array('type' => 'user');
                break;

            case 'post':
            default:
                $title     = htmlentities($INFO['meta']['title'] ?? $ID);
                $queryAttr = array('type' => 'post');
                if (isset($_GET['rev']) && $_GET['rev'] === (int)$_GET['rev']) {
                    $queryAttr['rev'] = $_GET['rev'];
                }
                break;
        }

        $metalink['title'] = $title;
        $metalink['href']  = normalizeUri(getAbsUrl(exportlink($ID, 'siocxml', $queryAttr, false, '&')));

        if ($event !== null) {
            $event->data['link'][] = $metalink;

            // set canocial link for type URIs to prevent indexing double content
            if ($_GET['type'] ?? "") {
                $event->data['link'][] = array('rel' => 'canonical', 'href' => getAbsUrl(wl($ID)));
            }
        }

        return $metalink;
    }
}

// TODO cleanup and just have this unconditionally
if (!function_exists('getAbsUrl')) {
    /**
     * @param $url
     * @return string
     * @deprecated cleanup, use build-in function
     */
    function getAbsUrl($url = null): string
    {
        if ($url === null) {
            $url = DOKU_BASE;
        }
        return str_replace(DOKU_BASE, DOKU_URL, $url);
    }
}

if (!function_exists('getDwUserEmail')) {
    /**
     * @param $user
     * @return string
     * @deprecated not used, will be removed
     */
    function getDwUserEmail($user): string
    {
        global $auth;
        if ($info = $auth->getUserData($user)) {
            return $info['mail'];
        } else {
            return false;
        }
    }
}

if (!function_exists('getDwUserInfo')) {
    /**
     * @param $id
     * @param $pobj
     * @param $key
     * @return array|false
     * @deprecated cleanup, use build-in function
     */
    function getDwUserInfo($id, $pobj, $key = null)
    {
        global $auth, $conf;

        if (!$pobj->getConf('userns')) {
            return false;
        }

        // get user id
        $userid = str_replace(cleanID($pobj->getConf('userns')) . ($conf['useslash'] ? '/' : ':'), '', $id);

        if ($info = $auth->getUserData($userid)) {
            if ($key) {
                return $info['key'];
            } else {
                return $info;
            }
        } else {
            return false;
        }
    }
}

// sort query attributes by name
if (!function_exists('normalizeUri')) {
    /**
     * @param $uri
     * @return string
     * @deprecated cleanup, use build-in function
     */
    function normalizeUri($uri): string
    {
        // part URI
        $parts = explode('?', $uri);

        // part query
        if (isset($parts[1])) {
            $query = $parts[1];

            // test separator
            $sep = '&';
            if (strpos($query, '&amp;') !== false) {
                $sep = '&amp;';
            }
            $attr = explode($sep, $query);

            sort($attr);

            $parts[1] = implode($sep, $attr);
        }

        return implode('?', $parts);
    }
}

