# DokuSIOC -- a SIOC plugin for DokuWiki

DokuSIOC integrates the [SIOC ontology](http://sioc-project.org/ontology) within 
[DokuWiki](http://dokuwiki.org/) and provides alternate RDF/XML views of the wiki documents.


## Features

  * Creates meta descriptions for sioc:User, sioct:WikiArticle and
    sioc:Container (incl. sioct:Wiki) and it includes information about
    next/previous versions, creator/modifier, contributors, date, content,
    container and inner wiki links between the articles.
  * It adds a link to those meta descriptions in the HTML header.
  * Pings [pingthesemanticweb.com](http://pingthesemanticweb.com/) for new/edited content
  * Linked Data
  * Content Negotiation for application/rdf+xml requests
  * Possibility to hide RDF content from search engines

