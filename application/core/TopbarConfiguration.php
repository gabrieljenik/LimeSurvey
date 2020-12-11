<?php

class TopbarConfiguration
{
    /** @var string Name of the topbar view */
    private $viewName = '';

    /** @var string Topbar ID */
    private $id = '';

    /** @var array Data to be passed to the view */
    private $data = array();

    /** @var array Extra data for topbar */
    private $extraData = array();

    /** @var string Name of the view used to render the left side of the topbar */
    private $leftSideView = '';

    /** @var string Name of the view used to render the right side of the topbar */
    private $rightSideView = '';

    /** @var array Maps views to the methods used to get their extra data */
    private $extraDataMapping = [
        'surveyTopbar_view' => 'TopbarConfiguration::getSurveyTopbarData',
        'responsesTopbarLeft_view' => 'TopbarConfiguration::getResponsesTopbarData',
        'surveyTopbarRight_view' => 'TopbarConfiguration::getRightSurveyTopbarData',
    ];

    /**
     * Creates and instance of TopbarConfiguration based on the received $config array, 
     * which is expected to have the following keys (all keys are optional):
     *  'name' => The name of the main view to use.
     *  'topbarId' => The topbar ID. Will normally be used as ID for container html element of the topbar.
     *  'leftSideView' => The name of the view to use for the left side of the topbar.
     *  'rightSideView' => The name of the view to use for the right side of the topbar.
     *  'extraDataMethod' => The name of the 'callable' to use to retrieve extra data for the topbar (ex: responses::getResponsesTopBarData). The method must exist and accept a SID as parameter.
     * 
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        // Set defaults
        $this->viewName = isset($config['name']) ? $config['name'] : 'surveyTopbar_view';
        $this->id = isset($config['topbarId']) ? $config['topbarId'] : 'surveybarid';

        if (isset($config['leftSideView'])) $this->leftSideView = $config['leftSideView'];
        if (isset($config['rightSideView'])) {
            $this->rightSideView = $config['rightSideView'];
        } elseif (!empty($config['showSaveButton'])||!empty($config['showCloseButton'])||!empty($config['showImportButton'])||!empty($config['showExportButton'])) {
            // If no right side view has been specified, and showSaveButton is set, use the default right side view.
            $this->rightSideView = "surveyTopbarRight_view";
        }

        $this->data = $config;

        /*// If the topbar is the general survey topbar, set the proper extraDataMethod
        if ($this->viewName == 'surveyTopbar_view') {
            $config["extraDataMethod"] = "TopbarConfiguration::getSurveyTopbarData";
        }

        // If "extraDataMethod" is specified, and exists, call it and add the result to the data array
        if (!empty($config["extraDataMethod"])) {
            $this->extraData = call_user_func($config["extraDataMethod"], !empty($this->data['sid']) ? $this->data['sid'] : null);
            $this->data = array_merge(
                $this->extraData,
                $this->data
            );
        }*/
    }

    /**
     * Creates and instance of TopbarConfiguration based on the data array used for the views
     * 
     * @param array $aData
     * @return TopbarConfiguration
     */
    public static function createFromViewData($aData)
    {
        $config = isset($aData['topBar']) ? $aData['topBar'] : [];

        // If 'sid' is not specified in the topbar config, but is present in the $aData array, assign it to the config
        if (empty($config['sid']) && isset($aData['sid'])) $config['sid'] = $aData['sid'];
     
        return new self($config);
    }

    /**
     * Gets the data for the specified view by calling the corresponding method and merging with general view data
     */
    public function getViewData($view)
    {
        if (empty($view)||empty($this->extraDataMapping[$view])) return [];
        $extraData = call_user_func($this->extraDataMapping[$view], !empty($this->data['sid']) ? $this->data['sid'] : null);
        if (!empty($extraData)) {
            return array_merge($extraData, $this->data);
        } else {
            return $this->data;
        }
    }

    /**
     * Get the data for the left side view
     */
    public function getLeftSideData()
    {
        return $this->getViewData($this->leftSideView);
    }

    /**
     * Get the data for the right side view
     */
    public function getRightSideData()
    {
        return $this->getViewData($this->rightSideView);
    }

    /**
     * This Method is returning the Data for Survey Top Bar
     *
     * @param int $sid Given Survey ID
     *
     * @return array
     * @throws CException
     *
     */
    protected static function getSurveyTopbarData($sid)
    {
        if (empty($sid)) return [];

        $oSurvey = Survey::model()->findByPk($sid);
        $hasSurveyContentPermission = Permission::model()->hasSurveyPermission($sid, 'surveycontent', 'update');
        $hasSurveyActivationPermission = Permission::model()->hasSurveyPermission($sid, 'surveyactivation', 'update');
        $hasDeletePermission = Permission::model()->hasSurveyPermission($sid, 'survey', 'delete');
        $hasSurveyTranslatePermission = Permission::model()->hasSurveyPermission($sid, 'translations', 'read');
        $hasSurveyReadPermission = Permission::model()->hasSurveyPermission($sid, 'surveycontent', 'read');
        $hasSurveyTokensPermission = Permission::model()->hasSurveyPermission($sid, 'surveysettings', 'update')
            || Permission::model()->hasSurveyPermission($sid, 'tokens', 'create');
        $hasResponsesCreatePermission = Permission::model()->hasSurveyPermission($sid, 'responses', 'create');
        $hasResponsesReadPermission = Permission::model()->hasSurveyPermission($sid, 'responses', 'read');
        $hasResponsesStatisticsReadPermission = Permission::model()->hasSurveyPermission($sid, 'statistics', 'read');

        $isActive = $oSurvey->active == 'Y';
        $condition = array('sid' => $sid, 'parent_qid' => 0);
        $sumcount = Question::model()->countByAttributes($condition);
        $hasAdditionalLanguages = (count($oSurvey->additionalLanguages) > 0);
        $canactivate = $sumcount > 0 && $hasSurveyActivationPermission;
        $expired = $oSurvey->expires != '' && ($oSurvey->expires < dateShift(date("Y-m-d H:i:s"),
                    "Y-m-d H:i", Yii::app()->getConfig('timeadjust')));
        $notstarted = ($oSurvey->startdate != '') && ($oSurvey->startdate > dateShift(date("Y-m-d H:i:s"),
                    "Y-m-d H:i", Yii::app()->getConfig('timeadjust')));

        if (!$isActive) {
            $context = gT("Preview survey");
            $contextbutton = 'preview_survey';
        } else {
            $context = gT("Execute survey");
            $contextbutton = 'execute_survey';
        }

        $language = $oSurvey->language;
        $conditionsCount = Condition::model()->with(array('questions' => array('condition' => 'sid =' . $sid)))->count();

        // Put menu items in tools menu
        $event = new PluginEvent('beforeToolsMenuRender', App()->getController());
        $event->set('surveyId', $oSurvey->sid);
        App()->getPluginManager()->dispatchEvent($event);
        $extraToolsMenuItems = $event->get('menuItems');

        // Add new menus in survey bar
        $event = new PluginEvent('beforeSurveyBarRender', App()->getController());
        $event->set('surveyId', $oSurvey->sid);
        App()->getPluginManager()->dispatchEvent($event);
        $beforeSurveyBarRender = $event->get('menus');

        $showToolsMenu = $hasDeletePermission
            || $hasSurveyTranslatePermission
            || $hasSurveyContentPermission
            || !is_null($extraToolsMenuItems);

        return array(
            'sid' => $sid,
            'oSurvey' => $oSurvey,
            'canactivate' => $canactivate,
            'expired' => $expired,
            'notstarted' => $notstarted,
            'context' => $context,
            'contextbutton' => $contextbutton,
            'language' => $language,
            'sumcount' => $sumcount,
            'hasSurveyContentPermission' => $hasSurveyContentPermission,
            'hasDeletePermission' => $hasDeletePermission,
            'hasSurveyTranslatePermission' => $hasSurveyTranslatePermission,
            'hasAdditionalLanguages' => $hasAdditionalLanguages,
            'conditionsCount' => $conditionsCount,
            'hasSurveyReadPermission' => $hasSurveyReadPermission,
            'hasSurveyTokensPermission' => $hasSurveyTokensPermission,
            'hasResponsesCreatePermission' => $hasResponsesCreatePermission,
            'hasResponsesReadPermission' => $hasResponsesReadPermission,
            'hasSurveyActivationPermission' => $hasSurveyActivationPermission,
            'hasResponsesStatisticsReadPermission' => $hasResponsesStatisticsReadPermission,
            'extraToolsMenuItems' => $extraToolsMenuItems ?? [],
            'beforeSurveyBarRender' => $beforeSurveyBarRender ?? [],
            'showToolsMenu' => $showToolsMenu,
        );
    }

    /**
     * Returns Data for Responses Top Bar
     *
     * @param $sid
     * @return array
     * @throws CException
     */
    public static function getResponsesTopbarData($sid)
    {
        if (empty($sid)) return [];

        $survey = Survey::model()->findByPk($sid);

        $hasResponsesReadPermission   = Permission::model()->hasSurveyPermission($sid, 'responses', 'read');
        $hasResponsesCreatePermission = Permission::model()->hasSurveyPermission($sid, 'responses', 'create');
        $hasStatisticsReadPermission  = Permission::model()->hasSurveyPermission($sid, 'statistics', 'read');
        $hasResponsesExportPermission = Permission::model()->hasSurveyPermission($sid, 'responses', 'export');
        $hasResponsesDeletePermission = Permission::model()->hasSurveyPermission($sid, 'responses', 'delete');
        $isActive                     = $survey->active;
        $isTimingEnabled              = $survey->savetimings;

        return array(
            'oSurvey' => $survey,
            'hasResponsesReadPermission'   => $hasResponsesReadPermission,
            'hasResponsesCreatePermission' => $hasResponsesCreatePermission,
            'hasStatisticsReadPermission'  => $hasStatisticsReadPermission,
            'hasResponsesExportPermission' => $hasResponsesExportPermission,
            'hasResponsesDeletePermission' => $hasResponsesDeletePermission,
            'isActive' => $isActive,
            'isTimingEnabled' => $isTimingEnabled,
        );
    }

    /**
     * Returns Data for Right Side of Survey Top Bar
     *
     * @param $sid
     * @return array
     * @throws CException
     */
    public static function getRightSurveyTopbarData($sid)
    {
        if (empty($sid)) return [];

        $closeUrl = Yii::app()->request->getUrlReferrer(Yii::app()->createUrl("/admin/responses/sa/browse/surveyid/".$sid));

        return array(
            'closeUrl' => $closeUrl,
        );
    }

    public function getViewName()
    {
        return $this->viewName;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getData()
    {
        return $this->getViewData($this->viewName);
    }

    public function getLeftSideView()
    {
        return $this->leftSideView;
    }

    public function getRightSideView()
    {
        return $this->rightSideView;
    }

    public function getSurveyData()
    {
        return $this->surveyData;
    }

}