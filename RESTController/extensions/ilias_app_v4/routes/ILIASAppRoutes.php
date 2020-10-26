<?php namespace RESTController\extensions\ILIASApp\V4;

use ilLoggerFactory;
use RESTController\libs\RESTAuth as RESTAuth;

require_once './Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/REST/RESTController/extensions/ilias_app_v4/libs/Validator.php';
require_once './Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/REST/RESTController/extensions/ilias_app_v4/libs/QueryParser.php';


$app->group('/v4/ilias-app', function () use ($app) {

    $app->post('/search', RESTAuth::checkAccess(RestAuth::TOKEN),Validator::validateLuceneQuery(), function() use($app){
        $accessToken = $app->request->getToken();
        $userId = $accessToken->getUserId();
        $body = $app->request->getBody();
        IlLoggerFactory::getLogger('Lucene')->debug($body);
        $parser = new QueryParser($body);

        $query = $parser->parse();
        IlLoggerFactory::getLogger('Lucene')->debug($query);

        $iliasApp = new ILIASAppModel();
        $iliasApp->search($query, $userId);
        $objects = $iliasApp->search($query,$userId);
        $app->response()->body(json_encode($objects));
    });

});