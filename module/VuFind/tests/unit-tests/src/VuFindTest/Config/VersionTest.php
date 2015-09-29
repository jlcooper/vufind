<?php
/**
 * Version Reader Test Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\Config;
use VuFind\Config\Version;

/**
 * Version Reader Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class VersionTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test the default directory parameter.
     *
     * @return void
     */
    public function testDefaultDir()
    {
        // If we don't pass in a directory, we'll get the APPLICATION_PATH
        $this->assertEquals(
            Version::getBuildVersion(),
            Version::getBuildVersion(realpath(APPLICATION_PATH))
        );
    }

    /**
     * Test with a bad directory.
     *
     * @return void
     *
     * @expectedException        Exception
     * @expectedExceptionMessage Cannot load /will/never/exist/ever/ever/build.xml.
     */
    public function testMissingFile()
    {
        Version::getBuildVersion('/will/never/exist/ever/ever');
    }

    /**
     * Test with a fixture to confirm that the right value is extracted.
     *
     * @return void
     */
    public function testKnownVersion()
    {
        $fixture = __DIR__ . '/../../../../fixtures/configs/buildxml-2.5';
        $this->assertEquals('2.5', Version::getBuildVersion($fixture));
    }
}
