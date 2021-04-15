<?php
/**
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
 */

/**
 * SIOC::WikiDokuArticle object
 *
 * Contains information about a wiki article
 */
class SIOCDokuWikiArticle extends SIOCObject {

    private $type = 'sioct:WikiArticle';
    private $id = null;
    private $url = null;
    private $subject = null;
    private $creator = array();
    private $contributors = array();
    private $created = null;
    private $modified = null;
    private $links = array();
    private $backlinks = array();
    private $previous_version = null;
    private $next_version = null;
    private $latest_version = false; // show latest version
    private $has_container = null;
    private $has_space = null;
    private $content = null;
    private $content_encoded = null;
    private $is_creator = false;

    public function __construct($id, $url, $subject, $content) {
        $this->id      = $id;
        $this->url     = $url;
        $this->subject = $subject;
        $this->content = $content;
    }

    public function addCreated($created) {
        $this->created = $created;
    }

    public function addModified($modified) {
        $this->modified = $modified;
    }

    public function addCreator($creator) {
        $this->creator = $creator;
    }

    public function addContributors($contributors) {
        $this->contributors = $contributors;
    }

    public function isCreator() {
        $this->is_creator = true;
    }

    public function addLinks($links) {
        if(is_array($links) && count($links) > 0) {
            $this->links = $links;
        }
    }

    public function addBacklinks($links) {
        $this->backlinks = $links;
    }

    //function addLinksExtern($links) { if (is_array($links) && count($links)>0) $this->ext_links = $links; }
    public function addVersionPrevious($rev) {
        $this->previous_version = $rev;
    }

    public function addVersionNext($rev) {
        $this->next_version = $rev;
    }

    public function addVersionLatest() {
        $this->latest_version = true;
    }

    public function addContentEncoded($encoded) {
        $this->content_encoded = $encoded;
    }

    public function addContainer($id) {
        $this->has_container = $id;
    }

    public function addSite($url) {
        $this->has_space = $url;
    }

    public function getContent(&$exp): string {
        $rdf = '<' . $this->type . " rdf:about=\"" . clean($this->url, true) . "\">\n";
        if($this->subject) {
            $rdf .= "\t<dc:title>" . clean($this->subject) . "</dc:title>\n";
            // if(strcmp($this->has_container, 'http://en.wikipedia.org')===0)
            //    $rdf .= "\t<foaf:primaryTopic rdf:resource=\"".clean('http://dbpedia.org/resource/'
            //      .$this->subject)."\"/>\n";
        }

        $creator_name = null;

        if(count($this->contributors) > 0) {
            foreach($this->contributors as $cont_id => $cont_name) {
                if(!isset($this->creator['sioc:modifier']) || ($this->creator['sioc:modifier'] != $cont_id)) {
                    $rdf .= "\t<sioc:has_modifier rdf:resource=\"" . normalizeUri($exp->siocURL('user', $cont_id))
                        . "\" rdfs:label=\"" . clean($cont_name) . "\"/>\n";
                }
            }

            if(isset($this->contributors[$this->creator['sioc:modifier']])) {
                $creator_name = 'rdfs:label="' . clean($this->contributors[$this->creator['sioc:modifier']]) . '"';
            }
        }

        if(is_array($this->creator)) {
            // if ($this->creator['foaf:maker'])
            //     $rdf .= "\t<foaf:maker rdf:resource=\"".clean($this->creator['foaf:maker'])."\"/>\n";
            if($this->creator['sioc:modifier']) {
                if($this->is_creator === false) {
                    $rdf .= "\t<sioc:has_modifier rdf:resource=\""
                        . normalizeUri($exp->siocURL('user', $this->creator['sioc:modifier'])) . "\" $creator_name/>\n";
                }
                if($this->is_creator === true) {
                    $rdf .= "\t<sioc:has_creator rdf:resource=\""
                        . normalizeUri($exp->siocURL('user', $this->creator['sioc:modifier'])) . "\" $creator_name/>\n";
                }
            }
        }

        if($this->created) {
            $rdf .= "\t<dcterms:created>" . $this->created . "</dcterms:created>\n";
        }

        if($this->modified) {
            $rdf .= "\t<dcterms:modified>" . $this->modified . "</dcterms:modified>\n";
        }

        if($this->has_space) {
            $rdf .= "\t<sioc:has_space rdf:resource=\"" . clean($this->has_space, true) . "\" />\n";
            // TODO: rdfs:label
        }

        if($this->has_container) {
            $rdf .= "\t<sioc:has_container rdf:resource=\""
                . normalizeUri($exp->siocURL('container', $this->has_container)) . "\" />\n";
            // TODO: rdfs:label
        }

        if($this->content) {
            $rdf .= "\t<sioc:content><![CDATA[" . pureContent($this->content) . "]]></sioc:content>\n";
        }

        if($this->content_encoded) {
            $rdf .= "\t<content:encoded><![CDATA[" . $this->content_encoded . "]]></content:encoded>\n";
        }

        /*
        if(is_array($this->topics)) {
            foreach($this->topics as $topic=>$url) {
                $rdf .= "\t<sioc:topic>\n";
                $rdf .= "\t\t<sioct:Category rdf:about=\"" . clean($url) ."\">\n";
                $rdf .= "\t\t\t<rdfs:seeAlso rdf:resource=\"" .
                        clean('http://ws.sioc-project.org/mediawiki/mediawiki.php?wiki='.$url);
                if ($this->api) $rdf .= clean("&api=" . $this->api);
                $rdf .= "\"/>\n";
                $rdf .= "\t\t</sioct:Category>\n";
                $rdf .= "\t</sioc:topic>\n";
            }
        }
        */

        if(is_array($this->links) && count($this->links) > 0) {
            foreach($this->links as $link_id => $link_exists) {
                if($link_exists && !isHiddenPage($link_id)) {
                    $rdf .= "\t<sioc:links_to rdf:resource=\""
                        . normalizeUri($exp->siocURL('post', $link_id)) . "\"/>\n";
                    // TODO: rdfs:label
                }
            }
        }

        if(count($this->backlinks) > 0) {
            foreach($this->backlinks as $link_id) {
                if(!isHiddenPage($link_id)) {
                    $rdf .= "\t<dcterms:isReferencedBy rdf:resource=\""
                        . normalizeUri($exp->siocURL('post', $link_id)) . "\"/>\n";
                    // TODO: rdfs:label
                }
            }
        }

        /*
        if(is_array($this->ext_links)) {
            foreach($this->ext_links as $label=>$url) {
                $rdf .= "\t<sioc:links_to rdf:resource=\"" . clean($url) ."\"/>\n";
            }
        }
        */

        if($this->previous_version) {
            $rdf .= "\t<sioc:previous_version rdf:resource=\""
                . normalizeUri(
                    $exp->siocURL(
                        'post', $this->id . $exp->_urlseparator . 'rev' . $exp->_urlequal
                              . $this->previous_version
                    )
                ) . "\"/>\n";
            // TODO: rdfs:label

            /* If there is support for inference and transitivity the following is not needed */
            $rdf .= "\t<sioc:earlier_version rdf:resource=\""
                . normalizeUri(
                    $exp->siocURL(
                        'post', $this->id . $exp->_urlseparator . 'rev' . $exp->_urlequal
                              . $this->previous_version
                    )
                ) . "\"/>\n";
            // TODO: rdfs:label

        }

        if($this->next_version) {
            $rdf .= "\t<sioc:next_version rdf:resource=\""
                . normalizeUri(
                    $exp->siocURL(
                        'post', $this->id . $exp->_urlseparator . 'rev' . $exp->_urlequal
                              . $this->next_version
                    )
                ) . "\"/>\n";
            // TODO: rdfs:label

            /* If there is support for inference and transitivity the following is not needed */
            $rdf .= "\t<sioc:later_version rdf:resource=\""
                . normalizeUri(
                    $exp->siocURL(
                        'post', $this->id . $exp->_urlseparator . 'rev' . $exp->_urlequal
                              . $this->next_version
                    )
                ) . "\"/>\n";
            // TODO: rdfs:label
        }

        if($this->latest_version) {
            $rdf .= "\t<sioc:latest_version rdf:resource=\""
                . normalizeUri($exp->siocURL('post', $this->id)) . "\"/>\n";
            // TODO: rdfs:label
        }

        /*
        if($this->has_discussion && (strpos($this->has_discussion, 'Talk:Talk:') == FALSE)) {
                $rdf .= "\t<sioc:has_discussion>\n";
                $rdf .= "\t\t<sioct:WikiArticle rdf:about=\"" . clean($this->has_discussion) ."\">\n";
                $rdf .= "\t\t\t<rdfs:seeAlso rdf:resource=\"" .
                        clean('http://ws.sioc-project.org/mediawiki/mediawiki.php?wiki='.$this->has_discussion);
                if ($this->api) $rdf .= clean("&api=" . $this->api);
                $rdf .= "\"/>\n";
                $rdf .= "\t\t</sioct:WikiArticle>\n";
                $rdf .= "\t</sioc:has_discussion>\n";
        }
        */

        /*
        if($this->redirpage)
        {
            $rdf .= "\t<owl:sameAs rdf:resource=\"" . clean($this->redirpage) ."\"/>\n";
            $rdf .= "\t<rdfs:seeAlso rdf:resource=\"" . 
                        clean('http://ws.sioc-project.org/mediawiki/mediawiki.php?wiki='.$this->redirpage);
            if ($this->api) $rdf .= clean("&api=" . $this->api);
            $rdf .= "\"/>\n";
        }
        */

        $rdf .= "</" . $this->type . ">\n";
        return $rdf;
    }
}

/**
 * SIOC::DokuWikiUser object
 *
 * Contains information about a wiki user
 */
class SIOCDokuWikiUser extends SIOCObject {

    private $type = 'user';

    private $id;
    private $nick;
    private $url;
    private $name;
    private $email;
    private $sha1;
    private $homepage;
    private $foaf_uri;
    private $role;
    private $sioc_url;
    private $foaf_url;

    public function __construct($id, $url, $userid, $name, $email) {
        $this->id   = $id;
        $this->nick = $userid;
        $this->name = $name;
        //$this->email = $email;
        $this->url = $url;

        if(preg_match_all('/^.+@.+\..+$/Ui', $email, $check, PREG_SET_ORDER)) {
            if(preg_match_all('/^mailto:(.+@.+\..+$)/Ui', $email, $matches, PREG_SET_ORDER)) {
                $this->email = $email;
                $this->sha1  = sha1($email);
            } else {
                $this->email = "mailto:" . $email;
                $this->sha1  = sha1("mailto:" . $email);
            }
        }
    }

    public function getContent(&$exp): string {
        $rdf = "<sioc:UserAccount rdf:about=\"" . clean($this->url, true) . "\">\n";
        if($this->nick) {
            $rdf .= "\t<sioc:name>" . clean($this->nick) . "</sioc:name>\n";
        }
        if($this->email) {
            if($exp->_export_email) {
                $rdf .= "\t<sioc:email rdf:resource=\"" . $this->email . "\"/>\n";
            }
            $rdf .= "\t<sioc:email_sha1>" . $this->sha1 . "</sioc:email_sha1>\n";
        }
        if($this->role) {
            $rdf .= "\t<sioc:has_function>\n";
            $rdf .= "\t\t<sioc:Role>\n";
            $rdf .= "\t\t\t<sioc:name>" . $this->role . "</sioc:name>\n";
            $rdf .= "\t\t</sioc:Role>\n";
            $rdf .= "\t</sioc:has_function>\n";
        }
        $rdf .= "\t<sioc:account_of>\n";
        $rdf .= "\t\t<foaf:Person>\n";
        if($this->name) {
            $rdf .= "\t\t\t<foaf:name>" . clean($this->name) . "</foaf:name>\n";
        }
        if($this->email) {
            $rdf .= "\t\t\t<foaf:mbox_sha1sum>" . $this->sha1 . "</foaf:mbox_sha1sum>\n";
        }
        if($this->foaf_url) {
            $rdf .= "\t\t\t<rdfs:seeAlso rdf:resource=\"" . $this->foaf_url . "\"/>\n";
        }
        $rdf .= "\t\t</foaf:Person>\n";
        $rdf .= "\t</sioc:account_of>\n";
        //if($this->sioc_url) { $rdf .= "\t\t\t<rdfs:seeAlso rdf:resource=\"". $this->sioc_url ."\"/>\n"; }
        $rdf .= "</sioc:UserAccount>\n";

        return $rdf;
    }
}

/**
 * SIOC::DokuWikiContainer object
 *
 * Contains information about a wiki container
 */
class SIOCDokuWikiContainer extends SIOCObject {

    private $type = 'sioc:Container';

    private $id = null;
    private $url = null;
    private $posts = array();
    private $subcontainers = array();
    private $has_parent = null;
    private $title = null;

    public function __construct($id, $url) {
        $this->id  = $id;
        $this->url = $url;
    }

    public function isWiki() {
        $this->type = 'sioct:Wiki';
    }

    public function addArticles($posts) {
        $this->posts = $posts;
    }

    public function addContainers($containers) {
        $this->subcontainers = $containers;
    }

    public function addTitle($title) {
        $this->title = $title;
    }

    public function addParent($id) {
        $this->_has_parent = $id;
    }

    public function getContent(&$exp): string {
        $rdf = '<' . $this->type . " rdf:about=\"" . normalizeUri(clean($this->url, true)) . "\" >\n";

        if($this->title) {
            $rdf .= "\t<sioc:name>" . clean($this->title) . "</sioc:name>\n";
        }

        if($this->_has_parent) {
            $rdf .= "\t<sioc:has_parent rdf:resource=\""
                . normalizeUri($exp->siocURL('container', $this->_has_parent)) . "\" />\n";
            // TODO: rdfs:label
        }

        foreach($this->posts as $article) {
            // TODO: test permission before?
            $rdf .= "\t<sioc:container_of rdf:resource=\""
                . normalizeUri($exp->siocURL('post', $article['id'])) . "\"/>\n";
            // TODO: inluding title/name
        }

        foreach($this->subcontainers as $container) {
            $rdf .= "\t<sioc:parent_of rdf:resource=\""
                . normalizeUri($exp->siocURL('container', $container['id'])) . "\"/>\n";
            // TODO: inluding title/name
        }

        $rdf .= "</" . $this->type . ">\n";
        return $rdf;
    }

}

