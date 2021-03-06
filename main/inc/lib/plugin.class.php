<?php
/* For licensing terms, see /license.txt */

use Chamilo\CourseBundle\Entity\CTool;

/**
 * Class Plugin
 * Base class for plugins
 *
 * This class has to be extended by every plugin. It defines basic methods
 * to install/uninstall and get information about a plugin
 *
 * @author    Julio Montoya <gugli100@gmail.com>
 * @author    Yannick Warnier <ywarnier@beeznest.org>
 * @author    Laurent Opprecht    <laurent@opprecht.info>
 * @copyright 2012 University of Geneva
 * @license   GNU General Public License - http://www.gnu.org/copyleft/gpl.html
 *
 */
class Plugin
{
    const TAB_FILTER_NO_STUDENT = '::no-student';
    const TAB_FILTER_ONLY_STUDENT = '::only-student';

    protected $version = '';
    protected $author = '';
    protected $fields = [];
    private $settings = [];
    // Translation strings.
    private $strings = null;
    public $isCoursePlugin = false;
    public $isAdminPlugin = false;
    public $isMailPlugin = false;
    // Adds icon in the course home
    public $addCourseTool = true;

    /**
     * When creating a new course, these settings are added to the course, in
     * the course_info/infocours.php
     * To show the plugin course icons you need to add these icons:
     * main/img/icons/22/plugin_name.png
     * main/img/icons/64/plugin_name.png
     * main/img/icons/64/plugin_name_na.png
     * @example
     * $course_settings = array(
    array('name' => 'big_blue_button_welcome_message',  'type' => 'text'),
    array('name' => 'big_blue_button_record_and_store', 'type' => 'checkbox')
    );
     */
    public $course_settings = array();
    /**
     * This indicates whether changing the setting should execute the callback
     * function.
     */
    public $course_settings_callback = false;

    /**
     * Default constructor for the plugin class. By default, it only sets
     * a few attributes of the object
     * @param string $version   of this plugin
     * @param string $author    of this plugin
     * @param array  $settings  settings to be proposed to configure the plugin
     */
    protected function __construct($version, $author, $settings = array())
    {
        $this->version = $version;
        $this->author = $author;
        $this->fields = $settings;

        global $language_files;
        $language_files[] = 'plugin_'.$this->get_name();
    }

    /**
     * Gets an array of information about this plugin (name, version, ...)
     * @return  array Array of information elements about this plugin
     */
    public function get_info()
    {
        $result = array();
        $result['obj'] = $this;
        $result['title'] = $this->get_title();
        $result['comment'] = $this->get_comment();
        $result['version'] = $this->get_version();
        $result['author'] = $this->get_author();
        $result['plugin_class'] = get_class($this);
        $result['is_course_plugin'] = $this->isCoursePlugin;
        $result['is_admin_plugin'] = $this->isAdminPlugin;
        $result['is_mail_plugin'] = $this->isMailPlugin;

        if ($form = $this->get_settings_form()) {
            $result['settings_form'] = $form;

            foreach ($this->fields as $name => $type) {
                $value = $this->get($name);

                if (is_array($type)) {
                    $value = $type['options'];
                }
                $result[$name] = $value;
            }
        }

        return $result;
    }

    /**
     * Returns the "system" name of the plugin in lowercase letters
     * @return string
     */
    public function get_name()
    {
        $result = get_class($this);
        $result = str_replace('Plugin', '', $result);
        $result = strtolower($result);

        return $result;
    }

    /**
     * @return string
     */
    public function getCamelCaseName()
    {
        $result = get_class($this);
        return str_replace('Plugin', '', $result);
    }

    /**
     * Returns the title of the plugin
     * @return string
     */
    public function get_title()
    {
        return $this->get_lang('plugin_title');
    }

    /**
     * Returns the description of the plugin
     * @return string
     */
    public function get_comment()
    {
        return $this->get_lang('plugin_comment');
    }

    /**
     * Returns the version of the plugin
     * @return string
     */
    public function get_version()
    {
        return $this->version;
    }

    /**
     * Returns the author of the plugin
     * @return string
     */
    public function get_author()
    {
        return $this->author;
    }

    /**
     * Returns the contents of the CSS defined by the plugin
     * @return string
     */
    public function get_css()
    {
        $name = $this->get_name();
        $path = api_get_path(SYS_PLUGIN_PATH)."$name/resources/$name.css";
        if (!is_readable($path)) {
            return '';
        }
        $css = array();
        $css[] = file_get_contents($path);
        $result = implode($css);

        return $result;
    }

    /**
     * Returns an HTML form (generated by FormValidator) of the plugin settings
     * @return FormValidator FormValidator-generated form
     */
    public function get_settings_form()
    {
        $result = new FormValidator($this->get_name());

        $defaults = array();
        $checkboxGroup = array();
        $checkboxCollection = array();

        if ($checkboxNames = array_keys($this->fields, 'checkbox')) {
            $pluginInfoCollection = api_get_settings('Plugins');
            foreach ($pluginInfoCollection as $pluginInfo) {
                if (array_search($pluginInfo['title'], $checkboxNames) !== false) {
                    $checkboxCollection[$pluginInfo['title']] = $pluginInfo;
                }
            }
        }

        foreach ($this->fields as $name => $type) {
            $options = null;
            if (is_array($type) && isset($type['type']) && $type['type'] === 'select') {
                $attributes = isset($type['attributes']) ? $type['attributes'] : [];
                $options = $type['options'];
                $type = $type['type'];
            }

            $value = $this->get($name);
            $defaults[$name] = $value;
            $type = isset($type) ? $type : 'text';

            $help = null;
            if ($this->get_lang_plugin_exists($name.'_help')) {
                $help = $this->get_lang($name.'_help');
                if ($name === "show_main_menu_tab") {
                    $pluginName = strtolower(str_replace('Plugin', '', get_class($this)));
                    $pluginUrl = api_get_path(WEB_PATH)."plugin/$pluginName/index.php";
                    $pluginUrl = "<a href=$pluginUrl>$pluginUrl</a>";
                    $help = sprintf($help, $pluginUrl);
                }
            }

            switch ($type) {
                case 'html':
                    $result->addElement('html', $this->get_lang($name));
                    break;
                case 'wysiwyg':
                    $result->addHtmlEditor($name, $this->get_lang($name), false);
                    break;
                case 'text':
                    $result->addElement($type, $name, array($this->get_lang($name), $help));
                    break;
                case 'boolean':
                    $group = array();
                    $group[] = $result->createElement(
                        'radio',
                        $name,
                        '',
                        get_lang('Yes'),
                        'true'
                    );
                    $group[] = $result->createElement(
                        'radio',
                        $name,
                        '',
                        get_lang('No'),
                        'false'
                    );
                    $result->addGroup($group, null, array($this->get_lang($name), $help));
                    break;
                case 'checkbox':
                    $selectedValue = null;
                    if (isset($checkboxCollection[$name])) {
                        if ($checkboxCollection[$name]['selected_value'] === 'true') {
                            $selectedValue = 'checked';
                        }
                    }
                    $element = $result->createElement(
                        $type,
                        $name,
                        '',
                        $this->get_lang($name),
                        $selectedValue
                    );
                    $element->_attributes['value'] = 'true';
                    $checkboxGroup[] = $element;
                    break;
                case 'select':
                    $result->addElement(
                        $type,
                        $name,
                        array($this->get_lang($name), $help),
                        $options,
                        $attributes
                    );
                    break;
            }
        }

        if (!empty($checkboxGroup)) {
            $result->addGroup(
                $checkboxGroup,
                null,
                array($this->get_lang('sms_types'), $help)
            );
        }
        $result->setDefaults($defaults);
        $result->addButtonSave($this->get_lang('Save'), 'submit_button');

        return $result;
    }

    /**
     * Returns the value of a given plugin global setting
     * @param string $name of the plugin
     *
     * @return string Value of the plugin
     */
    public function get($name)
    {
        $settings = $this->get_settings();
        foreach ($settings as $setting) {
            if ($setting['variable'] == $this->get_name().'_'.$name) {
                if (!empty($setting['selected_value']) &&
                    @unserialize($setting['selected_value']) !== false
                ) {
                    $setting['selected_value'] = unserialize($setting['selected_value']);
                }

                return $setting['selected_value'];
            }
        }

        return false;
    }

    /**
     * Returns an array with the global settings for this plugin
     * @param bool $forceFromDB Optional. Force get settings from the database
     * @return array Plugin settings as an array
     */
    public function get_settings($forceFromDB = false)
    {
        if (empty($this->settings) || $forceFromDB) {
            $settings = api_get_settings_params(
                array(
                    "subkey = ? AND category = ? AND type = ? AND access_url = ?" => array(
                        $this->get_name(),
                        'Plugins',
                        'setting',
                        api_get_current_access_url_id()
                    )
                )
            );
            $this->settings = $settings;
        }

        return $this->settings;
    }

    /**
     * Tells whether language variables are defined for this plugin or not
     * @param string $name System name of the plugin
     *
     * @return bool True if the plugin has language variables defined, false otherwise
     */
    public function get_lang_plugin_exists($name)
    {
        return isset($this->strings[$name]);
    }

    /**
     * Hook for the get_lang() function to check for plugin-defined language terms
     * @param string $name of the language variable we are looking for
     *
     * @return string The translated language term of the plugin
     */
    public function get_lang($name)
    {
        // Check whether the language strings for the plugin have already been
        // loaded. If so, no need to load them again.
        if (is_null($this->strings)) {
            $root = api_get_path(SYS_PLUGIN_PATH);
            $plugin_name = $this->get_name();

            $language_interface = api_get_language_isocode();

            //1. Loading english if exists
            $english_path = $root.$plugin_name."/lang/en.php";

            if (is_readable($english_path)) {
                $strings = array();
                include $english_path;
                $this->strings = $strings;
            }

            $path = $root.$plugin_name."/lang/$language_interface.php";
            // 2. Loading the system language
            if (is_readable($path)) {
                include $path;
                if (!empty($strings)) {
                    foreach ($strings as $key => $string) {
                        $this->strings[$key] = $string;
                    }
                }
            } elseif ($languageParentId > 0) {
                $languageParentInfo = api_get_language_info($languageParentId);
                $languageParentFolder = $languageParentInfo['dokeos_folder'];

                $parentPath = "{$root}{$plugin_name}/lang/{$languageParentFolder}.php";
                if (is_readable($parentPath)) {
                    include $parentPath;
                    if (!empty($strings)) {
                        foreach ($strings as $key => $string) {
                            $this->strings[$key] = $string;
                        }
                    }
                }
            }
        }

        if (isset($this->strings[$name])) {
            return $this->strings[$name];
        }

        return get_lang($name);
    }

    /**
     * Caller for the install_course_fields() function
     * @param int $courseId
     *
     * @param boolean $addToolLink Whether to add a tool link on the course homepage
     *
     * @return void
     */
    public function course_install($courseId, $addToolLink = true)
    {
        $this->install_course_fields($courseId, $addToolLink);
    }

    /**
     * Add course settings and, if not asked otherwise, add a tool link on the course homepage
     * @param int $courseId Course integer ID
     * @param boolean $add_tool_link Whether to add a tool link or not
     * (some tools might just offer a configuration section and act on the backend)
     *
     * @return boolean|null  False on error, null otherwise
     */
    public function install_course_fields($courseId, $add_tool_link = true)
    {
        $plugin_name = $this->get_name();
        $t_course = Database::get_course_table(TABLE_COURSE_SETTING);
        $courseId = (int) $courseId;

        if (empty($courseId)) {
            return false;
        }

        // Adding course settings.
        if (!empty($this->course_settings)) {
            foreach ($this->course_settings as $setting) {
                $variable = $setting['name'];
                $value = '';
                if (isset($setting['init_value'])) {
                    $value = $setting['init_value'];
                }

                $type = 'textfield';
                if (isset($setting['type'])) {
                    $type = $setting['type'];
                }

                if (isset($setting['group'])) {
                    $group = $setting['group'];
                    $sql = "SELECT value
                            FROM $t_course
                            WHERE
                                c_id = $courseId AND
                                variable = '".Database::escape_string($group)."' AND
                                subkey = '".Database::escape_string($variable)."'
                            ";
                    $result = Database::query($sql);
                    if (!Database::num_rows($result)) {
                        $params = [
                            'c_id' => $courseId,
                            'variable' => $group,
                            'subkey' => $variable,
                            'value' => $value,
                            'category' => 'plugins',
                            'type' => $type,
                            'title' => ''
                        ];
                        Database::insert($t_course, $params);
                    }
                } else {
                    $sql = "SELECT value FROM $t_course
                            WHERE c_id = $courseId AND variable = '$variable' ";
                    $result = Database::query($sql);
                    if (!Database::num_rows($result)) {
                        $params = [
                            'c_id' => $courseId,
                            'variable' => $variable,
                            'subkey' => $plugin_name,
                            'value' => $value,
                            'category' => 'plugins',
                            'type' => $type,
                            'title' => ''
                        ];
                        Database::insert($t_course, $params);
                    }
                }
            }
        }

        // Stop here if we don't want a tool link on the course homepage
        if (!$add_tool_link || $this->addCourseTool == false) {
            return true;
        }

        //Add an icon in the table tool list
        $this->createLinkToCourseTool($plugin_name, $courseId);
    }

    /**
     * Delete the fields added to the course settings page and the link to the
     * tool on the course's homepage
     * @param int $courseId
     *
     * @return false|null
     */
    public function uninstall_course_fields($courseId)
    {
        $courseId = intval($courseId);

        if (empty($courseId)) {
            return false;
        }
        $plugin_name = $this->get_name();

        $t_course = Database::get_course_table(TABLE_COURSE_SETTING);
        $t_tool = Database::get_course_table(TABLE_TOOL_LIST);

        if (!empty($this->course_settings)) {
            foreach ($this->course_settings as $setting) {
                $variable = Database::escape_string($setting['name']);
                if (!empty($setting['group'])) {
                    $variable = Database::escape_string($setting['group']);
                }
                if (empty($variable)) {
                    continue;
                }
                $sql = "DELETE FROM $t_course
                        WHERE c_id = $courseId AND variable = '$variable'";
                Database::query($sql);
            }
        }

        $plugin_name = Database::escape_string($plugin_name);
        $sql = "DELETE FROM $t_tool
                WHERE c_id = $courseId AND name = '$plugin_name'";
        Database::query($sql);
    }

    /**
     * Add an link for a course tool
     * @param string $name The tool name
     * @param int $courseId The course ID
     * @param string $iconName Optional. Icon file name
     * @param string $link Optional. Link URL
     * @return CTool|null
     */
    protected function createLinkToCourseTool(
        $name,
        $courseId,
        $iconName = null,
        $link = null
    ) {
        if (!$this->addCourseTool) {
            return null;
        }

        $em = Database::getManager();

        /** @var CTool $tool */
        $tool = $em
            ->getRepository('ChamiloCourseBundle:CTool')
            ->findOneBy([
                'name' => $name,
                'cId' => $courseId
            ]);

        if (!$tool) {
            $cToolId = AddCourse::generateToolId($courseId);
            $pluginName = $this->get_name();

            $tool = new CTool();
            $tool
                ->setId($cToolId)
                ->setCId($courseId)
                ->setName($name)
                ->setLink($link ?: "$pluginName/start.php")
                ->setImage($iconName ?: "$pluginName.png")
                ->setVisibility(true)
                ->setAdmin(0)
                ->setAddress('squaregrey.gif')
                ->setAddedTool(false)
                ->setTarget('_self')
                ->setCategory('plugin')
                ->setSessionId(0);

            $em->persist($tool);
            $em->flush();
        }

        return $tool;
    }   

    /**
     * Install the course fields and tool link of this plugin in all courses
     * @param boolean $add_tool_link Whether we want to add a plugin link on the course homepage
     *
     * @return void
     */
    public function install_course_fields_in_all_courses($add_tool_link = true)
    {
        // Update existing courses to add plugin settings
        $t_courses = Database::get_main_table(TABLE_MAIN_COURSE);
        $sql = "SELECT id FROM $t_courses ORDER BY id";
        $res = Database::query($sql);
        while ($row = Database::fetch_assoc($res)) {
            $this->install_course_fields($row['id'], $add_tool_link);
        }
    }

    /**
     * Uninstall the plugin settings fields from all courses
     * @return void
     */
    public function uninstall_course_fields_in_all_courses()
    {
        // Update existing courses to add conference settings
        $t_courses = Database::get_main_table(TABLE_MAIN_COURSE);
        $sql = "SELECT id FROM $t_courses
                ORDER BY id";
        $res = Database::query($sql);
        while ($row = Database::fetch_assoc($res)) {
            $this->uninstall_course_fields($row['id']);
        }
    }

    /**
     * @return array
     */
    public function getCourseSettings()
    {
        $settings = array();
        if (is_array($this->course_settings)) {
            foreach ($this->course_settings as $item) {
                if (isset($item['group'])) {
                    if (!in_array($item['group'], $settings)) {
                        $settings[] = $item['group'];
                    }
                } else {
                    $settings[] = $item['name'];
                }
            }
        }

        return $settings;
    }

    /**
     * Method to be extended when changing the setting in the course
     * configuration should trigger the use of a callback method
     * @param array $values sent back from the course configuration script
     *
     * @return void
     */
    public function course_settings_updated($values = array())
    {

    }

    /**
     * Add a tab to platform
     * @param string $tabName
     * @param string $url
     * @param string $userFilter Optional. Filter tab type
     * @return false|string
     */
    public function addTab($tabName, $url, $userFilter = null)
    {
        $sql = "SELECT * FROM settings_current
                WHERE
                    variable = 'show_tabs' AND
                    subkey LIKE 'custom_tab_%'";
        $result = Database::query($sql);
        $customTabsNum = Database::num_rows($result);

        $tabNum = $customTabsNum + 1;

        // Avoid Tab Name Spaces
        $tabNameNoSpaces = preg_replace('/\s+/', '', $tabName);
        $subkeytext = "Tabs".$tabNameNoSpaces;

        // Check if it is already added
        $checkCondition = array(
            'where' =>
                array(
                    "variable = 'show_tabs' AND subkeytext = ?" => array(
                        $subkeytext
                    )
                )
        );

        $checkDuplicate = Database::select('*', 'settings_current', $checkCondition);
        if (!empty($checkDuplicate)) {
            return false;
        }

        // End Check
        $subkey = 'custom_tab_'.$tabNum;

        if (!empty($userFilter)) {
            switch ($userFilter) {
                case self::TAB_FILTER_NO_STUDENT:
                    //no break
                case self::TAB_FILTER_ONLY_STUDENT:
                    $subkey .= $userFilter;
                    break;
            }
        }

        $attributes = array(
            'variable' => 'show_tabs',
            'subkey' => $subkey,
            'type' => 'checkbox',
            'category' => 'Platform',
            'selected_value' => 'true',
            'title' => $tabName,
            'comment' => $url,
            'subkeytext' => $subkeytext,
            'access_url' => 1,
            'access_url_changeable' => 0,
            'access_url_locked' => 0
        );
        $resp = Database::insert('settings_current', $attributes);

        // Save the id
        $settings = $this->get_settings();
        $setData = array(
            'comment' => $subkey
        );
        $whereCondition = array(
            'id = ?' => key($settings)
        );
        Database::update('settings_current', $setData, $whereCondition);

        return $resp;
    }

    /**
     * Delete a tab to chamilo's platform
     * @param string $key
     * @return boolean $resp Transaction response
     */
    public function deleteTab($key)
    {
        $t = Database::get_main_table(TABLE_MAIN_SETTINGS_CURRENT);
        $sql = "SELECT *
                FROM $t
                WHERE variable = 'show_tabs'
                AND subkey <> '$key'
                AND subkey like 'custom_tab_%'
                ";
        $resp = $result = Database::query($sql);
        $customTabsNum = Database::num_rows($result);

        if (!empty($key)) {
            $whereCondition = array(
                'variable = ? AND subkey = ?' => array('show_tabs', $key)
            );
            $resp = Database::delete('settings_current', $whereCondition);

            //if there is more than one tab
            //re enumerate them
            if (!empty($customTabsNum) && $customTabsNum > 0) {
                $tabs = Database::store_result($result, 'ASSOC');
                $i = 1;
                foreach ($tabs as $row) {
                    $newSubKey = "custom_tab_$i";

                    if (strpos($row['subkey'], self::TAB_FILTER_NO_STUDENT) !== false) {
                        $newSubKey .= self::TAB_FILTER_NO_STUDENT;
                    } elseif (strpos($row['subkey'], self::TAB_FILTER_ONLY_STUDENT) !== false) {
                        $newSubKey .= self::TAB_FILTER_ONLY_STUDENT;
                    }

                    $attributes = ['subkey' => $newSubKey];

                    $this->updateTab($row['subkey'], $attributes);
                    $i++;
                }
            }
        }

        return $resp;
    }

    /**
     * Update the tabs attributes
     * @param string $key
     * @param array  $attributes
     *
     * @return boolean
     */
    public function updateTab($key, $attributes)
    {
        $whereCondition = array(
            'variable = ? AND subkey = ?' => array('show_tabs', $key)
        );
        $resp = Database::update('settings_current', $attributes, $whereCondition);

        return $resp;
    }

    /**
     * This method shows or hides plugin's tab
     * @param boolean $showTab Shows or hides the main menu plugin tab
     * @param string $filePath Plugin starter file path
     */
    public function manageTab($showTab, $filePath = 'index.php')
    {
        $langString = str_replace('Plugin', '', get_class($this));
        $pluginName = strtolower($langString);
        $pluginUrl = 'plugin/'.$pluginName.'/'.$filePath;

        if ($showTab === 'true') {
            $tabAdded = $this->addTab($langString, $pluginUrl);
            if ($tabAdded) {
                // The page must be refreshed to show the recently created tab
                echo "<script>location.href = '".Security::remove_XSS($_SERVER['REQUEST_URI'])."';</script>";
            }
        } else {
            $settingsCurrentTable = Database::get_main_table(TABLE_MAIN_SETTINGS_CURRENT);
            $conditions = array(
                'where' => array(
                    "variable = 'show_tabs' AND title = ? AND comment = ? " => array(
                        $langString,
                        $pluginUrl
                    )
                )
            );
            $result = Database::select('subkey', $settingsCurrentTable, $conditions);
            if (!empty($result)) {
                $this->deleteTab($result[0]['subkey']);
            }
        }
    }

    /**
     * @param string $variable
     * @return bool
     */
    public function validateCourseSetting($variable)
    {
        return true;
    }

    /**
     * @param string $region
     * @return string
     */
    public function renderRegion($region)
    {
        return '';
    }

    /**
     * Returns true if the plugin is installed, false otherwise
     * @return bool True if plugin is installed/enabled, false otherwise
     */
    public function isEnabled()
    {
        $settings = api_get_settings_params_simple(
            array(
                "subkey = ? AND category = ? AND type = ? AND variable = 'status' " => array($this->get_name(), 'Plugins', 'setting')
            )
        );
        if (is_array($settings) && isset($settings['selected_value']) && $settings['selected_value'] == 'installed') {
            return true;
        }
        return false;
    }

    /**
     * Allow make some actions after configure the plugin parameters
     * This function is called from main/admin/configure_plugin.php page
     * when saving the plugin parameters
     * @return \Plugin
     */
    public function performActionsAfterConfigure()
    {
        return $this;
    }
}
