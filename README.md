# DokuSIOC -- a SIOC plugin for DokuWiki

DokuSIOC integrates the [SIOC ontology](http://rdfs.org/sioc/spec/) within 
[DokuWiki](https://dokuwiki.org/) and provides alternate RDF/XML views of the wiki documents.


## Features

  * Creates meta descriptions for sioc:User, sioct:WikiArticle and
    sioc:Container (incl. sioct:Wiki) and it includes information about
    next/previous versions, creator/modifier, contributors, date, content,
    container and inner wiki links between the articles.
  * It adds a link to those meta descriptions in the HTML header.
  * Linked Data
  * Content Negotiation for application/rdf+xml requests
  * Possibility to hide RDF content from search engines

----
[![CI](https://github.com/mprins/dokuwiki-plugin-dokusioc/actions/workflows/CI.yml/badge.svg)](https://github.com/mprins/dokuwiki-plugin-dokusioc/actions/workflows/CI.yml)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/mprins/DokuWiki-Plugin-DokuSIOC/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/mprins/DokuWiki-Plugin-DokuSIOC/?branch=master)
[![GitHub issues](https://img.shields.io/github/issues/mprins/DokuWiki-Plugin-DokuSIOC.svg)](https://github.com/mprins/DokuWiki-Plugin-DokuSIOC/issues)
[![GitHub forks](https://img.shields.io/github/forks/mprins/DokuWiki-Plugin-DokuSIOC.svg)](https://github.com/mprins/DokuWiki-Plugin-DokuSIOC/network)
[![GitHub stars](https://img.shields.io/github/stars/mprins/DokuWiki-Plugin-DokuSIOC.svg)](https://github.com/mprins/DokuWiki-Plugin-DokuSIOC/stargazers)
[![GitHub license](https://img.shields.io/badge/license-GPLv2-blue.svg)](https://raw.githubusercontent.com/mprins/DokuWiki-Plugin-DokuSIOC/master/LICENSE)
