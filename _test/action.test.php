<?php
/*
 * Copyright (c) 2016 Mark C. Prins <mprins@users.sf.net>
 *
 * Permission to use, copy, modify, and distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

/**
 * Action tests for the dokusioc plugin.
 *
 * @group plugin_dokusioc
 * @group plugins
 */
class action_plugin_dokusioc_test extends DokuWikiTest {

    protected $pluginsEnabled = array('dokusioc');

    public function setUp() {
        global $conf;

        parent::setUp();
    }

    public function testHeaders() {
        $request = new TestRequest();
        $response = $request->get(array('id'=>'wiki:dokuwiki'), '/doku.php');

        $this->assertTrue(
            strpos($response->getContent(), 'DokuWiki') !== false,
            'DokuWiki was not a word in the output'
        );

        // check meta header
        $this->assertEquals("Article 'DokuWiki' (SIOC document as RDF/XML)",
                        $response->queryHTML('link[type="application/rdf+xml"]')->attr('title'));
    }
}
