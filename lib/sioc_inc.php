<?php
/**
 * SIOC Exporter API.
 *
 * Allow people to easilly create their own SIOC Exporter for any PHP application
 *
 * @package sioc_inc
 * @author  Alexandre Passant <alex@passant.org>
 * @author  Uldis Bojars <captsolo@gmail.com> (adaptation to PHP4)
 * @author  Thomas Schandl <tom.schandl@gmail.com> (addition of SIOCThread)
 * @author  Fabrizio Orlandi <fabrizio.orlandi@deri.org> (addition of SIOCWIki SIOCWikiArticle SIOCCategory)
 *
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
 */

define('AUTHORS_NODE', 'authors');
define('EXPORTER_URL', 'http://wiki.sioc-project.org/index.php/PHPExportAPI');
define('EXPORTER_VERSION', '1.01');

/**
 * Main exporter class.
 *
 * Generates RDF/XML content of SIOC export.
 * - Sets up parameters for generating RDF/XML
 * - Sets up parameters for SIOC URL generation
 */
class SIOCExporter {

    public $profile_url;
    private $title;
    private $blog_url;
    private $sioc_url;
    private $encoding;
    private $generator;
    private $urlseparator;
    private $urlequal;
    private $url4type; // e.g. type   or   sioc_type
    private $url4id; // TS e. g.  id    or sioc_id
    private $url4page;
    // TS: if true: appends the "type" of a class to the url4id in order to compose the string for the "id part"
    // of the siocURL. e. g. for a forum that could produce "forum_id=" or "forum_sioc_id="
    private $url_usetype;
    private $url_suffix; // TS:  custom parameter to be appended at the end of a siocURL
    private $type_table;
    private $ignore_suffix; // TS: for types in this table the url_suffix  won't be appended to their siocURL
    private $export_email;
    private $objects;

    public function __construct() {
        $this->urlseparator  = '&';
        $this->urlequal      = '=';
        $this->url4type      = 'type';
        $this->url4id        = 'id';
        $this->url4page      = 'page';
        $this->url_usetype   = true;
        $this->url_suffix    = '';
        $this->type_table    = array();
        $this->ignore_suffix = array();
        $this->export_email  = false;
        $this->encoding      = 'UTF-8';
        $this->objects       = array();
    }

    public function setURLParameters(
        $type = 'type',
        $id = 'id',
        $page = 'page',
        $url_usetype = true,
        $urlseparator = '&',
        $urlequal = '=',
        $suffix = ''
    ) {
        $this->urlseparator = $urlseparator;
        $this->urlequal     = $urlequal;
        $this->url4type     = $type;
        $this->url4id       = $id;
        $this->url4page     = $page;
        $this->url_usetype  = $url_usetype;
        $this->url_suffix   = $suffix;
    }

    public function setParameters($title, $url, $sioc_url, $encoding, $generator, $export_email = false) {
        $this->title        = $title;
        $this->blog_url     = $url;
        $this->sioc_url     = $sioc_url;
        $this->encoding     = $encoding;
        $this->generator    = $generator;
        $this->export_email = $export_email;
    }

    // Assigns some objects to the exporter
    public function addObject(&$obj) {
        $this->objects[] = &$obj;
    }

    // TS: Used to replace url4id in the siocURL for a given type (site, forum, etc.) with a
    // parameter ($name) of your choice
    // E.g. b2evo exporter uses "blog=" instead of "sioc_id=" in the siocURL of a forum
    public function setURLTypeParm($type, $name) {
        $this->type_table[$type] = $name;
    }

    public function setSuffixIgnore($type) {
        $this->ignore_suffix[$type] = 1;
    }

    public function siocURL($type, $id, $page = "") {
        $type_part = $this->url4type . $this->urlequal . $type;

        if($id) {
            if(isset($this->type_table[$type]))
                $myID = $this->type_table[$type];
            else
                $myID = (($this->url_usetype) ? $type . '_' : '') . $this->url4id;

            $id_part = $this->urlseparator . $myID . $this->urlequal . $id;
        } else {
            $id_part = '';
        }

        ($page) ? $page_part = $this->urlseparator . $this->url4page . $this->urlequal . $page : $page_part = '';

        ($this->url_suffix && !isset($this->ignore_suffix[$type])) ? $suffix = $this->urlseparator
            . $this->url_suffix : $suffix = '';

        $siocURL = $this->sioc_url . $type_part . $id_part . $page_part . $suffix;
        return clean($siocURL, true);
    }

    public function export($rdf_content = '') {
        header('Content-Type: application/rdf+xml; charset=' . $this->encoding, true, 200);
        echo $this->makeRDF($rdf_content);
    }

    public function makeRDF($rdf_content = '') {
        $rdf = '<?xml version="1.0" encoding="' . $this->encoding . '" ?>' . "\n";
        $rdf .= ' 
<rdf:RDF
    xmlns="http://xmlns.com/foaf/0.1/"
    xmlns:foaf="http://xmlns.com/foaf/0.1/"
    xmlns:admin="http://webns.net/mvcb/"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:dcterms="http://purl.org/dc/terms/"
    xmlns:dcmitype="http://purl.org/dc/dcmitype/"
    xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
    xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
    xmlns:sioc="http://rdfs.org/sioc/ns#"
    xmlns:sioct="http://rdfs.org/sioc/types#"
    xmlns:owl="http://www.w3.org/2002/07/owl">
<foaf:Document rdf:about="' . clean($this->profile_url, true) . '">
	<dc:title>"' . clean($this->title) . '" (SIOC profile)</dc:title>
	<foaf:primaryTopic rdf:resource="' . clean($this->objects[0]->_url, true) . '"/>
	<admin:generatorAgent rdf:resource="' . clean($this->generator, true) . '"/>
	<admin:generatorAgent rdf:resource="' . clean(EXPORTER_URL, true) . '?version=' . EXPORTER_VERSION . '"/>
</foaf:Document>' . "\n";
        if($rdf_content) {
            $rdf .= $rdf_content;
        }
        if(count($this->objects)) {
            foreach($this->objects as $object) {
                if($object) {
                    $rdf .= $object->getContent($this);
                }
            }
        }
        $rdf .= "\n</rdf:RDF>";
        return $rdf;
    }
}

/**
 * Generic SIOC Object
 *
 * All SIOC objects are derived from this.
 */
class SIOCObject {
    protected $note = '';

    public function addNote($note) {
        $this->note = $note;
    }

    public function getContent(&$exp): string {
        $rdf = "<sioc:Object>\n";
        $rdf .= "    <rdfs:comment>Generic SIOC Object</rdfs:comment>\n";
        $rdf .= "</sioc:Object>\n";
        return $rdf;
    }
}

/**
 * SIOC::Site object
 *
 * Contains information about main SIOC page including:
 *  - site description
 *  - list of forums
 *  - list of users
 */
class SIOCSite extends SIOCObject {

    private $type = 'site';

    private $url;
    private $name;
    private $description;
    private $forums;
    private $users;
    private $page;
    private $next_users;
    private $next_forums;
    private $usergroup_uri;

    public function __construct($url, $name, $description, $page = '', $usergroup_uri = '') {
        $this->url           = $url;
        $this->name          = $name;
        $this->description   = $description;
        $this->forums        = array();
        $this->users         = array();
        $this->page          = $page;
        $this->next_users    = false;
        $this->next_forums   = false;
        $this->usergroup_uri = $usergroup_uri;
    }

    public function addForum($id, $url) {
        $this->forums[$id] = $url;
    }

    public function addUser($id, $url) {
        $this->users[$id] = $url;
    }

    public function setNextPageUsers($next) {
        $this->next_users = $next;
    }

    public function setNextPageForums($next) {
        $this->next_forums = $next;
    }

    public function getContent(&$exp): string {
        $rdf = "<sioc:Site rdf:about=\"" . clean($this->url) . "\">\n";
        $rdf .= "    <dc:title>" . clean($this->name) . "</dc:title>\n";
        $rdf .= "    <dc:description>" . clean($this->description) . "</dc:description>\n";
        $rdf .= "    <sioc:link rdf:resource=\"" . clean($this->url) . "\"/>\n";
        if($this->forums) {
            foreach($this->forums as $id => $url) {
                $rdf .= "    <sioc:host_of rdf:resource=\"" . clean($url) . "\"/>\n";
            }
        }
        if($this->next_forums) {
            $rdf .= "    <rdfs:seeAlso rdf:resource=\"" . $exp->siocURL('site', "", $this->page + 1) . "\"/>\n";
        }
        if($this->usergroup_uri) {
            $rdf .= "    <sioc:has_Usergroup rdf:resource=\"" . $this->usergroup_uri . "\"/>\n";
        } else {
            $rdf .= "    <sioc:has_Usergroup rdf:nodeID=\"" . AUTHORS_NODE . "\"/>\n";
        }
        $rdf .= "</sioc:Site>\n";
        // Forums
        if($this->forums) {
            $rdf .= "\n";
            foreach($this->forums as $id => $url) {
                $rdf .= '<sioc:Forum rdf:about="' . clean($url) . "\">\n";
                $rdf .= "    <sioc:link rdf:resource=\"" . clean($url) . "\"/>\n";
                $rdf .= "    <rdfs:seeAlso rdf:resource=\"" . $exp->siocURL('forum', $id) . "\"/>\n";
                $rdf .= "</sioc:Forum>\n";
            }
        }
        // Usergroup
        if($this->users) {
            $rdf .= "\n";
            if($this->usergroup_uri) {
                $rdf .= '<sioc:UserAccountgroup rdf:about="' . $this->usergroup_uri . "\">\n";
            } else {
                $rdf .= '<sioc:UserAccountgroup rdf:nodeID="' . AUTHORS_NODE . "\">\n";
            }
            $rdf .= "    <sioc:name>Authors for \"" . clean($this->name) . "\"</sioc:name>\n";
            foreach($this->users as $id => $url) {
                $rdf .= "    <sioc:has_member>\n";
                $rdf .= "        <sioc:UserAccount rdf:about=\"" . clean($url) . "\">\n";
                $rdf .= "            <rdfs:seeAlso rdf:resource=\"" . $exp->siocURL('user', $id) . "\"/>\n";
                $rdf .= "        </sioc:UserAccount>\n";
                $rdf .= "    </sioc:has_member>\n";
            }
            if($this->next_users) {
                $rdf .= "    <rdfs:seeAlso rdf:resource=\"" . $exp->siocURL('site', "", $this->page + 1) . "\"/>\n";
            }
            $rdf .= "</sioc:UserAccountgroup>\n";
        }

        return $rdf;
    }
}

// Export detaille d'un utilisateur

/**
 * SIOC::User object
 *
 * Contains user profile information
 */
class SIOCUser extends SIOCObject {

    private $type = 'user';

    private $id;
    private $nick;
    private $uri;
    private $name;
    private $email;
    private $sha1;
    private $homepage;
    private $foaf_uri;
    private $role;
    private $sioc_url;
    private $foaf_url;

    public function __construct(
        $id,
        $uri,
        $name,
        $email,
        $homepage = '',
        $foaf_uri = '',
        $role = false,
        $nick = '',
        $sioc_url = '',
        $foaf_url = ''
    ) {
        $this->id   = $id;
        $this->uri  = $uri;
        $this->name = $name;

        if(preg_match_all('/^.+@.+\..+$/Ui', $email, $check, PREG_SET_ORDER)) {
            if(preg_match_all('/^mailto:(.+@.+\..+$)/Ui', $email, $matches, PREG_SET_ORDER)) {
                $this->email = $email;
                $this->sha1  = sha1($email);
            } else {
                $this->email = "mailto:" . $email;
                $this->sha1  = sha1("mailto:" . $email);
            }
        }
        $this->homepage = $homepage;
        $this->foaf_uri = $foaf_uri;
        $this->_url     = $foaf_uri;
        $this->role     = $role;
        $this->nick     = $nick;
        $this->foaf_url = $foaf_url;
        $this->sioc_url = $sioc_url;
    }

    public function getContent(&$exp): string {
        $rdf = "<foaf:Person rdf:about=\"" . clean($this->foaf_uri) . "\">\n";
        if($this->name) {
            $rdf .= "    <foaf:name>" . $this->name . "</foaf:name>\n";
        }
        if($this->email) {
            $rdf .= "    <foaf:mbox_sha1sum>" . $this->sha1 . "</foaf:mbox_sha1sum>\n";
        }
        if($this->foaf_url) {
            $rdf .= "    <rdfs:seeAlso rdf:resource=\"" . $this->foaf_url . "\"/>\n";
        }
        $rdf .= "    <foaf:holdsAccount>\n";
        $rdf .= "        <sioc:UserAccount rdf:about=\"" . clean($this->uri) . "\">\n";
        if($this->nick) {
            $rdf .= "            <sioc:name>" . $this->nick . "</sioc:name>\n";
        }
        if($this->email) {
            if($exp->_export_email) {
                $rdf .= "            <sioc:email rdf:resource=\"" . $this->email . "\"/>\n";
            }
            $rdf .= "            <sioc:email_sha1>" . $this->sha1 . "</sioc:email_sha1>\n";
        }
        if($this->role) {
            $rdf .= "            <sioc:has_function>\n";
            $rdf .= "                <sioc:Role>\n";
            $rdf .= "                    <sioc:name>" . $this->role . "</sioc:name>\n";
            $rdf .= "                </sioc:Role>\n";
            $rdf .= "            </sioc:has_function>\n";
        }
        if($this->sioc_url) {
            $rdf .= "            <rdfs:seeAlso rdf:resource=\"" . $this->sioc_url . "\"/>\n";
        }
        $rdf .= "        </sioc:UserAccount>\n";
        $rdf .= "    </foaf:holdsAccount>\n";
        $rdf .= "</foaf:Person>\n";
        return $rdf;
    }
}

// Export detaille d'un utilisateur

/**
 * SIOC::Thread object
 *
 * Contains information about a SIOC Thread in a SIOC Forum
 * - list of posts in that thread
 */
class SIOCThread extends SIOCObject {

    private $type = 'thread';
    private $id;
    private $url;
    private $page;
    private $posts;
    private $next;
    private $views;
    private $tags;
    private $related;
    private $title;
    private $created;
    private $parents;
    /**
     * @var mixed|string
     */
    private $subject;

    public function __construct($id, $url, $page, $views = '', $tags = array(), $subject = '', $created = '') {
        $this->id      = $id;
        $this->url     = $url;
        $this->page    = $page;
        $this->posts   = array();
        $this->next    = false;
        $this->views   = $views;
        $this->tags    = $tags;
        $this->related = array();
        $this->subject = $subject;
        $this->created = $created;
    }

    public function addPost($id, $url, $prev = '', $next = '') {
        $this->posts[$id] = array("url" => $url, "prev" => $prev, "next" => $next);
    }

    // add links to things that are similar to this via sioc:related_to
    public function addRelated($id, $url) {
        $this->related[$id] = $url;
    }

    public function setNextPage($next) {
        $this->next = $next;
    }

    public function addParentForum($id, $url) {
        $this->parents[$id] = $url;
    }

    public function getContent(&$exp): string {
        $rdf = '<sioc:Thread rdf:about="' . clean($this->url) . "\">\n";
        $rdf .= "    <sioc:link rdf:resource=\"" . clean($this->url) . "\"/>\n";
        if($this->views) $rdf .= "    <sioc:num_views>" . $this->views . "</sioc:num_views>\n";
        if($this->note) $rdf .= "    <rdfs:comment>" . $this->note . "</rdfs:comment>\n";
        if($this->subject) {
            $rdf .= "    <dc:title>" . $this->subject . "</dc:title>\n";
        }
        if($this->created) {
            $rdf .= "    <dcterms:created>" . $this->created . "</dcterms:created>\n";
        }
        if($this->parents) {
            foreach($this->parents as $id => $uri) {
                $rdf .= "    <sioc:has_parent>\n";
                $rdf .= "        <sioc:Forum rdf:about=\"" . clean($uri) . "\">\n";
                $rdf .= "            <rdfs:seeAlso rdf:resource=\"" . $exp->siocURL('forum', $id) . "\"/>\n";
                $rdf .= "        </sioc:Forum>\n";
                $rdf .= "    </sioc:has_parent>\n";
            }
        }
        // here the tags are just used as keywords for dc:subject
        if($this->tags) {
            foreach($this->tags as $id => $tag) {
                $rdf .= "    <dc:subject>" . $tag . "</dc:subject>\n";
            }
        }
        // here the tags are used by creating a tag object with a blank node, with the keyword as moat:name - if you
        // use this insert prefixes for moat and tags
        // if ($this->tags) {
        // $i=1;
        // foreach ($this->tags as $id => $tag) {
        // $rdf .= "    <tags:taggedWithTag>\n";
        // $rdf .= "        <moat:tag rdf:nodeID=\"b$i\">\n";
        // // actually, the best way is to have 'reference URIs' for tags, e.g. URIs for all the platform
        // (http://tags.example.org/tag/soccer
        // $rdf .= "            <moat:name>" . $tag . "</moat:name>\n";
        // $rdf .= "        </moat:tag>\n";
        // $rdf .= "    </moat:taggedWithTag>\n";
        // $i++;
        // }
        // }

        // here the tags are used are used for sioc:topic, each topic needs to have a URI
        /*if($this->tags) {
                foreach($this->tags as $url=>$topic) {
                    $rdf .= "    <sioc:topic rdfs:label=\"$topic\" rdf:resource=\"" . clean($url) ."\"/>\n";
                }
            }
            */
        if($this->related) {
            foreach($this->related as $id => $url) {
                $rdf .= "    <sioc:related_to>\n";
                $rdf .= "        <sioc:Thread rdf:about=\"" . clean($url) . "\"/>\n";
                $rdf .= "    </sioc:related_to>\n"; // todo - each topic needs to have a URI
            }
        }

        if($this->posts) {
            foreach($this->posts as $id => $data) {
                $rdf .= "    <sioc:container_of>\n";
                $rdf .= "        <sioc:Post rdf:about=\"" . clean($data[url]) . "\">\n";
                $rdf .= "            <rdfs:seeAlso rdf:resource=\"" . $exp->siocURL('post', $id) . "\"/>\n";
                if($data[prev]) {
                    $rdf .= "            <sioc:previous_by_date rdf:resource=\"" . clean($data[prev]) . "\"/>\n";
                }
                if($data[next]) {
                    $rdf .= "            <sioc:next_by_date rdf:resource=\"" . clean($data[next]) . "\"/>\n";
                }
                $rdf .= "        </sioc:Post>\n";
                $rdf .= "    </sioc:container_of>\n";
            }
        }
        if($this->next) {
            $rdf .= "\r<rdfs:seeAlso rdf:resource=\"" . $exp->siocURL('thread', $this->id, $this->page + 1)
                . "\"/>\n";
        }
        $rdf .= "</sioc:Thread>\n";
        return $rdf;
    }
}

// Export d'un forum avec une liste de posts -variable (next with seeAlso)

/**
 * SIOC::Forum object
 *
 * Contains information about SIOC Forum (blog, ...):
 *  - description of a forum
 *  - list of posts within a forum [partial, paged]
 */
class SIOCForum extends SIOCObject {

    private $type = 'forum';

    private $id;
    private $url;
    private $page;
    private $posts;
    private $next;
    private $blog_title;
    private $description;
    private $threads;
    private $parents;
    private $creator;
    private $administrator;
    /**
     * @var array|mixed
     */
    private $links;

    public function __construct(
        $id,
        $url,
        $page,
        $title = '',
        $descr = '',
        $type = 'sioc:Forum',
        $creator = '',
        $admin = '',
        $links = array()
    ) {
        $this->id            = $id;
        $this->url           = $url;
        $this->page          = $page;
        $this->posts         = array();
        $this->next          = false;
        $this->blog_title    = $title;
        $this->description   = $descr;
        $this->threads       = array();
        $this->parents       = array();
        $this->_type         = $type;
        $this->creator       = $creator;
        $this->administrator = $admin;
        $this->links         = $links;
    }

    public function addPost($id, $url) {
        $this->posts[$id] = $url;
    }

    public function addThread($id, $url) {
        $this->threads[$id] = $url;
    }

    public function addParentForum($id, $url) {
        $this->parents[$id] = $url;
    }

    public function setNextPage($next) {
        $this->next = $next;
    }

    public function getContent(&$exp): string {
        $rdf = '<' . $this->_type . ' rdf:about="' . clean($this->url) . "\">\n";
        if($this->_type != 'sioc:Forum') $rdf .= "    <rdf:type rdf:resource=\"http://rdfs.org/sioc/ns#Forum\" />\n";
        $rdf .= "    <sioc:link rdf:resource=\"" . clean($this->url) . "\"/>\n";
        if($this->blog_title) $rdf .= "    <dc:title>" . $this->blog_title . "</dc:title>\n";
        if($this->description) $rdf .= "    <dc:description>" . $this->description . "</dc:description>\n";
        if($this->note) $rdf .= "    <rdfs:comment>" . $this->note . "</rdfs:comment>\n";

        if($this->parents) {
            foreach($this->parents as $id => $uri) {
                $rdf .= "    <sioc:has_parent>\n";
                $rdf .= "        <sioc:Forum rdf:about=\"" . clean($uri) . "\">\n";
                $rdf .= "            <rdfs:seeAlso rdf:resource=\"" . $exp->siocURL('forum', $id) . "\"/>\n";
                $rdf .= "        </sioc:Forum>\n";
                $rdf .= "    </sioc:has_parent>\n";
            }
        }

        if($this->threads) {
            foreach($this->threads as $id => $uri) {
                $rdf .= "    <sioc:parent_of>\n";
                $rdf .= "        <sioc:Thread rdf:about=\"" . clean($uri) . "\">\n";
                $rdf .= "            <rdfs:seeAlso rdf:resource=\"" . $exp->siocURL('thread', $id) . "\"/>\n";
                $rdf .= "        </sioc:Thread>\n";
                $rdf .= "    </sioc:parent_of>\n";
            }
        }

        if($this->posts) {
            foreach($this->posts as $id => $url) {
                $rdf .= "    <sioc:container_of>\n";
                $rdf .= "        <sioc:Post rdf:about=\"" . clean($url) . "\">\n";
                $rdf .= "            <rdfs:seeAlso rdf:resource=\"" . $exp->siocURL('post', $id) . "\"/>\n";
                $rdf .= "        </sioc:Post>\n";
                $rdf .= "    </sioc:container_of>\n";
            }
        }

        if($this->creator) {
            if($this->creator->_id) {
                $rdf .= "    <sioc:has_creator>\n";
                $rdf .= "        <sioc:UserAccount rdf:about=\"" . clean($this->creator->_uri) . "\">\n";
                if($this->creator->_sioc_url) {
                    $rdf .= "            <rdfs:seeAlso rdf:resource=\"" . $this->creator->_sioc_url . "\"/>\n";
                } else {
                    $rdf .= "            <rdfs:seeAlso rdf:resource=\"" . $exp->siocURL('user', $this->creator->_id)
                        . "\"/>\n";
                }
                $rdf .= "        </sioc:UserAccount>\n";
                $rdf .= "    </sioc:has_creator>\n";
                $rdf .= "    <foaf:maker>\n";
                $rdf .= "        <foaf:Person rdf:about=\"" . clean($this->creator->_foaf_uri) . "\">\n";
                if($this->creator->_foaf_url) {
                    $rdf .= "            <rdfs:seeAlso rdf:resource=\"" . $this->creator->_foaf_url . "\"/>\n";
                } else {
                    $rdf .= "            <rdfs:seeAlso rdf:resource=\"" . $exp->siocURL('user', $this->creator->_id)
                        . "\"/>\n";
                }
                $rdf .= "        </foaf:Person>\n";
                $rdf .= "    </foaf:maker>\n";
            } else {
                $rdf .= "    <foaf:maker>\n";
                $rdf .= "        <foaf:Person";
                if($this->creator->_name) {
                    $rdf .= " foaf:name=\"" . $this->creator->_name . "\"";
                }
                if($this->creator->_sha1) {
                    $rdf .= " foaf:mbox_sha1sum=\"" . $this->creator->_sha1 . "\"";
                }
                if($this->creator->_name) {
                    $rdf .= ">\n            <foaf:homepage rdf:resource=\"" . $this->creator->_homepage
                        . "\"/>\n        </foaf:Person>\n";
                } else {
                    $rdf .= "/>\n";
                }
                $rdf .= "    </foaf:maker>\n";
            }
        }

        if($this->administrator) {
            if($this->administrator->_id) {
                $rdf .= "    <sioc:has_administrator>\n";
                $rdf .= "        <sioc:UserAccount rdf:about=\"" . clean($this->administrator->_uri) . "\">\n";
                if($this->administrator->_sioc_url) {
                    $rdf .= "            <rdfs:seeAlso rdf:resource=\"" . $this->administrator->_sioc_url . "\"/>\n";
                } else $rdf .= "            <rdfs:seeAlso rdf:resource=\""
                                                        . $exp->siocURL('user', $this->administrator->_id) . "\"/>\n";
                $rdf .= "        </sioc:UserAccount>\n";
                $rdf .= "    </sioc:has_administrator>\n";
            }
        }
        if($this->links) {
            foreach($this->links as $url => $link) {
                $rdf .= "    <sioc:links_to rdfs:label=\"$link\" rdf:resource=\"" . clean($url) . "\"/>\n";
            }
        }

        if($this->next) {
            $rdf .= "\r<rdfs:seeAlso rdf:resource=\"" . $exp->siocURL('forum', $this->id, $this->page + 1) . "\"/>\n";
        }
        $rdf .= "</" . $this->_type . ">";

        return $rdf;
    }
}

/**
 * SIOC::Post object
 *
 * Contains information about a post
 */
class SIOCPost extends SIOCObject {

    private $type = 'post';

    private $url;
    private $subject;
    private $content;
    private $encoded;
    private $creator;
    private $created;
    private $updated;
    private $topics;
    private $links;
    private $comments;
    private $reply_of;
    private $has_part;

    public function __construct(
        $url,
        $subject,
        $content,
        $encoded,
        $creator,
        $created,
        $updated = "",
        $topics = array(),
        $links = array(),
        $type = 'sioc:Post',
        $has_part = array()
    ) {
        $this->url      = $url;
        $this->subject  = $subject;
        $this->content  = $content;
        $this->encoded  = $encoded;
        $this->creator  = $creator;
        $this->created  = $created;
        $this->updated  = $updated;
        $this->topics   = $topics;
        $this->links    = $links;
        $this->comments = array();
        $this->reply_of = array();
        $this->_type    = $type;
        $this->has_part = $has_part;
    }

    public function addComment($id, $url) {
        $this->comments[$id] = $url;
    }

    public function addReplyOf($id, $url) {
        $this->reply_of[$id] = $url;
    }

    public function getContent(&$exp): string {
        $rdf = '<' . $this->_type . " rdf:about=\"" . clean($this->url) . "\">\n";
        if($this->_type != 'sioc:Post') $rdf .= "    <rdf:type rdf:resource=\"http://rdfs.org/sioc/ns#Post\" />\n";
        if($this->subject) {
            $rdf .= "    <dc:title>" . $this->subject . "</dc:title>\n";
        }
        if($this->creator) {
            if($this->creator->_id) {
                $rdf .= "    <sioc:has_creator>\n";
                $rdf .= "        <sioc:UserAccount rdf:about=\"" . clean($this->creator->_uri) . "\">\n";
                if($this->creator->_sioc_url) {
                    $rdf .= "            <rdfs:seeAlso rdf:resource=\"" . $this->creator->_sioc_url . "\"/>\n";
                } else {
                    $rdf .= "            <rdfs:seeAlso rdf:resource=\"" . $exp->siocURL('user', $this->creator->_id)
                        . "\"/>\n";
                }
                $rdf .= "        </sioc:UserAccount>\n";
                $rdf .= "    </sioc:has_creator>\n";
                $rdf .= "    <foaf:maker>\n";
                $rdf .= "        <foaf:Person rdf:about=\"" . clean($this->creator->_foaf_uri) . "\">\n";
                if($this->creator->_foaf_url) {
                    $rdf .= "            <rdfs:seeAlso rdf:resource=\"" . $this->creator->_foaf_url . "\"/>\n";
                } else {
                    $rdf .= "            <rdfs:seeAlso rdf:resource=\"" . $exp->siocURL('user', $this->creator->_id)
                        . "\"/>\n";
                }
                $rdf .= "        </foaf:Person>\n";
                $rdf .= "    </foaf:maker>\n";
            } else {
                $rdf .= "    <foaf:maker>\n";
                $rdf .= "        <foaf:Person";
                if($this->creator->_name) {
                    $rdf .= " foaf:name=\"" . $this->creator->_name . "\"";
                }
                if($this->creator->_sha1) {
                    $rdf .= " foaf:mbox_sha1sum=\"" . $this->creator->_sha1 . "\"";
                }
                if($this->creator->_name) {
                    $rdf .= ">\n            <foaf:homepage rdf:resource=\"" . $this->creator->_homepage
                        . "\"/>\n        </foaf:Person>\n";
                } else {
                    $rdf .= "/>\n";
                }
                $rdf .= "    </foaf:maker>\n";
            }
        }
        $rdf .= "    <dcterms:created>" . $this->created . "</dcterms:created>\n";
        if($this->updated and ($this->created != $this->updated)) $rdf .= "    <dcterms:modified>"
            . $this->updated . "</dcterms:modified>\n";
        $rdf .= "    <sioc:content>" . pureContent($this->content) . "</sioc:content>\n";

        $rdf .= "    <content:encoded><![CDATA[" . $this->encoded . "]]></content:encoded>\n";
        if($this->topics) {
            foreach($this->topics as $url => $topic) {
                $rdf .= "    <sioc:topic rdfs:label=\"$topic\" rdf:resource=\"" . clean($url) . "\"/>\n";
            }
        }
        if($this->links) {
            foreach($this->links as $url => $link) {
                $rdf .= "    <sioc:links_to rdfs:label=\"$link\" rdf:resource=\"" . clean($url) . "\"/>\n";
            }
        }
        if($this->has_part) {
            foreach($this->has_part as $id => $url) {
                $rdf .= "    <dcterms:hasPart>\n";
                $rdf .= "        <dcmitype:Image rdf:about=\"" . clean($url) . "\"/>\n";
                $rdf .= "    </dcterms:hasPart>\n";
            }
        }
        if($this->reply_of) {
            foreach($this->reply_of as $id => $url) {
                $rdf .= "    <sioc:reply_of>\n";
                $rdf .= "        <sioc:Post rdf:about=\"" . clean($url) . "\">\n";
                $rdf .= "            <rdfs:seeAlso rdf:resource=\"" . $exp->siocURL('post', $id) . "\"/>\n";
                $rdf .= "        </sioc:Post>\n";
                $rdf .= "    </sioc:reply_of>\n";
            }
        }
        if($this->comments) {
            foreach($this->comments as $id => $url) {
                $rdf .= "    <sioc:has_reply>\n";
                $rdf .= "        <sioc:Post rdf:about=\"" . clean($url) . "\">\n";
                //        if($comments->f('comment_trackback')) $rdf .= "            <sioc:type>"
                // . POST_TRACKBACK . "</sioc:type>\n";
                //        else $rdf .= "            <sioc:type>" . POST_COMMENT  . "</sioc:type>\n";
                $rdf .= "            <rdfs:seeAlso rdf:resource=\"" . $exp->siocURL('comment', $id) . "\"/>\n";
                $rdf .= "        </sioc:Post>\n";
                $rdf .= "    </sioc:has_reply>\n";
            }
        }
        $rdf .= "</" . $this->_type . ">\n";
        return $rdf;
    }
}

/**
 * SIOC::WikiArticle object
 *
 * Contains information about a wiki article
 */
class SIOCWikiArticle extends SIOCObject {

    private $type = 'sioct:WikiArticle';

    private $url;
    private $api = null;
    private $subject;
    private $redirpage;
    private $creator;
    private $created;
    private $topics;
    private $links;
    private $ext_links;
    private $previous_version;
    private $next_version;
    private $latest_version;
    private $has_discussion;
    private $has_container;

    public function __construct(
        $url,
        $api,
        $subject,
        $redir,
        $user,
        $created,
        $prev_vers,
        $next_vers,
        $latest_vers,
        $has_discuss,
        $container,
        $topics = array(),
        $links = array(),
        $ext_links = array(),
        $type = 'sioct:WikiArticle'
    ) {
        $this->url              = $url;
        $this->api              = $api;
        $this->subject          = $subject;
        $this->redirpage        = $redir;
        $this->creator          = $user;
        $this->created          = $created;
        $this->topics           = $topics;
        $this->links            = $links;
        $this->ext_links        = $ext_links;
        $this->_type            = $type;
        $this->previous_version = $prev_vers;
        $this->next_version     = $next_vers;
        $this->latest_version   = $latest_vers;
        $this->has_discussion   = $has_discuss;
        $this->has_container    = $container;
    }

    public function getContent(&$exp): string {
        $rdf = '<' . $this->_type . " rdf:about=\"" . clean($this->url) . "\">\n";
        if($this->subject) {
            $rdf .= "    <dc:title>" . clean($this->subject) . "</dc:title>\n";
            if(strcmp($this->has_container, 'http://en.wikipedia.org') === 0) {
                $rdf .= "    <foaf:primaryTopic rdf:resource=\"" . clean(
                        'http://dbpedia.org/resource/'
                        . $this->subject
                    ) . "\"/>\n";
            }
        }
        if($this->creator->_nick) {
            /*if ($this->creator->id) {
                $rdf .= "    <sioc:has_creator>\n";
                $rdf .= "        <sioc:UserAccount rdf:about=\"" . clean($this->creator->uri) ."\">\n";
                if($this->creator->sioc_url) { $rdf .= "    <rdfs:seeAlso rdf:resource=\"". $this->creator->sioc_url
                ."\"/>\n"; }
                else $rdf .= "            <rdfs:seeAlso rdf:resource=\"" . $exp->siocURL('user', $this->creator->id)
                        . "\"/>\n";
                $rdf .= "        </sioc:UserAccount>\n";
                $rdf .= "    </sioc:has_creator>\n";
                $rdf .= "    <foaf:maker>\n";
                $rdf .= "        <foaf:Person rdf:about=\"" . clean($this->creator->foaf_uri) ."\">\n";
                if($this->creator->foaf_url) { $rdf .= "    <rdfs:seeAlso rdf:resource=\"". $this->creator->foaf_url
                        ."\"/>\n"; }
                else $rdf .= "            <rdfs:seeAlso rdf:resource=\"" . $exp->siocURL('user', $this->creator->id)
                        . "\"/>\n";
                $rdf .= "        </foaf:Person>\n";
                $rdf .= "    </foaf:maker>\n";
            } else {*/
            $rdf .= "    <sioc:has_creator>\n";
            $rdf .= "        <sioc:UserAccount rdf:about=\"" . clean($this->creator->_uri) . "\">\n";
            $rdf .= "            <rdfs:seeAlso rdf:resource=\"" .
                clean('http://ws.sioc-project.org/mediawiki/mediawiki.php?wiki=' . $this->creator->_uri);
            if($this->api) {
                $rdf .= clean("&api=" . $this->api);
            }
            $rdf .= "\"/>\n";
            $rdf .= "        </sioc:UserAccount>\n";
            $rdf .= "    </sioc:has_creator>\n";
            $rdf .= "    <dc:contributor>" . clean($this->creator->_nick) . "</dc:contributor>\n";
            /*$rdf .= "    <foaf:maker>\n";
            $rdf .= "        <foaf:Person";
            if($this->creator->name) $rdf .= " foaf:name=\"" . $this->creator->name ."\"";
            if($this->creator->sha1) $rdf .= " foaf:mbox_sha1sum=\"" . $this->creator->sha1 ."\"";
            if($this->creator->homepage) $rdf .= ">\n            <foaf:homepage rdf:resource=\""
            . $this->creator->homepage ."\"/>\n        </foaf:Person>\n";
            else $rdf .= "/>\n";
            $rdf .= "    </foaf:maker>\n";
        }*/
        } else {
            if($this->creator !== 'void') {
                $rdf .= "    <sioc:has_creator>\n";
                $rdf .= "        <sioc:UserAccount>\n";
                $rdf .= "        </sioc:UserAccount>\n";
                $rdf .= "    </sioc:has_creator>\n";
            }
        }
        if($this->created) {
            $rdf .= "    <dcterms:created>" . $this->created . "</dcterms:created>\n";
        }
        if(is_array($this->topics)) {
            foreach($this->topics as $topic => $url) {
                $rdf .= "    <sioc:topic>\n";
                $rdf .= "        <sioct:Category rdf:about=\"" . clean($url) . "\">\n";
                $rdf .= "            <rdfs:seeAlso rdf:resource=\"" .
                    clean('http://ws.sioc-project.org/mediawiki/mediawiki.php?wiki=' . $url);
                if($this->api) {
                    $rdf .= clean("&api=" . $this->api);
                }
                $rdf .= "\"/>\n";
                $rdf .= "        </sioct:Category>\n";
                $rdf .= "    </sioc:topic>\n";
            }
        }
        if(is_array($this->links)) {
            foreach($this->links as $label => $url) {
                $rdf .= "    <sioc:links_to>\n";
                $rdf .= "        <sioct:WikiArticle rdf:about=\"" . clean($url) . "\">\n";
                $rdf .= "            <rdfs:seeAlso rdf:resource=\"" .
                    clean('http://ws.sioc-project.org/mediawiki/mediawiki.php?wiki=' . $url);
                if($this->api) {
                    $rdf .= clean("&api=" . $this->api);
                }
                $rdf .= "\"/>\n";
                $rdf .= "        </sioct:WikiArticle>\n";
                $rdf .= "    </sioc:links_to>\n";
            }
        } else {
            if($this->links) {
                $rdf .= "    <sioc:links_to>\n";
                $rdf .= "        <sioct:WikiArticle rdf:about=\"" . clean($this->links) . "\">\n";
                $rdf .= "            <rdfs:seeAlso rdf:resource=\"" .
                    clean('http://ws.sioc-project.org/mediawiki/mediawiki.php?wiki=' . $this->links);
                if($this->api) {
                    $rdf .= clean("&api=" . $this->api);
                }
                $rdf .= "\"/>\n";
                $rdf .= "        </sioct:WikiArticle>\n";
                $rdf .= "    </sioc:links_to>\n";
            }
        }
        if(is_array($this->ext_links)) {
            foreach($this->ext_links as $label => $url) {
                $rdf .= "    <sioc:links_to rdf:resource=\"" . clean($url) . "\"/>\n";
            }
        }
        if($this->previous_version) {
            $rdf .= "    <sioc:previous_version>\n";
            $rdf .= "        <sioct:WikiArticle rdf:about=\"" . clean($this->previous_version) . "\">\n";
            $rdf .= "            <rdfs:seeAlso rdf:resource=\"" .
                clean('http://ws.sioc-project.org/mediawiki/mediawiki.php?wiki=' . $this->previous_version);
            if($this->api) {
                $rdf .= clean("&api=" . $this->api);
            }
            $rdf .= "\"/>\n";
            $rdf .= "        </sioct:WikiArticle>\n";
            $rdf .= "    </sioc:previous_version>\n";
            /*If there is support for inference and transitivity the following is not needed
            $rdf .= "    <sioc:earlier_version>\n";
            $rdf .= "        <sioct:WikiArticle rdf:about=\"" . clean($this->previous_version) ."\">\n";
            $rdf .= "            <rdfs:seeAlso rdf:resource=\"" .
                    clean('http://ws.sioc-project.org/mediawiki/mediawiki.php?wiki='.$this->previous_version);
            if ($this->api) {
              $rdf .= clean("&api=" . $this->api);
            }
            $rdf .= "\"/>\n";
            $rdf .= "        </sioct:WikiArticle>\n";
            $rdf .= "    </sioc:earlier_version>\n";
             */
        }
        if($this->next_version) {
            $rdf .= "    <sioc:next_version>\n";
            $rdf .= "        <sioct:WikiArticle rdf:about=\"" . clean($this->next_version) . "\">\n";
            $rdf .= "            <rdfs:seeAlso rdf:resource=\"" .
                clean('http://ws.sioc-project.org/mediawiki/mediawiki.php?wiki=' . $this->next_version);
            if($this->api) {
                $rdf .= clean("&api=" . $this->api);
            }
            $rdf .= "\"/>\n";
            $rdf .= "        </sioct:WikiArticle>\n";
            $rdf .= "    </sioc:next_version>\n";
            /*If there is support for inference and transitivity the following is not needed
            $rdf .= "    <sioc:later_version>\n";
            $rdf .= "        <sioct:WikiArticle rdf:about=\"" . clean($this->next_version) ."\">\n";
            $rdf .= "            <rdfs:seeAlso rdf:resource=\"" .
                    clean('http://ws.sioc-project.org/mediawiki/mediawiki.php?wiki='.$this->next_version);
            if ($this->api) {
              $rdf .= clean("&api=" . $this->api);
            }
            $rdf .= "\"/>\n";
            $rdf .= "        </sioct:WikiArticle>\n";
            $rdf .= "    </sioc:later_version>\n";
             */
        }
        if($this->latest_version) {
            $rdf .= "    <sioc:latest_version>\n";
            $rdf .= "        <sioct:WikiArticle rdf:about=\"" . clean($this->latest_version) . "\">\n";
            $rdf .= "            <rdfs:seeAlso rdf:resource=\"" .
                clean('http://ws.sioc-project.org/mediawiki/mediawiki.php?wiki=' . $this->latest_version);
            if($this->api) {
                $rdf .= clean("&api=" . $this->api);
            }
            $rdf .= "\"/>\n";
            $rdf .= "        </sioct:WikiArticle>\n";
            $rdf .= "    </sioc:latest_version>\n";
        }
        if($this->has_discussion && (strpos($this->has_discussion, 'Talk:Talk:') == false)) {
            $rdf .= "    <sioc:has_discussion>\n";
            $rdf .= "        <sioct:WikiArticle rdf:about=\"" . clean($this->has_discussion) . "\">\n";
            $rdf .= "            <rdfs:seeAlso rdf:resource=\"" .
                clean('http://ws.sioc-project.org/mediawiki/mediawiki.php?wiki=' . $this->has_discussion);
            if($this->api) {
                $rdf .= clean("&api=" . $this->api);
            }
            $rdf .= "\"/>\n";
            $rdf .= "        </sioct:WikiArticle>\n";
            $rdf .= "    </sioc:has_discussion>\n";
        }
        if($this->has_container) {
            $rdf .= "    <sioc:has_container>\n";
            $rdf .= "        <sioct:Wiki rdf:about=\"" . clean($this->has_container) . "\"/>\n";
            $rdf .= "    </sioc:has_container>\n";
        }
        if($this->redirpage) {
            $rdf .= "    <owl:sameAs rdf:resource=\"" . clean($this->redirpage) . "\"/>\n";
            $rdf .= "    <rdfs:seeAlso rdf:resource=\"" .
                clean('http://ws.sioc-project.org/mediawiki/mediawiki.php?wiki=' . $this->redirpage);
            if($this->api) {
                $rdf .= clean("&api=" . $this->api);
            }
            $rdf .= "\"/>\n";
        }

        $rdf .= "</" . $this->_type . ">\n";
        return $rdf;
    }
}

/**
 * SIOC::Wiki object
 *
 * Contains information about a wiki site
 */
class SIOCWiki extends SIOCObject {

    private $url;
    private $type;

    public function __construct($url, $type = 'sioct:Wiki') {
        $this->url  = $url;
        $this->type = $type;
    }

    public function getContent(&$exp): string {
        $rdf = '<' . $this->type . " rdf:about=\"" . clean($this->url) . "\"/>\n";
        return $rdf;
    }
}

/**
 * SIOC::Category object
 *
 * Contains information about the category which is object of the sioc:topic property
 */
class SIOCCategory extends SIOCObject {

    private $url;
    private $type;

    public function __construct($url, $type = 'sioct:Category') {
        $this->url  = $url;
        $this->type = $type;
    }

    public function getContent(&$exp): string {
        $rdf = '<' . $this->type . " rdf:about=\"" . clean($this->url) . "\"/>\n";
        return $rdf;
    }
}

/**
 * "Clean" text
 *
 * Transforms text so that it can be safely put into XML markup
 */
if(!function_exists('clean')) {
    function clean($text, $url = false) {
#    return htmlentities( $text );
#    return htmlentities2( $text );
        // double encoding is preventable now
        // $text = htmlspecialchars_decode($text, ENT_COMPAT);
        if($url) {
            $text = str_replace('&amp;', '&', $text);
        }
        return htmlspecialchars($text, ENT_COMPAT, 'UTF-8');
    }
}

/**
 * HTML Entities 2
 *
 * Same a HTMLEntities, but avoids double-encoding of entities
 */
if(!function_exists('htmlentities2')) {
    function htmlentities2($myHTML) {
        $translation_table          = get_html_translation_table(HTML_ENTITIES, ENT_QUOTES);
        $translation_table[chr(38)] = '&';
        return preg_replace(
            "/&(?![A-Za-z]{0,4}\w{2,3};|#[0-9]{2,3};)/", "&amp;",
            strtr($myHTML, $translation_table)
        );
        //return htmlentities(strtr(str_replace(' ', '%20', $myHTML), $translation_table));
    }
}

/**
 * pureContent
 *
 * Prepares text-only representation of HTML content
 */
if(!function_exists('pureContent')) {
    function pureContent($content) {
        // Remove HTML tags
        // May add more cleanup code later, if validation errors are found
        return strip_tags($content);
    }
}
