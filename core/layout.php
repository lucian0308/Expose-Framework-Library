<?php
/**
 * @package     Expose
 * @version     2.0
 * @author      ThemeXpert http://www.themexpert.com
 * @copyright   Copyright (C) 2010 - 2011 ThemeXpert
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 * @file        layout.php
 **/

//prevent direct access
defined ('EXPOSE_VERSION') or die ('resticted aceess');

//import joomla filesystem classes
jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.file');

class ExposeLayout
{

    protected $modules = array();
    public $widgets = array();


    public function __construct()
    {
        //load all widgets in an array and trigger the initialize event for those widgets.
        $this->loadWidgets();
    }

    public static function getInstance()
    {
        static $instance;

        if(!isset($instance))
        {
            $instance = New ExposeLayout;
        }

        return $instance;
    }

    public function countModules($position)
    {
        //check if the module schema already exist for this position return it back.
        //if not exist set if first
        if(!isset($this->modules[$position]['schema']))
        {
            $this->setModuleSchema($position);
        }

        $published = 0;

        //check orphan module position which have nos subset.
        if($this->countModulesForPosition($position) OR $this->countWidgetsForPosition($position))
        {
            //set this position in active array record
            $this->modules[$position]['active'][] = $position;
            $published ++;
            $this->modules[$position]['published'] = $published;
            return TRUE;
        }

        //loop through all module-position(eg: roof-1, roof-2) and set the total published module num.
        foreach($this->modules[$position]['schema'] as $num => $v)
        {
            $positionName = ($position . '-' . $num) ;

            if($this->countModulesForPosition($positionName) OR $this->countWidgetsForPosition($positionName))
            {
                //set this position in active array record
                $this->modules[$position]['active'][] = $positionName;

                $published ++;
            }

        }

        $this->modules[$position]['published'] = $published;

        if($published > 0) return TRUE;

        return FALSE;

    }

    public function renderModules($position)
    {
        global $expose;

        $totalPublished = $this->modules[$position]['published'];
        $i = 1;

        if($totalPublished > 0 AND isset($this->modules[$position]['active']))
        {
            $widths = $this->getModuleSchema($position);
            $containerClass = 'ex-container ex-column';

            foreach($this->getActiveModuleLists($position) as $positionName)
            {
                //$totalModulesInPosition = $this->countModulesForPosition( $positionName );
                $width = array_shift($widths);
                $class = '';
                $html = '';

                //we'll make all width 100% for mobile device
                if($expose->platform == 'mobile'){
                    $width = 100;
                }

                if($i == 1) $class .= 'ex-first ';

                $class .= ($i%2) ? 'ex-odd' : 'ex-even';

                if($i == ($totalPublished -1)) $class .= ' ie6-offset';

                $modWrapperStart = "<div class='$containerClass $class $positionName' style='width:" . $width ."%'>";
                $modWrapperEnd = "</div>";

                if($i == $totalPublished){
                    $class .= ' ex-last ';
                    $containerClass = str_replace('ex-column', 'ex-column-last', $containerClass);
                    $style = "style='width: $width%'";

                    if(count($this->modules[$position]['schema']) == 1) $style = '';

                    $modWrapperStart = "<div class='$containerClass $class $positionName' $style>";
                    $modWrapperEnd = "</div>";
                }

                //we'll load all widgets first published in this position
                if($this->countWidgetsForPosition($positionName))
                {
                    foreach($this->getWidgetsForPosition($positionName) as $widget)
                    {
                        $html .= '<div class="ex-block no-title column-spacing clearfix">';
                            $html .= $widget->render();
                        $html .= '</div>';

                    }
                }

                //now load modules content
                $chrome = $this->getModuleChrome($position,$positionName);

                $html .= '<jdoc:include type="modules" name="'.$positionName.'" style="'.$chrome.'" />';

                echo $modWrapperStart . $html . $modWrapperEnd;

                $i++;
            }
        }
    }

    protected function setModuleSchema($position)
    {
        global $expose;

        $values = $expose->get($position);
        $values = explode(',', $values);

        foreach($values as $value)
        {
            list($i, $v) = explode(':', "$value:");
            $this->modules[$position]['schema'][$i][] = $v;
        }
    }

    public function getModuleSchema($position)
    {
        if(!isset($this->modules[$position]))
        {
            return;
        }
        $published = $this->modules[$position]['published'];

        //return module schema based on active modules
        return $this->modules[$position]['schema'][$published];

    }

    public function getModuleChrome($position, $module)
    {
        if(!isset($this->modules[$position]['chrome']))
        {
            $this->setModuleChrome($position);
        }

        return $this->modules[$position]['chrome'][$module];
    }

    protected function setModuleChrome($position)
    {
        global $expose;

        $fieldName = $position . '-chrome';
        $data = $expose->get($fieldName);
        $data = explode(',', $data);

        foreach($data as $json)
        {
            list($modName, $chrome) = explode(':',$json);
            $this->modules[$position]['chrome'][$modName] = $chrome;
        }

    }

    public function getActiveModuleLists($position)
    {
        //return active module array associate with position
        return $this->modules[$position]['active'];

    }

    public function getWidget($name)
    {
        if(isset($this->widgets[$name]))
        {
            return $this->widgets[$name];
        }

        return FALSE;
    }

    public function getWidgetsForPosition($position)
    {
        if(!isset($this->widgets))
        {
            $this->loadWidgets();
        }

        $widgets = array();

        foreach($this->widgets as $name => $instance)
        {
            if($instance->isEnabled() AND $instance->isInPosition($position) AND method_exists($instance, 'render')){

                $widgets[$name] = $instance;
            }
        }

        return $widgets;
    }

    public function countWidgetsForPosition($position)
    {

        global $expose;

        if($expose->platform == 'mobile')
        {
            $count = 0;

            foreach($this->getWidgetsForPosition($position) as $gist)
            {
               ($gist->isInMobile()) ? $count++ : '';
            }

            return $count;
        }

        return count($this->getWidgetsForPosition($position));
    }

    public function countModulesForPosition($position)
    {
        global $expose;

        $parentField = substr($position,0,strpos($position,'-')); //split the number and get the parent field name

        if($expose->platform == 'mobile')
        {
            if($expose->get($parentField.'-mobile'))
            {
                return $expose->document->countModules($position);
            }else{
                return FALSE;
            }
        }

        return $expose->document->countModules($position);

    }

    protected function loadWidgets()
    {
        global $expose;
        //define widgets paths
        $widgetPaths = array(
            $expose->exposePath . DS . 'widgets',
            $expose->templatePath . DS .'widgets'
        );
        $widgetLists = array();

        //first loop through all the template and framework path and take widget instance
        foreach($widgetPaths as $widgetPath)
        {
            $widgets = JFolder::files($widgetPath, '.php');

            if(is_array($widgets))
            {
                foreach($widgets as $widget)
                {
                    $widgetName = JFile::stripExt($widget);
                    $path = $widgetPath . DS . $widgetName .'.php';
                    $widgetLists[$widgetName] = $path;
                }
            }
        }

        foreach($widgetLists as $name => $path)
        {
            $className = 'ExposeWidget'. ucfirst($name);

            if(!class_exists($className) AND JFile::exists($path))
            {
                require_once($path);

                if(class_exists($className))
                {
                    $this->widgets[$name] = new $className();
                }
            }
        }

        //now initialize the widgets which is not position specific
        foreach($this->widgets as $name => $instance)
        {
            //we'll load the widgets based on platform permission
            if($expose->platform == 'mobile')
            {
                if($instance->isEnabled() AND $instance->isInMobile() AND method_exists($instance , 'init'))
                {
                    $instance->init();
                }
            }else{
                if($instance->isEnabled() AND method_exists($instance, 'init'))
                {
                    $instance->init();
                }
            }
        }
    }

    public function renderBody()
    {
        global $expose;

        $layoutType = $expose->get('layout-type');
        $bPath = $expose->exposePath . DS . 'layouts';
        $tPath = $expose->templatePath . DS .'layouts';
        $ext = '.php';

        if( $expose->platform == 'mobile' )
        {
            $device = strtolower($expose->browser->getBrowser());
            $bfile = $bPath .DS . $device . $ext;
            $tfile = $tPath .DS . $device . $ext;

            if($expose->get('iphone-enabled') AND $device == 'iphone')
            {
                $this->loadFile(array($tfile,$bfile));
            }elseif($expose->get('android-enabled') AND $device == 'android'){
                $this->loadFile(array($tfile,$bfile));
            }else{
                return FALSE;
            }

        }else{
            $bfile = $bPath .DS . $layoutType . $ext;
            $tfile = $tPath .DS . $layoutType . $ext;

            $this->loadFile(array($tfile,$bfile));
        }
    }


    public function loadFile($paths)
    {
        if(is_array($paths))
        {
            foreach($paths as $path)
            {
                if(JFile::exists($path)){

                    require_once($path);
                    break;

                }
            }
        }else if(JFile::exists($paths))
        {
            require_once ($paths);
        }else{
            JError::raiseNotice(E_NOTICE,"No file file found on given path $paths");
        }
    }

    public function getModules()
    {
        return $this->modules;
    }



}