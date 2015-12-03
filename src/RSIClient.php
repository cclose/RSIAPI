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
 use GuzzleHttp\Exception\BadResponseException;
 use GuzzleHttp\Exception\RequestException;
 use GuzzleHttp\Stream\Stream;
 use RSIAPI\Exception\RequestFailedException;

 /**
 * Client.
 *
 * @author Cory Close <pulsar2612@hotmail.com>
 */
 class RSIClient {

     protected static $RSI_API_URL = 'https://robertsspaceindustries.com/api/';

     /**
      * @var GuzzleClient $client
      */
     protected $client;

     public function __construct(GuzzleClient $client = null) {
         if(is_null($client)) {
             $client = new GuzzleClient(array('defaults' => array('allow_redirects' => false, 'cookies' => true)));
         }

         $this->client = $client;
     }

     /**
      * @return GuzzleClient
      */
     public function getClient() {
         return $this->client;
     }

     /**
      * @return string|null
      */
     protected function getRSIToken() {
         return null;
     }

     /**
      * @param       $url
      * @param array $data
      */
     public function submitRequest($url, array $data = null) {
         $fullurl = $this::$RSI_API_URL . $url;

         $client = $this->getClient();
         $request = $client->createRequest('POST', $fullurl);

         $request->setHeader('X-Requested-With', 'XMLHttpRequest');
         $request->setHeader('Content-Type', 'application/json');

         //set rsi token if one exists
         $rsiToken = $this->getRSIToken();
         if(!is_null($rsiToken)) {
             $request->setHeader('X-Rsi-Token', $rsiToken);
         }

         $payload = json_encode($data);
         $payloadStream = Stream::factory($payload);
         $request->setBody($payloadStream);

         $response = $client->send($request);
         $responseCode = $response->getStatusCode();
         if($responseCode !== 200) {
             $error = "Request returned $responseCode: ".$response->getReasonPhrase();
             throw new BadResponseException($error, $request, $response);
         }

         $body = $response->getBody();

         $returnData = json_decode($body, true);

         return $returnData;
     }

     /**
      * @return mixed
      * @throws RequestFailedException
      */
     public function getFundingData() {
         $data   = array(
             'fans'  => true,
             'fleet' => true,
             'funds' => true
         );
         $return = $this->submitRequest('stats/getCrowdfundStats', $data);

         if(array_key_exists('success', $return) && $return['success'] == 1) {
             $fundData = $return['data'];
         } else {
             throw new RequestFailedException('FundingData Request failed: '.$return['msg']);
         }

         return $fundData;
     }
 }
