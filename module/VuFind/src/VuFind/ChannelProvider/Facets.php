<?php
/**
 * Facet-driven channel provider.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2016.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\ChannelProvider;
use VuFind\RecordDriver\AbstractBase as RecordDriver;
use VuFind\Search\Base\Params, VuFind\Search\Base\Results;
use VuFind\Search\Results\PluginManager as ResultsManager;
use Zend\Mvc\Controller\Plugin\Url;

/**
 * Facet-driven channel provider.
 *
 * @category VuFind
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Facets extends AbstractChannelProvider
{
    /**
     * Facet fields to use (field name => description).
     *
     * @var array
     */
    protected $fields = [
        'topic_facet' => 'Topic',
        'author_facet' => 'Author',
    ];

    /**
     * Maximum number of different fields to suggest in the channel list.
     *
     * @var int
     */
    protected $maxFieldsToSuggest = 2;

    /**
     * Maximum number of values to suggest per field.
     *
     * @var int
     */
    protected $maxValuesToSuggestPerField = 2;

    /**
     * Search results manager.
     *
     * @var ResultsManager
     */
    protected $resultsManager;

    /**
     * URL helper
     *
     * @var Url
     */
    protected $url;

    /**
     * Constructor
     *
     * @param ResultsManager $rm  Results manager
     * @param Url            $url URL helper
     */
    public function __construct(ResultsManager $rm, Url $url)
    {
        $this->resultsManager = $rm;
        $this->url = $url;
    }

    /**
     * Hook to configure search parameters before executing search.
     *
     * @param Params $params Search parameters to adjust
     *
     * @return void
     */
    public function configureSearchParams(Params $params)
    {
        foreach ($this->fields as $field => $desc) {
            $params->addFacet($field, $desc);
        }
    }

    /**
     * Return channel information derived from a record driver object.
     *
     * @param RecordDriver $driver       Record driver
     * @param string       $channelToken Token identifying a single specific channel
     * to load (if omitted, all channels will be loaded)
     *
     * @return array
     */
    public function getFromRecord(RecordDriver $driver, $channelToken = null)
    {
        $results = $this->resultsManager->get($driver->getSourceIdentifier());
        if (null !== $channelToken) {
            return [$this->buildChannelFromToken($results, $channelToken)];
        }
        $channels = [];
        $fieldCount = 0;
        $data = $driver->getRawData();
        foreach (array_keys($this->fields) as $field) {
            if (!isset($data[$field])) {
                continue;
            }
            $currentValueCount = 0;
            foreach ($data[$field] as $value) {
                $current = [
                    'value' => $value,
                    'displayText' => $value,
                ];
                $channel = $this
                    ->buildChannelFromFacet($results, $field, $current);
                if (count($channel['contents']) > 0) {
                    $channels[] = $channel;
                    $currentValueCount++;
                }
                if ($currentValueCount >= $this->maxValuesToSuggestPerField) {
                    break;
                }
            }
            if ($currentValueCount > 0) {
                $fieldCount++;
            }
            if ($fieldCount >= $this->maxFieldsToSuggest) {
                break;
            }
        }
        return $channels;
    }

    /**
     * Return channel information derived from a search results object.
     *
     * @param Results $results      Search results
     * @param string  $channelToken Token identifying a single specific channel
     * to load (if omitted, all channels will be loaded)
     *
     * @return array
     */
    public function getFromSearch(Results $results, $channelToken = null)
    {
        if (null !== $channelToken) {
            return [$this->buildChannelFromToken($results, $channelToken)];
        }
        $channels = [];
        $fieldCount = 0;
        $facetList = $results->getFacetList();
        foreach (array_keys($this->fields) as $field) {
            if (!isset($facetList[$field])) {
                continue;
            }
            $currentValueCount = 0;
            foreach ($facetList[$field]['list'] as $current) {
                if (!$current['isApplied']) {
                    $channel = $this
                        ->buildChannelFromFacet($results, $field, $current);
                    if (count($channel['contents']) > 0) {
                        $channels[] = $channel;
                        $currentValueCount++;
                    }
                }
                if ($currentValueCount >= $this->maxValuesToSuggestPerField) {
                    break;
                }
            }
            if ($currentValueCount > 0) {
                $fieldCount++;
            }
            if ($fieldCount >= $this->maxFieldsToSuggest) {
                break;
            }
        }
        return $channels;
    }

    /**
     * Add a new filter to an existing search results object to populate a
     * channel.
     *
     * @param Results $results Results object
     * @param string  $field   Field name (for filter)
     * @param array   $value   Field value information (for filter)
     *
     * @return array
     */
    protected function buildChannel(Results $results, $filter, $title)
    {
        $newResults = clone($results);
        $params = $newResults->getParams();

        // Determine the filter for the current channel, and add it:
        $params->addFilter($filter);

        $query = $newResults->getUrlQuery()->addFilter($filter);
        $searchUrl = $this->url->fromRoute($params->getOptions()->getSearchAction())
            . $query;
        $channelsUrl = $this->url->fromRoute('channels-search') . $query
            . '&source=' . urlencode($params->getSearchClassId());

        // Run the search and convert the results into a channel:
        $newResults->performAndProcessSearch();
        return [
            'title' => $title,
            'searchUrl' => $searchUrl,
            'channelsUrl' => $channelsUrl,
            'contents' => $this->summarizeRecordDrivers($newResults->getResults())
        ];
    }

    /**
     * Call buildChannel using data from a token.
     *
     * @param Results $results Results object
     * @param string  $token   Token to parse
     *
     * @return array
     */
    protected function buildChannelFromToken(Results $results, $token)
    {
        $parts = explode('|', $token, 2);
        if (count($parts) < 2) {
            return [];
        }
        return $this->buildChannel($results, $parts[1], $parts[0]);
    }

    /**
     * Call buildChannel using data from facet results.
     *
     * @param Results $results Results object
     * @param string  $field   Field name (for filter)
     * @param array   $value   Field value information (for filter)
     *
     * @return array
     */
    protected function buildChannelFromFacet(Results $results, $field, $value)
    {
        return $this->buildChannel(
            $results,
            "$field:{$value['value']}",
            "{$this->fields[$field]}: {$value['displayText']}"
        );
    }
}
