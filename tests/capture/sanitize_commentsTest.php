<?php

use PHPUnit\Framework\TestCase;

define('CAPTUREROLES', serialize(array('track')));
require_once __DIR__ . '../../../capture/query_manager.php';

class Test_sanitize_comments extends TestCase
{
    public function testEmptyCommentShouldBeFine()
    {
        $input = '';
        $this->assertEquals(sanitize_comments($input), '');
    }

    public function testShortCommentShouldBeFine() {
        $input = 'A short comment.';
        $this->assertEquals(sanitize_comments($input), 'A short comment.');
    }

    public function testCommentWithLeadingSpaceShouldBeTrimmed() {
        $input = ' Has leading space.';
        $this->assertEquals(sanitize_comments($input), 'Has leading space.');
    }

    public function testCommentWithTrailingSpaceShouldBeTrimmed() {
        $input = 'Has trailing space. ';
        $this->assertEquals(sanitize_comments($input), 'Has trailing space.');
    }

    public function testCommentWithLessThanShouldBeEncoded() {
        $input = 'This comment has < relevance than some';
        $this->assertEquals(sanitize_comments($input), 'This comment has &lt; relevance than some');
    }

    public function testCommentWithGreaterThanShouldBeEncoded() {
        $input = 'This :> is perhaps my favourite smilie';
        $this->assertEquals(sanitize_comments($input), 'This :&gt; is perhaps my favourite smilie');
    }

    public function testHtmlEntityShouldBeEncoded() {
        $input = 'This &lt; is already encoded';
        $this->assertEquals(sanitize_comments($input), 'This &amp;lt; is already encoded');
    }

    public function testCommentWithAmpersandShouldBeEncoded() {
        $input = 'The & is not a boolean conjunction in TCAT';
        $this->assertEquals(sanitize_comments($input), 'The &amp; is not a boolean conjunction in TCAT');
    }

    public function testCommentWithDoubleQuoteShouldBeEncoded() {
        $input = 'This is one of those "great" bins';
        $this->assertEquals(sanitize_comments($input), 'This is one of those &quot;great&quot; bins');
    }

    public function testCommentWithSingleQuoteShouldBeEncoded() {
        $input = 'A \'clever\' comment';
        $this->assertEquals(sanitize_comments($input), 'A &#039;clever&#039; comment');
    }

    public function testCommentOutsideLatin1ShouldBeFine() {
        $input = 'It is 2019, øæøæøæ is fine';
        $this->assertEquals(sanitize_comments($input), $input);
    }

    public function testCommentWithEmojiShouldBeFine() {
        // Hoping the database is UTF-8, of course
        $input = 'It is 2019, 🦄 is fine in comments. What a time to be alive';
        $this->assertEquals(sanitize_comments($input), $input);
    }

    public function testCommentArrayShouldThrowTypeException() {
        $input = [];
        $this->expectException(PHPUnit\Framework\Error\Error::class);
        sanitize_comments($input);
    }

    /**
     * Example case from
     * https://github.com/digitalmethodsinitiative/dmi-tcat/issues/350
     */
    public function testReportedCommentWithEmailShouldBeEncoded() {
        $input = 'A query for Firstname Lastname <firstname.lastname@instituti.on>, expected to run until 2019-02-28. set up by Mace Ojala on 2018-11-27';
        $this->assertEquals(sanitize_comments($input), 'A query for Firstname Lastname &lt;firstname.lastname@instituti.on&gt;, expected to run until 2019-02-28. set up by Mace Ojala on 2018-11-27');
    }

    /**
     * Example case from
     * https://github.com/digitalmethodsinitiative/dmi-tcat/issues/350
     * . I have not properly thought about and investigated very hard
     * where the problem with this one actually is.
     */
    public function testReportedCommentWithDanishShouldFine() {
        $input = 'helse tværspor added 23-04-2017 (JMB)';
        $this->assertEquals(sanitize_comments($input), $input);
    }
}
