<?php namespace RESTController\extensions\ILIASApp\V4;

use RESTController\libs as Libs;
use ilContainerReference;
use ilSessionAppointment;
use RESTController\extensions\ILIASApp\V2\data\IliasTreeItem;


final class ILIASAppModel extends Libs\RESTModel{


    /**
     * @var \ilDB
     */
    private $db;

    /**
     * @var \ilAccessHandler
     */
    private $access;
    private $logger;


    	/**
	 * Holds all reference types which may use the
	 * title of the element they are referring to.
	 *
	 * @var string[]
	 */
	private static $REFERENCE_TYPES = [
		'grpr',
		'catr',
		'crsr'
	];


    public function __construct() {
        global $ilDB, $ilAccess, $ilLog;
        Libs\RESTilias::loadIlUser();
        $this->access = $ilAccess;
        $this->logger = $ilLog;
        $this->db = $ilDB;

    }

    public function search($query, $user_id){
        
        //TODO Check if server is running

        //guard for enabled search settings
        if (\ilSearchSettings::getInstance()->enabledLucene() == false)
            return "Lucene disabled: please enable Lucene";
        
        // construct/parse query
        $query_parser = new \ilLuceneQueryParser($query);
        $query_parser->parse();
        
        $searcher = \ilLuceneSearcher::getInstance($query_parser);
        $searcher->search();

        $filter = \ilLuceneSearchResultFilter::getInstance($user_id);
        $filter->setCandidates($searcher->getResult());
        $filter->filter();

        $result_ids = $filter->getResults();
        $objs[] = $this->fetchObjectData($result_ids); 

        // foreach ((array) $result_ids as $ref_id => $obj_id) {
        //     $obj = \ilObjectFactory::getInstanceByRefId($ref_id, false);
        //     if ($obj instanceof \ilObject) {

        //         // if ($obj->getType() == 'file'){
        //         //     //TODO get Meta data
        //         //     $objs[] = $v3AppModel->getFileData($ref_id, $user_id);
        //         // }
        //         // else {
        //         //     $objs[] = $v2AppModel->getObjec
        //         //     //TODO get object data
        //         // }
        //     }
        // }

        return $objs;

    }





        /**
     * @param string[] $objIds
     *
     * @return IliasTreeItem[]
     */
	private function fetchObjectData(array $objIds)
	{

		if (!count($objIds)) {
			return array();
		}
		$sql = "SELECT
                object_data.*,
                tree.child AS ref_id,
                tree.parent AS parent_ref_id,
                page_object.parent_id AS page_layout,
                cs.value AS timeline
                FROM object_data
                  INNER JOIN object_reference ON (object_reference.obj_id = object_data.obj_id AND object_reference.deleted IS NULL)
                  INNER JOIN tree ON (tree.child = object_reference.ref_Id)
                  LEFT JOIN page_object ON page_object.parent_id = object_data.obj_id
                  LEFT JOIN container_settings AS cs ON cs.id = object_data.obj_id AND cs.keyword = 'news_timeline'
                WHERE (object_data.obj_id IN (" . implode(',', $objIds) . ") AND object_data.type NOT IN ('rolf', 'itgr'))
                GROUP BY object_data.obj_id;";
        $set = $this->db->query($sql);
		$return = array();

		while ($row = $this->db->fetchAssoc($set)) {

			if(!$this->isVisible($row['ref_id'])) {
				continue;
			}

			if ($this->isRead($row['ref_id'])) {
				$row['permissionType'] = "read";
			} else {
				$row['permissionType'] = "visible";
			}

			$treeItem = new IliasTreeItem(
                strval($row['obj_id']),
                strval($row['title']),
                strval($row['description']),
                ($row['page_layout'] !== NULL),
                (intval($row['timeline']) === 1),
                strval($row['permissionType']),
                strval($row['ref_id']),
                strval($row['parent_ref_id']),
                strval($row['type']),
                strval(\ilLink::_getStaticLink($row['ref_id'], $row['type'])),
                $this->createRepoPath($row['ref_id'])
            );

			$treeItem = $this->fixSessionTitle($treeItem);
			$treeItem = $this->fixReferenceTitle($treeItem);
            $return[] = $treeItem;
		}

		return $return;
    }
    

	/**
	 * Fixes the title for reference repository objects.
	 *
	 * @param IliasTreeItem $treeItem   The item which may need a title fix.
	 *
	 * @return IliasTreeItem            A clone of the ilias tree item with the fixed title.
	 */
	private function fixReferenceTitle(IliasTreeItem $treeItem) {
		if(in_array($treeItem->getType(), self::$REFERENCE_TYPES)) {
			require_once './Services/ContainerReference/classes/class.ilContainerReference.php';
			$targetTitle = ilContainerReference::_lookupTitle($treeItem->getObjId());
			$treeItem = $treeItem->setTitle($targetTitle);
		}
		return $treeItem;
	}

	private function fixSessionTitle(IliasTreeItem $treeItem) {
	    if($treeItem->getType() === "sess") {
	        // required for ILIAS 5.2
	        require_once './Modules/Session/classes/class.ilSessionAppointment.php';

            $appointment = ilSessionAppointment::_lookupAppointment($treeItem->getObjId());
            $title = strlen($treeItem->getTitle()) ? (': '. $treeItem->getTitle()) : '';
            $title = ilSessionAppointment::_appointmentToString($appointment['start'], $appointment['end'],$appointment['fullday']) . $title;
            return $treeItem->setTitle($title);
        }

        return $treeItem;
    }


	/**
	 * @param $ref_id int
	 * @return array
	 */
	private function createRepoPath($ref_id)
	{
		global $tree;
		$path = array();
		foreach ($tree->getPathFull($ref_id) as $node) {
			$path[] = strval($node['title']);
		}

		return $path;
	}


	/**
	 * Checks the access right of the given $refId for visible permission.
	 *
	 * @param $refId int a ref_id to check the access
	 *
	 * @return bool true if the permission is visible, otherwise false
	 */
	private function isVisible($refId) {
		return $this->access->checkAccess('visible', '', $refId);
	}


	/**
	 * Checks the access right of the given $refId for read permission.
	 *
	 * @param $refId int a ref_id to check the access
	 *
	 * @return bool true if the permission is read, otherwise false
	 */
	private function isRead($refId) {
		return $this->access->checkAccess('read', '', $refId);
	}


}