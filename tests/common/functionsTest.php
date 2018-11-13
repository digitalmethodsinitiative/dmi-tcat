<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '../../../common/functions.php';

class Test_functions extends TestCase
{
    public function setUp()
    {
        // setup goes here
    }

    /**
     * @dataProvider shortEnoughPhrasesProvider
     *
     * @keywords array Optionally comma separated list of keywords
     * @return   boolean
     */
    public function testValidateCapturePhrasesWithShortEnoughPhrasesShouldPass($keywords)
    {
        $this->assertTrue(validate_capture_phrases($keywords));
    }

    /**
     * @dataProvider illegalCharactersInPhrasesProvider
     *
     * @keywords array Optionally comma separated list of keywords
     * @return   boolean
     */
    public function testValidateCapturePhrasesWithIllegalCharactersShouldFail($keywords)
    {
        $this->assertFalse(validate_capture_phrases($keywords));
    }

    /**
     * @dataProvider tooLongPhrasesProvider
     *
     * @keywords array Optionally comma separated list of keywords
     * @return   boolean
     */
    public function testValidateCapturePhrasesWithTooLongPhrasesShouldFail($keywords)
    {
        $this->assertFalse(validate_capture_phrases($keywords));
    }

    /**
     * @dataProvider tooLongPhrasesDueToSpacesProvider
     *
     * @keywords array Optionally comma separated list of keywords 
     * @return   boolean
     */
    public function testValidateCapturePhrasesWithTooLongPhrasesBecauseOfSpacesShouldPass($keywords)
    {
        $this->assertTrue(validate_capture_phrases($keywords));
    }

    /**
     * @dataProvider tooLongPhrasesDueToCommasProvider
     *
     * @keywords array Optionally comma separated list of keywords
     * @return   boolean
     */
    public function testValidateCapturePhrasesWithTooLongPhreasesBecauseOfCommasShouldPass($keywords)
    {
        $this->assertTrue(validate_capture_phrases($keywords));
    }

    /**
     * Data providers
     */

    /**
     * Just straighforwardly reasonable input for a query phrase.
     *
     * @return array An array of arrays of keywords
     */
    public function shortEnoughPhrasesProvider()
    {
        return [
            'Short enough phrases' => ["kitten,lizard,aarvark,anteater"],
            'Barely short enough phrases' => ["kitten,lizard,aarvark,012345678901234567890123456789012345678901234567890123456789"],
            'Repeated commas but short enough' => ["kitten,lizard,,,,aarvark,anteater"]
        ];
    }
            
    /**
     * Input for query phrase which contains illegal characters.
     *
     * @return array An array of arrays of keywords
     */
    public function illegalCharactersInPhrasesProvider()
    {
        return [
            'Only illegal phrases' => [
                "\t", "\n", ";", "(", ")", "\t\n", "()"],
            'Starts with illegal character' => [
                "\ttab and other stuff",
                "(stuff in parens) and then something else"],
            'Ends with illegal character' => ["Hello :)", "end of line\n"]
        ];
    }

    /**
     * Input for query phrase which contains too long phrases.
     *
     * @return array An array of arrays of keywords
     */
    public function tooLongPhrasesProvider()
    {
        return [
            'Way too long phrase' => ["kitten,lizard,aarvark,012345678901234567890123456789012345678901234567890123456789aaaaaaaaaaaaaaaaaaaaa"],
            'Still long long phrases' => ["kitten,lizard,aarvark,012345678901234567890123456789012345678901234567890123456789a"]
        ];
    }

    /**
     * Input for query phrase which contains too long phrases with space.
     *
     * @return array An array of arrays of keywords
     */
    public function tooLongPhrasesDueToSpacesProvider()
    {
        return [
            'Just a load of space' => ["                                                                      "],
            'Too long with space in the beginning' => [
                "",
                "kitten,aardvark                                                            ",
                "kitten,aardvark,                                                            ",
            ],
            'Too long with space in the end' => [
                "                                                       aardvark", "                                                       ,aardvark"
            ]
        ];
    }

    /**
     * These are just plain weird.
     *
     * @return array An array of arrays of keywords
     */
    public function tooLongPhrasesDueToCommasProvider()
    {
        return [
            'Just a load of commas' => [",,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,"],
            'Too long with a comma in the beginning' => [",kitten,lizard,aarvark,012345678901234567890123456789012345678901234567890123456789"],
            'Too long with commas in the beginning' => [",,,,,kitten,lizard,aarvark,012345678901234567890123456789012345678901234567890123456789"]
        ];
    }
}
