<?php namespace RESTController\extensions\ILIASApp;

use \RESTController\libs as Libs;
use ILIAS\DI\Container;

require_once('./Services/Membership/classes/class.ilParticipants.php');
require_once('./Modules/File/classes/class.ilObjFile.php');
require_once('./Services/Link/classes/class.ilLink.php');
require_once('./Services/Administration/classes/class.ilSetting.php');


class ILIASAppModel extends Libs\RESTModel
{

    /**
     * @var \ilDB
     */
    protected $db;

    /**
     * @var \ilAccessHandler
     */
    protected $access;


    public function __construct()
    {
        Libs\RESTilias::initILIAS();
        Libs\RESTilias::loadIlUser();
        Libs\RESTilias::initAccessHandling();

        /**
         * @var Container $container
         */
        $container = $GLOBALS["DIC"];

        $this->db = $container->database();
        $this->access = $container->access();
    }

    /**
     * Return courses and groups from desktop
     *
     * @param int $userId
     * @return array
     */
    public function getDesktopData($userId)
    {
        return $this->fetchObjectData(\ilParticipants::_getMembershipByType($userId, array('crs', 'grp')));
    }


    /**
     * Return objects in tree under the given $refId.
     *
     * @param int $refId
     * @param int $userId
     * @return array
     */
    public function getChildren($refId, $userId)
    {
        $sql = 'SELECT object_reference.obj_id FROM tree 
                INNER JOIN object_reference ON (object_reference.ref_id = tree.child AND object_reference.deleted IS NULL)
                WHERE parent = ' . $this->db->quote($refId, 'integer');
        $set = $this->db->query($sql);
        $objIds = array();
        while ($row = $this->db->fetchObject($set)) {
            $objIds[] = $row->obj_id;
        }

        return $this->fetchObjectData($objIds);
    }


    public function getFileData($refId, $userId)
    {
        $file = new \ilObjFile($refId);
        $fileName = mb_strtolower($file->getFileName());
        $fileName = preg_replace('/[^a-z0-9\-_\.]+/', '', $fileName);

        return array(
            'fileExtension' => $file->getFileExtension(),
            'fileName' => $fileName,
            'fileSize' => $file->getFileSize(),
            'fileType' => $file->getFileType(),
            'fileVersion' => $file->getVersion(),
            'fileVersionDate' => $file->getLastUpdateDate(),
        );
    }


    public function getChildrenRecursive($refId, $userId)
    {
        if ($this->isNestedSet()) {
            return $this->getChildrenRecursiveOnNestedSet($refId, $userId);
        } else {
            return $this->getChildrenRecursiveOnMaterializedPath($refId, $userId);
        }
    }


    protected function getChildrenRecursiveOnMaterializedPath($refId, $userId)
    {
        $sql = "SELECT object_reference.obj_id FROM tree AS parent
                INNER JOIN tree AS child ON child.path LIKE CONCAT(parent.path, '.%')
                INNER JOIN object_reference on child.child = object_reference.ref_id
                WHERE parent.child = " . $this->db->quote($refId, 'integer');
        $set = $this->db->query($sql);
        $objIds = array();
        while ($row = $this->db->fetchObject($set)) {
            $objIds[] = $row->obj_id;
        }

        return $this->fetchObjectData($objIds);
    }


    protected function getChildrenRecursiveOnNestedSet($refId, $userId)
    {
        $sql = 'SELECT object_reference.obj_id FROM tree
                INNER JOIN tree AS tree_children ON (tree_children.lft > tree.lft AND tree_children.rgt < tree.rgt)
                INNER JOIN object_reference ON (object_reference.ref_id = tree_children.child AND object_reference.deleted IS NULL)
                WHERE tree.child = ' . $this->db->quote($refId, 'integer');
        $set = $this->db->query($sql);
        $objIds = array();
        while ($row = $this->db->fetchObject($set)) {
            $objIds[] = $row->obj_id;
        }

        return $this->fetchObjectData($objIds);
    }


    protected function isNestedSet()
    {
        $query = "SELECT * FROM settings WHERE keyword LIKE 'main_tree_impl'";
        $set = $this->db->query($query);
        $setting = $this->db->fetchAssoc($set);
        //if nothing is set, then it's a nested set.
        if (!$setting) {
            return true;
        } else {
            return $setting['value'] == 'ns';
        }
    }


    protected function fetchObjectData(array $objIds)
    {
        if (!count($objIds)) {
            return array();
        }
        $sql = "SELECT
                object_data.*,
                tree.child AS ref_id,
                tree.parent AS parent_ref_id
                FROM object_data 
                INNER JOIN object_reference ON (object_reference.obj_id = object_data.obj_id AND object_reference.deleted IS NULL)
                INNER JOIN tree ON (tree.child = object_reference.ref_Id)

                WHERE object_data.obj_id IN (" . implode(',', $objIds) . ") AND object_data.type NOT IN ('rolf', 'itgr')";
        $set = $this->db->query($sql);
        $return = array();

        while ($row = $this->db->fetchAssoc($set)) {
            if (!$this->access->checkAccess('read', '', $row['ref_id'])) {
                continue;
            }

            $return[] = array(
                'objId' => $row['obj_id'],
                'title' => $row['title'],
                'description' => $row['description'],
                'refId' => $row['ref_id'],
                'parentRefId' => $row['parent_ref_id'],
                'type' => $row['type'],
                'link' => \ilLink::_getStaticLink($row['ref_id'], $row['type']),
                'repoPath' => $this->createRepoPath($row['ref_id'])
            );
        }

        return $return;
    }


    /**
     * @param $ref_id int
     * @return array
     */
    protected function createRepoPath($ref_id)
    {
        /**
         * @var Container $container
         */
        $container = $GLOBALS["DIC"];
        $tree = $container->repositoryTree();

        $path = array();
        foreach ($tree->getPathFull($ref_id) as $node) {
            $path[] = $node['title'];
        }

        return $path;
    }
}
