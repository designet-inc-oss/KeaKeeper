<?php
/********************************************************************
KeaKeeper

Copyright (C) 2017 DesigNET, INC.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
********************************************************************/

/******************************************************************************
 * Class:  View
 *
 * [Description]
 *   Class to keep and make values for pagination.
 *   Usage is call this class with data type(e.g. "mysql"),
 *   and put necessary values into variables in this class from the caller,
 *   finally, use "run" method.
 *****************************************************************************/
class View {
    public $view;
    public $base_view;
    public $base_locale;
    public $lang;
    public $encode;
    public $domain;

    /*************************************************************************
    * Method         : __construct
    * Description    : Set property for smarty and gettext.
    * args           : None
    * return         : None
    *************************************************************************/
    public function __construct()
    {
        /* Setting property for Smarty  */
        $base_view = APP_ROOT. "/view";
        $this->view = new Smarty();
        $this->view->escape_html = true;
        $this->view->template_dir  = $base_view. '/tmpl/';
        $this->view->compile_dir   = $base_view. '/compiled/';
        $this->view->config_dir    = $base_view. '/configs/';
        $this->view->cache_dir     = $base_view. '/cache/';
        $this->view->force_compile = true;

        /* Setting property for gettext  */
        $this->base_locale = APP_ROOT. '/locale';
        $this->lang      = 'en_US';
        $this->encode    = 'UTF-8';
        $this->domain    = 'messages';
    }

    /*************************************************************************
    * Method         : assign
    * Description    : Assign argument for smarty.
    * args           : $placeholder : name for tag
    *                  $value       : value of tag(default is NULL)
    * return         : None
    *************************************************************************/
    public function assign($placeholder, $value = NULL)
    {
        if ($placeholder == NULL) {
            return;
        }

        if(is_array($placeholder)){
            $this->view->assign($placeholder);
            return;
        }

        if(is_object($placeholder)){
            if  ($value == NULL) {
                return;
            }
            $this->view->assign($value, $placeholder);
            return;
        }

        $this->view->assign($placeholder, $value);
        return;
    }

    /*************************************************************************
    * Method         : clear
    * Description    : Clear assigned tags.
    * args           : None
    * return         : None
    *************************************************************************/
    public function clear()
    {
        $this->view->clearAllAssign();
    } 

    /*************************************************************************
    * Method         : setgettext
    * Description    : Set locale for gettext.
    * args           : None
    * return         : None
    *************************************************************************/
    public function setgettext()
    {
        /* Set locale using public variable */
        $fulllocale = $this->lang. ".". $this->encode;
        setLocale(LC_ALL, $fulllocale);
        bindtextdomain($this->domain, $this->base_locale);
        textdomain($this->domain);
    }

    /*************************************************************************
    * Method         : render
    * Description    : Display template with replace tags.
    * args           : $file
    *                  $placeholder
    * return         : None
    *************************************************************************/
    public function render($file, $placeholder)
    {
        $ret = $this->view->templateExists($file);
        if ($ret === false) {
            throw new Exception("Cannot find the template file.($file)");
            $file = "syserror.tmpl";
        }

        $ret = is_readable($this->view->template_dir[0]. $file);
        if ($ret === false) {
            throw new Exception("Cannot read the template file.($file)");
            $file = "syserror.tmpl";
        }

        /* Assign placeholder */
        $this->assign($placeholder);

        $this->view->display($file);

        return;
    }
}
