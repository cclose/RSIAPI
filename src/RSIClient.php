<?php

/*
 * This file is part of the RSIAPI package.
 *
 * (c) Cory Close <pulsar2612@hotmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

 namespace RSIAPI;

 use GuzzleHttp\Client as GuzzleClient;
 use GuzzleHttp\ClientInterface as GuzzleClientInterface;
 use GuzzleHttp\Exception\RequestException;
 use GuzzleHttp\Message\RequestInterface;
 use GuzzleHttp\Message\Response as GuzzleResponse;

/**
 * Client.
 *
 * @author Cory Close <pulsar2612@hotmail.com>
 */
class RSIClient {

  /**
   * @var GuzzleClient $client
   */
  protected $client;

  public function __construct(GuzzleClient $client = null) {
    if(is_null($client)) {
      $this->client = new GuzzleClient(array('defaults' => array('allow_redirects' => false, 'cookies' => true)));
    }

    $this->client = $client;
  }

  public function sayHi() {
    echo "Hello again, Sybil";
  }
   
}
