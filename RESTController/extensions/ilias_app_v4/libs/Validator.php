<?php namespace RESTController\extensions\ILIASApp\V4;

use RESTController\libs\RESTException;
use RESTController\RESTController;

class Validator {

   static $schema = array(
    "message" => '',
    "types" => [
        "file",
        "grp",
        "crs",
        "objf"
],
    "query" => "searchTerm"
);

   public static function validateLuceneQuery () {
       return function ($route){
        $app = RESTController::getInstance();
        $body = $app->request->getBody();

        //if body is not an array, either an empty json was provided or no body at all
        if (!is_array($body)){
            Validator::$schema["message"] = 'please enter query';
            $app->halt(201, Validator::$schema);
            }
       };


    }



}