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

 use DOMElement;
 use DOMNode;
 use DOMNodeList;
 use DOMXPath;
 use GuzzleHttp\Client as GuzzleClient;
 use GuzzleHttp\Exception\BadResponseException;
 use GuzzleHttp\Exception\RequestException;
 use GuzzleHttp\Stream\Stream;
 use RSIAPI\Exception\BadResponseDataException;
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
             $client = new GuzzleClient(array('defaults' => array('allow_redirects' => false, 'cookies' => false)));
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
      *
      * @return array
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

         if(array_key_exists('success', $returnData) && $returnData['success'] == 1) {
             $payload = $returnData['data'];
         } else {
             throw new BadResponseException($url . ' Request failed: '.$returnData['msg'], $request, $response);
         }

         return $payload;
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
         $fundData = $this->submitRequest('stats/getCrowdfundStats', $data);

         return $fundData;
     }

     /**
      * @param $orgId
      *
      * @return array('totalMembers', 'memberList')
      * @throws BadResponseDataException
      * @throws RequestFailedException
      */
     public function getOrgMembers($orgId) {
         $pageSize = 30;
         $memberData = $this->retrieveOrgMemberlist($orgId, 1, $pageSize);
         if(!array_key_exists('totalrows', $memberData)) {
             throw new BadResponseDataException('getOrgMembers missing value \'totalrows\'');
         }
         $totalMembers = $memberData['totalrows'];

         $members = $this->parseMemberData($memberData);

         //find the number of pages by dividing total by page size, rounding up
         $numPages = ceil($totalMembers / $pageSize);
         //retrieve all pages. Our first request already got page 1, so start at 2
         for($page = 2; $page <= $numPages; $page++) {
             $memberData = $this->retrieveOrgMemberlist($orgId, $page, $pageSize);
             $members = array_merge($members, $this->parseMemberData($memberData));
         }


         $return = array(
             'totalMembers' => $totalMembers,
             'memberList' => $members
         );

         return $return;
     }

     /**
      * @param     $orgId
      * @param     $page
      * @param int $pageSize
      *
      * @return mixed
      * @throws RequestFailedException
      */
     private function retrieveOrgMemberlist($orgId, $page, $pageSize=30) {
         $data = array(
             'page' => $page,
             'pagesize' => $pageSize,
             'search' => '',
             'symbol' => $orgId
         );
         $memberData = $this->submitRequest('orgs/getOrgMembers', $data);

         return $memberData;
     }

     /**
      * @param array $memberData
      *
      * @return array
      * @throws BadResponseDataException
      */
     private function parseMemberData(array $memberData) {
         $members = array();
         if(!array_key_exists('html', $memberData)) {
             throw new BadResponseDataException('getOrgMembers missing value \'html\'');
         }
         $html = $memberData['html'];
         if(!is_null($html) && $html != '') {

             $DOM = new \DOMDocument();
             $DOM->loadHTML($html);
             $finder    = new DomXPath($DOM);

             //get the member-item li's
             $nodes     = $finder->query("//li[contains(@class, 'member-item')]");

             /** @var DOMElement $node */
             foreach($nodes as $node) {
                 $affiliate = true;
                 $type = false;
                 $name = null;
                 $nick = null;
                 $rank = null;
                 $roles = array();


                 //get the class of the li
                 // this contains main/affil and visibility
                 $class = $node->getAttribute('class');
                 //visible members
                 if(preg_match('/org-visibility-V/', $class)) {
                     $type = 'main';
                 }
                 //redacted
                 if(preg_match('/org-visibility-R/', $class)) {
                     $type = 'reddacted';
                 }
                 //hidden
                 if(preg_match('/org-visibility-H/', $class)) {
                     $type = 'hidden';
                 }

                 //non-affils have this
                 if(preg_match('/org-main/', $class)) {
                     $affiliate = false;
                 }

                 //VERY IMPORTANT NOTE
                 // If you are back-engineering this THE LEADING PERIOD IS VITALLY IMPORTANT
                 // the // makes the f%$%^ing query relative to the document root, so you NEED
                 // the leading period to make the query relative to the $node
                 //
                 // When i originally wrote this, i didn't have the leading period and this query
                 // returned ALL name-wrap spans from the return data, every. time.
                 // SUCH FRUSTERATION. MANY BALD. WOW.
                 $nameNode = $finder->query(".//span[contains(@class, 'name-wrap')]", $node);
                 //we should only have on name-wrap span. bitch if this is not true
                 if($nameNode->length == 0) {
                    throw new BadResponseDataException('No NameNodes detected for member node');
                 }
                 if($nameNode->length > 1) {
                     throw new BadResponseDataException('Multiple NameNodes detected for member node');
                 }

                 //get the name-wrap span. 0 because we only got one, DUH
                 $nameNode = $nameNode->item(0);
                 //get the childen spans, theses contain Nick and Name
                 $nameNodes = $nameNode->getElementsByTagName('span');
                 /** @var DOMElement $nameNode */
                 foreach($nameNodes as $nameNode) {
                     $nnclass = $nameNode->getAttribute('class');
                     //if this is the name node
                     if(preg_match('/name/', $nnclass)) {
                         $name = $nameNode->textContent;
                     }
                     //or if this is the nick node
                     if(preg_match('/nick/', $nnclass)) {
                         $nick = $nameNode->textContent;
                     }
                 }
                 //TODO filter out names/nicks that are 100% &nbsp;

                 //get the rank div
                 $rankDiv = $finder->query(".//span[contains(@class, 'rank') and not(contains(@class, 'ranking-stars'))]", $node);
                 if($rankDiv->length > 1) {
                     throw new BadResponseDataException('Multiple Rank Divs detected for member node');
                 } elseif($rankDiv->length == 1) {
                     $rankDiv = $rankDiv->item(0); //zero because there's only 1
                     $rank = $rankDiv->textContent;
                 }

                 //get the role ul
                 $roleUL = $finder->query(".//ul[contains(@class, 'rolelist')]", $node);
                 if($roleUL->length > 1) {
                     throw new BadResponseDataException('Multiple RoleULs detected for member node');
                 } elseif($roleUL->length == 1) {
                     $roleUL = $roleUL->item(0); //zero because there's only 1
                     //get the child li's, these have Roles in them
                     $roleLIs = $roleUL->getElementsByTagName('li');
                     foreach($roleLIs as $roleLI) {
                         $rliclass = $roleLI->getAttribute('class');
                         //if this is a role li
                         if(preg_match('/role/', $rliclass)) {
                             $roles[] = $roleLI->textContent;
                         }
                     }
                 }

                 //package it up nicely
                 //TODO replace with a class-struct
                 $memberInfo = array(
                     'name' => $name,
                     'nick' => $nick,
                     'affiliate' => $affiliate,
                     'vis' => $type,
                     'rank' => $rank,
                     'roles' => $roles
                 );
                 $members[] = $memberInfo;

             }
         }

       return $members;
     }

 }
