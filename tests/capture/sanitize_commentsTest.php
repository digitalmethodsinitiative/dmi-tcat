<?php

use PHPUnit\Framework\TestCase;

define('CAPTUREROLES', serialize(array('track')));
require_once __DIR__ . '../../../capture/query_manager.php';

class Test_sanitize_comments extends TestCase
{
    public function testEmptyCommentShouldPass()
    {
        $input = '';
        $this->assertEquals(sanitize_comments($input), '');
    }

    public function testShortCommentShouldPass() {
        $input = 'A short comment.';
        $this->assertEquals(sanitize_comments($input), 'A short comment.');
    }

    public function testCommentWithLeadingSpaceShouldBeTrimmed() {
        $input = ' Has leading space.';
        $this->assertEquals(sanitize_comments($input), 'Has leading space.');
    }

    public function testCommentWithTrailingSpaceShouldBeTrimmer() {
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
        $this->assertEquals(sanitize_comments($input), 'This &lt; is already encoded');
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
}
