<?php

namespace RESTController\extensions\eBook\v2\models;

use ILIAS\DI\Container;
use \RESTController\libs as Libs;

final class EBookModel extends Libs\RESTModel
{
    /**
     * @var \ilDB
     */
    private $db;

    /**
     * @var \ilAccessHandler
     */
    private $access;


    public function __construct($load_user = true)
    {
        /**
         * @var Container $dic
         */
        $dic = $GLOBALS['DIC'];
        if ($load_user) {
            Libs\RESTilias::loadIlUser();
            try {
                Libs\RESTilias::initAccessHandling();
            } catch (\Exception $exception) {
                // noop in certain ilias versions access handling is already initialized.
            }
        }
        $this->db = $dic->database();
        $this->access = $dic->access();
    }

    /**
     * @param $user_id int
     *
     * @return mixed
     */
    public function getEBooks($user_id)
    {
        global $DIC;

        $access = $DIC->access();
        $config = new \ileBookConfig($DIC->database());
        $root_ref = $config->get('library_root_ref_id');


        $query = "
				SELECT id, ref.ref_id, data.title, data.description, data.create_date, data.last_update, language_code, key_words, chapter_sequence, cover_updated_at FROM rep_robj_xebk_data AS book
				INNER JOIN object_data AS data ON data.obj_id = book.id 
				INNER JOIN object_reference AS ref ON ref.obj_id = data.obj_id 
				WHERE is_online = 1 AND ref.deleted is NULL 
				";
        $set = $this->db->query($query);
        $books = [];
        while ($res = $this->db->fetchAssoc($set)) {
            if (!$access->checkAccessOfUser($user_id, "read", "", $res['ref_id'])) {
                continue;
            }
            $path = $this->createPath($res, $root_ref);
            $res['id'] = (int) $res['id'];
            $res['ref_id'] = (int) $res['ref_id'];
            $res['chapter_sequence'] = (int) $res['chapter_sequence'];
            $res['cover_updated_at'] = (int) $res['cover_updated_at'];
            $res['path'] = $path;
            // Path must contain root ref. otherwise we are not in scope.
            if (0 == count(array_filter($path, function ($element) use ($root_ref) {
                return $element['ref_id'] == $root_ref;
            }))) {
                continue;
            }
            $books[] = $res;
        }
        return $books;
    }

    /**
     * @param $res  array
     * @param $root_ref
     *
     * @return \array[]
     */
    private function createPath($res, $root_ref)
    {
        /**
         * @var Container $container
         */
        $container = $GLOBALS["DIC"];
        $tree = $container->repositoryTree();

        $path = $tree->getNodePath($res['ref_id']);
        $path = array_map(function ($element) {
            return ["ref_id" => $element['child'], "title" => $element['title']];
        }, $path);
        $path_ids = array_map(function ($e) {
            return $e['ref_id'];
        }, $path);
        $slice_id = array_search($root_ref, $path_ids);
        if ($slice_id === false) {
            return [];
        }
        return array_slice($path, $slice_id, -1);
    }

    /**
     * @param $user_id
     * @param $ref_id
     *
     * @return string
     * @throws NoAccessException
     * @throws NoFileException
     */
    public function getFilePathByRefId($user_id, $ref_id)
    {
        if (!$this->checkAccessOfUser($user_id, $ref_id)) {
            throw new NoAccessException();
        }

        $object = new \ilObjeBook($ref_id);

        if (!$object->hasFile()) {
            throw new NoFileException();
        }

        return $object->getEncryptedFilePath();
    }

    /**
     * @param $user_id
     * @param $ref_id
     *
     * @return string
     * @throws NoAccessException
     * @throws NoFileException
     */
    public function getCoverPathByRefId($user_id, $ref_id)
    {
        if (!$this->checkAccessOfUser($user_id, $ref_id)) {
            throw new NoAccessException();
        }

        $object = new \ilObjeBook($ref_id);

        if (!$object->hasCover()) {
            throw new NoFileException();
        }

        return $object->getCoverPath();
    }


    public function getKeyByRefId($user_id, $ref_id)
    {
        if (!$this->checkAccessOfUser($user_id, $ref_id)) {
            throw new NoAccessException();
        }

        $object = new \ilObjeBook($ref_id);

        if (!$object->hasFile()) {
            throw new NoFileException("There's no key to this ref id.");
        }

        return bin2hex($object->getSecret());
    }

    public function getIVByRefId($user_id, $ref_id)
    {
        if (!$this->checkAccessOfUser($user_id, $ref_id)) {
            throw new NoAccessException();
        }

        $object = new \ilObjeBook($ref_id);

        if (!$object->hasFile()) {
            throw new NoFileException("There's no key to this ref id.");
        }

        return bin2hex($object->getInitialVector());
    }

    private function checkAccessOfUser($user_id, $ref_id)
    {
        global $DIC;

        $access = $DIC->access();
        $db = $DIC->database();
        if (!$access->checkAccessOfUser($user_id, "read", "", $ref_id)) {
            return false;
        }

        $query = "SELECT * FROM rep_robj_xebk_data as data
					INNER JOIN object_reference ref ON ref.obj_id = data.id AND ref.ref_id = {$db->quote($ref_id, "integer")}
					WHERE data.is_online = 1";
        $set = $db->query($query);
        if ($db->numRows($set) == 0) {
            return false;
        }

        return true;
    }
}
