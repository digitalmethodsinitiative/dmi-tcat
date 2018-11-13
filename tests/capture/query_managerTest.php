<?php
/**
 * Reading from Stack Overflow how to use PHPUnit for testing
 * procedural code
 * https://stackoverflow.com/questions/5021254/php-testing-for-procedural-code
 */

use PHPUnit\Framework\TestCase;

define('CAPTUREROLES', serialize(array('track')));
require_once __DIR__ . '../../../capture/query_manager.php';

class Test_query_manager extends TestCase
{
    public function setUp()
    {
        // setup goes here
    }

    /**
     * This test setup relies on config.php to NOT be in place, and
     * then expecting to get those PDO exceptions once the database
     * action begins in `create_new_bin`. This makes phpunit throw
     * include errors when run. This is cumbersome, but I believe
     * somewhat sensible for the current organization of the TCAT
     * codebase and long, coupled functions. But not relying on this
     * fact would be preferable. Anyway...
     *
     * @dataProvider newQueryBinWithShortEnoughPhrasesProvider
     */
    public function testCreateNewBinWithShortEnoughQueryPhrasesShouldProceed($params)
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage("1046 No database selected");
        create_new_bin($params);
    }

    /**
     * @dataProvider newQueryBinWithTooLongPhrasesProvider
     */
    public function testCreateNewBinLongQueryPhraseShouldThrow($params)
    {
        $this->expectException(LengthException::class);
        $this->expectExceptionMessage("exceeds 60 chrs");
        create_new_bin($params);
    }

    /**
     * @dataProvider modifiedQueryBinWithShortEnoughPhrasesProvider
     */
    public function testModifyBinWithShortEnoughQueryPhrasesShouldProceed($params)
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage("1046 No database selected");
        modify_bin($params);
    }

    /**
     * @dataProvider modifiedQueryBinWithTooLongPhrasesProvider
     */
    public function testModifyBinLongQueryPhraseShouldThrow($params)
    {
        $this->expectException(LengthException::class);
        $this->expectExceptionMessage("exceeds 60 chrs");
        modify_bin($params);
    }

    /**
     * Data providers
     */
    public function newQueryBinWithTooLongPhrasesProvider()
    {
        return [
            'Way too long phrase' => [
                ["type" => "track",
                 "newbin_name" => "a_new_bin",
                 "newbin_phrases" => "kitten,lizard,aarvark,012345678901234567890123456789012345678901234567890123456789aaaaaaaaaaaaaaaaaaaaa",
                 "newbin_comments" => "",
                 "active" => true]],
            'Still too long phrases' => [
                ["newbin_name" => "a_new_bin",
                 "type" => "track",
                 "newbin_comments" => "",
                 "newbin_phrases" => "kitten,lizard,aarvark,012345678901234567890123456789012345678901234567890123456789a"]]
        ];
    }

    public function newQueryBinWithShortEnoughPhrasesProvider()
    {
        return [
            'Short enough phrases' => [
                ["newbin_name" => "a_new_bin",
                 "type" => "track",
                 "newbin_comments" => "",
                 "newbin_phrases" => "kitten,lizard,aarvark,anteater"]],
            'Barely short enough phrases' => [
                ["newbin_name" => "a_new_bin",
                 "type" => "track",
                 "newbin_comments" => "",
                 "newbin_phrases" => "kitten,lizard,aarvark,012345678901234567890123456789012345678901234567890123456789"]]
        ];
    }

    public function modifiedQueryBinWithTooLongPhrasesProvider()
    {
        return [
            'Way too long phrase' => [
                ["type" => "track",
                 "bin" => "a_new_bin",
                 "newphrases" => "kitten,lizard,aarvark,012345678901234567890123456789012345678901234567890123456789aaaaaaaaaaaaaaaaaaaaa",
                 "comments" => "",
                 "active" => true]],
            'Still too long phrases' => [
                ["bin" => "a_new_bin",
                 "type" => "track",
                 "comments" => "",
                 "newphrases" => "kitten,lizard,aarvark,012345678901234567890123456789012345678901234567890123456789a"]]
        ];
    }

    public function modifiedQueryBinWithShortEnoughPhrasesProvider()
    {
        return [
            'Short enough phrases' => [
                ["bin" => "a_new_bin",
                 "type" => "track",
                 "comments" => "",
                 "newphrases" => "kitten,lizard,aarvark,anteater"]],
            'Barely short enough phrases' => [
                ["bin" => "a_new_bin",
                 "type" => "track",
                 "comments" => "",
                 "newphrases" => "kitten,lizard,aarvark,012345678901234567890123456789012345678901234567890123456789"]]
        ];
    }
}
