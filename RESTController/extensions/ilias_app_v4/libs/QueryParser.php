<?php namespace RESTController\extensions\ILIASApp\V4;
use ilLoggerFactory;

class QueryParser {

/** 
 * @var array
 */
   private $queryObj;
   private $queryString;


   public function __construct(array &$obj){
    IlLoggerFactory::getLogger('Lucene')->debug($obj);
    $this->queryObj = $obj;

   }

   public function parse(){
       $this->parseType();
       $this->parseTerm();
       return $this->queryString;
   }

   private function parseType(){

    if (array_key_exists("types", $this->queryObj) && is_array($this->queryObj["types"])) {
        $query = '';
        foreach ($this->queryObj["types"] as $objectType) {
            if (0 === strlen($query)) {
                $query .= '+( ';
            } else {
                $query .= 'OR';
            }
            $query .= (' type:' . (string) $objectType . ' ');
        }
        $query .= ') ';
    }
    $this->queryString .= $query;
   }

   private function parseTerm(){
    if(array_key_exists("query",$this->queryObj)){
        $this->queryString .= $this->queryObj['query'];
    }
   }
}