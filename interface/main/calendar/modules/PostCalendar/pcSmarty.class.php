<?php

/**
 *  $Id$
 *
 *  PostCalendar::PostNuke Events Calendar Module
 *  Copyright (C) 2002  The PostCalendar Team
 *  http://postcalendar.tv
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 *  To read the license please read the docs/license.txt or visit
 *  http://www.gnu.org/copyleft/gpl.html
 *
 */
class pcSmarty extends SmartyBC
{
    function __construct()
    {
        global $bgcolor1,$bgcolor2,$bgcolor3,$bgcolor4,$bgcolor5,$bgcolor6,$textcolor1,$textcolor2;

        // call constructor
        parent::__construct();

        // gather module information
        $pcModInfo = pnModGetInfo(pnModGetIDFromName(__POSTCALENDAR__));
        $pcDir = pnVarPrepForOS($pcModInfo['directory']);
        $pcDisplayName = $pcModInfo['displayname'];
        unset($pcModInfo);

        // setup up pcSmarty configs
        $this->compile_check    =   true;
        $this->force_compile    =   false;
        $this->debugging        =   false;
        $this->template_dir     =   "modules/$pcDir/pntemplates";
        array_push($this->plugins_dir, "modules/$pcDir/pnincludes/Smarty/plugins");
        array_push($this->plugins_dir, "modules/$pcDir/plugins");
        array_push($this->plugins_dir, "../../../../library/smarty/plugins");
        $this->compile_dir      =   $GLOBALS['OE_SITE_DIR'] . '/documents/smarty/main';
        $this->caching      =   0;
        $this->left_delimiter   =   '[-';
        $this->right_delimiter  =   '-]';

        //============================================================
        //  checks for safe mode
        //  i think it's safe to say we can do this automagically now
        //============================================================
        $safe_mode      = ini_get('safe_mode');
        $safe_mode_gid  = ini_get('safe_mode_gid');
        $open_basedir   = ini_get('open_basedir');

        $use_safe_mode = ((bool)$safe_mode || (bool)$safe_mode_gid || !empty($open_basedir));
        if ($use_safe_mode) {
            $this->use_sub_dirs = false;
        } else {
            $this->use_sub_dirs = true;
        }

        unset($use_safe_mode, $safe_mode, $safe_mode_gid, $open_basedir);

        // looks like this is unused
        //$this->autoload_filters = array('output' => array('trimwhitespace'));

        $lang = 'eng';
        $func = pnVarCleanFromInput('func');
        $print = pnVarCleanFromInput('print');
        // assign theme globals
        $this->assignByRef('BGCOLOR1', $bgcolor1);
        $this->assignByRef('BGCOLOR2', $bgcolor2);
        $this->assignByRef('BGCOLOR3', $bgcolor3);
        $this->assignByRef('BGCOLOR4', $bgcolor4);
        $this->assignByRef('BGCOLOR5', $bgcolor5);
        $this->assignByRef('BGCOLOR6', $bgcolor6);
        $this->assignByRef('TEXTCOLOR1', $textcolor1);
        $this->assignByRef('TEXTCOLOR2', $textcolor2);
        $this->assignByRef('USER_LANG', $lang);
        $this->assignByRef('FUNCTION', $func);
        $this->assign('PRINT_VIEW', $print);
        $this->assign('USE_POPUPS', _SETTING_USE_POPUPS);
        $this->assign('USE_TOPICS', _SETTING_DISPLAY_TOPICS);
        $this->assign('USE_INT_DATES', _SETTING_USE_INT_DATES);
        $this->assign('OPEN_NEW_WINDOW', _SETTING_OPEN_NEW_WINDOW);
        $this->assign('EVENT_DATE_FORMAT', _SETTING_DATE_FORMAT);
        $this->assign('HIGHLIGHT_COLOR', _SETTING_DAY_HICOLOR);
        $this->assign('24HOUR_TIME', _SETTING_TIME_24HOUR);
        $this->assignByRef('MODULE_NAME', $pcDisplayName);
        $this->assignByRef('MODULE_DIR', $pcDir);
        //=================================================================
        //  Find out what Template we're using
        //=================================================================
        $template_name = _SETTING_TEMPLATE;
        if (!isset($template_name)) {
            $template_name = 'default';
        }

        //=================================================================
        //  Find out what Template View to use
        //=================================================================
        $template_view = pnVarCleanFromInput('tplview');
        if (!isset($template_view)) {
            $template_view = 'default';
        }

        $this->config_dir = "modules/$pcDir/pntemplates/$template_name/config/";
        $this->assignByRef('TPL_NAME', $template_name);
        $this->assignByRef('TPL_VIEW', $template_view);
        $this->assign('TPL_IMAGE_PATH', $GLOBALS['rootdir'] . "/main/calendar/modules/$pcDir/pntemplates/$template_name/images");
        $this->assign('TPL_ROOTDIR', $GLOBALS['rootdir']);
        $this->assign('TPL_STYLE_PATH', "modules/$pcDir/pntemplates/$template_name/style");
    }
}
