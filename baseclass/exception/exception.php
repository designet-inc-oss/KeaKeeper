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

set_exception_handler('syserr_exception_handler');

/******************************************************************************
* Class: SyserrException
*
* [Description]
* When an exception is thrown, occur systemerror.
*
******************************************************************************/
class SyserrException extends Exception {

}

function syserr_exception_handler($e)
{
    $log = new Syslog();
    $log->log($e->getMessage(), NULL);

    /* check syserr_tmpl */
    if (is_readable(SYSERR_TMPL) === false) {
        /* return response code 501 */
        header("HTTP/1.1 501 Not Implemented");
        exit(1);
    }

    $view = new view();
    $ret = $view->render("syserror.tmpl", NULL);
    exit(1);
}
