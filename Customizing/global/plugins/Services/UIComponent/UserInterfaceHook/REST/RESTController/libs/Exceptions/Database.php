<?php
/**
 * ILIAS REST Plugin for the ILIAS LMS
 *
 * Authors: D.Schaefer and T.Hufschmidt <(schaefer|hufschmidt)@hrz.uni-marburg.de>
 * Since 2014
 */
namespace RESTController\libs\Exceptions;

// This allows us to use shortcuts instead of full quantifier
use \RESTController\libs as Libs;


/**
 * Exception: Database($message, $restCode, $previous)
 *  This exception should be thrown, when
 *  there is an issue related to one of the plugins
 *  OWN database queries, such as empty-queries, etc.
 *  (Do not used this to forward exceptions generated by ILIAS!)
 *
 * Parameters:
 *  @See RESTException for parameter description
 */
class Database extends Libs\RESTException { }
