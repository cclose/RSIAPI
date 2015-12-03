<?php

namespace RSIAPI\Test;

use RSIAPI\RSIClient;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\Response as GuzzleResponse;


class RSIClientTest extends \PHPUnit_Framework_TestCase {

    /**
     * @return GuzzleClient
     */
    protected function getGuzzle() {
        return new GuzzleClient(array('defaults' => array('allow_redirects' => false, 'cookies' => true)));
    }

    /**
     *
     */
    public function testDefaultGuzzle() {
        $client = new RSIClient();
        $this->assertInstanceOF('GuzzleHttp\\ClientInterface', $client->getClient());
    }

    /**
     *
     */
    public function testCustomClient() {
        $guzzle = $this->getGuzzle();
        $client = new RSIClient($guzzle);
        $this->assertSame($guzzle, $client->getClient());
    }

    public function testFundingRequest() {
        $client = new RSIClient();
        $fundingData = $client->getFundingData();

        $this->assertArrayHasKey('fans',  $fundingData);
        $this->assertArrayHasKey('fleet', $fundingData);
        $this->assertArrayHasKey('funds', $fundingData);
    }

}
