<?php namespace RESTController\extensions\ILIASApp\V3;

use ilLPStatus;
use RESTController\extensions\ILIASApp\V2\data\ErrorAnswer;
use RESTController\libs as Libs;
use ilCalendarSchedule;
use ilDate;
use ilCalendarCategories;
use ilCalendarUserSettings;
use ilCalendarRecurrences;

require_once('./Modules/File/classes/class.ilObjFile.php');


final class ILIASAppModel extends Libs\RESTModel {

    /**
     * @var \ilDB
     */
    private $db;

    /**
     * @var \ilAccessHandler
     */
    private $access;


    public function __construct() {
        global $ilDB, $ilAccess;
        Libs\RESTilias::loadIlUser();
        $this->db = $ilDB;
        $this->access = $ilAccess;
    }


    /**
     * collects the metadata of a file with reference $refId
     *
     * @param $refId int
     * @param $userId int
     * @return array
     */
    public function getFileData($refId, $userId) {
        // check access
        if(!$this->isVisible($refId))
            return ["body" => new ErrorAnswer("Forbidden"), "status" => 403];

        // get file data
        $file = new \ilObjFile($refId);
        // file name
        $fileName = mb_strtolower($file->getFileName());
        $fileName = preg_replace('/[^a-z0-9\-_\.]+/', '', $fileName);

        // learning progress
        global $DIC;
        $ilDB = $DIC['ilDB'];
        $q = "SELECT status FROM ut_lp_marks WHERE obj_id=" . $file->getId() . " AND usr_id=" . $userId;
        $ret = $ilDB->query($q);
        $fileLearningProgress = boolval($ilDB->fetchAssoc($ret)["status"]);

        return ["body" => array(
            'fileExtension' => $file->getFileExtension(),
            'fileName' => $fileName,
            'fileSize' => strval($file->getFileSize()),
            'fileType' => $file->getFileType(),
            'fileVersion' => strval($file->getVersion()),
            'fileVersionDate' => $file->getLastUpdateDate(),
            'fileLearningProgress' => $fileLearningProgress
        )];
    }

    /**
     * sets the lp-status for a file with reference $refId and user $userId to completed
     *
     * @param $refId int
     * @param $userId int
     * @return array
     */
    public function setFileLearningProgressToDone($refId, $userId) {
        // check access
        if(!($this->isVisible($refId) && $this->isRead($refId)))
            return ["body" => new ErrorAnswer("Forbidden"), "status" => 403];

		// set state
		$file = new \ilObjFile($refId);
		$status = ilLPStatus::LP_STATUS_COMPLETED_NUM;

		global $DIC;
		$ilDB = $DIC['ilDB'];
		$q = "INSERT INTO ut_lp_marks (obj_id, usr_id, status) VALUES({$file->getId()}, {$userId}, {$status})
		      ON DUPLICATE KEY UPDATE status={$status}";
		$result = $ilDB->query($q);

		if($result === false)
			return ["body" => new ErrorAnswer("Bad Request"), "status" => 400];

		return ["body" => array("message" => "Learning progress was successfully set to done")];
	}

    /**
     * collects the parameters for the theming of the app
     *
     * @param $timestamp integer timestamp of the last synchronization in the app
     * @return array
     */
    public function getThemeData($timestamp) {
        global $DIC;
        $ilDB = $DIC['ilDB'];
        $q = "SELECT * FROM ui_uihk_pegasus_theme WHERE id=1";
        $ret = $ilDB->query($q);
        if($ret === false)
            return ["body" => new ErrorAnswer("Internal Server Error"), "status" => 500];
        $dat = $ilDB->fetchAssoc($ret);

        $clientName = CLIENT_ID;
        $iconsDir = "data/$clientName/pegasushelper/theme/icons/";
        $keys = ["course", "file", "folder", "group", "learningplace", "learningmodule", "link"];
        $resources = [];
        if($timestamp < intval($dat["timestamp"]))
            foreach($keys as $key)
                $resources[] = ["key" => $key, "path" => $iconsDir . "icon_$key.svg"];

        return ["body" => array(
            "themePrimaryColor" => $dat["primary_color"],
            "themeContrastColor" => boolval($dat["contrast_color"]),
            "themeTimestamp" => intval($dat["timestamp"]),
            "themeIconResources" => $resources
        )];
    }

  /**
     * Retrieves up to 1000 future calendar entries in JSON format
     *
     * @param $user_id 
     * @return JSON
     */
    function getEvents($user_id)
    {
        $cats = ilCalendarCategories::_getInstance($user_id);
        $settings = ilCalendarUserSettings::_getInstance();
        if ($settings->getCalendarSelectionType() == ilCalendarUserSettings::CAL_SELECTION_MEMBERSHIP)
            $cats->initialize(ilCalendarCategories::MODE_PERSONAL_DESKTOP_MEMBERSHIP);
        else
            $cats->initialize(ilCalendarCategories::MODE_PERSONAL_DESKTOP_ITEMS);

        $schedule = new ilCalendarSchedule(new ilDate(time(), IL_CAL_UNIX), ilCalendarSchedule::TYPE_INBOX);
        $schedule->setEventsLimit(1000);
        $schedule->addSubitemCalendars(true);
        $schedule->calculate();
        $events = $schedule->getScheduledEvents();

        $result = array();
        foreach ($events as $event) {

            $entry = $event['event'];
            $catInfo = $cats->getCategoryInfo($event['category_id']);
            $freq = ilCalendarRecurrences::_getFirstRecurrence($entry->getEntryId());
            $rec = ilCalendarRecurrences::_getRecurrences($entry->getEntryId());


            $actualCategory = array(
                        'calendar_id' => $event['category_id'],
                        'calendar_type' => $event['category_type'],
                        'calendar_title' => $catInfo['title'],
                        'calendar_color' => $catInfo['color'],
                        'events' => array()
            );

            if (!isset($result[$event['category_id']])){
                $result[$event['category_id']] = $actualCategory;
            }

            $actualEvent = array(
                        'event_id' => $entry->getEntryId(),
                        'event_title' => $entry->getTitle(),
                        'event_subtitle' => $entry->getSubtitle(),
                        'event_location' => $entry->getLocation(),
                        'event_description' => $entry->getDescription(),
                        'event_beginDate' => $event['dstart'],
                        'event_endDate' => $event['dend'],
                        'event_duration' =>  $event['dend'] - $event['dstart'],
                        'event_last_update' => $entry->getLastUpdate()->get(IL_CAL_UNIX),
                        'event_frequence' => $freq->getFrequenceType()
            );

            $result[$event['category_id']]['events'][] = $actualEvent;
        }

   

        return array_values($result);
    }

    /**
     * Checks the access right of the given $refId for visible permission.
     *
     * @param $refId int a ref_id to check the access
     * @return bool true if the permission is visible, otherwise false
     */
    private function isVisible($refId) {
        return $this->access->checkAccess('visible', '', $refId);
    }


    /**
     * Checks the access right of the given $refId for read permission.
     *
     * @param $refId int a ref_id to check the access
     * @return bool true if the permission is read, otherwise false
     */
    private function isRead($refId) {
        return $this->access->checkAccess('read', '', $refId);
    }
}