// Use ECMAScript 5 restricted variant of Javascript
'use strict';

/*
 * All filters will be stored in this module.
 */
var filters = angular.module('myApp.filters', []);


/*
 * Replace VERSION string with given version number.
 */
filters.filter('interpolate', [ 
    'version', 
    function(version) {
        return function(text) {
            return String(text).replace(/\%VERSION\%/mg, version);
        };
    } 
]);


/*
 * Replace INFO variable with additional formated warning information.
 */
filters.filter('restInfo',function() {
    return function(text, status, data) {
        var statusClean = addslashes(status);
        var dataClean = addslashes(data);
                
        return String(text).replace(/\%INFO\%/mg, '(Status: <u><span href="#" tooltip="'+dataClean+'">'+statusClean+'</span></u>)');
    };
});


/*
 * Used to format (prettify) client permission (clientlist.html)
 * by adding predefined css-classes for each permission.
 */
filters.filter('formatListPermissions', function($sce) {
    return function(value) {
        if (typeof value != 'undefined') {
            var jsonValue = angular.fromJson(value);
            
            var resultHtml = '<table>';
            for (var i = 0; i < jsonValue.length; i++) {
                resultHtml += '<tr><td style="width: 5em">';
                
                switch (jsonValue[i].verb) {
                case "GET":
                    resultHtml += '<span class="label label-primary">GET</span>';
                    break;
                case "POST":
                    resultHtml += '<span class="label label-success">POST</span>';
                    break;
                case "PUT":
                    resultHtml += '<span class="label label-warning">UPDATE</span>';
                    break;
                case "DELETE":
                    resultHtml += '<span class="label label-danger">DELETE</span>';
                    break;
                }
                
                resultHtml += '</td><td><span class="label label-permission">' + jsonValue[i].pattern + '</span></td></tr>';
            }
            resultHtml += '</table>';
            
            return $sce.trustAsHtml(resultHtml);
        }
        
        return "";
    };
});
filters.filter('formatEditPermissions', function($sce) {
    return function(value) {
        if (typeof value != 'undefined') {
            var jsonValue = angular.fromJson(value);
            
            var resultHtml;
            switch (jsonValue.verb) {
            case "GET":
                resultHtml = '<span class="label label-primary">GET</span>';
                break;
            case "POST":
                resultHtml = '<span class="label label-success">POST</span>';
                break;
            case "PUT":
                resultHtml = '<span class="label label-warning">UPDATE</span>';
                break;
            case "DELETE":
                resultHtml = '<span class="label label-danger">DELETE</span>';
                break;
            }
            resultHtml += '<span class="label label-permission">' + jsonValue.pattern + '</span>';
            
            return $sce.trustAsHtml(resultHtml);
        }
        
        return "";
    };
});


/*
 * Convert a (html) string to a (possibly) unsafe but trusted string
 * such that it can be used in ng-bind-html (or else).
 */
filters.filter('toTrusted', ['$sce', function($sce){
    return function(text) {
        return $sce.trustAsHtml(text);
    };
}]);